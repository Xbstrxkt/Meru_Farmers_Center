<?php
session_start();
require_once 'db.php';
require_once 'daraja.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['farmer']);

$paymentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$userId = (int) $currentUser['user_id'];
$stmt = $conn->prepare('SELECT payment_id, payment_type, amount, status, mpesa_receipt, result_desc FROM payment_transactions WHERE payment_id = ? AND user_id = ?');
$stmt->bind_param('ii', $paymentId, $userId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    exit('Payment request not found.');
}

if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

$isDemoPayment = darajaMode() === 'demo'
    && str_starts_with((string) ($payment['result_desc'] ?? ''), 'DEMO ONLY:');
$messages = [
    'pending' => $isDemoPayment
        ? 'This is a local demo. No money has been charged. Confirm below to test the order or booking flow.'
        : 'Payment request sent. Approve the M-Pesa prompt on your phone. This page refreshes while payment is pending.',
    'paid' => 'Payment confirmed. Your ' . htmlspecialchars($payment['payment_type']) . ' is recorded.',
    'failed' => 'Payment was not completed. Start a new request from the ' . htmlspecialchars($payment['payment_type']) . ' page.',
    'paid_unfulfilled' => 'Payment was received, but the request needs staff attention. Contact Meru Farmers Centre with the receipt below.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
    <?php if ($payment['status'] === 'pending' && !$isDemoPayment): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="form-panel" style="max-width: 560px; margin: 10vh auto;">
        <?php if ($isDemoPayment): ?>
            <p role="status" style="background:#fff4d7;color:#624400;padding:12px;border-radius:8px;font-weight:700;">DEMO PAYMENT: no money will be charged.</p>
        <?php endif; ?>
        <h1>Payment <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['status']))); ?></h1>
        <p><?php echo $messages[$payment['status']] ?? 'Payment status is unavailable.'; ?></p>
        <p><strong>Amount:</strong> KSh <?php echo number_format((int) $payment['amount']); ?></p>
        <?php if (!empty($payment['mpesa_receipt'])): ?>
            <p><strong>Receipt:</strong> <?php echo htmlspecialchars($payment['mpesa_receipt']); ?></p>
        <?php endif; ?>
        <?php if ($isDemoPayment && $payment['status'] === 'pending'): ?>
            <form action="complete_demo_payment.php" method="POST">
                <input type="hidden" name="payment_id" value="<?php echo (int) $payment['payment_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payment_csrf']); ?>">
                <button type="submit" class="btn">Simulate successful payment</button>
            </form>
        <?php endif; ?>
        <p><a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a></p>
        <?php if ($payment['status'] === 'failed'): ?>
            <p><a href="<?php echo $payment['payment_type'] === 'order' ? 'inventory.php' : 'bookings.php'; ?>">Try again</a></p>
        <?php endif; ?>
    </main>
</body>
</html>