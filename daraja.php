<?php
function darajaMode(): string
{
    $configuredMode = strtolower(trim(getenv('DARAJA_ENV') ?: ''));
    if ($configuredMode !== '') {
        return $configuredMode;
    }

    $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    return in_array($serverName, ['localhost', '127.0.0.1'], true) ? 'demo' : 'sandbox';
}

function darajaConfig(): array
{
    $config = [
        'consumer_key' => getenv('DARAJA_CONSUMER_KEY') ?: '',
        'consumer_secret' => getenv('DARAJA_CONSUMER_SECRET') ?: '',
        'shortcode' => getenv('DARAJA_SHORTCODE') ?: '',
        'passkey' => getenv('DARAJA_PASSKEY') ?: '',
        'callback_url' => getenv('DARAJA_CALLBACK_URL') ?: '',
        'environment' => darajaMode(),
    ];

    if ($config['environment'] === 'demo') {
        return $config;
    }

    if ($config['environment'] !== 'sandbox') {
        throw new RuntimeException('Only local demo and Daraja sandbox modes are enabled.');
    }

    foreach (['consumer_key', 'consumer_secret', 'shortcode', 'passkey'] as $key) {
        if ($config[$key] === '') {
            throw new RuntimeException('Daraja sandbox credentials are missing. Configure DARAJA_CONSUMER_KEY, DARAJA_CONSUMER_SECRET, DARAJA_SHORTCODE, and DARAJA_PASSKEY.');
        }
    }

    if (!filter_var($config['callback_url'], FILTER_VALIDATE_URL) || !str_starts_with($config['callback_url'], 'https://')) {
        throw new RuntimeException('Set DARAJA_CALLBACK_URL to a publicly reachable HTTPS callback URL.');
    }

    return $config;
}

function darajaApiRequest(string $url, array $headers = [], ?string $body = null, ?string $basicAuth = null): array
{
    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($body !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    if ($basicAuth !== null) {
        $options[CURLOPT_USERPWD] = $basicAuth;
        $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('M-Pesa request failed' . ($error !== '' ? ': ' . $error : '.') );
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('M-Pesa returned an unreadable response.');
    }

    return $decoded;
}

function normalizeKenyanPhone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    } elseif (preg_match('/^[17]\d{8}$/', $digits)) {
        $digits = '254' . $digits;
    }

    if (!preg_match('/^254[17]\d{8}$/', $digits)) {
        throw new InvalidArgumentException('Enter a valid Kenyan M-Pesa number, such as 0712345678.');
    }

    return $digits;
}

