<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Define the path to the private key
$privateKeyPath = __DIR__ . '/storage/app/upc/1731267.pem';

// Define variables matching your .env and notify test case
$merchantId = '1731267'; // UPC_MERCHANT_ID
$terminalId = 'E7051267'; // UPC_TERMINAL_ID
$purchaseTime = '250630165645'; // From logs
$orderId = 'ORD16564511'; // From logs
$delay = '1'; // From logs
$currencyId = '941'; // UPC_CURRENCY_ID (RSD)
$totalAmount = '16'; // Integer for RSD, from logs
$xid = '';
$altCurrencyId = '';
$altTotalAmount = '';
$sd = '';
$tranCode = '';
$approvalCode = '';

// Construct signature data string for notify endpoint (Page 9)
$data = implode(';', [
$merchantId,
$terminalId,
$purchaseTime,
$orderId . ',' . $delay,
$xid,
$currencyId . ($altCurrencyId ? ',' . $altCurrencyId : ''),
$totalAmount . ($altTotalAmount ? ',' . $altTotalAmount : ''),
$sd,
$tranCode,
$approvalCode,
'', // Single trailing semicolon per documentation
]);
$data = str_replace(["\r", "\n", " "], '', $data);

echo "Signature Data: $data\n";

if (!file_exists($privateKeyPath)) {
die("Private key not found at $privateKeyPath\n");
}

$privateKey = file_get_contents($privateKeyPath);
$pkeyid = openssl_get_privatekey($privateKey);
if (!$pkeyid) {
die("Failed to load private key\n");
}

if (!openssl_sign($data, $signature, $pkeyid, OPENSSL_ALGO_SHA1)) {
die("Failed to generate signature\n");
}
openssl_free_key($pkeyid);

$b64sign = base64_encode($signature);
echo "Generated Signature: $b64sign\n";
echo "Signature Length: " . strlen($signature) . "\n";