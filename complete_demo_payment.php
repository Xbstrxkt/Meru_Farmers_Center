<?php
session_start();
require_once 'db.php';
require_once 'daraja.php';
require_once 'payment_fulfillment.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['farmer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || darajaMode() !== 'demo'
    || empty($_SESSION['payment_csrf'])
    || !hash_equals($_SESSION['payment_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('This demo payment request is not valid.');
}

$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT) ?: 0;
$userId = (int) $currentUser['user_id'];
$stmt = $conn->prepare('SELECT status, result_desc FROM payment_transactions WHERE payment_id = ? AND user_id = ?');
$stmt->bind_param('ii', $paymentId, $userId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment || $payment['status'] !== 'pending'
    || !str_starts_with((string) ($payment['result_desc'] ?? ''), 'DEMO ONLY:')) {
    http_response_code(404);
    exit('Demo payment request not found.');
}

if (fulfillPaidIntent($conn, $paymentId)) {
    $receipt = 'DEMO-' . str_pad((string) $paymentId, 8, '0', STR_PAD_LEFT);
    $update = $conn->prepare('UPDATE payment_transactions SET mpesa_receipt = ?, result_desc = ? WHERE payment_id = ? AND user_id = ?');
    $description = 'DEMO ONLY: simulated payment; no money was charged.';
    $update->bind_param('ssii', $receipt, $description, $paymentId, $userId);
    $update->execute();
    $update->close();
} else {
    $update = $conn->prepare("UPDATE payment_transactions SET status = 'failed', result_desc = 'Demo checkout could not be fulfilled; no money was charged.' WHERE payment_id = ? AND user_id = ? AND status = 'pending'");
    $update->bind_param('ii', $paymentId, $userId);
    $update->execute();
    $update->close();
}

header('Location: payment_status.php?id=' . $paymentId);
exit();