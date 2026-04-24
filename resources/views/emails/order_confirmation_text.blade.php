Poštovani,

Hvala na uspešnoj narudžbini! Vaša narudžbina broj {{ $order_id }} je uspešno obrađena.

@if ($payment_method === 'uplatnica')
Molimo Vas da izvršite uplatu u skladu sa sledećim podacima kako bismo obradili Vašu porudžbinu:

📌 Podaci za uplatu:
Naziv primaoca: Amo Academy doo
Adresa: Ložnička 13
PIB: 114865225
MB: 22081705
Tekući račun: 265-1110310009284-83
Svrha uplate: Plaćanje po predračunu – Amo Academy
Iznos: {{ number_format($amount, 2, ',', '.') }} RSD

✅ Nakon uplate, poslaćemo Vam račun i potvrdu.
@else
✅ Vaša transakcija je uspešno zabeležena.
@endif

Srdačno,
Amo Academy tim

www.amoacademy.rs
Kontakt: info@amoacademy.rs