<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['admin', 'finance_manager']);
mfcRequirePost();

if (empty($_SESSION['finance_csrf']) || !hash_equals($_SESSION['finance_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$recordType = $_POST['record_type'] ?? 'order';
$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT) ?: 0;
$paymentStatus = $_POST['payment_status'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$allowedStatuses = ['unpaid', 'paid', 'refunded'];
$allowedMethods = ['cash', 'mpesa'];

if ($recordId < 1 || !in_array($paymentStatus, $allowedStatuses, true) || !in_array($recordType, ['order', 'booking', 'supplier_po'], true)) {
    http_response_code(400);
    exit('Invalid payment update.');
}

if ($recordType === 'supplier_po') {
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        http_response_code(400);
        exit('Choose a valid supplier payment method.');
    }
    $stmt = $conn->prepare('UPDATE supplier_purchase_orders SET payment_status = ?, payment_method = ? WHERE purchase_order_id = ?');
    $stmt->bind_param('ssi', $paymentStatus, $paymentMethod, $recordId);
} elseif ($recordType === 'booking') {
    $stmt = $conn->prepare('UPDATE bookings SET payment_status = ? WHERE booking_id = ?');
    $stmt->bind_param('si', $paymentStatus, $recordId);
} else {
    $stmt = $conn->prepare('UPDATE orders SET payment_status = ? WHERE order_id = ?');
    $stmt->bind_param('si', $paymentStatus, $recordId);
}

$stmt->execute();
$stmt->close();
header('Location: staff.php#finance');
exit();
?>
