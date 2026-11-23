<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['farmer']);

$user_id = $_SESSION['user_id'];
$user_role = $currentUser['role'];
$user_id = (int) $currentUser['user_id'];

// Fetch user profile details
$user_stmt = $conn->prepare("SELECT full_name, email, phone, role FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_profile = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-shell">
        <div class="dashboard-header">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($user_profile['full_name']); ?>!</h1>
                <p style="text-transform: uppercase; font-size: 0.85rem; color: #2e7d32; font-weight: bold; margin-top: 4px;">
                    Role: <?php echo htmlspecialchars($user_role); ?>
                </p>
                <p class="nav-links" style="margin-top: 5px;">
                    <a href="index.php">Home</a>
                    <a href="inventory.php">Catalog</a>
                    <?php if ($user_role === 'admin'): ?>
                        <a href="admin.php">Admin Panel</a>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['finance_msg']) && $_GET['finance_msg'] === 'payment_updated'): ?>
            <div class="card" role="status"><p>Payment status updated.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['order_msg']) && $_GET['order_msg'] === 'cash_order'): ?>
            <div class="card" role="status"><p>Your cash-on-delivery order was placed. Payment remains due on delivery.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['booking_msg']) && $_GET['booking_msg'] === 'cash_booking'): ?>
            <div class="card" role="status"><p>Your cash booking request was submitted for service team review.</p></div>
        <?php endif; ?>

        <!-- PROFILE CARD -->
        <div class="card">
            <h2>Account Profile</h2>
            <p style="margin-top: 10px;">
                <strong>Email:</strong> <?php echo htmlspecialchars($user_profile['email']); ?><br>
                <strong>Phone:</strong> <?php echo htmlspecialchars($user_profile['phone'] ?: 'Not provided'); ?><br>
            </p>
        </div>

        <!-- 1. FARMER SPECIFIC VIEW -->
        <?php if ($user_role === 'farmer'): ?>
            <?php
            // Fetch bookings for this farmer
            $booking_stmt = $conn->prepare("SELECT booking_id, service_type, booking_date, status, total_amount, payment_status, payment_method FROM bookings WHERE farmer_id = ? ORDER BY booking_id DESC");
            $booking_stmt->bind_param("i", $user_id);
            $booking_stmt->execute();
            $user_bookings = $booking_stmt->get_result();
            $booking_stmt->close();

            // Fetch orders for this farmer using unit_price
            $orders_query = "SELECT o.order_id, i.item_name, o.total_amount AS total_price, o.quantity, o.status, o.payment_status, o.payment_method, o.delivery_address, o.order_status 
                            FROM orders o 
                            LEFT JOIN inventory i ON o.item_id = i.item_id 
                            WHERE o.farmer_id = ? 
                            ORDER BY o.order_id DESC";
            $order_stmt = $conn->prepare($orders_query);
            $order_stmt->bind_param("i", $user_id);
            $order_stmt->execute();
            $user_orders = $order_stmt->get_result();
            $order_stmt->close();
            ?>

            <div class="card">
                <h2>My Service Bookings</h2>
                <?php if ($user_bookings && $user_bookings->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Booking ID</th><th>Service</th><th>Date</th><th>Amount</th><th>Method / payment</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $user_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['booking_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['service_type']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['booking_date']); ?></td>
                                        <td>KSh <?php echo number_format((float) $row['total_amount'], 2); ?></td>
                                        <td><?php echo strtoupper(htmlspecialchars($row['payment_method'])); ?> / <span class="badge <?php echo htmlspecialchars($row['payment_status']); ?>"><?php echo strtoupper($row['payment_status']); ?></span></td>
                                        <td><span class="badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="margin-top: 10px;">No active service bookings found.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>My Product Orders</h2>
                <?php if ($user_orders && $user_orders->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Order ID</th><th>Item</th><th>Qty</th><th>Total</th><th>Method / payment</th><th>Delivery</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $user_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['order_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($order['item_name'] ?? 'Agricultural Input'); ?></strong></td>
                                        <td><?php echo $order['quantity']; ?></td>
                                        <td>KSh <?php echo number_format($order['total_price'] ?? 0, 2); ?></td>
                                        <td><?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?> / <span class="badge <?php echo htmlspecialchars($order['payment_status'] ?? 'unpaid'); ?>"><?php echo strtoupper($order['payment_status'] ?? 'unpaid'); ?></span></td>
                                        <td><?php echo htmlspecialchars($order['order_status'] ?? $order['status']); ?><br><small><?php echo htmlspecialchars($order['delivery_address'] ?? ''); ?></small></td>
                                        <td><span class="badge <?php echo htmlspecialchars($order['status']); ?>"><?php echo strtoupper($order['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="margin-top: 10px;">No product orders placed yet. <a href="inventory.php">Browse catalog</a>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 2. FINANCE MANAGER SPECIFIC VIEW -->
        <?php if ($user_role === 'finance_manager' || $user_role === 'admin'): ?>
            <p><a href="staff.php#finance" class="btn btn-secondary">Open Finance Workspace</a></p>
            <?php
            $finance_result = $conn->query("SELECT o.order_id, u.full_name AS farmer_name, i.item_name, o.total_amount AS total_price, o.payment_status FROM orders o LEFT JOIN users u ON o.farmer_id = u.user_id LEFT JOIN inventory i ON o.item_id = i.item_id ORDER BY o.order_id DESC");
            ?>
            <div class="card" style="border-left: 5px solid #2e7d32;">
                <h2>Finance Manager Panel (Payment Approvals)</h2>
                <?php if ($finance_result && $finance_result->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Order ID</th><th>Farmer</th><th>Item</th><th>Total</th><th>Current Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $finance_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['farmer_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                        <td>KSh <?php echo number_format($row['total_price'], 2); ?></td>
                                        <td><span class="badge <?php echo $row['payment_status']; ?>"><?php echo strtoupper($row['payment_status']); ?></span></td>
                                        <td>
                                            <form action="update_payment.php" method="POST" style="display:flex; gap:5px;">
                                                <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['finance_csrf']); ?>">
                                                <select name="payment_status" style="padding:3px; border-radius:4px;">
                                                    <option value="unpaid" <?php if($row['payment_status']=='unpaid') echo 'selected'; ?>>Unpaid</option>
                                                    <option value="paid" <?php if($row['payment_status']=='paid') echo 'selected'; ?>>Paid</option>
                                                    <option value="refunded" <?php if($row['payment_status']=='refunded') echo 'selected'; ?>>Refunded</option>
                                                </select>
                                                <button type="submit" class="btn" style="padding:3px 8px; font-size:0.8rem;">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No financial records found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 3. DISPATCH MANAGER SPECIFIC VIEW -->
        <?php if ($user_role === 'dispatch_manager' || $user_role === 'admin'): ?>
            <?php
            $dispatch_result = $conn->query("SELECT d.dispatch_id, d.order_id, u.full_name AS farmer_name, d.delivery_status, d.dispatched_at FROM dispatches d LEFT JOIN orders o ON d.order_id = o.order_id LEFT JOIN users u ON o.farmer_id = u.user_id ORDER BY d.dispatch_id DESC");
            ?>
            <div class="card" style="border-left: 5px solid #1976d2;">
                <h2>Dispatch Manager Panel (Logistics & Deliveries)</h2>
                <?php if ($dispatch_result && $dispatch_result->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Dispatch ID</th><th>Order ID</th><th>Farmer</th><th>Delivery Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $dispatch_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $row['dispatch_id']; ?></td>
                                        <td>#<?php echo $row['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['farmer_name'] ?? 'Unknown'); ?></td>
                                        <td><span class="badge <?php echo $row['delivery_status']; ?>"><?php echo strtoupper($row['delivery_status']); ?></span></td>
                                        <td>
                                            <form action="update_dispatch.php" method="POST" style="display:flex; gap:5px;">
                                                <input type="hidden" name="dispatch_id" value="<?php echo $row['dispatch_id']; ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                                <select name="delivery_status" style="padding:3px; border-radius:4px;">
                                                    <option value="assigned" <?php if($row['delivery_status']=='assigned') echo 'selected'; ?>>Assigned</option>
                                                    <option value="in_transit" <?php if($row['delivery_status']=='in_transit') echo 'selected'; ?>>In Transit</option>
                                                    <option value="delivered" <?php if($row['delivery_status']=='delivered') echo 'selected'; ?>>Delivered</option>
                                                </select>
                                                <button type="submit" class="btn" style="padding:3px 8px; font-size:0.8rem;">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No dispatch tasks found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
