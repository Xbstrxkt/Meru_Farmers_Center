<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';

if (isset($_SESSION['user_id'])) {
    mfcRequireRoles($conn, MFC_USER_ROLES);
}

$query = trim($_GET['q'] ?? '');
$inventory = $orders = $services = [];

if ($query !== '' && mb_strlen($query) <= 100) {
    $like = '%' . $query . '%';

    $stmt = $conn->prepare('SELECT item_id, item_name, category, unit_price, quantity_in_stock FROM inventory WHERE item_name LIKE ? OR category LIKE ? ORDER BY item_name LIMIT 25');
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (isset($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
        $isStaff = in_array($_SESSION['role'] ?? '', ['admin', 'finance_manager', 'dispatch_manager'], true);
        if ($isStaff) {
            $stmt = $conn->prepare('SELECT o.order_id, o.farmer_id, u.full_name, i.item_name, o.quantity, o.total_amount, o.payment_status, o.status FROM orders o LEFT JOIN users u ON u.user_id = o.farmer_id LEFT JOIN inventory i ON i.item_id = o.item_id WHERE CAST(o.order_id AS CHAR) LIKE ? OR u.full_name LIKE ? OR i.item_name LIKE ? ORDER BY o.order_id DESC LIMIT 25');
            $stmt->bind_param('sss', $like, $like, $like);
        } else {
            $stmt = $conn->prepare('SELECT o.order_id, o.farmer_id, u.full_name, i.item_name, o.quantity, o.total_amount, o.payment_status, o.status FROM orders o LEFT JOIN users u ON u.user_id = o.farmer_id LEFT JOIN inventory i ON i.item_id = o.item_id WHERE o.farmer_id = ? AND (CAST(o.order_id AS CHAR) LIKE ? OR i.item_name LIKE ? OR o.status LIKE ?) ORDER BY o.order_id DESC LIMIT 25');
            $stmt->bind_param('isss', $userId, $like, $like, $like);
        }
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if (($_SESSION['role'] ?? '') === 'trainer') {
        $userId = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT b.booking_id, b.farmer_id, u.full_name, b.service_type, b.booking_date, b.status, b.payment_status FROM bookings b LEFT JOIN users u ON u.user_id = b.farmer_id WHERE b.trainer_id = ? AND (b.service_type LIKE ? OR b.booking_date LIKE ? OR b.status LIKE ?) ORDER BY b.booking_date DESC LIMIT 25');
        $stmt->bind_param('isss', $userId, $like, $like, $like);
    } else {
        $stmt = $conn->prepare('SELECT b.booking_id, b.farmer_id, u.full_name, b.service_type, b.booking_date, b.status, b.payment_status FROM bookings b LEFT JOIN users u ON u.user_id = b.farmer_id WHERE b.service_type LIKE ? OR b.booking_date LIKE ? OR b.status LIKE ? ORDER BY b.booking_date DESC LIMIT 25');
        $stmt->bind_param('sss', $like, $like, $like);
    }
    $stmt->execute();
    $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$role = $_SESSION['role'] ?? '';
$canSeeServices = in_array($role, ['admin', 'service_manager', 'trainer'], true);
$canSeeAllServices = in_array($role, ['admin', 'service_manager'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search | Meru Farmers Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="container py-4">
        <h1 class="h2 mb-3">Search Meru Farmers Centre</h1>
        <form class="row g-2 mb-4" method="GET" action="search.php">
            <div class="col-sm-9"><label class="visually-hidden" for="q">Search terms</label><input class="form-control" id="q" name="q" value="<?php echo htmlspecialchars($query); ?>" maxlength="100" placeholder="Products, order number, service..." required></div>
            <div class="col-sm-3 d-grid"><button class="btn btn-success" type="submit">Search</button></div>
        </form>
        <?php if ($query === ''): ?>
            <p class="text-secondary">Enter a name, category, order number, or service to search.</p>
        <?php elseif (mb_strlen($query) > 100): ?>
            <div class="alert alert-warning">Search terms must be 100 characters or fewer.</div>
        <?php else: ?>
            <p class="text-secondary">Results for <strong><?php echo htmlspecialchars($query); ?></strong></p>
            <section class="mb-4"><h2 class="h4">Inventory</h2>
                <?php if (!$inventory): ?><p>No matching products.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th></tr></thead><tbody><?php foreach ($inventory as $item): ?><tr><td><?php echo htmlspecialchars($item['item_name']); ?></td><td><?php echo htmlspecialchars($item['category'] ?? ''); ?></td><td>KSh <?php echo number_format((float) $item['unit_price'], 2); ?></td><td><?php echo (int) $item['quantity_in_stock']; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section>
            <?php if (isset($_SESSION['user_id'])): ?><section class="mb-4"><h2 class="h4">Orders</h2>
                <?php if (!$orders): ?><p>No matching orders.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?php echo (int) $order['order_id']; ?></td><td><?php echo htmlspecialchars($order['full_name'] ?? 'Customer'); ?></td><td><?php echo htmlspecialchars($order['item_name'] ?? 'Product'); ?></td><td><?php echo (int) $order['quantity']; ?></td><td>KSh <?php echo number_format((float) $order['total_amount'], 2); ?></td><td><?php echo htmlspecialchars($order['payment_status'] ?? 'unpaid'); ?></td><td><?php echo htmlspecialchars($order['status'] ?? 'pending'); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section><?php endif; ?>
            <?php if ($canSeeServices): ?><section><h2 class="h4">Service bookings</h2>
                <?php if (!$services): ?><p>No matching service bookings.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Date</th><th>Status</th><th>Payment</th></tr></thead><tbody><?php foreach ($services as $service): ?><tr><td>#<?php echo (int) $service['booking_id']; ?></td><td><?php echo htmlspecialchars($service['full_name'] ?? 'Customer'); ?></td><td><?php echo htmlspecialchars($service['service_type']); ?></td><td><?php echo htmlspecialchars($service['booking_date']); ?></td><td><?php echo htmlspecialchars($service['status']); ?></td><td><?php echo htmlspecialchars($service['payment_status']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section><?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>