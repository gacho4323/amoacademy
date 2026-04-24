<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MinimaxService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MinimaxTest extends Command
{
    protected $signature = 'minimax:test';
    protected $description = 'Test Minimax API integration';

    public function handle()
    {
        $minimax = new MinimaxService();

        $orgId = (int) env('MINIMAX_ORGANIZATION_ID', 81028);

        $date = now()->format('Y-m-d');

        $vat = $minimax->getVatRate($orgId, 'S', $date);
        $currency = $minimax->getCurrency($orgId, 'EUR');
        $country = $minimax->getCountry($orgId, 'HU');

        $itemCode = Str::random(16);
        $item = $minimax->createItem($orgId, [
            'Name' => 'Test item',
            'Code' => $itemCode,
            'ItemType' => 'B',
            'VatRate' => ['ID' => $vat->VatRateId],
            'Price' => 100.0,
            'Currency' => ['ID' => $currency->CurrencyId],
        ]);

        $customerCode = Str::random(16);
        $customer = $minimax->createCustomer($orgId, [
            'Name' => 'Test customer',
            'Address' => 'Some Street',
            'PostalCode' => '11000',
            'City' => 'Belgrade',
            'Code' => $customerCode,
            'Country' => ['ID' => $country->CountryId],
            'CountryName' => '-',
            'SubjectToVAT' => 'N',
            'Currency' => ['ID' => $currency->CurrencyId],
            'EInvoiceIssuing' => 'SeNePripravlja',
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
        Storage::disk('local')->put("minimax/{$filename}", $decodedPdf);

        $this->info('Invoice created and PDF saved as: ' . storage_path("app/minimax/{$filename}"));
    }
}
