<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\MinimaxInvoice;
use App\Models\Payment;
use App\Services\MinimaxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class CreateMinimaxInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string|int $paymentId;
    protected string $organizationId;

    /**
     * Create a new job instance.
     *
     * @param string|int $paymentId
     */
    public function __construct(string|int $paymentId)
    {
        $this->paymentId = $paymentId;
        $this->organizationId = config('services.minimax.organization_id');
    }

    /**
     * Execute the job.
     *
     * @param MinimaxService $minimaxService
     * @return void
     */
    public function handle(MinimaxService $minimaxService): void
    {
        Log::info('🚀 Starting CreateMinimaxInvoice job', ['payment_id' => $this->paymentId]);

        $orgId = $this->organizationId;

        try {
            DB::connection()->getPdo();
            Log::info('✅ Database connection successful.');
        } catch (\Exception $e) {
            Log::error('❌ Database connection failed', ['error' => $e->getMessage()]);
            $this->fail($e);
            return;
        }

        $payment = Payment::where('payment_id', $this->paymentId)->with(['user', 'course'])->first();
        if (!$payment) {
            Log::error('❌ Payment not found', ['payment_id' => $this->paymentId]);
            $this->fail(new \Exception('Payment not found'));
            return;
        }

        if ($payment->status !== 'completed') {
            Log::warning('⚠️ Invoice creation skipped: Payment not completed', ['payment_id' => $this->paymentId]);
            return;
        }

        try {
            $date = now()->toDateString();
            $dateTime = now()->toDateTimeString(); // For DateIssued, which is datetime

            // Fetch VAT rate and currency
            Log::info('Fetching VAT rate and currency from Minimax.');
            $vat = $minimaxService->getVatRate($orgId, 'S', $date); // 'S' for Standard VAT rate
            if (!$vat || !isset($vat->VatRateId) || !isset($vat->Percent)) {
                $errorMsg = 'Minimax VAT rate "S" not found or malformed.';
                Log::error($errorMsg, ['vat_response' => (array)$vat]);
                throw new \Exception($errorMsg);
            }
            $vatPercent = (float)$vat->Percent;

            $currency = $minimaxService->getCurrencyById($orgId, 2); // Directly use ID 2
            if (!$currency) {
                Log::error("❌ Minimax currency RSD (ID 2) not found or malformed.", ["payment_id" => $this->paymentId]);
                throw new \Exception('Minimax currency RSD (ID 2) not found or malformed.');
            }
            Log::info("✔ Minimax currency found for invoice creation.", [
                'currency_id' => $currency->CurrencyId,
                'currency_code' => $currency->Code,
                'currency_name' => $currency->Name
            ]);

            // Fetch or create customer
            $customerCode = 'CUST-' . $payment->user->id;
            Log::info('Looking up or creating Minimax customer', ['customer_code' => $customerCode]);
            $customer = $minimaxService->getCustomerByCode($orgId, $customerCode);

            if (!$customer) {
                Log::info('Customer not found in Minimax, creating new one.', ['customer_code' => $customerCode]);
                $customerData = [
                    'Name' => $payment->user->name ?? 'Unknown Customer',
                    'Address' => $payment->user->address ?? 'N/A',
                    'PostalCode' => $payment->user->postal_code ?? '11000',
                    'City' => $payment->user->city ?? 'Belgrade',
                    'Code' => $customerCode,
                    'Country' => ['ID' => 3], // ID for Serbia
                    'TaxNumber' => $payment->user->tax_number ?? null,
                    'Usage' => 'D', // Domestic customer
                    'SubjectToVAT' => 'N', // Assuming not VAT liable
                    'Currency' => ['ID' => $currency->CurrencyId],
                    'EInvoiceIssuing' => 'SeNePripravlja',
                ];
                $customer = $minimaxService->createCustomer($orgId, $customerData);
                Log::info('Minimax customer created.', ['customer_id' => $customer->CustomerId]);
            } else {
                Log::info('Minimax customer found.', ['customer_id' => $customer->CustomerId, 'customer_code' => $customer->Code]);
            }

            if (!$customer || !isset($customer->CustomerId)) {
                $errorMsg = 'Failed to find or create Minimax customer.';
                Log::error($errorMsg, ['customer_code' => $customerCode, 'response' => (array)$customer]);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            // Fetch or create item/service
            $itemCodeFromSearch = $minimaxService->findItemCodeByCourseTitle($orgId, $payment->course->title);
            $item = null;
            $itemCode = '';

            if (!$itemCodeFromSearch) {
                $itemCode = 'ITEM-' . Str::random(8); // Generate a unique code
                Log::info('No matching Minimax item found, creating new.', ['item_code' => $itemCode, 'course_title' => $payment->course->title]);

                $item = $minimaxService->createItem($orgId, [
                    'Name' => $payment->course->title,
                    'Code' => $itemCode,
                    'ItemType' => 'S', // Service
                    'VatRate' => ['ID' => $vat->VatRateId],
                    'Price' => (float)$payment->converted_amount,
                    'Currency' => ['ID' => $currency->CurrencyId],
                    'UnitOfMeasurement' => 'kom',
                    'RevenueAccountDomestic' => ['ID' => 217381669],
                    'RevenueAccountOutsideEU' => ['ID' => 217381673],
                ]);
            } else {
                $itemCode = $itemCodeFromSearch;
                $item = $minimaxService->getItem($orgId, $itemCode);
                Log::info('Minimax item found by course title.', ['item_id' => $item->ItemId, 'item_code' => $item->Code]);
            }

            if (!$item || !isset($item->ItemId)) {
                $errorMsg = 'Minimax item not created or invalid.';
                Log::error($errorMsg, ['item' => (array)$item]);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            // Fetch Report Template IDs
            $templateId = $minimaxService->getReportTemplateIdByType('IR'); // Izdat Račun
            $deliveryNoteTemplateId = $minimaxService->getReportTemplateIdByType('DO'); // Dostavnica (optional)
            if (!$templateId) {
                $errorMsg = 'Missing Minimax report template ID for Issued Invoice (IR).';
                Log::error($errorMsg, ['template_id' => $templateId]);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            // Fetch Document Numbering
            $numberingList = $minimaxService->getDocumentNumbering($orgId);
            $defaultNumbering = collect($numberingList->Rows)
                ->first(fn ($item) => $item->Document === 'IR' && $item->Usage === 'D');
            if (!$defaultNumbering || !isset($defaultNumbering->DocumentNumberingId)) {
                $errorMsg = 'Minimax document numbering not found for Issued Invoices.';
                Log::error($errorMsg, ['raw' => (array)$numberingList]);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            $employeeId = (int) config('services.minimax.default_employee_id');

            if (!$employeeId) {
                $errorMsg = 'Minimax default employee ID is not configured or invalid.';
                Log::error($errorMsg);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            $employee = $minimaxService->getEmployeeById($orgId, $employeeId);

            if (!$employee || !isset($employee->EmployeeId)) {
                $errorMsg = 'Minimax Employee not found. Verify default_employee_id in config or Minimax.';
                Log::error($errorMsg, ['employee_id_attempted' => $employeeId, 'employee_response' => (array)$employee]);
                $this->fail(new \Exception($errorMsg));
                return;
            }
            Log::info('✔ Minimax Employee found.', ['employee_id' => $employee->EmployeeId, 'employee_name' => $employee->FirstName . ' ' . $employee->LastName]);

            // Fetch Payment Method ID
            $paymentMethodType = 'Kartica';
            $paymentMethodId = $minimaxService->getPaymentMethodIdByName($paymentMethodType);

            if (is_null($paymentMethodId)) {
                $errorMsg = "Minimax payment method '{$paymentMethodType}' not found. Verify correct type or create it in Minimax.";
                Log::error($errorMsg);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            // Calculate PriceWithVAT and total InvoiceValue
            $basePrice = (float)$payment->converted_amount;
            $priceWithVat = round($basePrice * (1 + ($vatPercent / 100)), 2);
            $invoiceValue = $priceWithVat;

            // Construct invoice creation payload
            $invoiceCreationPayload = [
                'Customer' => [
                    'ID' => $customer->CustomerId,
                    'Name' => $customer->Name,
                    'ResourceUrl' => "/api/orgs/{$orgId}/customers/{$customer->CustomerId}"
                ],
                'DateIssued' => $dateTime,
                'DateTransaction' => $date,
                'DateTransactionFrom' => $date,
                'DateDue' => $date,
                'AddresseeName' => $payment->user->name ?? 'Unknown Customer',
                'AddresseeAddress' => $payment->user->address ?? 'N/A',
                'AddresseePostalCode' => $payment->user->postal_code ?? '11000',
                'AddresseeCity' => $payment->user->city ?? 'Belgrade',
                'AddresseeCountry' => [
                    'ID' => 3,
                    'Name' => 'RS',
                    'ResourceUrl' => "/api/orgs/{$orgId}/countries/3"
                ],
                'Currency' => [
                    'ID' => $currency->CurrencyId,
                    'Name' => $currency->Code,
                    'ResourceUrl' => "/api/orgs/{$orgId}/currencies/{$currency->CurrencyId}"
                ],
                'Status' => 'O',
                'PricesOnInvoice' => 'D',
                'RecurringInvoice' => 'N',
                'InvoiceType' => 'R',
                'PaymentStatus' => 'NeplacanNezapadel',
                'DeliveryNoteReportTemplate' => $deliveryNoteTemplateId ? ['ID' => $deliveryNoteTemplateId] : null,
                'DocumentNumbering' => [
                    'ID' => $defaultNumbering->DocumentNumberingId,
                    'Name' => $defaultNumbering->Name,
                    'ResourceUrl' => "/api/orgs/{$orgId}/document-numbering/{$defaultNumbering->DocumentNumberingId}"
                ],
                'IssuedInvoiceReportTemplate' => [
                    'ID' => $templateId,
                    'Name' => 'Standardno - Izdat račun',
                    'ResourceUrl' => "/api/orgs/{$orgId}/report-templates/{$templateId}"
                ],
                'Employee' => [
                    'ID' => $employee->EmployeeId,
                    'Name' => $employee->FirstName . ' ' . $employee->LastName,
                    'ResourceUrl' => "/api/orgs/{$orgId}/employees/{$employee->EmployeeId}"
                ],
                'InvoiceValue' => $invoiceValue,
                'IssuedInvoiceRows' => [
                    [
                        'Item' => [
                            'ID' => $item->ItemId,
                            'Name' => $item->Name,
                            'ResourceUrl' => "/api/orgs/{$orgId}/items/{$item->ItemId}"
                        ],
                        'ItemName' => $payment->course->title,
                        'RowNumber' => 1,
                        'ItemCode' => $item->Code,
                        'Quantity' => 1,
                        'UnitOfMeasurement' => 'kom',
                        'Price' => $basePrice,
                        'PriceWithVAT' => $priceWithVat,
                        'VATPercent' => $vatPercent,
                        'Discount' => 0,
                        'DiscountPercent' => 0,
                        'Value' => $priceWithVat,
                        'VatRate' => [
                            'ID' => $vat->VatRateId,
                            'Name' => $vat->Code,
                            'ResourceUrl' => "/api/orgs/{$orgId}/vatrates/{$vat->VatRateId}"
                        ],
                    ],
                ],
                'IssuedInvoicePaymentMethods' => [
                    [
                        'PaymentMethod' => [
                            'ID' => $paymentMethodId,
                            'Name' => $paymentMethodType,
                            'ResourceUrl' => "/api/orgs/{$orgId}/paymentMethod/{$paymentMethodId}"
                        ],
                        'Amount' => $invoiceValue,
                        'AmountInDomesticCurrency' => $invoiceValue,
                        'AlreadyPaid' => 'N',
                    ],
                ],
            ];

            Log::info('Minimax invoice creation payload prepared.', ['invoiceData' => $invoiceCreationPayload]);

            // --- STEP 1: Create the invoice ---
            $issuedInvoiceId = $minimaxService->createInvoice($orgId, $invoiceCreationPayload);

            Log::debug('DEBUG: Return value from createInvoice:', [
                'value' => $issuedInvoiceId,
                'type' => gettype($issuedInvoiceId)
            ]);

            Log::info('Minimax Invoice Created successfully', ['issued_invoice_id' => $issuedInvoiceId]);

            if (is_null($issuedInvoiceId)) {
                $errorMsg = 'Issued Invoice ID could not be determined after creation.';
                Log::error($errorMsg, ['payment_id' => $this->paymentId]);
                $this->fail(new \Exception($errorMsg));
                return;
            }

            // Fetch invoice details for RowVersion for the 'issue' action
            $invoiceDetailsForIssue = $minimaxService->getIssuedInvoiceDetails($orgId, (int)$issuedInvoiceId);
            if (!$invoiceDetailsForIssue || !isset($invoiceDetailsForIssue->RowVersion)) {
                $errorMsg = 'Failed to fetch invoice details or RowVersion for issue action.';
                Log::error($errorMsg, ['issued_invoice_id' => $issuedInvoiceId]);
                $this->fail(new \Exception($errorMsg));
                return;
            }
            $rowVersion = $invoiceDetailsForIssue->RowVersion;
            Log::info('Fetched RowVersion for issue action', ['issued_invoice_id' => $issuedInvoiceId, 'row_version' => $rowVersion]);


            // --- STEP 2: Execute "issue" action (fiscalization) ---
            Log::info('Attempting to issue/finalize Minimax invoice via API action.', ['IssuedInvoiceId' => $issuedInvoiceId]);
            $issueActionResponse = $minimaxService->triggerInvoiceAction($orgId, (int)$issuedInvoiceId, 'issue', $rowVersion);

            $fiscalDocumentId = null;
            $fiscalAttachmentId = null;
            $documentNumber = null;
            $documentType = null;
            $fiscalFilename = null;
            $fiscalPdfPath = null;

            if ($issueActionResponse) {
                if (isset($issueActionResponse->DocumentId)) {
                    $fiscalDocumentId = $issueActionResponse->DocumentId;
                    Log::info('Extracted DocumentId from issue action response body.', ['DocumentId' => $fiscalDocumentId]);
                }
                if (isset($issueActionResponse->AttachmentId)) {
                    $fiscalAttachmentId = $issueActionResponse->AttachmentId;
                    Log::info('Extracted AttachmentId from issue action response body.', ['AttachmentId' => $fiscalAttachmentId]);
                }
                if (isset($issueActionResponse->DocumentNumber)) {
                    $documentNumber = $issueActionResponse->DocumentNumber;
                }
                if (isset($issueActionResponse->DocumentType)) {
                    $documentType = $issueActionResponse->DocumentType;
                }
                Log::info('Minimax invoice successfully issued/finalized.', [
                    'IssuedInvoiceId' => $issuedInvoiceId,
                    'fiscal_document_id_from_issue' => $fiscalDocumentId,
                    'fiscal_attachment_id_from_issue' => $fiscalAttachmentId,
                ]);
            } else {
                $errorMsg = 'Failed to issue/finalize Minimax invoice via API action or response was empty.';
                Log::error($errorMsg, [
                    'IssuedInvoiceId' => $issuedInvoiceId,
                    'issue_action_response' => (array)$issueActionResponse
                ]);
            }

            Log::info('Fetching FINAL invoice details to get InvoiceAttachment after issue action.', ['issued_invoice_id' => $issuedInvoiceId]);
            $invoiceDetailsAfterIssue = $minimaxService->getIssuedInvoiceDetails($orgId, (int)$issuedInvoiceId);

            if ($invoiceDetailsAfterIssue && isset($invoiceDetailsAfterIssue->InvoiceAttachment) && isset($invoiceDetailsAfterIssue->InvoiceAttachment->ID)) {
                $fiscalDocumentId = $invoiceDetailsAfterIssue->Document->ID ?? null; // ID glavnog dokumenta, npr. izdatog računa
                $fiscalAttachmentId = $invoiceDetailsAfterIssue->InvoiceAttachment->ID;
                $fiscalFilenameFromMinimax = $invoiceDetailsAfterIssue->InvoiceAttachment->Name ?? 'fiscal_invoice_from_minimax.pdf';
                $documentNumber = $invoiceDetailsAfterIssue->InvoiceNumber ?? null; // Broj fakture
                $documentType = $invoiceDetailsAfterIssue->Document->Name ?? null; // Ime dokumenta, npr. "Izdat račun br:..."

                Log::info('✅ Found InvoiceAttachment details for download.', [
                    'document_id' => $fiscalDocumentId,
                    'attachment_id' => $fiscalAttachmentId,
                    'filename_from_minimax' => $fiscalFilenameFromMinimax
                ]);

                // --- STEP 3: Download this specific InvoiceAttachment ---
                try {
                    $fiscalPdfPath = $minimaxService->downloadAttachmentPdf(
                        (int)$fiscalDocumentId,
                        (int)$fiscalAttachmentId,
                        "{$payment->payment_id}"
                    );
                    $fiscalFilename = basename($fiscalPdfPath);
                    Log::info('✅ Fiscal PDF (InvoiceAttachment) successfully downloaded and saved.', ['fiscal_path' => $fiscalPdfPath]);

                } catch (\Exception $e) {
                    Log::error('❌ Failed to download InvoiceAttachment PDF.', [
                        'error' => $e->getMessage(),
                        'document_id' => $fiscalDocumentId,
                        'attachment_id' => $fiscalAttachmentId
                    ]);
                    $fiscalPdfPath = null;
                    $fiscalFilename = null;
                }
            } else {
                Log::warning('⚠️ No InvoiceAttachment found or accessible in final invoice details. Fiscal PDF will not be available.', [
                    'issued_invoice_id' => $issuedInvoiceId,
                    'invoice_details_response_summary' => [
                        'has_attachment' => isset($invoiceDetailsAfterIssue->InvoiceAttachment),
                        'attachment_id_set' => isset($invoiceDetailsAfterIssue->InvoiceAttachment->ID),
                        'full_response' => (array)$invoiceDetailsAfterIssue
                    ]
                ]);
                $fiscalPdfPath = null;
                $fiscalFilename = null;
            }

            Log::info('Generating and saving initial invoice PDF (non-fiscal).');

            $invoiceDetailsForPdf = $minimaxService->getIssuedInvoiceDetails($orgId, (int)$issuedInvoiceId);
            if (!$invoiceDetailsForPdf) {
                Log::warning('Failed to get issued invoice details for PDF generation. Proceeding with empty data.', ['invoice_id' => $issuedInvoiceId]);
            }

            $pdfAttach = Pdf::loadView('emails.attachment', ['invoice' => $invoiceDetailsForPdf ?? (object)[]]);
            $filename = 'invoice_' . $issuedInvoiceId . '.pdf';
            $path = "invoices/{$payment->payment_id}/{$filename}";
            Storage::disk('public')->put($path, $pdfAttach->output());
            Log::info('Invoice PDF saved.', ['path' => $path]);

            $totalAmount = (float)$payment->converted_amount;

            // Save Minimax invoice data to local database
            $minimaxInvoiceData = [
                'invoice_id' => $issuedInvoiceId,
                'customer_name' => $payment->user->name ?? 'Unknown Customer',
                'file_name' => $filename,
                'storage_path' => $path,
                'total_amount' => $totalAmount,
                'invoice_date' => $date,
                'payment_id' => $payment->payment_id,
                'document_id' => $fiscalDocumentId,
                'document_number' => $documentNumber,
                'document_type' => $documentType,
                'fiscal_attachment_id' => $fiscalAttachmentId,
                'fiscal_file_name' => $fiscalFilename,
            ];
            Log::info('Saving MinimaxInvoice record to local database.', ['minimaxInvoiceData' => $minimaxInvoiceData]);

            try {
                MinimaxInvoice::create($minimaxInvoiceData);
                Log::info('✅ MinimaxInvoice record saved successfully.', ['invoice_id' => $issuedInvoiceId]);
            } catch (\Exception $e) {
                $errorMsg = 'Failed to save MinimaxInvoice to local database.';
                Log::error($errorMsg, [
                    'error' => $e->getMessage(),
                    'data' => $minimaxInvoiceData,
                ]);
                $this->fail(new \Exception($errorMsg, 0, $e));
                return;
            }

            Log::info('Final paths for email attachment:', [
                'invoice_pdf_path' => $path,
                'fiscal_pdf_path' => $fiscalPdfPath ?? 'N/A (not found/downloaded)'
            ]);

            Mail::to($payment->user->email)->send(new InvoiceMail($payment, $path, $fiscalPdfPath));
            Log::info('✅ Minimax invoice processed and emailed successfully.', [
                'payment_id' => $this->paymentId,
                'invoice_id' => $issuedInvoiceId,
                'filename' => $filename,
                'fiscal_filename' => $fiscalFilename ?? 'N/A',
            ]);

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'N/A';
            Log::error('❌ Minimax API Request Exception occurred.', [
                'error' => $e->getMessage(),
                'request_url' => $e->getRequest()->getUri(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A',
                'response_body' => $responseBody,
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail($e);
        } catch (Throwable $e) {
            Log::error('❌ General error in CreateMinimaxInvoice job.', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->fail($e);
        }
    }
}