function startMpesaPayment(string $phone, int $amount, string $accountReference, string $description): array
{
    if ($amount < 1) {
        throw new InvalidArgumentException('The payment amount must be at least KSh 1.');
    }

    $normalizedPhone = normalizeKenyanPhone($phone);

    if (darajaMode() === 'demo') {
        $demoId = bin2hex(random_bytes(12));
        return [
            'ResponseCode' => '0',
            'ResponseDescription' => 'Demo request only. No money was charged.',
            'MerchantRequestID' => 'DEMO-MERCHANT-' . $demoId,
            'CheckoutRequestID' => 'DEMO-CHECKOUT-' . $demoId,
            'CustomerMessage' => 'Demo only: confirm the simulated payment on the next page.',
        ];
    }

    $config = darajaConfig();
    $baseUrl = 'https://sandbox.safaricom.co.ke';
    $credentials = darajaApiRequest(
        $baseUrl . '/oauth/v1/generate?grant_type=client_credentials',
        ['Accept: application/json'],
        null,
        $config['consumer_key'] . ':' . $config['consumer_secret']
    );
    if (empty($credentials['access_token'])) {
        throw new RuntimeException('M-Pesa could not authorize the payment request.');
    }

    $timestamp = date('YmdHis');
    $request = darajaApiRequest(
        $baseUrl . '/mpesa/stkpush/v1/processrequest',
        [
            'Authorization: Bearer ' . $credentials['access_token'],
            'Content-Type: application/json',
        ],
        json_encode([
            'BusinessShortCode' => $config['shortcode'],
            'Password' => base64_encode($config['shortcode'] . $config['passkey'] . $timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $normalizedPhone,
            'PartyB' => $config['shortcode'],
            'PhoneNumber' => $normalizedPhone,
            'CallBackURL' => $config['callback_url'],
            'AccountReference' => substr($accountReference, 0, 12),
            'TransactionDesc' => substr($description, 0, 13),
        ], JSON_THROW_ON_ERROR)
    );

    if (($request['ResponseCode'] ?? null) !== '0'
        || empty($request['CheckoutRequestID'])
        || empty($request['MerchantRequestID'])) {
        throw new RuntimeException($request['ResponseDescription'] ?? 'M-Pesa did not accept the payment request.');
    }

    return $request;
}

function verifyMpesaPayment(string $checkoutRequestId): array
{
    $config = darajaConfig();
    if ($config['environment'] !== 'sandbox') {
        throw new RuntimeException('Provider verification is available only in Daraja sandbox mode.');
    }

    $baseUrl = 'https://sandbox.safaricom.co.ke';
    $credentials = darajaApiRequest(
        $baseUrl . '/oauth/v1/generate?grant_type=client_credentials',
        ['Accept: application/json'],
        null,
        $config['consumer_key'] . ':' . $config['consumer_secret']
    );
    if (empty($credentials['access_token'])) {
        throw new RuntimeException('M-Pesa could not authorize payment verification.');
    }

    $timestamp = date('YmdHis');
    return darajaApiRequest(
        $baseUrl . '/mpesa/stkpushquery/v1/query',
        [
            'Authorization: Bearer ' . $credentials['access_token'],
            'Content-Type: application/json',
        ],
        json_encode([
            'BusinessShortCode' => $config['shortcode'],
            'Password' => base64_encode($config['shortcode'] . $config['passkey'] . $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ], JSON_THROW_ON_ERROR)
    );
}

function createPaymentIntent(mysqli $conn, int $userId, string $type, array $payload, int $amount, string $phone): int
{
    if (!in_array($type, ['order', 'booking'], true)) {
        throw new InvalidArgumentException('Invalid payment type.');
    }

    $normalizedPhone = normalizeKenyanPhone($phone);
    $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
    $stmt = $conn->prepare('INSERT INTO payment_transactions (user_id, payment_type, payload, amount, phone) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare the payment record.');
    }
    $stmt->bind_param('issis', $userId, $type, $payloadJson, $amount, $normalizedPhone);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Could not save the payment request.');
    }
    $paymentId = $stmt->insert_id;
    $stmt->close();

    try {
        $providerRequest = startMpesaPayment(
            $normalizedPhone,
            $amount,
            ($type === 'order' ? 'ORDER' : 'BOOKING') . $paymentId,
            ucfirst($type) . ' payment'
        );

        $demoNote = darajaMode() === 'demo' ? 'DEMO ONLY: no money was charged.' : null;
        $update = $conn->prepare('UPDATE payment_transactions SET checkout_request_id = ?, merchant_request_id = ?, result_desc = ? WHERE payment_id = ?');
        if (!$update) {
            throw new RuntimeException('Could not connect the M-Pesa request to the payment record.');
        }
        $checkoutId = $providerRequest['CheckoutRequestID'];
        $merchantId = $providerRequest['MerchantRequestID'];
        $update->bind_param('sssi', $checkoutId, $merchantId, $demoNote, $paymentId);
        if (!$update->execute()) {
            $update->close();
            throw new RuntimeException('Could not save the M-Pesa request reference.');
        }
        $update->close();
    } catch (Throwable $error) {
        $failure = $conn->prepare("UPDATE payment_transactions SET status = 'failed', result_desc = ? WHERE payment_id = ?");
        if ($failure) {
            $description = substr($error->getMessage(), 0, 255);
            $failure->bind_param('si', $description, $paymentId);
            $failure->execute();
            $failure->close();
        }
        throw $error;
    }

    return $paymentId;
}