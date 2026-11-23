<?php
session_start();
require_once 'db.php';
require_once 'daraja.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['farmer']);
mfcRequirePost();
if (empty($_SESSION['commerce_csrf']) || !hash_equals($_SESSION['commerce_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$userId = (int) $currentUser['user_id'];
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: 0;
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 0;
$overridePhone = trim($_POST['payment_phone'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'mpesa';
$deliveryAddress = trim($_POST['delivery_address'] ?? '');

try {
    if ($itemId < 1 || $quantity < 1 || !in_array($paymentMethod, ['mpesa', 'cash'], true) || $deliveryAddress === '' || strlen($deliveryAddress) > 255) {
        throw new InvalidArgumentException('Choose a valid product, quantity, payment method, and delivery address.');
    }

    $itemStmt = $conn->prepare('SELECT item_name, unit_price, quantity_in_stock FROM inventory WHERE item_id = ?');
    $itemStmt->bind_param('i', $itemId);
    $itemStmt->execute();
    $item = $itemStmt->get_result()->fetch_assoc();
    $itemStmt->close();

    if (!$item || $quantity > (int) $item['quantity_in_stock']) {
        throw new RuntimeException('There is not enough stock for that quantity.');
    }

    $userStmt = $conn->prepare('SELECT phone FROM users WHERE user_id = ?');
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $registeredPhone = (string) ($userStmt->get_result()->fetch_assoc()['phone'] ?? '');
    $userStmt->close();

    $phone = $overridePhone !== '' ? $overridePhone : $registeredPhone;
    $total = (float) $item['unit_price'] * $quantity;
    $amount = (int) round($total);
    if ($amount < 1) {
        throw new RuntimeException('The order total must be at least KSh 1.');
    }

    if ($paymentMethod === 'cash') {
        $conn->begin_transaction();
        $stockStmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? WHERE item_id = ? AND quantity_in_stock >= ?');
        $stockStmt->bind_param('iii', $quantity, $itemId, $quantity);
        $stockStmt->execute();
        if ($stockStmt->affected_rows !== 1) {
            $stockStmt->close();
            $conn->rollback();
            throw new RuntimeException('There is not enough stock for that quantity.');
        }
        $stockStmt->close();
        $paymentStatus = 'unpaid';
        $orderStatus = 'processing';
        $displayStatus = 'pending';
        $stmt = $conn->prepare('INSERT INTO orders (farmer_id, item_id, quantity, total_amount, payment_status, payment_method, status, order_status, delivery_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiidsssss', $userId, $itemId, $quantity, $total, $paymentStatus, $paymentMethod, $displayStatus, $orderStatus, $deliveryAddress);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            throw new RuntimeException('Could not record the cash order.');
        }
        $stmt->close();
        $conn->commit();
        header('Location: dashboard.php?order_msg=cash_order');
        exit();
    }

    $paymentId = createPaymentIntent($conn, $userId, 'order', [
        'item_id' => $itemId,
        'quantity' => $quantity,
        'item_name' => $item['item_name'],
        'total_amount' => number_format($total, 2, '.', ''),
        'delivery_address' => $deliveryAddress,
    ], $amount, $phone);

    header('Location: payment_status.php?id=' . $paymentId);
    exit();
} catch (Throwable $error) {
    http_response_code(400);
    $message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Payment Could Not Start</title><link rel="stylesheet" href="style.css"></head><body><header style="padding:12px 20px;background:#2e7d32"><a href="index.php" style="display:flex;align-items:center;gap:10px;color:white;text-decoration:none;font-weight:700"><img src="Logo.png" alt="Meru Farmers Centre logo" style="width:54px;height:54px;object-fit:contain;border-radius:50%;background:white">Meru Farmers Centre</a></header><main class="form-panel" style="max-width:560px;margin:10vh auto"><h1>Payment Could Not Start</h1><p>' . $message . '</p><p><a href="inventory.php" class="btn">Return to catalog</a></p></main></body></html>';
}
