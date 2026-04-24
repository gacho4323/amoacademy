<?php

namespace App\Http\Controllers\API;

use App\Jobs\CreateMinimaxInvoice;
use App\Models\Payment;
use App\Services\MinimaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class MinimaxController extends Controller
{
    protected MinimaxService $minimaxService;

    /**
     * MinimaxController constructor.
     * @param MinimaxService $minimaxService
     */
    public function __construct(MinimaxService $minimaxService)
    {
        $this->minimaxService = $minimaxService;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getIssuedInvoices(Request $request): mixed
    {
        try {
            $params = [
                'dateFrom' => $request->input('dateFrom', '01-01-2025'),
                'dateTo' => $request->input('dateTo', '31-12-2025'),
            ];

            $orgId = config('services.minimax.organization_id');

            $response = $this->minimaxService->getIssuedInvoices($orgId, $params);

            Log::info('Issued invoices retrieved', [
                'total' => count($response->Rows),
                'invoices' => collect($response->Rows)->take(5)->toArray(), // Log first 5 for brevity
            ]);

            return response()->json([
                'success' => true,
                'data' => $response->Rows,
                'total' => count($response->Rows),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch issued invoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param string $paymentId
     * @return mixed
     */
    public function testInvoice(string $paymentId): mixed
    {
        $payment = Payment::where('payment_id', $paymentId)->firstOrFail();

        if ($payment->status !== 'completed') {
            $payment->update(['status' => 'completed']);
        }

        CreateMinimaxInvoice::dispatch($paymentId);

        return response()->json([
            'message' => 'Invoice creation job dispatched for payment ID: ' . $paymentId,
        ], 200);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function test(): mixed
    {
        $minimax = new MinimaxService();

        $orgId = config('services.minimax.organization_id');
        $date = now()->format('Y-m-d');

        $vat = $minimax->getVatRate($orgId, 'S', $date);
        $currency = $minimax->getCurrency($orgId, 'EUR');
        $country = $minimax->getCountry($orgId, 'HU');

        $itemCode = Str::random(16);
        $item = $minimax->createItem($orgId, [
            'Name'     => 'Test item',
            'Code'     => $itemCode,
            'ItemType' => 'B',
            'VatRate'  => ['ID' => $vat->VatRateId],
            'Price'    => 100.0,
            'Currency' => ['ID' => $currency->CurrencyId],
        ]);

        $customerCode = Str::random(16);
        $customer = $minimax->createCustomer($orgId, [
            'Name'             => 'Test customer',
            'Address'          => 'Some Street',
            'PostalCode'       => '11000',
            'City'             => 'Belgrade',
            'Code'             => $customerCode,
            'Country'          => ['ID' => $country->CountryId],
            'CountryName'      => '-',
            'SubjectToVAT'     => 'N',
            'Currency'         => ['ID' => $currency->CurrencyId],
            'EInvoiceIssuing'  => 'SeNePripravlja',
        ]);

        $invoiceJson = json_encode([
             'Customer' => ['ID' => $customer->CustomerId],
             'DateIssued' => $date,
             'DateTransaction' => $date,
             'DateTransactionFrom' => $date,
             'DateDue' => $date,
             'AddresseeName' => 'Test Customer',
             'AddresseeAddress' => 'Somewhere',
             'AddresseePostalCode' => '11000',
             'AddresseeCity' => 'Belgrade',
             'AddresseeCountryName' => '-',
             'AddresseeCountry' => ['ID' => $country->CountryId],
             'Currency' => ['ID' => $currency->CurrencyId],
             'IssuedInvoiceReportTemplate' => ['ID' => 1],
             'DeliveryNoteReportTemplate' => ['ID' => 1],
             'Status' => 'O',
             'PricesOnInvoice' => 'N',
             'RecurringInvoice' => 'N',
             'InvoiceType' => 'R',
             'IssuedInvoiceRows' => [[
                 'Item' => ['ID' => $item->ItemId],
                 'ItemName' => 'Test',
                 'RowNumber' => 1,
                 'ItemCode' => $itemCode,
                 'Description' => 'description',
                 'Quantity' => 1,
                 'UnitOfMeasurement' => 'kom',
                 'Price' => 100,
                 'PriceWithVAT' => 120,
                 'VATPercent' => $vat->Percent,
                 'Discount' => 0,
                 'DiscountPercent' => 0,
                 'Value' => 100,
                 'VatRate' => ['ID' => $vat->VatRateId],
             ]]
         ]);

        $invoice = $minimax->createInvoice($orgId, $invoiceJson);

        $pdfData = $minimax->triggerInvoiceAction($orgId, $invoice->IssuedInvoiceId, $invoice->RowVersion);

        $filename = $pdfData->Data->AttachmentFileName;
        $decodedPdf = base64_decode($pdfData->Data->AttachmentData);
        $path = "minimax/{$filename}";

        Storage::disk('local')->put($path, $decodedPdf);

        return response($decodedPdf)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * @param $invoiceTitle
     * @return JsonResponse
     * @throws Exception
     */
    public function downloadFiscal($invoiceTitle): JsonResponse
    {
        $orgId = config('services.minimax.organization_id');
        $minimax = $this->minimaxService;

        $documentId = $minimax->getDocumentIdByInvoiceTitle($orgId, $invoiceTitle);
        $attachmentId = $minimax->getFirstPdfAttachmentId($orgId, $documentId);

        if ($documentId && $attachmentId) {
            $pdf = $minimax->getFiskalniPdf($orgId, $documentId, $attachmentId);
            return response($pdf, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "inline; filename={$invoiceTitle}.pdf");
        }

        return response()->json(['error' => 'Fiskalni PDF nije pronađen.'], 404);
    }
}
