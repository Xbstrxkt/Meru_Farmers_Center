<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (isset($_SESSION['user_id'])) {
    $currentUser = mfcRequireRoles($conn, MFC_USER_ROLES);
}
if (empty($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$validPhone = $phone === '' || (bool) preg_match('/^[+0-9\s().-]{7,30}$/', $phone);

if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 100 || !$validPhone || $subject === '' || strlen($subject) > 150
    || $message === '' || strlen($message) > 10000) {
    $_SESSION['contact_flash'] = ['type' => 'error', 'message' => 'Please check the name, email, optional phone number, subject, and message fields.'];
    header('Location: contact.php?error=1#contact-form');
    exit();
}

$userId = isset($currentUser) ? (int) $currentUser['user_id'] : null;
$storedMessage = "From: {$name}\nEmail: {$email}\nPhone: " . ($phone !== '' ? $phone : 'Not provided') . "\n\nMessage:\n{$message}";
$category = 'contact';
$stmt = $conn->prepare('INSERT INTO feedback (user_id, subject, message, category) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    error_log('Contact message prepare failed: ' . $conn->error);
    $_SESSION['contact_flash'] = ['type' => 'error', 'message' => 'We could not save your message right now. Please try again.'];
    header('Location: contact.php?error=1#contact-form');
    exit();
}

$stmt->bind_param('isss', $userId, $subject, $storedMessage, $category);
$saved = $stmt->execute();
$stmt->close();

if (!$saved) {
    error_log('Contact message insert failed: ' . $conn->error);
    $_SESSION['contact_flash'] = ['type' => 'error', 'message' => 'We could not save your message right now. Please try again.'];
    header('Location: contact.php?error=1#contact-form');
    exit();
}

$_SESSION['contact_flash'] = ['type' => 'success', 'message' => 'Your message was received by Meru Farmers Centre. Our team will follow up using the contact details you provided.'];
header('Location: contact.php?sent=1');
exit();