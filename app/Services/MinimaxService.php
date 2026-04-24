<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MinimaxService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected string $grant_type;
    protected string $scope;
    protected string $locale;
    protected string $accessToken;
    protected string $apiBaseUrl;
    protected string $tokenEndpoint;
    protected string $organizationId;
    protected Client $client;

    public function __construct()
    {
        $config = config('services.minimax', []);
        Log::debug('Minimax configuration in MinimaxService', [
            'config' => $config,
            'env_MINIMAX_ORGANIZATION_ID' => env('MINIMAX_ORGANIZATION_ID'),
        ]);

        $this->clientId = $config['client_id'] ?? throw new Exception('Minimax client_id is missing in configuration');
        $this->clientSecret = $config['client_secret'] ?? throw new Exception('Minimax client_secret is missing in configuration');
        $this->username = $config['username'] ?? throw new Exception('Minimax username is missing in configuration');
        $this->password = $config['password'] ?? throw new Exception('Minimax password is missing in configuration');
        $this->grant_type = $config['grant_type'] ?? throw new Exception('Minimax grant_type is missing in configuration');
        $this->scope = $config['scope'] ?? 'minimax.rs';
        $this->locale = $config['locale'] ?? 'RS';
        $this->organizationId = $config['organization_id'] ?? '81028';

        $this->tokenEndpoint = "https://moj.minimax.rs/{$this->locale}/aut/oauth20/token";
        $this->apiBaseUrl = "https://moj.minimax.rs/{$this->locale}/API/";
        $this->client = new Client();

        Log::info('MinimaxService initialized', [
            'client_id' => $this->clientId,
            'username' => $this->username,
            'scope' => $this->scope,
            'locale' => $this->locale,
            'token_endpoint' => $this->tokenEndpoint,
            'organization_id' => $this->organizationId,
            'client' => $this->client,
        ]);

        $this->authenticate();
    }

    protected function authenticate(): void
    {
        try {
            $response = Http::asForm()->post(
                $this->tokenEndpoint,
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => $this->grant_type,
                    'username' => $this->username,
                    'password' => $this->password,
                    'scope' => $this->scope,
                ]
            );

            Log::info('Minimax authentication response', ['response' => $response->body()]);

            if (!$response->successful()) {
                Log::error('Minimax authentication failed', ['response' => $response->body()]);
                throw new Exception('Authentication failed: ' . $response->body());
            }

            $this->accessToken = $response->json()['access_token'];
            Log::info('Minimax authentication successful', ['access_token' => '***********']);
        } catch (Exception $e) {
            Log::critical('Minimax authentication error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function request(string $method, string $endpoint, array $params = [], bool $returnFullResponse = false)
    {
        if (!is_array($params)) {
            Log::critical('🚫 request() called with non-array $params', [
                'type' => gettype($params),
                'params' => $params,
            ]);
            throw new \InvalidArgumentException('MinimaxService::request() expects $params to be an array.');
        }

        $url = rtrim($this->apiBaseUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        Log::info('📤 Sending API request', [
            'method' => $method,
            'url' => $url,
            'params' => (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT') ? '***' : $params,
            'headers' => array_keys($headers),
        ]);

        try {
            $response = match (strtoupper($method)) {
                'GET' => Http::withHeaders($headers)->get($url, $params),
                'DELETE' => Http::withHeaders($headers)->delete($url, $params),
                default => Http::withHeaders($headers)->send($method, $url, [
                    'json' => $params,
                ]),
            };

            Log::debug('API response details', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                // 'headers' => $response->headers(),
            ]);

            if (!$response->successful()) {
                Log::error('❌ API request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'sent_params' => $params,
                ]);
                throw new \Exception('API error: ' . $response->status() . ' - ' . $response->body());
            }

            if ($returnFullResponse) {
                return $response;
            }

            Log::debug('Raw response body', ['body' => $response->body()]);

            if ($response->body() === '[]') {
                return (object)[];
            }

            $decodedBody = $response->object();

            if ($decodedBody === null) {
                Log::warning('Minimax API response body is not a valid JSON object or is null string.', [
                    'url' => $url,
                    'body' => $response->body(),
                ]);
                return (object)[];
            }

            return $decodedBody;

        } catch (\Exception $e) {
            Log::critical('🚨 API request exception', [
                'url' => $url,
                'method' => $method,
                'params' => $params,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getVatRate($organizationId, $code, $date): object
    {
        $response = $this->request(
            'GET',
            "api/orgs/{$organizationId}/vatrates",
            [
                'date' => Carbon::parse($date)->format('d-m-Y'),
                'countryID' => 3,
            ]
        );
        Log::info('✔ VatRate API response', ['rows' => json_decode(json_encode($response->Rows), true)]);

        Log::info('🔍 Searching for VAT Code', ['code' => $code]);

        $vatRate = collect($response->Rows)->firstWhere('Code', strtoupper($code));

        if (!$vatRate) {
            throw new \Exception('VAT API returned malformed data or VAT rate not found.'); // Poboljšana poruka
        }

        return (object)$vatRate;
    }

    public function getCurrencyById(string $orgId, int $currencyId): ?object
    {
        Log::info("📤 Sending API request to fetch currency by ID.", [
            "method" => "GET",
            "url" => "api/orgs/{$orgId}/currencies/{$currencyId}"
        ]);

        try {
            $response = $this->request('GET', "api/orgs/{$orgId}/currencies/{$currencyId}");

            if (isset($response->CurrencyId) && $response->CurrencyId === $currencyId) {
                Log::info("✅ Currency found by direct ID lookup.", ['currency' => $response]);
                return $response;
            }

            Log::warning("⚠️ Currency with ID {$currencyId} not found or response malformed.", ['response' => $response]);
            return null;

        } catch (\Exception $e) {
            Log::error("❌ Failed to fetch currency by ID from Minimax API.", [
                "currency_id" => $currencyId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function createCustomer(string $orgId, array $data): object
    {
        Log::info('Attempting to create customer', ['orgId' => $orgId, 'code' => $data['Code'] ?? 'N/A']); // Loguj samo code

        $response = $this->request('POST', "api/orgs/{$orgId}/customers", $data);

        if (is_object($response) && isset($response->CustomerId)) {
            Log::info('Customer created successfully', ['customer_id' => $response->CustomerId, 'code' => $data['Code']]);
            return $response;
        }

        Log::warning('Customer creation response is empty or invalid, attempting to fetch by code', [
            'code' => $data['Code'],
            'response' => (array)$response,
        ]);

        sleep(1);

        $customer = $this->getCustomerByCode($orgId, $data['Code']);
        if ($customer && isset($customer->CustomerId)) {
            Log::info('Customer found after creation', ['customer_id' => $customer->CustomerId, 'code' => $data['Code']]);
            return $customer;
        }

        Log::error('Failed to create or retrieve customer', [
            'orgId' => $orgId,
            'code' => $data['Code'],
            'response' => (array)$response,
        ]);
        throw new \Exception('Failed to create or retrieve customer with code: ' . $data['Code']);
    }

    public function getCustomerByCode(string $orgId, string $code): ?object
    {
        try {
            $response = $this->request('GET', "api/orgs/{$orgId}/customers/code({$code})");
            if (isset($response->CustomerId)) {
                Log::info('✅ Customer found by direct code lookup.', ['customer_code' => $code, 'customer_id' => $response->CustomerId]);
                return $response;
            }
            Log::warning('⚠️ Customer not found by direct code lookup.', ['customer_code' => $code, 'response' => (array)$response]);
            return null;
        } catch (\Exception $e) {
            // Ako direct lookup po kodu ne radi (npr. vrati 404), fallback na getCustomers()
            Log::warning('Direct customer lookup by code failed, falling back to all customers search.', ['code' => $code, 'error' => $e->getMessage()]);
            $response = $this->getCustomers($orgId);

            Log::info('Customer list fetched for fallback search', [
                'searching_code' => $code,
                'total_customers' => count($response->Rows ?? []),
            ]);

            return collect($response->Rows)->firstWhere('Code', $code);
        }
    }

    public function getCustomers(string $organizationId): object
    {
        $response = $this->request('GET', "api/orgs/{$organizationId}/customers");

        if (!isset($response->Rows) || !is_array($response->Rows)) {
            throw new \Exception('Customers API returned malformed data.');
        }

        return $response;
    }

    public function getItem(string $orgId, string $itemCode)
    {
        Log::info('Fetching item', ['orgId' => $orgId, 'itemCode' => $itemCode]);
        $encodedItemCode = urlencode($itemCode);
        $response = $this->request('GET', "api/orgs/{$orgId}/items/code({$encodedItemCode})");
        Log::info('Item fetched', ['response' => (array)$response]);
        return $response;
    }

    public function getItems(string $orgId)
    {
        Log::info('Fetching all items', ['orgId' => $orgId]);
        $response = $this->request('GET', "api/orgs/{$orgId}/items");
        Log::info('Items fetched', ['response' => (array)$response]);
        return $response;
    }

    public function createItem(string $orgId, array $data)
    {
        Log::info('Creating item', ['orgId' => $orgId, 'item_code' => $data['Code'] ?? 'N/A']);
        $response = $this->request('POST', "api/orgs/{$orgId}/items", $data);
        Log::info('Item created', ['response' => (array)$response]);
        return $response;
    }

    public function findItemCodeByCourseTitle(string $orgId, string $courseTitle): ?string
    {
        $items = $this->getItems($orgId);

        if (!isset($items->Rows) || !is_array($items->Rows)) {
            Log::error('Items API returned malformed data when searching by course title.');
            return null;
        }

        foreach ($items->Rows as $item) {
            $itemName = $item->Name ?? $item->Title ?? '';
            if (stripos($itemName, $courseTitle) !== false) {
                Log::info('🔍 Matching item found for course', [
                    'course' => $courseTitle,
                    'matched_item_name' => $itemName,
                    'matched_code' => $item->Code,
                ]);
                return $item->Code;
            }
        }

        Log::warning('⚠️ No matching item found for course', ['course' => $courseTitle]);
        return null;
    }

    public function getReportTemplates()
    {
        return $this->request('GET', "api/orgs/{$this->organizationId}/report-templates");
    }

    public function getReportTemplateByType(string $displayType): ?object
    {
        $templates = $this->getReportTemplates();

        return collect($templates->Rows)
            ->first(fn($t) => $t->DisplayType === $displayType);
    }

    public function getReportTemplateIdByType(string $displayType): ?int
    {
        $template = $this->getReportTemplateByType($displayType);
        return $template->ReportTemplateId ?? null;
    }

    public function getDocumentNumbering(string $orgId)
    {
        return $this->request('GET', "api/orgs/{$orgId}/document-numbering");
    }

    public function createInvoice(string $orgId, array $invoiceData): ?int
    {
        Log::info('Attempting to create invoice', ['customer_id' => $invoiceData['Customer']['ID']]);

        try {
            $response = $this->request('POST', "api/orgs/{$orgId}/issuedinvoices", $invoiceData, true);

            if ($response->status() === 201) {
                $locationHeader = $response->header('Location');
                if ($locationHeader) {
                    $parts = explode('/', $locationHeader);
                    $issuedInvoiceId = (int) end($parts);
                    Log::info('✅ Minimax Invoice Created. ID from Location header.', ['issued_invoice_id' => $issuedInvoiceId]);
                    return $issuedInvoiceId;
                } else {
                    Log::error('Minimax createInvoice returned 201 but missing Location header.', ['response_body' => $response->body(), 'response_headers' => $response->headers()]);
                    return null;
                }
            } else {
                Log::error('Minimax createInvoice failed with unexpected status.', [
                    'status' => $response->status(),
                    'response_body' => $response->body()
                ]);
                throw new \Exception('Minimax invoice creation failed: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Exception during Minimax invoice creation.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invoice_data' => $invoiceData
            ]);
            throw $e;
        }
    }

    public function getIssuedInvoices(string $orgId, array $params = []): object
    {
        Log::info('Fetching issued invoices', ['orgId' => $orgId, 'params' => $params]);

        $endpoint = "api/orgs/{$orgId}/issuedinvoices";
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        $response = $this->request('GET', $endpoint);

        if (!isset($response->Rows) || !is_array($response->Rows)) {
            Log::error('Issued invoices API returned malformed data', ['response' => (array)$response]);
            throw new Exception('Issued invoices API returned malformed data.');
        }

        Log::info('Issued invoices fetched', [
            'orgId' => $orgId,
            'total_invoices' => count($response->Rows),
            'params' => $params
        ]);

        return $response;
    }

    public function triggerInvoiceAction(string $orgId, string $invoiceId, string $actionName, string $rowVersion, array $params = []): ?object
    {
        Log::info('Triggering invoice action', [
            'invoiceId' => $invoiceId,
            'actionName' => $actionName,
            'rowVersion' => $rowVersion,
            'params' => $params,
        ]);

        $endpoint = "api/orgs/{$orgId}/issuedinvoices/{$invoiceId}/actions/{$actionName}";
        if ($rowVersion) {
            $endpoint .= '?rowVersion=' . urlencode($rowVersion);
        }

        try {
            return $this->request('PUT', $endpoint, $params);
        } catch (Exception $e) {
            Log::error('Failed to trigger invoice action', [
                'invoiceId' => $invoiceId,
                'actionName' => $actionName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getEmployeeByCode(string|int $code): ?object
    {

        $employeeId = (int)$code;
        if ($employeeId > 0) {
            $employee = $this->getEmployeeById($this->organizationId, $employeeId);
            if ($employee) {
                return $employee;
            }
        }

        Log::info('Fetching all employees to search by code.', ['code' => $code]);
        $response = $this->request('GET', "api/orgs/{$this->organizationId}/employees");
        return collect($response->Rows ?? [])->firstWhere('Code', (string)$code); // Poređenje kao string
    }

    public function getIssuedInvoiceDetails($organisationId, $issuedInvoiceId): ?object
    {
        try {
            $response = $this->request('GET', "api/orgs/{$organisationId}/issuedinvoices/{$issuedInvoiceId}");
            Log::info('Fetched invoice details', [
                'invoiceId' => $issuedInvoiceId,
                'status' => $response->Status ?? 'N/A',
                'rowVersion' => $response->RowVersion ?? 'N/A',
                'response_summary' => ['Customer' => $response->Customer->Name ?? 'N/A', 'DateIssued' => $response->DateIssued ?? 'N/A'], // Loguj sažetak
            ]);
            return $response;
        } catch (Exception $e) {
            Log::error('Failed to fetch invoice details', [
                'invoiceId' => $issuedInvoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function getInvoicePdf($orgId, $issuedInvoiceId)
    {
        $url = "api/orgs/{$orgId}/issuedinvoices/{$issuedInvoiceId}/pdf";

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/pdf',
        ];

        $response = Http::withHeaders($headers)->get($this->apiBaseUrl . $url);

        if ($response->successful()) {
            return $response->body();
        }

        throw new \Exception('Failed to get invoice PDF: ' . $response->status() . ' - ' . $response->body());
    }

    public function getFiscalPdf(string $orgId, int $documentId, int $attachmentId): string
    {
        $url = "api/orgs/{$orgId}/documents/{$documentId}/attachments/{$attachmentId}";

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/pdf',
        ];

        $response = Http::withHeaders($headers)->get($this->apiBaseUrl . $url);

        if ($response->successful()) {
            return $response->body();
        }

        throw new \Exception('Failed to fetch fiscal invoice PDF: ' . $response->status());
    }

    public function getDocuments(string $orgId, array $filters = []): object
    {
        return $this->request('GET', 'api/orgs/' . $orgId . '/documents?' . http_build_query($filters));
    }

    public function getDocumentAttachment(string $orgId, int $documentId, int $attachmentId): object
    {
        return $this->request('GET', "api/orgs/{$orgId}/documents/{$documentId}/attachments/{$attachmentId}");
    }

    public function updateIssuedInvoice(string $orgId, string $invoiceId, array $payload)
    {
        Log::info('Updating issued invoice', [
            'orgId' => $orgId,
            'invoiceId' => $invoiceId,
            // 'payload' => $payload,
        ]);

        return $this->request(
            'PUT',
            "api/orgs/{$orgId}/issuedinvoices/{$invoiceId}",
            $payload
        );
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function getIssuedInvoice(string $orgId, string $invoiceId): array
    {
        Log::info('Fetching single issued invoice', [
            'orgId' => $orgId,
            'invoiceId' => $invoiceId
        ]);

        $endpoint = "api/orgs/{$orgId}/issuedinvoices/{$invoiceId}";

        $response = $this->request('GET', $endpoint);

        if (!isset($response->IssuedInvoiceId)) {
            Log::error('Single issued invoice fetch failed', ['response' => (array) $response]);
            throw new Exception('Issued invoice API returned malformed data.');
        }

        return (array) $response;
    }

    /**
     * Dohvata sve načine plaćanja za datu organizaciju.
     * @param string $orgId ID Minimax organizacije.
     * @return object Objekat sa listom načina plaćanja (u 'Rows' property-u).
     * @throws Exception Ako API vrati loše formatirane podatke.
     */
    public function getPaymentMethods(string $orgId): object
    {
        $response = $this->request('GET', "api/orgs/{$orgId}/paymentmethods");

        if (!isset($response->Rows) || !is_array($response->Rows)) {
            Log::error('Payment methods API returned malformed data or empty rows.', ['response' => (array)$response]);
            throw new Exception('Payment methods API returned malformed data.');
        }

        return $response;
    }

    /**
     * Dohvata specifičan način plaćanja po njegovom nazivu (npr. "Tekući račun", "Kartica").
     * @param string $paymentMethodName Naziv načina plaćanja.
     * @return object|null Objekat načina plaćanja ako je pronađen, inače null.
     */
    public function getPaymentMethodByName(string $paymentMethodName): ?object
    {
        $orgId = config('services.minimax.organization_id');
        Log::info("📤 Sending API request to fetch payment methods for search.", [
            "method" => "GET",
            "url" => "api/orgs/{$orgId}/paymentmethods"
        ]);

        try {
            $response = $this->request('GET', "api/orgs/{$orgId}/paymentmethods");

            if (!isset($response->Rows) || !is_array($response->Rows)) {
                Log::warning("⚠️ Minimax payment methods response missing 'Rows' or it's not an array for search.", ['response' => $response]);
                return null;
            }

            $paymentMethod = collect($response->Rows)->first(function ($method) use ($paymentMethodName) {
                return isset($method->Name) && (mb_strtolower(trim($method->Name)) === mb_strtolower(trim($paymentMethodName)));
            });

            if ($paymentMethod) {
                Log::info("✅ Payment method found by name.", ['name' => $paymentMethodName, 'id' => $paymentMethod->PaymentMethodId]);
                return $paymentMethod;
            }

            Log::warning("⚠️ Payment method '{$paymentMethodName}' not found in Minimax response by name.", ['response_rows_count' => count($response->Rows)]);
            return null;

        } catch (\Exception $e) {
            Log::error("❌ Failed to fetch payment methods by name from Minimax API.", [
                "payment_method_name" => $paymentMethodName,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
            return null;
        }
    }


    /**
     * Dohvata ID načina plaćanja po njegovom nazivu.
     * Ovo je metoda koju treba da pozoveš iz svog Joba.
     * @param string $paymentMethodName Naziv načina plaćanja (npr. "Tekući račun", "Kartica").
     * @return int|null ID načina plaćanja ako je pronađen, inače null.
     */
    public function getPaymentMethodIdByName(string $paymentMethodName): ?int
    {
        $method = $this->getPaymentMethodByName($paymentMethodName);
        return $method->PaymentMethodId ?? null;
    }

    /**
     * Dohvata zaposlenog iz Minimaxa po njegovom ID-ju.
     * Koristi direktan API endpoint: GET /api/orgs/{organisationId}/employees/{employeeId}
     *
     * @param string $orgId ID Minimax organizacije.
     * @param int $employeeId Specifičan EmployeeId zaposlenog.
     * @return object|null Objekat zaposlenog ako je pronađen, inače null.
     */
    public function getEmployeeById(string $orgId, int $employeeId): ?object
    {
        Log::info("📤 Sending API request to fetch employee by ID.", [
            "method" => "GET",
            "url" => "api/orgs/{$orgId}/employees/{$employeeId}"
        ]);

        try {
            $response = $this->request('GET', "api/orgs/{$orgId}/employees/{$employeeId}");

            if (isset($response->EmployeeId) && $response->EmployeeId === $employeeId) {
                Log::info("✅ Employee found by direct ID lookup.", ['employee' => $response]);
                return $response;
            }

            Log::warning("⚠️ Employee with ID {$employeeId} not found or response malformed.", ['response' => $response]);
            return null;

        } catch (\Exception $e) {
            Log::error("❌ Failed to fetch employee by ID from Minimax API.", [
                "employee_id" => $employeeId,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getDocument(string $orgId, string $documentId): object
    {
        $url = "{$this->apiBaseUrl}/api/orgs/{$orgId}/documents/{$documentId}";

        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->get($url)
            ->object();
    }

    public function getFiscalInvoicePdf(string $orgId, string $documentId, string $attachmentId): string
    {
        $url = "{$this->apiBaseUrl}/api/orgs/{$orgId}/documents/{$documentId}/attachments/{$attachmentId}";

        return Http::withToken($this->accessToken)
            ->accept('application/pdf')
            ->get($url)
            ->body();
    }

    public function downloadAttachmentPdf(int $documentId, int $attachmentId, string $folder = ''): string
    {
        Log::debug("MinimaxService::downloadAttachmentPdf called.", [
            'document_id' => $documentId,
            'attachment_id' => $attachmentId,
            'folder' => $folder
        ]);

        try {
            $data = $this->getDocumentAttachment($this->organizationId, $documentId, $attachmentId);

            if (empty($data->AttachmentData)) {
                Log::error("MinimaxService::downloadAttachmentPdf - AttachmentData is empty for doc:{$documentId}, att:{$attachmentId}. Full response:", ['data' => (array)$data]);
                throw new \Exception("AttachmentData is empty for document {$documentId}, attachment {$attachmentId}.");
            }

            $decoded = base64_decode($data->AttachmentData);

            $baseStoragePath = 'invoices/';
            $finalDirectory = $baseStoragePath;

            if (!empty($folder)) {
                $cleanFolder = trim($folder, '/');
                $finalDirectory .= $cleanFolder . '/';
            }

            $proposedFileName = $data->FileName ?? 'document_' . $attachmentId . '.pdf';
            $proposedFileName = basename($proposedFileName);

            $filename = $finalDirectory . $proposedFileName;

            $directory = dirname($filename);
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
                Log::debug("MinimaxService::downloadAttachmentPdf - Directory created: " . $directory);
            } else {
                Log::debug("MinimaxService::downloadAttachmentPdf - Directory already exists: " . $directory);
            }

            Storage::disk('public')->put($filename, $decoded);
            Log::info("MinimaxService::downloadAttachmentPdf - File successfully saved: " . $filename);

            return $filename;
        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? (string) $e->getResponse()->getBody() : 'N/A';
            Log::error('MinimaxService::downloadAttachmentPdf - API Request Exception:', [
                'error' => $e->getMessage(),
                'request_url' => $e->getRequest()->getUri(),
                'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A',
                'response_body' => $responseBody,
                'document_id' => $documentId,
                'attachment_id' => $attachmentId
            ]);
            throw new \Exception("Failed to download attachment PDF from Minimax API: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            Log::error('MinimaxService::downloadAttachmentPdf - General Exception:', [
                'error' => $e->getMessage(),
                'document_id' => $documentId,
                'attachment_id' => $attachmentId
            ]);
            throw $e;
        }
    }
}
