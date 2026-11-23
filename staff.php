<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';

$staffRoles = ['admin', 'finance_manager', 'inventory_manager', 'dispatch_manager', 'driver', 'service_manager', 'trainer', 'supplier'];
$currentUser = mfcRequireRoles($conn, $staffRoles);
$role = $currentUser['role'];
$userId = (int) $currentUser['user_id'];
$canManagePurchaseOrders = in_array($role, ['admin', 'inventory_manager'], true);

if (empty($_SESSION['staff_csrf'])) {
    $_SESSION['staff_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['message_csrf'])) {
    $_SESSION['message_csrf'] = bin2hex(random_bytes(32));
}

if (isset($_GET['certificate_id'])) {
    $certificateId = filter_input(INPUT_GET, 'certificate_id', FILTER_VALIDATE_INT) ?: 0;
    if ($certificateId < 1 || !in_array($role, ['admin', 'service_manager', 'trainer'], true)) {
        http_response_code(404);
        exit('Certificate not found.');
    }
    if ($role === 'trainer') {
        $stmt = $conn->prepare("SELECT c.certificate_code, c.title, c.issue_date, b.completed_at, b.trainer_id, b.is_certifiable, farmer.full_name AS farmer_name FROM certifications c INNER JOIN bookings b ON b.booking_id = c.booking_id INNER JOIN users farmer ON farmer.user_id = c.farmer_id WHERE c.cert_id = ? AND b.trainer_id = ? AND b.is_certifiable = 1 AND b.status = 'completed'");
        $stmt->bind_param('ii', $certificateId, $userId);
    } else {
        $stmt = $conn->prepare("SELECT c.certificate_code, c.title, c.issue_date, b.completed_at, b.trainer_id, b.is_certifiable, farmer.full_name AS farmer_name FROM certifications c INNER JOIN bookings b ON b.booking_id = c.booking_id INNER JOIN users farmer ON farmer.user_id = c.farmer_id WHERE c.cert_id = ? AND b.is_certifiable = 1 AND b.status = 'completed'");
        $stmt->bind_param('i', $certificateId);
    }
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$certificate) {
        http_response_code(404);
        exit('Certificate not found.');
    }
    $completionDate = $certificate['completed_at'] ?: $certificate['issue_date'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Certificate of Completion | Meru Farmers Centre</title>
        <link rel="stylesheet" href="style.css">
        <style>
            .certificate { max-width: 1000px; min-height: 620px; margin: 40px auto; padding: 64px; border: 12px double #1d6f3c; background: #fff; text-align: center; display:flex; flex-direction:column; align-items:center; justify-content:center; }
            .certificate img { width: 92px; height: 92px; object-fit:contain; margin-bottom:18px; }
            .certificate h1 { margin: 0 0 10px; color:#124c2d; font-size:2.4rem; }
            .certificate h2 { margin: 28px 0 10px; color:#1d6f3c; font-size:2rem; }
            .certificate-name { margin:12px 0; color:#183a26; font-size:2rem; font-weight:700; }
            .certificate-title { max-width:720px; margin:8px 0 24px; color:#27372c; font-size:1.35rem; }
            .certificate-code { margin-top:30px; color:#5d6d61; font-family:monospace; font-size:1rem; }
            .certificate-actions { display:flex; justify-content:center; gap:12px; margin:24px auto; }
            @media print { body { padding:0; background:#fff; } .global-topbar,.sidebar-backdrop,.global-sidebar,.certificate-actions { display:none !important; } .certificate { margin:0; max-width:none; min-height:95vh; break-inside:avoid; } }
            @media (max-width:640px) { .certificate { min-height:520px; margin:16px; padding:28px 18px; border-width:7px; } .certificate h1 { font-size:1.7rem; } .certificate h2,.certificate-name { font-size:1.5rem; } }
        </style>
    </head>
    <body>
        <main class="certificate">
            <img src="Logo.png" alt="Meru Farmers Centre logo">
            <h1>Meru Farmers Centre</h1>
            <p>Certificate of Completion</p>
            <p>This certificate is proudly presented to</p>
            <p class="certificate-name"><?php echo htmlspecialchars($certificate['farmer_name']); ?></p>
            <p>for successfully completing the training session</p>
            <h2><?php echo htmlspecialchars($certificate['title']); ?></h2>
            <p>Completed on <?php echo htmlspecialchars(date('F j, Y', strtotime($completionDate))); ?></p>
            <p class="certificate-code">Certificate code: <?php echo htmlspecialchars($certificate['certificate_code']); ?></p>
        </main>
        <div class="certificate-actions">
            <button type="button" class="btn" onclick="window.print()">Print certificate</button>
            <a class="btn btn-secondary" href="staff.php#services">Back to sessions</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

function staffRedirect(string $message): never
{
    header('Location: staff.php?msg=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['staff_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'adjust_stock' && in_array($role, ['admin', 'inventory_manager'], true)) {
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: 0;
        $adjustment = filter_input(INPUT_POST, 'adjustment', FILTER_VALIDATE_INT);
        if ($itemId < 1 || $adjustment === false || $adjustment === null || $adjustment === 0 || abs($adjustment) > 100000) {
            staffRedirect('Enter a valid stock adjustment.');
        }
        $stmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? WHERE item_id = ? AND quantity_in_stock + ? >= 0');
        $stmt->bind_param('iii', $adjustment, $itemId, $adjustment);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed === 1 ? 'Stock updated.' : 'Stock was not changed; check the item and available quantity.');
    }

    if ($action === 'set_order_payment' && in_array($role, ['admin', 'finance_manager'], true)) {
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
        $paymentStatus = $_POST['payment_status'] ?? '';
        if ($orderId < 1 || !in_array($paymentStatus, ['unpaid', 'paid', 'refunded'], true)) {
            staffRedirect('Choose a valid order and payment status.');
        }
        $stmt = $conn->prepare('UPDATE orders SET payment_status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $paymentStatus, $orderId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed > 0 ? 'Product order payment updated.' : 'Payment status was unchanged or the order was not found.');
    }

    if ($action === 'set_booking_payment' && in_array($role, ['admin', 'finance_manager'], true)) {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
        $paymentStatus = $_POST['payment_status'] ?? '';
        if ($bookingId < 1 || !in_array($paymentStatus, ['unpaid', 'paid', 'refunded'], true)) {
            staffRedirect('Choose a valid service booking and payment status.');
        }
        $stmt = $conn->prepare('UPDATE bookings SET payment_status = ? WHERE booking_id = ?');
        $stmt->bind_param('si', $paymentStatus, $bookingId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed > 0 ? 'Service booking payment updated.' : 'Payment status was unchanged or the booking was not found.');
    }

    if ($action === 'reconcile_payment' && in_array($role, ['admin', 'finance_manager'], true)) {
        require_once 'payment_fulfillment.php';
        $paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT) ?: 0;
        if ($paymentId < 1) {
            staffRedirect('Choose a valid payment to reconcile.');
        }
        staffRedirect(reconcilePaidIntent($conn, $paymentId)
            ? 'Payment fulfilled and reconciled.'
            : 'Payment could not be fulfilled; leave it for follow-up.');
    }

    if ($action === 'set_supplier_po_payment' && in_array($role, ['admin', 'finance_manager'], true)) {
        $purchaseOrderId = filter_input(INPUT_POST, 'purchase_order_id', FILTER_VALIDATE_INT) ?: 0;
        $paymentStatus = $_POST['payment_status'] ?? '';
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentReference = trim($_POST['payment_reference'] ?? '');
        if ($purchaseOrderId < 1 || !in_array($paymentStatus, ['unpaid', 'paid'], true) || !in_array($paymentMethod, ['cash', 'mpesa'], true) || ($paymentStatus === 'paid' && ($paymentReference === '' || strlen($paymentReference) > 100))) {
            staffRedirect('Choose a valid supplier purchase order payment status.');
        }
        $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;
        $paymentReferenceValue = $paymentStatus === 'paid' ? $paymentReference : null;
        $stmt = $conn->prepare("UPDATE supplier_purchase_orders SET payment_status = ?, payment_method = ?, payment_reference = ?, paid_at = ? WHERE purchase_order_id = ? AND status IN ('dispatched', 'received')");
        $stmt->bind_param('ssssi', $paymentStatus, $paymentMethod, $paymentReferenceValue, $paidAt, $purchaseOrderId);
        $stmt->execute();
        $stmt->close();
        staffRedirect('Supplier purchase order payment saved.');
    }

    if ($action === 'create_purchase_order' && $canManagePurchaseOrders) {
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT) ?: 0;
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: 0;
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 0;
        $unitCost = filter_input(INPUT_POST, 'unit_cost', FILTER_VALIDATE_FLOAT);
        if ($supplierId < 1 || $itemId < 1 || $quantity < 1 || $quantity > 100000 || $unitCost === false || $unitCost === null || $unitCost <= 0) {
            staffRedirect('Enter a supplier, product, valid quantity, and unit cost.');
        }
        $stmt = $conn->prepare("INSERT INTO supplier_purchase_orders (supplier_id, item_id, ordered_by, quantity, unit_cost) SELECT s.user_id, i.item_id, ?, ?, ? FROM users s CROSS JOIN inventory i WHERE s.user_id = ? AND s.role = 'supplier' AND s.approval_status = 'approved' AND i.item_id = ?");
        $stmt->bind_param('iidii', $userId, $quantity, $unitCost, $supplierId, $itemId);
        $stmt->execute();
        $created = $stmt->affected_rows === 1;
        $stmt->close();
        staffRedirect($created ? 'Purchase order sent to supplier.' : 'Choose an approved supplier account and an existing inventory item.');
    }

    if ($action === 'respond_purchase_order' && $role === 'supplier') {
        $purchaseOrderId = filter_input(INPUT_POST, 'purchase_order_id', FILTER_VALIDATE_INT) ?: 0;
        $newStatus = $_POST['purchase_order_status'] ?? '';
        $supplierNote = trim($_POST['supplier_note'] ?? '');
        if ($purchaseOrderId < 1 || !in_array($newStatus, ['accepted', 'rejected', 'pending'], true) || strlen($supplierNote) > 2000) {
            staffRedirect('Invalid supplier order response.');
        }
        $stmt = $conn->prepare("UPDATE supplier_purchase_orders SET status = ?, supplier_note = ? WHERE purchase_order_id = ? AND supplier_id = ? AND status = 'pending'");
        $stmt->bind_param('ssii', $newStatus, $supplierNote, $purchaseOrderId, $userId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed === 1 ? ($newStatus === 'pending' ? 'Delay note saved.' : 'Purchase order response recorded.') : 'That order is no longer awaiting your response.');
    }

    if ($action === 'fulfill_purchase_order' && $role === 'supplier') {
        $purchaseOrderId = filter_input(INPUT_POST, 'purchase_order_id', FILTER_VALIDATE_INT) ?: 0;
        $expectedDate = trim($_POST['expected_delivery_date'] ?? '');
        $supplierNote = trim($_POST['supplier_note'] ?? '');
        $parsedExpectedDate = $expectedDate === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $expectedDate);
        if ($purchaseOrderId < 1 || ($expectedDate !== '' && (!$parsedExpectedDate || $parsedExpectedDate->format('Y-m-d') !== $expectedDate)) || strlen($supplierNote) > 2000) {
            staffRedirect('Enter a valid expected delivery date and note.');
        }
        $expectedDateValue = $expectedDate === '' ? null : $expectedDate;
        $stmt = $conn->prepare("UPDATE supplier_purchase_orders SET status = 'dispatched', expected_delivery_date = ?, supplier_note = ? WHERE purchase_order_id = ? AND supplier_id = ? AND status = 'accepted'");
        $stmt->bind_param('ssii', $expectedDateValue, $supplierNote, $purchaseOrderId, $userId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed === 1 ? 'Delivery marked as dispatched.' : 'Only an accepted purchase order can be marked dispatched.');
    }

    if ($action === 'receive_purchase_order' && in_array($role, ['admin', 'inventory_manager'], true)) {
        $purchaseOrderId = filter_input(INPUT_POST, 'purchase_order_id', FILTER_VALIDATE_INT) ?: 0;
        if ($purchaseOrderId < 1) {
            staffRedirect('Choose a valid purchase order.');
        }
        $conn->begin_transaction();
        $stmt = $conn->prepare("SELECT item_id, quantity FROM supplier_purchase_orders WHERE purchase_order_id = ? AND status = 'dispatched' FOR UPDATE");
        $stmt->bind_param('i', $purchaseOrderId);
        $stmt->execute();
        $purchaseOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$purchaseOrder) {
            $conn->rollback();
            staffRedirect('Only dispatched purchase orders can be received.');
        }
        $stmt = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock + ? WHERE item_id = ?');
        $stmt->bind_param('ii', $purchaseOrder['quantity'], $purchaseOrder['item_id']);
        $stmt->execute();
        $stockUpdated = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$stockUpdated) {
            $conn->rollback();
            staffRedirect('Could not add the received quantity to inventory.');
        }
        $stmt = $conn->prepare("UPDATE supplier_purchase_orders SET status = 'received', received_at = NOW() WHERE purchase_order_id = ? AND status = 'dispatched'");
        $stmt->bind_param('i', $purchaseOrderId);
        $stmt->execute();
        $statusUpdated = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$statusUpdated) {
            $conn->rollback();
            staffRedirect('Purchase order status changed; stock was not updated.');
        }
        $conn->commit();
        staffRedirect('Purchase order received and inventory updated.');
    }

    if ($action === 'set_staff_role' && $role === 'admin') {
        $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
        $newRole = $_POST['staff_role'] ?? '';
        $assignableRoles = ['admin', 'farmer', 'finance_manager', 'inventory_manager', 'dispatch_manager', 'driver', 'service_manager', 'trainer', 'supplier'];
        if ($targetUserId < 1 || $targetUserId === $userId || !in_array($newRole, $assignableRoles, true)) {
            staffRedirect('Choose another account and a valid staff role.');
        }
        $stmt = $conn->prepare('UPDATE users SET role = ? WHERE user_id = ?');
        $stmt->bind_param('si', $newRole, $targetUserId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed > 0 ? 'Account role updated.' : 'Role was unchanged or the account was not found.');
    }

    if ($action === 'assign_dispatch' && in_array($role, ['admin', 'dispatch_manager'], true)) {
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
        $driverId = filter_input(INPUT_POST, 'driver_id', FILTER_VALIDATE_INT) ?: 0;
        if ($orderId < 1 || $driverId < 1) {
            staffRedirect('Choose a paid order and a driver.');
        }
        $stmt = $conn->prepare("INSERT INTO dispatches (order_id, driver_id, dispatch_manager_id, delivery_status) SELECT o.order_id, ?, ?, 'assigned' FROM orders o INNER JOIN users d ON d.user_id = ? AND d.role = 'driver' AND d.approval_status = 'approved' WHERE o.order_id = ? AND o.payment_status = 'paid' AND NOT EXISTS (SELECT 1 FROM dispatches x WHERE x.order_id = o.order_id)");
        $stmt->bind_param('iiii', $driverId, $userId, $driverId, $orderId);
        $stmt->execute();
        $created = $stmt->affected_rows === 1;
        $stmt->close();
        staffRedirect($created ? 'Paid order assigned to driver.' : 'Could not assign that order. It must be paid, unassigned, and the selected account must be a driver.');
    }

    if ($action === 'update_dispatch' && in_array($role, ['admin', 'dispatch_manager', 'driver'], true)) {
        $dispatchId = filter_input(INPUT_POST, 'dispatch_id', FILTER_VALIDATE_INT) ?: 0;
        $newStatus = $_POST['delivery_status'] ?? '';
        $allowed = $role === 'driver' ? ['in_transit', 'delivered'] : ['assigned', 'in_transit', 'delivered'];
        if ($dispatchId < 1 || !in_array($newStatus, $allowed, true)) {
            staffRedirect('Invalid dispatch update.');
        }
        $conn->begin_transaction();
        if ($role === 'driver') {
            $stmt = $conn->prepare('UPDATE dispatches SET delivery_status = ?, delivered_at = IF(? = \'delivered\', NOW(), delivered_at) WHERE dispatch_id = ? AND driver_id = ?');
            $stmt->bind_param('ssii', $newStatus, $newStatus, $dispatchId, $userId);
        } else {
            $stmt = $conn->prepare('UPDATE dispatches SET delivery_status = ?, delivered_at = IF(? = \'delivered\', NOW(), delivered_at) WHERE dispatch_id = ?');
            $stmt->bind_param('ssi', $newStatus, $newStatus, $dispatchId);
        }
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed > 0) {
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
        staffRedirect($changed > 0 ? 'Delivery status updated.' : 'No matching dispatch was updated.');
    }

    if ($action === 'schedule_booking' && in_array($role, ['admin', 'service_manager'], true)) {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
        $trainerId = filter_input(INPUT_POST, 'trainer_id', FILTER_VALIDATE_INT);
        $bookingDate = trim($_POST['booking_date'] ?? '');
        $newStatus = $_POST['booking_status'] ?? '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
        if ($bookingId < 1 || !$date || $date->format('Y-m-d') !== $bookingDate || !in_array($newStatus, ['pending', 'approved', 'cancelled'], true)) {
            staffRedirect('Enter a valid schedule, trainer, and booking status.');
        }
        if ($trainerId) {
            $stmt = $conn->prepare("UPDATE bookings b INNER JOIN users t ON t.user_id = ? AND t.role = 'trainer' AND t.approval_status = 'approved' SET b.trainer_id = ?, b.booking_date = ?, b.status = ? WHERE b.booking_id = ? AND b.status <> 'completed'");
            $stmt->bind_param('iissi', $trainerId, $trainerId, $bookingDate, $newStatus, $bookingId);
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET booking_date = ?, status = ? WHERE booking_id = ? AND status <> 'completed'");
            $stmt->bind_param('ssi', $bookingDate, $newStatus, $bookingId);
        }
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed > 0 ? 'Booking scheduled.' : 'Booking schedule was not changed. Check the booking and trainer.');
    }

    if ($action === 'complete_training' && in_array($role, ['admin', 'service_manager', 'trainer'], true)) {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
        $trainerNotes = trim($_POST['trainer_notes'] ?? '');
        $serviceNotes = trim($_POST['service_notes'] ?? '');
        if ($bookingId < 1 || strlen($trainerNotes) > 5000 || strlen($serviceNotes) > 5000 || ($role !== 'trainer' && $serviceNotes === '')) {
            staffRedirect('Training notes must be 5,000 characters or fewer.');
        }
        if ($role === 'trainer') {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'completed', trainer_notes = ?, completed_at = NOW() WHERE booking_id = ? AND trainer_id = ? AND status = 'approved' AND is_certifiable = 1");
            $stmt->bind_param('sii', $trainerNotes, $bookingId, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'completed', service_notes = ?, completed_at = NOW() WHERE booking_id = ? AND status = 'approved' AND is_certifiable = 0");
            $stmt->bind_param('si', $serviceNotes, $bookingId);
        }
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        staffRedirect($changed > 0 ? 'Session marked completed.' : 'That approved session cannot be completed by this role.');
    }

    if ($action === 'issue_certificate' && in_array($role, ['admin', 'trainer'], true)) {
        $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT) ?: 0;
        $stmt = $conn->prepare("SELECT b.farmer_id, b.service_type FROM bookings b WHERE b.booking_id = ? AND b.status = 'completed' AND b.is_certifiable = 1 AND (? = 'admin' OR b.trainer_id = ?)");
        $stmt->bind_param('isi', $bookingId, $role, $userId);
        $stmt->execute();
        $completedBooking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$completedBooking) {
            staffRedirect('A certificate can only be issued for your completed assigned training.');
        }
        $stmt = $conn->prepare('SELECT cert_id FROM certifications WHERE booking_id = ? LIMIT 1');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            header('Location: staff.php?certificate_id=' . (int) $existing['cert_id']);
            exit();
        }
        $title = (string) $completedBooking['service_type'];
        $issueDate = date('Y-m-d');
        $certificateCode = 'MFC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $stmt = $conn->prepare('INSERT INTO certifications (booking_id, farmer_id, title, issue_date, certificate_code) VALUES (?, ?, ?, ?, ?)');
        $farmerId = (int) $completedBooking['farmer_id'];
        $stmt->bind_param('iisss', $bookingId, $farmerId, $title, $issueDate, $certificateCode);
        $stmt->execute();
        $issued = $stmt->affected_rows === 1;
        $certificateId = (int) $stmt->insert_id;
        $stmt->close();
        if ($issued) {
            header('Location: staff.php?certificate_id=' . $certificateId);
            exit();
        }
        staffRedirect('Certificate could not be issued.');
    }

    http_response_code(403);
    exit('You do not have permission to perform that action.');
}

$canFinance = in_array($role, ['admin', 'finance_manager'], true);
$canInventory = in_array($role, ['admin', 'inventory_manager'], true);
$canDispatch = in_array($role, ['admin', 'dispatch_manager', 'driver'], true);
$canManageService = in_array($role, ['admin', 'service_manager'], true);
$canTrain = in_array($role, ['admin', 'service_manager', 'trainer'], true);
$staffAccounts = $role === 'admin' ? $conn->query('SELECT user_id, full_name, email, role FROM users ORDER BY full_name') : null;

$revenue = 0.0;
$salesRevenue = 0.0;
$serviceRevenue = 0.0;
$transactions = null;
$financeOrders = null;
$financeBookings = null;
$financePurchaseOrders = null;
if ($canFinance) {
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders WHERE payment_status = 'paid'");
    $salesRevenue = (float) ($result->fetch_assoc()['revenue'] ?? 0);
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM bookings WHERE payment_status = 'paid'");
    $serviceRevenue = (float) ($result->fetch_assoc()['revenue'] ?? 0);
    $revenue = $salesRevenue + $serviceRevenue;
    $transactions = $conn->query('SELECT payment_id, user_id, payment_type, amount, phone, status, mpesa_receipt, result_desc, created_at FROM payment_transactions ORDER BY payment_id DESC LIMIT 100');
    $financeOrders = $conn->query('SELECT o.order_id, o.total_amount, o.payment_status, o.payment_method, o.status, o.quantity, u.full_name AS customer_name, i.item_name FROM orders o LEFT JOIN users u ON u.user_id = o.farmer_id LEFT JOIN inventory i ON i.item_id = o.item_id ORDER BY o.order_id DESC LIMIT 100');
    $financeBookings = $conn->query('SELECT b.booking_id, b.service_type, b.booking_date, b.total_amount, b.payment_status, b.payment_method, b.status, u.full_name AS customer_name FROM bookings b LEFT JOIN users u ON u.user_id = b.farmer_id ORDER BY b.booking_id DESC LIMIT 100');
    $financePurchaseOrders = $conn->query('SELECT po.purchase_order_id, po.quantity, po.unit_cost, po.status, po.payment_status, po.payment_method, po.payment_reference, po.paid_at, po.created_at, supplier.full_name AS supplier_name, item.item_name FROM supplier_purchase_orders po INNER JOIN users supplier ON supplier.user_id = po.supplier_id INNER JOIN inventory item ON item.item_id = po.item_id ORDER BY po.purchase_order_id DESC LIMIT 100');
}

$lowStock = null;
if ($canInventory) {
    $lowStock = $conn->query('SELECT item_id, item_name, category, quantity_in_stock, unit_price FROM inventory WHERE quantity_in_stock <= 10 ORDER BY quantity_in_stock, item_name');
}

$purchaseOrderSuppliers = null;
$purchaseOrderItems = null;
$purchaseOrders = null;
$supplierSummary = null;
$poSummary = null;
$poFilter = $_GET['po_status'] ?? 'all';
$allowedPoFilters = ['all', 'pending', 'accepted', 'dispatched', 'received', 'unpaid'];
if (!in_array($poFilter, $allowedPoFilters, true)) {
    $poFilter = 'all';
}
if ($canManagePurchaseOrders) {
    $purchaseOrderSuppliers = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'supplier' AND approval_status = 'approved' ORDER BY full_name");
    $purchaseOrderItems = $conn->query('SELECT item_id, item_name, quantity_in_stock FROM inventory ORDER BY item_name');
    $purchaseOrders = $conn->query('SELECT po.purchase_order_id, po.quantity, po.unit_cost, po.status, po.payment_status, po.payment_method, po.payment_reference, po.paid_at, po.supplier_note, po.expected_delivery_date, po.created_at, po.received_at, supplier.full_name AS supplier_name, item.item_name, creator.full_name AS ordered_by_name FROM supplier_purchase_orders po INNER JOIN users supplier ON supplier.user_id = po.supplier_id INNER JOIN inventory item ON item.item_id = po.item_id INNER JOIN users creator ON creator.user_id = po.ordered_by ORDER BY po.purchase_order_id DESC LIMIT 100');
} elseif ($role === 'supplier') {
    $supplierSummaryStmt = $conn->prepare("SELECT COUNT(*) AS total_pos, SUM(status IN ('pending', 'accepted', 'dispatched')) AS active_pos, COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN quantity * unit_cost ELSE 0 END), 0) AS unpaid_balance, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN quantity * unit_cost ELSE 0 END), 0) AS total_paid FROM supplier_purchase_orders WHERE supplier_id = ?");
    $supplierSummaryStmt->bind_param('i', $userId);
    $supplierSummaryStmt->execute();
    $supplierSummary = $supplierSummaryStmt->get_result()->fetch_assoc();
    $supplierSummaryStmt->close();

    $poSql = 'SELECT po.purchase_order_id, po.quantity, po.unit_cost, po.status, po.payment_status, po.payment_method, po.payment_reference, po.paid_at, po.supplier_note, po.expected_delivery_date, po.created_at, po.received_at, supplier.full_name AS supplier_name, item.item_name, creator.full_name AS ordered_by_name FROM supplier_purchase_orders po INNER JOIN users supplier ON supplier.user_id = po.supplier_id INNER JOIN inventory item ON item.item_id = po.item_id INNER JOIN users creator ON creator.user_id = po.ordered_by WHERE po.supplier_id = ?';
    if ($poFilter === 'unpaid') {
        $poSql .= " AND po.payment_status = 'unpaid'";
    } elseif ($poFilter !== 'all') {
        $poSql .= ' AND po.status = ?';
    }
    $poSql .= ' ORDER BY po.purchase_order_id DESC LIMIT 100';
    $stmt = $conn->prepare($poSql);
    if ($poFilter !== 'all' && $poFilter !== 'unpaid') {
        $stmt->bind_param('is', $userId, $poFilter);
    } else {
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $purchaseOrders = $stmt->get_result();
    $stmt->close();
}

if (isset($_GET['po_summary'])) {
    $summaryId = filter_var($_GET['po_summary'], FILTER_VALIDATE_INT) ?: 0;
    $canViewPo = in_array($role, ['admin', 'inventory_manager', 'finance_manager', 'supplier'], true);
    if ($summaryId < 1 || !$canViewPo) {
        http_response_code(404);
        exit('Purchase order not found.');
    }
    if ($role === 'supplier') {
        $stmt = $conn->prepare('SELECT po.purchase_order_id, po.quantity, po.unit_cost, po.status, po.payment_status, po.payment_method, po.payment_reference, po.paid_at, po.supplier_note, po.expected_delivery_date, po.created_at, po.received_at, supplier.full_name AS supplier_name, item.item_name, item.category, creator.full_name AS ordered_by_name FROM supplier_purchase_orders po INNER JOIN users supplier ON supplier.user_id = po.supplier_id INNER JOIN inventory item ON item.item_id = po.item_id INNER JOIN users creator ON creator.user_id = po.ordered_by WHERE po.purchase_order_id = ? AND po.supplier_id = ?');
        $stmt->bind_param('ii', $summaryId, $userId);
    } else {
        $stmt = $conn->prepare('SELECT po.purchase_order_id, po.quantity, po.unit_cost, po.status, po.payment_status, po.payment_method, po.payment_reference, po.paid_at, po.supplier_note, po.expected_delivery_date, po.created_at, po.received_at, supplier.full_name AS supplier_name, item.item_name, item.category, creator.full_name AS ordered_by_name FROM supplier_purchase_orders po INNER JOIN users supplier ON supplier.user_id = po.supplier_id INNER JOIN inventory item ON item.item_id = po.item_id INNER JOIN users creator ON creator.user_id = po.ordered_by WHERE po.purchase_order_id = ?');
        $stmt->bind_param('i', $summaryId);
    }
    $stmt->execute();
    $poSummary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$poSummary) {
        http_response_code(404);
        exit('Purchase order not found.');
    }
}

$drivers = null;
$availableOrders = null;
$dispatches = null;
if ($canDispatch) {
    if (in_array($role, ['admin', 'dispatch_manager'], true)) {
        $drivers = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'driver' AND approval_status = 'approved' ORDER BY full_name");
        $availableOrders = $conn->query("SELECT o.order_id, u.full_name, o.total_amount, o.delivery_address FROM orders o LEFT JOIN users u ON u.user_id = o.farmer_id LEFT JOIN dispatches d ON d.order_id = o.order_id WHERE o.payment_status = 'paid' AND d.dispatch_id IS NULL ORDER BY o.order_id DESC");
        $dispatches = $conn->query('SELECT d.dispatch_id, d.order_id, d.driver_id, d.delivery_status, d.dispatched_at, d.delivered_at, driver.full_name AS driver_name, customer.full_name AS customer_name, customer.phone AS customer_phone, customer.email AS customer_email, o.delivery_address FROM dispatches d LEFT JOIN users driver ON driver.user_id = d.driver_id LEFT JOIN orders o ON o.order_id = d.order_id LEFT JOIN users customer ON customer.user_id = o.farmer_id ORDER BY d.dispatch_id DESC');
    } else {
        $stmt = $conn->prepare('SELECT d.dispatch_id, d.order_id, d.driver_id, d.delivery_status, d.dispatched_at, d.delivered_at, customer.full_name AS customer_name, customer.phone AS customer_phone, customer.email AS customer_email, o.delivery_address FROM dispatches d INNER JOIN orders o ON o.order_id = d.order_id LEFT JOIN users customer ON customer.user_id = o.farmer_id WHERE d.driver_id = ? ORDER BY d.dispatched_at DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $dispatches = $stmt->get_result();
        $stmt->close();
    }
}

$bookings = null;
$trainers = null;
$certifications = null;
if ($canTrain) {
    if ($canManageService) {
        $trainers = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'trainer' AND approval_status = 'approved' ORDER BY full_name");
        $bookings = $conn->query('SELECT b.booking_id, b.farmer_id, b.trainer_id, b.service_type, b.booking_date, b.status, b.payment_status, b.total_amount, b.notes, b.is_certifiable, b.service_notes, b.trainer_notes, b.completed_at, customer.full_name AS customer_name, trainer.full_name AS trainer_name, c.cert_id AS existing_cert_id FROM bookings b LEFT JOIN users customer ON customer.user_id = b.farmer_id LEFT JOIN users trainer ON trainer.user_id = b.trainer_id LEFT JOIN certifications c ON c.booking_id = b.booking_id ORDER BY b.booking_date DESC');
        $certifications = $conn->query("SELECT c.cert_id, c.title, c.issue_date, c.certificate_code, u.full_name AS farmer_name FROM certifications c INNER JOIN bookings b ON b.booking_id = c.booking_id LEFT JOIN users u ON u.user_id = c.farmer_id WHERE b.is_certifiable = 1 AND b.status = 'completed' ORDER BY c.issue_date DESC LIMIT 100");
    } else {
        $stmt = $conn->prepare('SELECT b.booking_id, b.farmer_id, b.trainer_id, b.service_type, b.booking_date, b.status, b.payment_status, b.total_amount, b.notes, b.is_certifiable, b.service_notes, b.trainer_notes, b.completed_at, u.full_name AS customer_name, c.cert_id AS existing_cert_id FROM bookings b LEFT JOIN users u ON u.user_id = b.farmer_id LEFT JOIN certifications c ON c.booking_id = b.booking_id WHERE b.trainer_id = ? AND b.is_certifiable = 1 ORDER BY b.booking_date DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $bookings = $stmt->get_result();
        $stmt->close();
        $stmt = $conn->prepare("SELECT c.cert_id, c.title, c.issue_date, c.certificate_code, u.full_name AS farmer_name FROM certifications c INNER JOIN bookings b ON b.booking_id = c.booking_id LEFT JOIN users u ON u.user_id = c.farmer_id WHERE b.trainer_id = ? AND b.is_certifiable = 1 AND b.status = 'completed' ORDER BY c.issue_date DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $certifications = $stmt->get_result();
        $stmt->close();
    }
}

$contactMessages = null;
$farmerFeedback = null;
if (in_array($role, ['admin', 'service_manager'], true)) {
    $contactMessages = $conn->query("SELECT f.feedback_id, f.subject, f.message, f.created_at, f.review_status, f.staff_reply, f.replied_at, f.resolved_at, u.full_name FROM feedback f LEFT JOIN users u ON u.user_id = f.user_id WHERE f.category = 'contact' ORDER BY (f.review_status = 'new') DESC, f.created_at DESC");
    $farmerFeedback = $conn->query("SELECT f.feedback_id, f.subject, f.message, f.rating, f.created_at, f.review_status, f.staff_reply, f.replied_at, f.resolved_at, u.full_name FROM feedback f LEFT JOIN users u ON u.user_id = f.user_id WHERE f.category = 'feedback' ORDER BY (f.review_status = 'new') DESC, f.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Workspace | Meru Farmers Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h1 class="h2 mb-1">Staff Workspace</h1><p class="text-secondary mb-0">Signed in as <?php echo htmlspecialchars(str_replace('_', ' ', $role)); ?></p></div><a class="btn btn-outline-success" href="search.php">Global search</a></div>
        <?php if (isset($_GET['msg'])): ?><div class="alert alert-info" role="status"><?php echo htmlspecialchars($_GET['msg']); ?></div><?php endif; ?>

        <?php if ($canManagePurchaseOrders || $role === 'supplier'): ?>
            <section class="mb-5" id="supplier-orders">
                <h2 class="h4"><?php echo $role === 'supplier' ? 'Purchase orders for your business' : 'Supplier purchase orders'; ?></h2>
                <?php if ($role === 'supplier' && $supplierSummary): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-lg-3"><div class="card h-100"><small>Total POs</small><strong class="fs-4"><?php echo (int) $supplierSummary['total_pos']; ?></strong></div></div>
                        <div class="col-6 col-lg-3"><div class="card h-100"><small>Pending / active POs</small><strong class="fs-4"><?php echo (int) $supplierSummary['active_pos']; ?></strong></div></div>
                        <div class="col-6 col-lg-3"><div class="card h-100"><small>Unpaid balance</small><strong>KSh <?php echo number_format((float) $supplierSummary['unpaid_balance'], 2); ?></strong></div></div>
                        <div class="col-6 col-lg-3"><div class="card h-100"><small>Total paid</small><strong>KSh <?php echo number_format((float) $supplierSummary['total_paid'], 2); ?></strong></div></div>
                    </div>
                    <form method="GET" action="staff.php" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="_section" value="supplier-orders">
                        <div class="col-sm-5 col-md-4"><label for="poStatusFilter" class="form-label">Filter purchase orders</label><select class="form-select" id="poStatusFilter" name="po_status"><option value="all" <?php echo $poFilter === 'all' ? 'selected' : ''; ?>>All</option><option value="pending" <?php echo $poFilter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="accepted" <?php echo $poFilter === 'accepted' ? 'selected' : ''; ?>>Accepted</option><option value="dispatched" <?php echo $poFilter === 'dispatched' ? 'selected' : ''; ?>>Dispatched</option><option value="received" <?php echo $poFilter === 'received' ? 'selected' : ''; ?>>Received</option><option value="unpaid" <?php echo $poFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option></select></div>
                        <div class="col-auto"><button class="btn btn-outline-success">Apply filter</button></div>
                    </form>
                <?php endif; ?>
                <?php if ($poSummary): ?>
                    <section class="card po-summary mb-4" id="po-summary">
                        <div class="d-flex justify-content-between align-items-start gap-3"><div><p class="text-uppercase small mb-1">Purchase order summary</p><h3 class="h4">PO #<?php echo (int) $poSummary['purchase_order_id']; ?></h3></div><button type="button" class="btn btn-outline-success no-print" onclick="window.print()">Print summary</button></div>
                        <div class="row g-3"><div class="col-md-6"><p><strong>Product:</strong> <?php echo htmlspecialchars($poSummary['item_name']); ?><br><strong>Category:</strong> <?php echo htmlspecialchars($poSummary['category'] ?? ''); ?><br><strong>Supplier:</strong> <?php echo htmlspecialchars($poSummary['supplier_name']); ?><br><strong>Requested by:</strong> <?php echo htmlspecialchars($poSummary['ordered_by_name']); ?></p></div><div class="col-md-6"><p><strong>Quantity:</strong> <?php echo (int) $poSummary['quantity']; ?><br><strong>Unit cost:</strong> KSh <?php echo number_format((float) $poSummary['unit_cost'], 2); ?><br><strong>Total cost:</strong> KSh <?php echo number_format((float) $poSummary['quantity'] * (float) $poSummary['unit_cost'], 2); ?><br><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($poSummary['status'])); ?></p></div></div>
                        <p><strong>Created:</strong> <?php echo htmlspecialchars($poSummary['created_at']); ?> · <strong>Expected delivery:</strong> <?php echo htmlspecialchars($poSummary['expected_delivery_date'] ?: 'Not set'); ?> · <strong>Received:</strong> <?php echo htmlspecialchars($poSummary['received_at'] ?: 'Not received'); ?></p>
                        <p><strong>Payment:</strong> <?php echo htmlspecialchars(strtoupper($poSummary['payment_status']) . ' via ' . strtoupper($poSummary['payment_method'])); ?><?php if ($poSummary['payment_status'] === 'paid'): ?> · <strong>Reference:</strong> <?php echo htmlspecialchars($poSummary['payment_reference'] ?: 'Not recorded'); ?> · <strong>Paid:</strong> <?php echo htmlspecialchars($poSummary['paid_at'] ?: 'Date not recorded'); ?><?php endif; ?></p>
                        <?php if (!empty($poSummary['supplier_note'])): ?><p><strong>Supplier note:</strong> <?php echo nl2br(htmlspecialchars($poSummary['supplier_note'])); ?></p><?php endif; ?>
                        <a class="btn btn-secondary no-print" href="staff.php#supplier-orders">Back to purchase orders</a>
                    </section>
                    <style>@media print { .global-topbar,.global-sidebar,.sidebar-backdrop,.no-print { display:none !important; } body { padding:0 !important; } .po-summary { border:0 !important; box-shadow:none !important; } }</style>
                <?php endif; ?>
                <?php if ($canManagePurchaseOrders): ?>
                    <form method="POST" class="row g-2 mb-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>">
                        <input type="hidden" name="action" value="create_purchase_order">
                        <div class="col-md-3"><label class="form-label" for="purchaseSupplier">Supplier</label><select class="form-select" id="purchaseSupplier" name="supplier_id" required><option value="">Choose supplier</option><?php if ($purchaseOrderSuppliers): while ($supplier = $purchaseOrderSuppliers->fetch_assoc()): ?><option value="<?php echo (int) $supplier['user_id']; ?>"><?php echo htmlspecialchars($supplier['full_name']); ?></option><?php endwhile; endif; ?></select></div>
                        <div class="col-md-3"><label class="form-label" for="purchaseItem">Product</label><select class="form-select" id="purchaseItem" name="item_id" required><option value="">Choose product</option><?php if ($purchaseOrderItems): while ($item = $purchaseOrderItems->fetch_assoc()): ?><option value="<?php echo (int) $item['item_id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo (int) $item['quantity_in_stock']; ?> in stock)</option><?php endwhile; endif; ?></select></div>
                        <div class="col-md-2"><label class="form-label" for="purchaseQuantity">Quantity</label><input class="form-control" id="purchaseQuantity" type="number" name="quantity" min="1" max="100000" required></div>
                        <div class="col-md-2"><label class="form-label" for="purchaseUnitCost">Unit cost (KSh)</label><input class="form-control" id="purchaseUnitCost" type="number" name="unit_cost" min="0.01" step="0.01" required></div>
                        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100">Send purchase order</button></div>
                    </form>
                <?php endif; ?>
                <?php if (!$purchaseOrders || $purchaseOrders->num_rows === 0): ?><p>No supplier purchase orders yet.</p><?php else: ?>
                    <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>PO #</th><th>Product</th><th>Supplier</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th><th>Requested By</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead><tbody>
                        <?php while ($purchaseOrder = $purchaseOrders->fetch_assoc()): ?><tr><td>#<?php echo (int) $purchaseOrder['purchase_order_id']; ?><br><small><?php echo htmlspecialchars($purchaseOrder['created_at']); ?></small></td><td><?php echo htmlspecialchars($purchaseOrder['item_name']); ?></td><td><?php echo htmlspecialchars($purchaseOrder['supplier_name']); ?></td><td><?php echo (int) $purchaseOrder['quantity']; ?></td><td>KSh <?php echo number_format((float) $purchaseOrder['unit_cost'], 2); ?></td><td><strong>KSh <?php echo number_format((float) $purchaseOrder['quantity'] * (float) $purchaseOrder['unit_cost'], 2); ?></strong></td><td><?php echo htmlspecialchars($purchaseOrder['ordered_by_name']); ?></td><td><?php echo htmlspecialchars(ucfirst($purchaseOrder['status'])); ?><?php if (!empty($purchaseOrder['expected_delivery_date'])): ?><br><small>Expected: <?php echo htmlspecialchars($purchaseOrder['expected_delivery_date']); ?></small><?php endif; ?></td><td><?php echo htmlspecialchars(strtoupper($purchaseOrder['payment_status']) . ' / ' . strtoupper($purchaseOrder['payment_method'])); ?><?php if ($purchaseOrder['payment_status'] === 'paid'): ?><br><small>Ref: <?php echo htmlspecialchars($purchaseOrder['payment_reference'] ?: 'Not recorded'); ?><br>Paid: <?php echo htmlspecialchars($purchaseOrder['paid_at'] ?: 'Date not recorded'); ?></small><?php endif; ?></td><td>
                            <?php if ($role === 'supplier' && $purchaseOrder['status'] === 'pending'): ?><form method="POST" class="d-grid gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="respond_purchase_order"><input type="hidden" name="purchase_order_id" value="<?php echo (int) $purchaseOrder['purchase_order_id']; ?>"><input class="form-control form-control-sm" name="supplier_note" maxlength="2000" placeholder="Optional note"><div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-success" name="purchase_order_status" value="accepted">Accept PO</button><button class="btn btn-sm btn-outline-warning" name="purchase_order_status" value="pending">Delay</button><button class="btn btn-sm btn-outline-danger" name="purchase_order_status" value="rejected">Decline</button></div></form>
                            <?php elseif ($role === 'supplier' && $purchaseOrder['status'] === 'accepted'): ?><form method="POST" class="d-grid gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="fulfill_purchase_order"><input type="hidden" name="purchase_order_id" value="<?php echo (int) $purchaseOrder['purchase_order_id']; ?>"><label class="small">Expected delivery<input class="form-control form-control-sm" type="date" name="expected_delivery_date"></label><input class="form-control form-control-sm" name="supplier_note" maxlength="2000" placeholder="Optional delivery note"><button class="btn btn-sm btn-outline-success">Mark Dispatched</button></form>
                            <?php elseif ($canManagePurchaseOrders && $purchaseOrder['status'] === 'dispatched'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="receive_purchase_order"><input type="hidden" name="purchase_order_id" value="<?php echo (int) $purchaseOrder['purchase_order_id']; ?>"><button class="btn btn-sm btn-outline-success">Record Received</button></form>
                            <?php elseif ($purchaseOrder['status'] === 'dispatched' || $purchaseOrder['status'] === 'received'): ?><a class="btn btn-sm btn-outline-primary" href="staff.php?po_summary=<?php echo (int) $purchaseOrder['purchase_order_id']; ?>#supplier-orders">View / Print Summary</a>
                            <?php else: ?><?php echo htmlspecialchars($purchaseOrder['supplier_note'] ?? ''); ?><?php endif; ?>
                        </td></tr><?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canFinance): ?>
            <section class="mb-5" id="finance"><h2 class="h4">Finance</h2><p class="fs-5">Paid product sales: <strong>KSh <?php echo number_format($salesRevenue, 2); ?></strong> · Paid services: <strong>KSh <?php echo number_format($serviceRevenue, 2); ?></strong> · Total revenue: <strong>KSh <?php echo number_format($revenue, 2); ?></strong></p>
                <h3 class="h5 mt-4">Product sales</h3>
                <?php if (!$financeOrders || $financeOrders->num_rows === 0): ?><p>No product sales.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Method / status</th><th>Update payment</th></tr></thead><tbody><?php while ($order = $financeOrders->fetch_assoc()): ?><tr><td>#<?php echo (int) $order['order_id']; ?></td><td><?php echo htmlspecialchars($order['customer_name'] ?? 'Customer'); ?></td><td><?php echo htmlspecialchars($order['item_name'] ?? 'Product'); ?></td><td><?php echo (int) $order['quantity']; ?></td><td>KSh <?php echo number_format((float) $order['total_amount'], 2); ?></td><td><?php echo htmlspecialchars(strtoupper($order['payment_method']) . ' / ' . strtoupper($order['payment_status'])); ?></td><td><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="set_order_payment"><input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>"><select class="form-select form-select-sm" name="payment_status"><option value="unpaid" <?php echo $order['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option><option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option></select><button class="btn btn-sm btn-outline-success">Save</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
                <h3 class="h5 mt-4">Service financials</h3>
                <?php if (!$financeBookings || $financeBookings->num_rows === 0): ?><p>No service bookings.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Date</th><th>Total</th><th>Method / status</th><th>Update payment</th></tr></thead><tbody><?php while ($booking = $financeBookings->fetch_assoc()): ?><tr><td>#<?php echo (int) $booking['booking_id']; ?></td><td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Customer'); ?></td><td><?php echo htmlspecialchars($booking['service_type']); ?></td><td><?php echo htmlspecialchars($booking['booking_date']); ?></td><td>KSh <?php echo number_format((float) $booking['total_amount'], 2); ?></td><td><?php echo htmlspecialchars(strtoupper($booking['payment_method']) . ' / ' . strtoupper($booking['payment_status'])); ?></td><td><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="set_booking_payment"><input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>"><select class="form-select form-select-sm" name="payment_status"><option value="unpaid" <?php echo $booking['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option><option value="paid" <?php echo $booking['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="refunded" <?php echo $booking['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option></select><button class="btn btn-sm btn-outline-success">Save</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
                <h3 class="h5 mt-4">Payment transactions</h3>
                <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Payment</th><th>User</th><th>Type</th><th>Amount</th><th>Phone</th><th>Status</th><th>Receipt / detail</th><th>Created</th><th>Action</th></tr></thead><tbody><?php if ($transactions): while ($payment = $transactions->fetch_assoc()): ?><tr><td>#<?php echo (int) $payment['payment_id']; ?></td><td>#<?php echo (int) $payment['user_id']; ?></td><td><?php echo htmlspecialchars($payment['payment_type']); ?></td><td>KSh <?php echo number_format((float) $payment['amount'], 2); ?></td><td><?php echo htmlspecialchars($payment['phone']); ?></td><td><?php echo htmlspecialchars($payment['status']); ?></td><td><?php echo htmlspecialchars($payment['mpesa_receipt'] ?: $payment['result_desc'] ?: ''); ?></td><td><?php echo htmlspecialchars($payment['created_at']); ?></td><td><?php if ($payment['status'] === 'paid_unfulfilled'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="reconcile_payment"><input type="hidden" name="payment_id" value="<?php echo (int) $payment['payment_id']; ?>"><button class="btn btn-sm btn-outline-success">Reconcile</button></form><?php endif; ?></td></tr><?php endwhile; endif; ?></tbody></table></div>
                <h3 class="h5 mt-4">Supplier purchase order payments</h3>
                <?php if (!$financePurchaseOrders || $financePurchaseOrders->num_rows === 0): ?><p>No supplier purchase orders.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>PO</th><th>Supplier</th><th>Product</th><th>Quantity</th><th>Total</th><th>PO status</th><th>Payment</th></tr></thead><tbody><?php while ($purchaseOrder = $financePurchaseOrders->fetch_assoc()): ?><tr><td>#<?php echo (int) $purchaseOrder['purchase_order_id']; ?></td><td><?php echo htmlspecialchars($purchaseOrder['supplier_name']); ?></td><td><?php echo htmlspecialchars($purchaseOrder['item_name']); ?></td><td><?php echo (int) $purchaseOrder['quantity']; ?></td><td>KSh <?php echo number_format((float) $purchaseOrder['quantity'] * (float) $purchaseOrder['unit_cost'], 2); ?></td><td><?php echo htmlspecialchars($purchaseOrder['status']); ?></td><td><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="set_supplier_po_payment"><input type="hidden" name="purchase_order_id" value="<?php echo (int) $purchaseOrder['purchase_order_id']; ?>"><select class="form-select form-select-sm" name="payment_status"><option value="unpaid" <?php echo $purchaseOrder['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option><option value="paid" <?php echo $purchaseOrder['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option><option value="refunded" <?php echo $purchaseOrder['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option></select><select class="form-select form-select-sm" name="payment_method"><option value="cash" <?php echo $purchaseOrder['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option><option value="mpesa" <?php echo $purchaseOrder['payment_method'] === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option></select><button class="btn btn-sm btn-outline-success">Save</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
            </section>
            <section class="mb-5" id="supplier-settlement">
                <h3 class="h5">Record supplier settlement</h3>
                <?php $financePurchaseOrders->data_seek(0); ?>
                <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>PO</th><th>Supplier</th><th>Amount</th><th>Settlement status</th><th>Payment method</th><th>Reference</th><th>Paid date</th><th>Action</th></tr></thead><tbody>
                    <?php while ($purchaseOrder = $financePurchaseOrders->fetch_assoc()): ?><tr><td>#<?php echo (int) $purchaseOrder['purchase_order_id']; ?></td><td><?php echo htmlspecialchars($purchaseOrder['supplier_name']); ?></td><td>KSh <?php echo number_format((float) $purchaseOrder['quantity'] * (float) $purchaseOrder['unit_cost'], 2); ?></td><td><?php echo htmlspecialchars(strtoupper($purchaseOrder['payment_status'])); ?></td><td><?php echo htmlspecialchars(strtoupper($purchaseOrder['payment_method'])); ?></td><td><?php echo htmlspecialchars($purchaseOrder['payment_status'] === 'paid' ? ($purchaseOrder['payment_reference'] ?: 'Not recorded') : ''); ?></td><td><?php echo htmlspecialchars($purchaseOrder['payment_status'] === 'paid' ? ($purchaseOrder['paid_at'] ?: 'Date not recorded') : ''); ?></td><td><?php if ($purchaseOrder['status'] === 'dispatched' || $purchaseOrder['status'] === 'received'): ?><form method="POST" class="d-grid gap-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="set_supplier_po_payment"><input type="hidden" name="purchase_order_id" value="<?php echo (int) $purchaseOrder['purchase_order_id']; ?>"><select class="form-select form-select-sm" name="payment_method"><option value="cash" <?php echo $purchaseOrder['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option><option value="mpesa" <?php echo $purchaseOrder['payment_method'] === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option></select><input class="form-control form-control-sm" name="payment_reference" maxlength="100" placeholder="Reference required to mark paid" value="<?php echo htmlspecialchars($purchaseOrder['payment_reference'] ?? ''); ?>"><button class="btn btn-sm btn-success" name="payment_status" value="paid">Mark paid</button><button class="btn btn-sm btn-outline-secondary" name="payment_status" value="unpaid">Keep unpaid</button></form><?php else: ?>Available after dispatch<?php endif; ?></td></tr><?php endwhile; ?>
                </tbody></table></div>
            </section>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <section class="mb-5" id="staff-accounts"><h2 class="h4">Staff accounts and roles</h2><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Account</th><th>Email</th><th>Current role</th><th>Assign role</th></tr></thead><tbody><?php while ($account = $staffAccounts->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($account['full_name']); ?></td><td><?php echo htmlspecialchars($account['email']); ?></td><td><?php echo htmlspecialchars(str_replace('_', ' ', $account['role'])); ?></td><td><?php if ((int) $account['user_id'] === $userId): ?>Current account<?php else: ?><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="set_staff_role"><input type="hidden" name="user_id" value="<?php echo (int) $account['user_id']; ?>"><select class="form-select form-select-sm" name="staff_role"><?php foreach (['admin','farmer','finance_manager','inventory_manager','dispatch_manager','driver','service_manager','trainer','supplier'] as $assignableRole): ?><option value="<?php echo $assignableRole; ?>" <?php echo $account['role'] === $assignableRole ? 'selected' : ''; ?>><?php echo htmlspecialchars(str_replace('_', ' ', $assignableRole)); ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-success">Save</button></form><?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></section>
        <?php endif; ?>

        <?php if ($canInventory): ?>
            <section class="mb-5" id="inventory"><h2 class="h4">Inventory alerts</h2><p class="text-secondary">Low stock is 10 units or fewer.</p>
                <?php if (!$lowStock || $lowStock->num_rows === 0): ?><p>No low-stock items.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Item</th><th>Category</th><th>On hand</th><th>Unit price</th><th>Adjust stock</th></tr></thead><tbody><?php while ($item = $lowStock->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($item['item_name']); ?></td><td><?php echo htmlspecialchars($item['category'] ?? ''); ?></td><td><?php echo (int) $item['quantity_in_stock']; ?></td><td>KSh <?php echo number_format((float) $item['unit_price'], 2); ?></td><td><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="adjust_stock"><input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>"><input class="form-control form-control-sm" style="max-width:120px" type="number" name="adjustment" aria-label="Stock adjustment for <?php echo htmlspecialchars($item['item_name']); ?>" required><button class="btn btn-sm btn-success">Apply</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canDispatch): ?>
            <section class="mb-5" id="dispatch"><h2 class="h4"><?php echo $role === 'driver' ? 'My delivery tasks' : 'Dispatch and deliveries'; ?></h2>
                <?php if (in_array($role, ['admin', 'dispatch_manager'], true)): ?><form method="POST" class="row g-2 mb-3"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="assign_dispatch"><div class="col-md-5"><label class="form-label" for="orderToDispatch">Paid order</label><select class="form-select" id="orderToDispatch" name="order_id" required><option value="">Choose order</option><?php if ($availableOrders): while ($order = $availableOrders->fetch_assoc()): ?><option value="<?php echo (int) $order['order_id']; ?>">#<?php echo (int) $order['order_id']; ?> · <?php echo htmlspecialchars($order['full_name'] ?? 'Customer'); ?> · KSh <?php echo number_format((float) $order['total_amount'], 2); ?></option><?php endwhile; endif; ?></select></div><div class="col-md-4"><label class="form-label" for="driverId">Driver</label><select class="form-select" id="driverId" name="driver_id" required><option value="">Choose driver</option><?php if ($drivers): while ($driver = $drivers->fetch_assoc()): ?><option value="<?php echo (int) $driver['user_id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?></option><?php endwhile; endif; ?></select></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-success w-100">Assign delivery</button></div></form><?php endif; ?>
                <?php if (!$dispatches || $dispatches->num_rows === 0): ?><p>No delivery tasks.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Dispatch</th><th>Order</th><th>Customer / destination</th><?php if ($role !== 'driver'): ?><th>Driver</th><?php endif; ?><th>Status</th><th>Update</th></tr></thead><tbody><?php while ($dispatch = $dispatches->fetch_assoc()): ?><tr><td>#<?php echo (int) $dispatch['dispatch_id']; ?></td><td>#<?php echo (int) $dispatch['order_id']; ?></td><td><?php echo htmlspecialchars($dispatch['customer_name'] ?? 'Customer'); ?><?php if ($role === 'driver'): ?><br><a href="tel:<?php echo htmlspecialchars($dispatch['customer_phone'] ?? ''); ?>"><?php echo htmlspecialchars($dispatch['customer_phone'] ?? 'No phone'); ?></a><br><?php echo htmlspecialchars($dispatch['customer_email'] ?? ''); ?><br><strong>Deliver to:</strong> <?php echo htmlspecialchars($dispatch['delivery_address'] ?? 'Address not provided'); ?><?php endif; ?></td><?php if ($role !== 'driver'): ?><td><?php echo htmlspecialchars($dispatch['driver_name'] ?? 'Unassigned'); ?></td><?php endif; ?><td><?php echo htmlspecialchars(str_replace('_', ' ', $dispatch['delivery_status'])); ?></td><td><form method="POST" class="d-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="update_dispatch"><input type="hidden" name="dispatch_id" value="<?php echo (int) $dispatch['dispatch_id']; ?>"><select class="form-select form-select-sm" name="delivery_status"><?php if ($role !== 'driver'): ?><option value="assigned" <?php echo $dispatch['delivery_status'] === 'assigned' ? 'selected' : ''; ?>>Assigned</option><?php endif; ?><option value="in_transit" <?php echo $dispatch['delivery_status'] === 'in_transit' ? 'selected' : ''; ?>>In transit</option><option value="delivered" <?php echo $dispatch['delivery_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option></select><button class="btn btn-sm btn-outline-success">Save</button></form></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canTrain): ?>
            <section class="mb-5" id="services"><h2 class="h4"><?php echo $role === 'trainer' ? 'My training schedule' : 'Service requests and training schedule'; ?></h2>
                <?php if (!$bookings || $bookings->num_rows === 0): ?><p>No service bookings.</p><?php else: ?><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Type</th><th>Date</th><th>Trainer / field officer</th><th>Payment</th><th>Status / action</th></tr></thead><tbody><?php while ($booking = $bookings->fetch_assoc()): ?><tr><td>#<?php echo (int) $booking['booking_id']; ?></td><td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Customer'); ?></td><td><?php echo htmlspecialchars($booking['service_type']); ?></td><td><?php echo $booking['is_certifiable'] ? 'Certifiable training' : 'Standard service'; ?></td><td><?php echo htmlspecialchars($booking['booking_date']); ?></td><td><?php echo htmlspecialchars($booking['trainer_name'] ?? ($role === 'trainer' ? 'You' : 'Unassigned')); ?></td><td><?php echo htmlspecialchars($booking['payment_status']); ?> · KSh <?php echo number_format((float) $booking['total_amount'], 2); ?></td><td>
                    <?php if ($canManageService): ?><form method="POST" class="d-grid gap-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="schedule_booking"><input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>"><input class="form-control form-control-sm" type="date" name="booking_date" value="<?php echo htmlspecialchars($booking['booking_date']); ?>" required><select class="form-select form-select-sm" name="trainer_id"><option value="">Keep current trainer</option><?php if ($trainers): $trainers->data_seek(0); while ($trainer = $trainers->fetch_assoc()): ?><option value="<?php echo (int) $trainer['user_id']; ?>" <?php echo (int) $booking['trainer_id'] === (int) $trainer['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($trainer['full_name']); ?></option><?php endwhile; endif; ?></select><select class="form-select form-select-sm" name="booking_status"><?php foreach (['pending' => 'Pending', 'approved' => 'Confirmed', 'cancelled' => 'Cancelled'] as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $booking['status'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-success">Approve / save schedule</button></form><?php if (!$booking['is_certifiable'] && $booking['status'] === 'approved'): ?><form method="POST" class="d-grid gap-1 mt-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="complete_training"><input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>"><textarea class="form-control form-control-sm" name="service_notes" rows="2" maxlength="5000" placeholder="Service / field completion notes" required></textarea><button class="btn btn-sm btn-outline-primary">Complete standard service</button></form><?php endif; ?><?php elseif ($role === 'trainer' && $booking['is_certifiable'] && $booking['status'] === 'approved'): ?><form method="POST" class="d-grid gap-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="complete_training"><input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>"><textarea class="form-control form-control-sm" name="trainer_notes" rows="2" maxlength="5000" placeholder="Training completion notes"></textarea><button class="btn btn-sm btn-outline-success">Mark training completed</button></form><?php else: ?><?php echo htmlspecialchars($booking['status']); ?><?php endif; ?>
                    <?php if (!empty($booking['service_notes'])): ?><p class="small mt-1 mb-0"><strong>Service notes:</strong> <?php echo nl2br(htmlspecialchars($booking['service_notes'])); ?></p><?php endif; ?>
                    <?php if (!empty($booking['trainer_notes'])): ?><p class="small mt-1 mb-0"><strong>Training notes:</strong> <?php echo nl2br(htmlspecialchars($booking['trainer_notes'])); ?></p><?php endif; ?>
                    <?php if ($booking['is_certifiable'] && $booking['status'] === 'completed' && !empty($booking['existing_cert_id']) && in_array($role, ['admin', 'service_manager', 'trainer'], true)): ?><a class="btn btn-sm btn-outline-primary mt-1" href="staff.php?certificate_id=<?php echo (int) $booking['existing_cert_id']; ?>">View / Issue Certificate</a><?php elseif ($booking['is_certifiable'] && $booking['status'] === 'completed' && empty($booking['existing_cert_id']) && ($role === 'admin' || ($role === 'trainer' && (int) $booking['trainer_id'] === $userId))): ?><form method="POST" class="mt-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['staff_csrf']); ?>"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>"><button class="btn btn-sm btn-outline-primary">View / Issue Certificate</button></form><?php endif; ?>
                </td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?>
            </section>
            <?php if ($certifications): ?><section id="certifications"><h2 class="h4">Certifications</h2><?php if ($certifications->num_rows === 0): ?><p>No certificates issued.</p><?php else: ?><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Holder</th><th>Training</th><th>Issue date</th><th>Certificate code</th></tr></thead><tbody><?php while ($cert = $certifications->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($cert['farmer_name'] ?? ($_SESSION['full_name'] ?? 'Farmer')); ?></td><td><?php echo htmlspecialchars($cert['title']); ?></td><td><?php echo htmlspecialchars($cert['issue_date']); ?></td><td><?php echo htmlspecialchars($cert['certificate_code']); ?></td></tr><?php endwhile; ?></tbody></table></div><?php endif; ?></section><?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'service_manager'], true)): ?>
            <section class="mb-5" id="messages">
                <h2 class="h4">Contact Messages</h2>
                <?php if (!$contactMessages || $contactMessages->num_rows === 0): ?><p>No contact inquiries have been received.</p><?php else: ?>
                    <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Received</th><th>From</th><th>Topic</th><th>Message</th><th>Status / reply</th></tr></thead><tbody>
                        <?php while ($message = $contactMessages->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($message['created_at']); ?></td><td><?php echo htmlspecialchars($message['full_name'] ?? 'Guest'); ?></td><td><?php echo htmlspecialchars($message['subject']); ?></td><td style="min-width:240px;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($message['message'])); ?></td><td><span class="badge text-bg-<?php echo $message['review_status'] === 'resolved' ? 'success' : ($message['review_status'] === 'reviewed' ? 'info' : 'warning'); ?>"><?php echo htmlspecialchars($message['review_status'] === 'new' ? 'New / unread' : ucfirst($message['review_status'])); ?></span><?php if (!empty($message['staff_reply'])): ?><p class="small mt-1 mb-0"><?php echo nl2br(htmlspecialchars($message['staff_reply'])); ?><br><?php echo htmlspecialchars($message['replied_at'] ?? ''); ?></p><?php endif; ?><form method="POST" action="message_handler.php" class="mt-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['message_csrf']); ?>"><input type="hidden" name="message_id" value="<?php echo (int) $message['feedback_id']; ?>"><input type="hidden" name="category" value="contact"><textarea class="form-control form-control-sm mb-1" name="staff_reply" rows="2" maxlength="5000" placeholder="Reply (optional)"></textarea><select class="form-select form-select-sm mb-1" name="review_status"><option value="new" <?php echo $message['review_status'] === 'new' ? 'selected' : ''; ?>>New / unread</option><option value="reviewed" <?php echo $message['review_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option><option value="resolved" <?php echo $message['review_status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option></select><button class="btn btn-sm btn-outline-success">Save reply / status</button></form></td></tr><?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <section class="mb-5" id="feedback-queue">
                <h2 class="h4">Farmer Feedback</h2>
                <?php if (!$farmerFeedback || $farmerFeedback->num_rows === 0): ?><p>No farmer feedback has been received.</p><?php else: ?>
                    <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Received</th><th>Farmer</th><th>Service / category</th><th>Rating</th><th>Feedback</th><th>Status / reply</th></tr></thead><tbody>
                        <?php while ($message = $farmerFeedback->fetch_assoc()): ?><tr><td><?php echo htmlspecialchars($message['created_at']); ?></td><td><?php echo htmlspecialchars($message['full_name'] ?? 'Guest'); ?></td><td><?php echo htmlspecialchars($message['subject']); ?></td><td><?php echo $message['rating'] ? ((int) $message['rating'] . ' / 5') : 'Not rated'; ?></td><td style="min-width:240px;white-space:pre-wrap"><?php echo nl2br(htmlspecialchars($message['message'])); ?></td><td><span class="badge text-bg-<?php echo $message['review_status'] === 'resolved' ? 'success' : ($message['review_status'] === 'reviewed' ? 'info' : 'warning'); ?>"><?php echo htmlspecialchars($message['review_status'] === 'new' ? 'New / unread' : ucfirst($message['review_status'])); ?></span><?php if (!empty($message['staff_reply'])): ?><p class="small mt-1 mb-0"><?php echo nl2br(htmlspecialchars($message['staff_reply'])); ?><br><?php echo htmlspecialchars($message['replied_at'] ?? ''); ?></p><?php endif; ?><form method="POST" action="message_handler.php" class="mt-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['message_csrf']); ?>"><input type="hidden" name="message_id" value="<?php echo (int) $message['feedback_id']; ?>"><input type="hidden" name="category" value="feedback"><textarea class="form-control form-control-sm mb-1" name="staff_reply" rows="2" maxlength="5000" placeholder="Reply (optional)"></textarea><select class="form-select form-select-sm mb-1" name="review_status"><option value="new" <?php echo $message['review_status'] === 'new' ? 'selected' : ''; ?>>New / unread</option><option value="reviewed" <?php echo $message['review_status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option><option value="resolved" <?php echo $message['review_status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option></select><button class="btn btn-sm btn-outline-success">Save reply / status</button></form></td></tr><?php endwhile; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>