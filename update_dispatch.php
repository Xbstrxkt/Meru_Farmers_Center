<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['admin', 'dispatch_manager', 'driver']);
mfcRequirePost();

if (empty($_SESSION['staff_csrf']) || !hash_equals($_SESSION['staff_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$userId = (int) $currentUser['user_id'];
$role = $currentUser['role'];
$dispatchId = filter_input(INPUT_POST, 'dispatch_id', FILTER_VALIDATE_INT) ?: 0;
$newStatus = $_POST['delivery_status'] ?? '';
$allowedStatuses = $role === 'driver' ? ['in_transit', 'delivered'] : ['assigned', 'in_transit', 'delivered'];
if ($dispatchId < 1 || !in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid dispatch update.');
}

$conn->begin_transaction();
if ($role === 'driver') {
    $stmt = $conn->prepare("UPDATE dispatches SET delivery_status = ?, delivered_at = IF(? = 'delivered', NOW(), delivered_at) WHERE dispatch_id = ? AND driver_id = ?");
    $stmt->bind_param('ssii', $newStatus, $newStatus, $dispatchId, $userId);
} else {
    $stmt = $conn->prepare("UPDATE dispatches SET delivery_status = ?, delivered_at = IF(? = 'delivered', NOW(), delivered_at) WHERE dispatch_id = ?");
    $stmt->bind_param('ssi', $newStatus, $newStatus, $dispatchId);
}
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();

if ($changed === 1) {
    $orderStatus = $newStatus === 'delivered' ? 'delivered' : ($newStatus === 'in_transit' ? 'dispatched' : 'processing');
    if ($role === 'driver') {
        $stmt = $conn->prepare('UPDATE orders o INNER JOIN dispatches d ON d.order_id = o.order_id SET o.order_status = ?, o.status = ? WHERE d.dispatch_id = ? AND d.driver_id = ?');
        $stmt->bind_param('ssii', $orderStatus, $orderStatus, $dispatchId, $userId);
    } else {
        $stmt = $conn->prepare('UPDATE orders o INNER JOIN dispatches d ON d.order_id = o.order_id SET o.order_status = ?, o.status = ? WHERE d.dispatch_id = ?');
        $stmt->bind_param('ssi', $orderStatus, $orderStatus, $dispatchId);
    }
    $stmt->execute();
    $stmt->close();
}
$conn->commit();

if ($changed !== 1) {
    http_response_code(404);
    exit('No matching assigned delivery was updated.');
}

header('Location: staff.php#dispatch');
exit();
?>
