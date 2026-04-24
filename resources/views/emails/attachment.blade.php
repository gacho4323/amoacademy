
<style>
    body {
        font-family: DejaVu Sans, sans-serif; /* podržava ćirilicu */
        font-size: 12px;
        color: #333;
        margin: 0 40px;
    }
    h1 {
        font-size: 24px;
        margin-bottom: 20px;
    }
    p, span {
        font-size: 14px;
        margin: 2px 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    table, th, td {
        border: 1px solid #ccc;
    }
    th {
        background-color: #f2f2f2;
        padding: 8px;
        font-weight: bold;
        text-align: left;
    }
    td {
        padding: 8px;
    }
    .total {
        font-weight: bold;
        text-align: right;
        padding-right: 10px;
    }
</style>

<h1>Faktura #{{ $invoice->IssuedInvoiceId }}</h1>

<p>Datum: {{ \Carbon\Carbon::parse($invoice->DateIssued)->format('d.m.Y') }}</p>
<p>Kupac: {{ $invoice->AddresseeName }}</p>
<p>Adresa: {{ $invoice->AddresseeAddress ?? 'N/A' }}, {{ $invoice->AddresseeCity ?? '' }}</p>

<table>
    <thead>
    <tr>
        <th>Stavka</th>
        <th>Količina</th>
        <th>Cena</th>
        <th>Ukupno</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoice->IssuedInvoiceRows as $row)
        <tr>
            <td>{{ $row->ItemName }}</td>
            <td>{{ number_format($row->Quantity, 0, ',', '.') }}</td>
            <td>{{ number_format($row->Price, 2, ',', '.') }} {{ $invoice->Currency->Name }}</td>
            <td>{{ number_format($row->Price * $row->Quantity, 2, ',', '.') }} {{ $invoice->Currency->Name }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="total">Ukupno: {{ number_format(collect($invoice->IssuedInvoiceRows)->sum(fn($r) => $r->Price * $r->Quantity), 2, ',', '.') }} {{ $invoice->Currency->Name }}</p>
