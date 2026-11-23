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

$servicePrices = [
    'Soil Testing & Analysis' => 2500,
    'Machinery & Tractor Hire' => 4500,
    'Agronomy Advisory Session' => 1500,
    'Crop Protection Consultancy' => 2000,
    'Training & Workshops' => 2500,
];
$certifiableServices = ['Training & Workshops'];

$userId = (int) $currentUser['user_id'];
$serviceType = trim($_POST['service_type'] ?? '');
$bookingDate = trim($_POST['booking_date'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$overridePhone = trim($_POST['payment_phone'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'mpesa';
$isCertifiable = in_array($serviceType, $certifiableServices, true) ? 1 : 0;

try {
    if (!isset($servicePrices[$serviceType]) || !in_array($paymentMethod, ['mpesa', 'cash'], true)) {
        throw new InvalidArgumentException('Choose a valid service and payment method.');
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $bookingDate || $parsedDate < new DateTimeImmutable('today')) {
        throw new InvalidArgumentException('Choose a valid booking date that is today or later.');
    }

    if ($paymentMethod === 'cash') {
        $bookingStatus = 'pending';
        $paymentStatus = 'unpaid';
        $amount = $servicePrices[$serviceType];
        $stmt = $conn->prepare('INSERT INTO bookings (farmer_id, service_type, booking_date, status, payment_method, notes, total_amount, payment_status, is_certifiable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssssdsi', $userId, $serviceType, $bookingDate, $bookingStatus, $paymentMethod, $notes, $amount, $paymentStatus, $isCertifiable);
        if (!$stmt->execute()) {
            throw new RuntimeException('Could not save your cash booking.');
        }
        $stmt->close();
        header('Location: dashboard.php?booking_msg=cash_booking');
        exit();
    }

    $userStmt = $conn->prepare('SELECT phone FROM users WHERE user_id = ?');
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $registeredPhone = (string) ($userStmt->get_result()->fetch_assoc()['phone'] ?? '');
    $userStmt->close();

    $phone = $overridePhone !== '' ? $overridePhone : $registeredPhone;
    $paymentId = createPaymentIntent($conn, $userId, 'booking', [
        'service_type' => $serviceType,
        'booking_date' => $bookingDate,
        'notes' => $notes,
        'is_certifiable' => $isCertifiable,
    ], $servicePrices[$serviceType], $phone);

    header('Location: payment_status.php?id=' . $paymentId);
    exit();
} catch (Throwable $error) {
    http_response_code(400);
    $message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Payment Could Not Start</title><link rel="stylesheet" href="style.css"></head><body><header style="padding:12px 20px;background:#2e7d32"><a href="index.php" style="display:flex;align-items:center;gap:10px;color:white;text-decoration:none;font-weight:700"><img src="Logo.png" alt="Meru Farmers Centre logo" style="width:54px;height:54px;object-fit:contain;border-radius:50%;background:white">Meru Farmers Centre</a></header><main class="form-panel" style="max-width:560px;margin:10vh auto"><h1>Payment Could Not Start</h1><p>' . $message . '</p><p><a href="bookings.php" class="btn">Return to bookings</a></p></main></body></html>';
}
