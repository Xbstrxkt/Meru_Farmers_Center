<?php
require_once 'db.php';
require_once 'daraja.php';
require_once 'payment_fulfillment.php';

function callbackValue(array $items, string $name): ?string
{
    foreach ($items as $item) {
        if (($item['Name'] ?? '') === $name && isset($item['Value'])) {
            return (string) $item['Value'];
        }
    }
    return null;
}

$body = file_get_contents('php://input');
$callback = json_decode($body, true);
$result = $callback['Body']['stkCallback'] ?? null;
if (!is_array($result)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback payload']);
    exit();
}

$checkoutId = (string) ($result['CheckoutRequestID'] ?? '');
$resultCode = (int) ($result['ResultCode'] ?? 1);
$description = substr((string) ($result['ResultDesc'] ?? ''), 0, 255);
$items = $result['CallbackMetadata']['Item'] ?? [];
$receipt = callbackValue($items, 'MpesaReceiptNumber');
$chargedAmount = callbackValue($items, 'Amount');
$chargedPhone = callbackValue($items, 'PhoneNumber');
$verifiedSuccess = false;

if ($resultCode === 0) {
    try {
        $verification = verifyMpesaPayment($checkoutId);
        $verifiedSuccess = (string) $verification['ResultCode'] === '0';
        if (!$verifiedSuccess) {
            $resultCode = (int) $verification['ResultCode'];
            $description = substr((string) ($verification['ResultDesc'] ?? 'M-Pesa reports that the payment did not complete.'), 0, 255);
        }
    } catch (Throwable $error) {
        error_log('M-Pesa server-side verification failed: ' . $error->getMessage());
        http_response_code(503);
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment verification is temporarily unavailable']);
        exit();
    }
}

$conn->begin_transaction();
$stmt = $conn->prepare('SELECT payment_id, amount, phone, status FROM payment_transactions WHERE checkout_request_id = ? FOR UPDATE');
$stmt->bind_param('s', $checkoutId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment request not found']);
    exit();
}

if ($payment['status'] !== 'pending') {
    $conn->commit();
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback already processed']);
    exit();
}

if ($resultCode !== 0) {
    $status = 'failed';
    $update = $conn->prepare('UPDATE payment_transactions SET status = ?, result_desc = ? WHERE payment_id = ?');
    $update->bind_param('ssi', $status, $description, $payment['payment_id']);
    $update->execute();
    $update->close();
    $conn->commit();
} elseif ($verifiedSuccess && $receipt && (float) $chargedAmount === (float) $payment['amount'] && (string) $chargedPhone === (string) $payment['phone']) {
    $status = 'pending';
    $update = $conn->prepare('UPDATE payment_transactions SET status = ?, mpesa_receipt = ?, result_desc = ? WHERE payment_id = ?');
    $update->bind_param('sssi', $status, $receipt, $description, $payment['payment_id']);
    $update->execute();
    $update->close();
    $conn->commit();
    if (!fulfillPaidIntent($conn, (int) $payment['payment_id'])) {
        $status = 'paid_unfulfilled';
        $description = 'Payment was received but the order or booking could not be completed. Staff review is required.';
        $failedFulfillment = $conn->prepare('UPDATE payment_transactions SET status = ?, result_desc = ? WHERE payment_id = ?');
        $failedFulfillment->bind_param('ssi', $status, $description, $payment['payment_id']);
        $failedFulfillment->execute();
        $failedFulfillment->close();
    }
} else {
    $status = 'paid_unfulfilled';
    $description = 'Payment received but callback amount or phone did not match the request.';
    $update = $conn->prepare('UPDATE payment_transactions SET status = ?, mpesa_receipt = ?, result_desc = ? WHERE payment_id = ?');
    $update->bind_param('sssi', $status, $receipt, $description, $payment['payment_id']);
    $update->execute();
    $update->close();
    $conn->commit();
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received']);