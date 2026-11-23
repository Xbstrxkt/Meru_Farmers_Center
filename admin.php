<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'auth_guard.php';

$adminUser = mfcRequireRoles($conn, ['admin']);

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['message_csrf'])) {
    $_SESSION['message_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !hash_equals($_SESSION['admin_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_action'])) {
    if (!hash_equals($_SESSION['admin_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $accountId = (int) ($_POST['user_id'] ?? 0);
    $accountAction = $_POST['account_action'];
    if ($accountId < 1 || !in_array($accountAction, ['approve', 'reject'], true)) {
        http_response_code(400);
        exit('Invalid account approval request.');
    }

    $approvalStatus = $accountAction === 'approve' ? 'approved' : 'rejected';
    $approvalStmt = $conn->prepare('UPDATE users SET approval_status = ? WHERE user_id = ? AND approval_status = \'pending\'');
    $approvalStmt->bind_param('si', $approvalStatus, $accountId);
    $approvalStmt->execute();
    $accountUpdated = $approvalStmt->affected_rows > 0;
    $approvalStmt->close();
    $message = $accountUpdated
        ? ($accountAction === 'approve' ? 'Account approved.' : 'Account rejected.')
        : 'That account is no longer awaiting approval.';
    header('Location: admin.php?account_msg=' . urlencode($message));
    exit();
}

// --- CSV EXPORT HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Item Name', 'Quantity', 'Paid Amount (KSh)', 'Status']);

    $export_query = "SELECT o.order_id, u.full_name, i.item_name, o.quantity, o.total_amount AS total_price, o.status 
                    FROM orders o 
                    LEFT JOIN users u ON o.farmer_id = u.user_id 
                    LEFT JOIN inventory i ON o.item_id = i.item_id 
                    WHERE o.payment_status = 'paid'
                    ORDER BY o.order_id DESC";
    $export_res = $conn->query($export_query);

    while ($row = $export_res->fetch_assoc()) {
        fputcsv($output, [
            '#' . $row['order_id'],
            $row['full_name'] ?? 'Guest',
            $row['item_name'] ?? 'N/A',
            $row['quantity'],
            number_format($row['total_price'] ?? 0, 2),
            strtoupper($row['status'])
        ]);
    }
    fclose($output);
    exit();
}

// --- POST HANDLERS ---
// 1. Handle Booking Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status'])) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
    $new_status = $_POST['status'] ?? '';
    if ($booking_id < 1 || !in_array($new_status, ['pending', 'approved', 'completed', 'cancelled'], true)) {
        http_response_code(400);
        exit('Invalid booking status update.');
    }

    $update_stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $update_stmt->bind_param("si", $new_status, $booking_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// 2. Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
    $new_status = $_POST['status'] ?? '';
    if ($order_id < 1 || !in_array($new_status, ['pending', 'shipped', 'completed'], true)) {
        http_response_code(400);
        exit('Invalid order status update.');
    }

    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $update_stmt->bind_param("si", $new_status, $order_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// 3. Handle Add New Inventory Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inventory_item'])) {
    $item_name  = trim($_POST['item_name']);
    $category   = trim($_POST['category']);
    $unit_price = floatval($_POST['unit_price']);
    $stock_qty  = intval($_POST['quantity_in_stock']);

    if (!empty($item_name) && $unit_price > 0) {
        $add_stmt = $conn->prepare("INSERT INTO inventory (item_name, category, unit_price, quantity_in_stock) VALUES (?, ?, ?, ?)");
        $add_stmt->bind_param("ssdi", $item_name, $category, $unit_price, $stock_qty);
        $add_stmt->execute();
        $add_stmt->close();
    }
}

// 4. Handle Delete Inventory Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inventory_item'])) {
    $item_id = intval($_POST['item_id']);
    
    $del_stmt = $conn->prepare("DELETE FROM inventory WHERE item_id = ?");
    $del_stmt->bind_param("i", $item_id);
    $del_stmt->execute();
    $del_stmt->close();
}

// --- ANALYTICS & REPORTING METRICS ---
$total_users_res = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'farmer' OR role = 'user'");
$total_farmers = $total_users_res ? $total_users_res->fetch_assoc()['total'] : 0;

$total_bookings_res = $conn->query("SELECT COUNT(*) AS total FROM bookings");
$total_bookings = $total_bookings_res ? $total_bookings_res->fetch_assoc()['total'] : 0;

$total_orders_res = $conn->query("SELECT COUNT(*) AS total FROM orders");
$total_orders = $total_orders_res ? $total_orders_res->fetch_assoc()['total'] : 0;

$sales_revenue_res = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE payment_status = 'paid'");
$service_revenue_res = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM bookings WHERE payment_status = 'paid'");
$sales_revenue = (float) ($sales_revenue_res ? $sales_revenue_res->fetch_assoc()['total'] : 0);
$service_revenue = (float) ($service_revenue_res ? $service_revenue_res->fetch_assoc()['total'] : 0);
$total_revenue = $sales_revenue + $service_revenue;

// --- DATA FETCHING ---
$bookings_query = "SELECT b.booking_id, u.full_name, u.role, u.email, u.phone, b.service_type, b.booking_date, b.total_amount, b.payment_status, b.status 
                  FROM bookings b 
                  LEFT JOIN users u ON b.farmer_id = u.user_id 
                  ORDER BY b.booking_id DESC";
$bookings_result = $conn->query($bookings_query);

$orders_query = "SELECT o.order_id, u.full_name, u.email, i.item_name, o.quantity, o.total_amount AS total_price, o.payment_status, o.status 
                FROM orders o 
                LEFT JOIN users u ON o.farmer_id = u.user_id 
                LEFT JOIN inventory i ON o.item_id = i.item_id 
                ORDER BY o.order_id DESC";
$orders_result = $conn->query($orders_query);

$payment_issues_query = "SELECT p.payment_id, p.payment_type, p.amount, p.phone, p.mpesa_receipt, p.result_desc, p.created_at, u.full_name
                         FROM payment_transactions p
                         LEFT JOIN users u ON p.user_id = u.user_id
                         WHERE p.status = 'paid_unfulfilled'
                         ORDER BY p.payment_id DESC";
$payment_issues_result = $conn->query($payment_issues_query);

$contact_messages = $conn->query("SELECT f.feedback_id, f.subject, f.message, f.created_at, f.review_status, f.staff_reply, f.replied_at, f.resolved_at, u.full_name FROM feedback f LEFT JOIN users u ON u.user_id = f.user_id WHERE f.category = 'contact' ORDER BY (f.review_status = 'new') DESC, f.created_at DESC");
$farmer_feedback = $conn->query("SELECT f.feedback_id, f.subject, f.message, f.rating, f.created_at, f.review_status, f.staff_reply, f.replied_at, f.resolved_at, u.full_name FROM feedback f LEFT JOIN users u ON u.user_id = f.user_id WHERE f.category = 'feedback' ORDER BY (f.review_status = 'new') DESC, f.created_at DESC");

$inventory_query = "SELECT * FROM inventory ORDER BY item_id DESC";
$inventory_result = $conn->query($inventory_query);

$pending_accounts = $conn->query("SELECT user_id, full_name, email, phone, role FROM users WHERE approval_status = 'pending' ORDER BY user_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard & Reports | Meru Farmers Centre</title>
    <style>
        :root {
            --farm-green: #1d6f3c;
            --farm-green-dark: #124c2d;
            --farm-green-soft: #edf7ee;
            --farm-gold: #d9a23a;
            --farm-gold-soft: #fff4d7;
            --text: #1d2a22;
            --muted: #5d6d61;
            --line: #dfeae1;
            --white: #ffffff;
            --surface: #f5faf6;
            --shadow-soft: 0 16px 30px rgba(17, 56, 33, 0.08);
            --shadow-strong: 0 18px 40px rgba(17, 56, 33, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: linear-gradient(180deg, #f4f8f5 0%, #eef4ee 100%);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }

        h1, h2, h3 { margin-top: 0; color: var(--farm-green-dark); letter-spacing: -0.03em; }
        p { margin-top: 0; color: var(--muted); }

        .admin-shell { max-width: 1280px; margin: 0 auto; padding: 32px 20px 48px; }

        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 28px 0 24px;
            flex-wrap: wrap;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1rem;
            border-radius: 999px;
            background: rgba(29, 111, 60, 0.08);
            border: 1px solid rgba(29, 111, 60, 0.12);
            color: var(--farm-green-dark);
            font-weight: 600;
        }

        .search-bar-wrap {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-bar-wrap input {
            width: 100%;
            max-width: 400px;
            padding: 0.75rem 1.2rem;
            border-radius: 12px;
            border: 1px solid var(--line);
            font-size: 0.95rem;
            box-shadow: var(--shadow-soft);
            outline: none;
        }

        .btn-export {
            background: #105d88;
            color: #fff;
            padding: 0.75rem 1.2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: var(--shadow-soft);
            transition: transform 0.2s ease;
        }

        .btn-export:hover { transform: translateY(-2px); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 0 0 28px;
        }

        .stat-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
            border: 1px solid rgba(21, 74, 35, 0.08);
            border-left: 5px solid var(--farm-green);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            transition: transform 0.2s ease;
        }

        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-strong); }
        .stat-card h3 { margin: 0; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; color: var(--muted); }
        .stat-card .number { margin-top: 12px; color: var(--farm-green-dark); font-size: clamp(1.8rem, 2.5vw, 2.4rem); font-weight: 800; }

        .card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(21, 74, 35, 0.08);
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            padding: 24px 18px 18px;
            margin-bottom: 26px;
        }

        .section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }

        .btn-add {
            background: var(--farm-green);
            color: #fff;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 16px; }

        table { width: 100%; min-width: 920px; border-collapse: separate; border-spacing: 0; background: var(--white); }
        thead th {
            position: sticky; top: 0; z-index: 2;
            background: linear-gradient(180deg, var(--farm-green) 0%, var(--farm-green-dark) 100%);
            color: #fff; text-transform: uppercase; font-size: 0.74rem; text-align: left; padding: 14px 16px;
        }
        tbody td { padding: 14px 16px; border-bottom: 1px solid var(--line); vertical-align: top; color: #2d3b31; }
        tbody tr:nth-child(even) { background: rgba(29, 111, 60, 0.03); }
        tbody tr:hover { background: rgba(29, 111, 60, 0.06); }

        .badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 92px; padding: 0.42rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
        }
        .pending { background: var(--farm-gold-soft); color: #8c6502; }
        .approved, .confirmed, .completed { background: rgba(29, 111, 60, 0.10); color: var(--farm-green-dark); }
        .shipped { background: rgba(16, 93, 136, 0.10); color: #145a8a; }

        .inline-status-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .inline-status-form select, .inline-status-form button { border-radius: 10px; border: 1px solid var(--line); font: inherit; }
        .inline-status-form select { padding: 0.5rem 0.7rem; background: #f8fbf8; }
        .inline-status-form button {
            padding: 0.55rem 0.9rem; background: var(--farm-green); color: #fff; font-weight: 700; cursor: pointer; border: none;
        }

        .btn-danger { background: #c62828 !important; color: white !important; padding: 0.4rem 0.8rem; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-grid input, .form-grid select { width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--line); }

        .back-link { display: inline-block; margin-top: 14px; color: var(--farm-green-dark); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>

    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>

    <div class="admin-shell">
        <div class="dashboard-header">
            <div>
                <h1>System Admin Dashboard & Analytics</h1>
            </div>
            <div class="user-badge">
                <span>Logged in as:</span>
                <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></strong>
                <span>(<?php echo strtoupper($_SESSION['role'] ?? 'ADMIN'); ?>)</span>
            </div>
        </div>

        <?php if (isset($_GET['account_msg'])): ?>
            <div class="card" role="status"><?php echo htmlspecialchars($_GET['account_msg']); ?></div>
        <?php endif; ?>

        <div class="search-bar-wrap">
            <input type="text" id="globalSearch" onkeyup="filterAllTables()" placeholder="Search bookings, orders, items, or feedback...">
            <a href="admin.php?action=export_csv" class="btn-export">⬇ Export Sales Log (CSV)</a>
        </div>

        <!-- METRICS -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Gross Revenue</h3>
                <div class="number">KSh <?php echo number_format($total_revenue, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Registered Farmers</h3>
                <div class="number"><?php echo $total_farmers; ?></div>
            </div>
            <div class="stat-card">
                <h3>Product Orders</h3>
                <div class="number"><?php echo $total_orders; ?></div>
            </div>
            <div class="stat-card">
                <h3>Service Bookings</h3>
                <div class="number"><?php echo $total_bookings; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="section-head">
                <h2>New Accounts Awaiting Approval</h2>
            </div>
            <?php if ($pending_accounts && $pending_accounts->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Requested Role</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($account = $pending_accounts->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($account['email']); ?></td>
                                    <td><?php echo htmlspecialchars($account['phone']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($account['role'])); ?></td>
                                    <td>
                                        <form method="POST" class="inline-status-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $account['user_id']; ?>">
                                            <button type="submit" name="account_action" value="approve">Approve</button>
                                            <button type="submit" name="account_action" value="reject" class="btn-danger">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No new accounts are waiting for approval.</p>
            <?php endif; ?>
        </div>

        <!-- INVENTORY MANAGEMENT CARD -->
        <div class="card">
            <div class="section-head">
                <h2>Catalog & Inventory Management</h2>
                <button class="btn-add" onclick="toggleForm('addInvForm')">+ Add New Item</button>
            </div>

            <div id="addInvForm" style="display:none; background: var(--surface); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--line);">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                    <div class="form-grid">
                        <div>
                            <label><strong>Item Name</strong></label>
                            <input type="text" name="item_name" required placeholder="e.g. NPK Fertilizer 50kg">
                        </div>
                        <div>
                            <label><strong>Category</strong></label>
                            <select name="category" required>
                                <option value="Fertilizer">Fertilizer</option>
                                <option value="Seeds">Seeds</option>
                                <option value="Pesticides">Pesticides</option>
                                <option value="Equipment">Equipment</option>
                            </select>
                        </div>
                        <div>
                            <label><strong>Unit Price (KSh)</strong></label>
                            <input type="number" step="0.01" name="unit_price" required placeholder="3200.00">
                        </div>
                        <div>
                            <label><strong>Initial Stock</strong></label>
                            <input type="number" name="quantity_in_stock" required placeholder="100">
                        </div>
                    </div>
                    <button type="submit" name="add_inventory_item" class="btn-add">Save Product</button>
                </form>
            </div>

            <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Unit Price</th>
                                <th>Stock Level</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $inventory_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $item['item_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>KSh <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td><?php echo $item['quantity_in_stock']; ?> units</td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_inventory_item" class="btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No inventory items found.</p>
            <?php endif; ?>
        </div>

        <!-- SERVICE BOOKINGS TABLE -->
        <div class="card">
            <div class="section-head">
                <h2>Service Bookings</h2>
            </div>
            <?php if ($bookings_result && $bookings_result->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Name & Role</th>
                                <th>Contact Info</th>
                                <th>Service Requested</th>
                                <th>Booking Date</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $bookings_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['booking_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['full_name'] ?? 'Guest'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['service_type']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['booking_date']); ?></td>
                                    <td>KSh <?php echo number_format((float) $row['total_amount'], 2); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($row['payment_status']); ?>"><?php echo strtoupper($row['payment_status']); ?></span></td>
                                    <td><span class="badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo $row['status'] === 'approved' ? 'CONFIRMED' : strtoupper($row['status']); ?></span></td>
                                    <td>
                                        <form method="POST" class="inline-status-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="booking_id" value="<?php echo $row['booking_id']; ?>">
                                            <select name="status">
                                                <option value="pending" <?php if ($row['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                                <option value="approved" <?php if ($row['status'] == 'approved') echo 'selected'; ?>>Confirmed</option>
                                                <option value="completed" <?php if ($row['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                            </select>
                                            <button type="submit" name="update_booking_status">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No service bookings found.</p>
            <?php endif; ?>
        </div>

        <!-- PRODUCT ORDERS TABLE -->
        <div class="card">
            <div class="section-head">
                <h2>Product Orders</h2>
            </div>
            <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Item Requested</th>
                                <th>Qty</th>
                                <th>Total Price</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ord = $orders_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $ord['order_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($ord['full_name'] ?? 'Customer'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ord['item_name'] ?? 'Input Product'); ?></td>
                                    <td><?php echo $ord['quantity']; ?></td>
                                    <td>KSh <?php echo number_format($ord['total_price'] ?? 0, 2); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($ord['payment_status']); ?>"><?php echo strtoupper($ord['payment_status']); ?></span></td>
                                    <td><span class="badge <?php echo htmlspecialchars($ord['status']); ?>"><?php echo strtoupper($ord['status']); ?></span></td>
                                    <td>
                                        <form method="POST" class="inline-status-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $ord['order_id']; ?>">
                                            <select name="status">
                                                <option value="pending" <?php if ($ord['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                                <option value="shipped" <?php if ($ord['status'] == 'shipped') echo 'selected'; ?>>Shipped</option>
                                                <option value="completed" <?php if ($ord['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                            </select>
                                            <button type="submit" name="update_order_status">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No product orders found.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-head">
                <h2>Payments Requiring Review</h2>
            </div>
            <?php if ($payment_issues_result && $payment_issues_result->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Customer</th><th>Type</th><th>Amount</th><th>Phone</th><th>M-Pesa Receipt</th><th>Details</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($paymentIssue = $payment_issues_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($paymentIssue['full_name'] ?? 'Customer'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($paymentIssue['payment_type'])); ?></td>
                                    <td>KSh <?php echo number_format((float) $paymentIssue['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($paymentIssue['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($paymentIssue['mpesa_receipt'] ?? 'Not supplied'); ?></td>
                                    <td><?php echo htmlspecialchars($paymentIssue['result_desc'] ?? 'Review payment and fulfill or refund.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No payments require review.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-head">
                <h2>Contact Messages</h2>
            </div>
            <?php if ($contact_messages && $contact_messages->num_rows > 0): ?>
                <div class="table-wrap"><table><thead><tr><th>Received</th><th>From</th><th>Topic</th><th>Message</th><th>Status / reply</th></tr></thead><tbody>
                    <?php while ($msg = $contact_messages->fetch_assoc()): ?><tr>
                        <td><?php echo htmlspecialchars($msg['created_at']); ?></td><td><?php echo htmlspecialchars($msg['full_name'] ?? 'Guest'); ?></td><td><?php echo htmlspecialchars($msg['subject']); ?></td><td style="white-space:pre-wrap;min-width:240px;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></td>
                        <td><span class="badge <?php echo $msg['review_status'] === 'resolved' ? 'paid' : ($msg['review_status'] === 'reviewed' ? 'processing' : 'unpaid'); ?>"><?php echo htmlspecialchars(ucfirst($msg['review_status'] === 'new' ? 'New / unread' : $msg['review_status'])); ?></span><?php if (!empty($msg['staff_reply'])): ?><p><?php echo nl2br(htmlspecialchars($msg['staff_reply'])); ?><br><small><?php echo htmlspecialchars($msg['replied_at'] ?? ''); ?></small></p><?php endif; ?>
                            <form method="POST" action="message_handler.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['message_csrf']); ?>"><input type="hidden" name="message_id" value="<?php echo (int) $msg['feedback_id']; ?>"><input type="hidden" name="category" value="contact"><textarea name="staff_reply" rows="2" maxlength="5000" placeholder="Reply (optional)"></textarea><select name="review_status"><option value="new" <?php echo $msg['review_status'] === 'new' ? 'selected' : ''; ?>>New / unread</option><option value="reviewed" <?php echo $msg['review_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option><option value="resolved" <?php echo $msg['review_status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option></select><button class="btn-add">Save reply / status</button></form></td>
                    </tr><?php endwhile; ?>
                </tbody></table></div>
            <?php else: ?>
                <p>No contact inquiries have been received.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-head"><h2>Farmer Feedback</h2></div>
            <?php if ($farmer_feedback && $farmer_feedback->num_rows > 0): ?>
                <div class="table-wrap"><table><thead><tr><th>Received</th><th>Farmer</th><th>Service / category</th><th>Rating</th><th>Feedback</th><th>Status / reply</th></tr></thead><tbody>
                    <?php while ($msg = $farmer_feedback->fetch_assoc()): ?><tr>
                        <td><?php echo htmlspecialchars($msg['created_at']); ?></td><td><?php echo htmlspecialchars($msg['full_name'] ?? 'Guest'); ?></td><td><?php echo htmlspecialchars($msg['subject']); ?></td><td><?php echo $msg['rating'] ? ((int) $msg['rating'] . ' / 5') : 'Not rated'; ?></td><td style="white-space:pre-wrap;min-width:240px;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></td>
                        <td><span class="badge <?php echo $msg['review_status'] === 'resolved' ? 'paid' : ($msg['review_status'] === 'reviewed' ? 'processing' : 'unpaid'); ?>"><?php echo htmlspecialchars(ucfirst($msg['review_status'] === 'new' ? 'New / unread' : $msg['review_status'])); ?></span><?php if (!empty($msg['staff_reply'])): ?><p><?php echo nl2br(htmlspecialchars($msg['staff_reply'])); ?><br><small><?php echo htmlspecialchars($msg['replied_at'] ?? ''); ?></small></p><?php endif; ?>
                            <form method="POST" action="message_handler.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['message_csrf']); ?>"><input type="hidden" name="message_id" value="<?php echo (int) $msg['feedback_id']; ?>"><input type="hidden" name="category" value="feedback"><textarea name="staff_reply" rows="2" maxlength="5000" placeholder="Reply (optional)"></textarea><select name="review_status"><option value="new" <?php echo $msg['review_status'] === 'new' ? 'selected' : ''; ?>>New / unread</option><option value="reviewed" <?php echo $msg['review_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option><option value="resolved" <?php echo $msg['review_status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option></select><button class="btn-add">Save reply / status</button></form></td>
                    </tr><?php endwhile; ?>
                </tbody></table></div>
            <?php else: ?><p>No farmer feedback has been received.</p><?php endif; ?>
        </div>

        <a class="back-link" href="index.php">Back to Main Webpage</a>
    </div>

    <script>
    function toggleForm(id) {
        let form = document.getElementById(id);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    function filterAllTables() {
        let input = document.getElementById("globalSearch").value.toLowerCase();
        let rows = document.querySelectorAll("tbody tr");

        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        });
    }
    </script>

</body>
</html>