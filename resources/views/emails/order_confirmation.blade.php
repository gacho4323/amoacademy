<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Potvrda narudžbine</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
        }
        .header img {
            max-width: 150px;
        }
        .content {
            padding: 20px;
        }
        .content h2 {
            color: #1a73e8;
        }
        .payment-details {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .payment-details p {
            margin: 5px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            font-size: 14px;
            color: #777;
        }
        .footer a {
            color: #1a73e8;
            text-decoration: none;
        }
        .success-message {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="Amo Academy Logo">
            <h2>Potvrda narudžbine</h2>
        </div>
        <div class="content">
            <p>Poštovani,</p>
            <p>Hvala na uspešnoj narudžbini! Vaša narudžbina broj <strong>{{ $order_id }}</strong> je uspešno obrađena.</p>

            @if ($payment_method === 'uplatnica')
                <p>Molimo Vas da izvršite uplatu u skladu sa sledećim podacima kako bismo obradili Vašu porudžbinu:</p>
                <div class="payment-details">
                    <h3>📌 Podaci za uplatu:</h3>
                    <p><strong>Naziv primaoca:</strong> Amo Academy doo</p>
                    <p><strong>Adresa:</strong> Ložnička 13</p>
                    <p><strong>PIB:</strong> 114865225</p>
                    <p><strong>MB:</strong> 22081705</p>
                    <p><strong>Tekući račun:</strong> 265-1110310009284-83</p>
                    <p><strong>Svrha uplate:</strong> Plaćanje po predračunu – Amo Academy</p>
                    <p><strong>Iznos:</strong> {{ number_format($amount, 2, ',', '.') }} RSD</p>
                </div>
                <p>✅ Nakon uplate, poslaćemo Vam račun i potvrdu.</p>
            @else
                <p class="success-message">✅ Vaša transakcija je uspešno zabeležena.</p>
            @endif

            <p>Srdačno,<br>Amo Academy tim</p>
        </div>
        <div class="footer">
            <p><a href="https://www.amoacademy.rs">www.amoacademy.rs</a></p>
            <p>Kontakt: <a href="mailto:info@amoacademy.rs">info@amoacademy.rs</a></p>
        </div>
    </div>
</body>
</html>