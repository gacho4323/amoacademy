<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Invoice</title>
</head>
<body>
    <h1>Thank You for Your Purchase!</h1>
    <p>Dear {{ $payment->user->name }},</p>
    <p>Thank you for purchasing the course <strong>{{ $payment->course->title }}</strong>.</p>
    <p>Please find your invoice attached to this email.</p>
    <p>Details:</p>
    <ul>
        <li>Course: {{ $payment->course->title }}</li>
        <li>Amount: {{ $payment->converted_amount }} RSD</li>
        <li>Payment ID: {{ $payment->payment_id }}</li>
        <li>Date: {{ optional($payment->created_at)->format('d.m.Y') ?? now()->format('d.m.Y') }}</li>
    </ul>
    <p>If you have any questions, feel free to contact us.</p>
    <p>Best regards,<br>Your Amo Academy</p>
</body>
</html>
