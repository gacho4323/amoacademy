<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Instrukcije za uplatu</title>
</head>
<body style="font-family: sans-serif; color: #333; line-height: 1.6;">
    <h2>✅ Vaša porudžbina je primljena</h2>

    <p>Poštovani {{ $order->user->name }},</p>

    <p>
        Hvala Vam na poverenju. Vaša porudžbina broj <strong>#{{ $order->id }}</strong> je uspešno zabeležena.
    </p>

    <p>Molimo Vas da izvršite uplatu u skladu sa sledećim podacima:</p>

    <ul>
        <li><strong>Iznos za uplatu:</strong> {{ number_format($order->amount, 2, ',', '.') }} RSD</li>
        <li><strong>Primalac:</strong> Amo Academy d.o.o.</li>
        <li><strong>Račun:</strong> 160-1234567890123-45</li>
        <li><strong>Svrha uplate:</strong> Porudžbina #{{ $order->id }}</li>
        <li><strong>Poziv na broj:</strong> /nije potreban/</li>
    </ul>

    <p><strong>Možete koristiti i QR kod za jednostavno plaćanje putem mobilne banke:</strong></p>

    <p>
        <img src="https://amoacademy.net/qr.jpg" alt="QR kod za uplatu" width="200" height="200">
    </p>

    <p>
        Nakon što uplata bude evidentirana, biće Vam poslat račun i pristup kupljenim materijalima.
    </p>

    <p>Srdačno,<br>
    <strong>Amo Academy tim</strong><br>
    <a href="https://www.amoacademy.net">www.amoacademy.net</a>
    </p>
</body>
</html>
