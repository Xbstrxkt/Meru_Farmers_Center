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
if (empty($_SESSION['feedback_csrf']) || !hash_equals($_SESSION['feedback_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$subject = trim($_POST['service_rated'] ?? '');
$message = trim($_POST['message'] ?? '');
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$validPhone = $phone === '' || (bool) preg_match('/^[+0-9\s().-]{7,30}$/', $phone);

if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 100 || !$validPhone || $subject === '' || strlen($subject) > 150
    || $message === '' || strlen($message) > 10000 || $rating === false || $rating === null
    || $rating < 1 || $rating > 5) {
    $_SESSION['feedback_flash'] = ['type' => 'error', 'message' => 'Please provide your name, a valid email, a service/category, a 1–5 rating, and feedback.'];
    header('Location: feedback.php?error=1#feedback-form');
    exit();
}

$userId = isset($currentUser) ? (int) $currentUser['user_id'] : null;
$category = 'feedback';
$storedMessage = "From: {$name}\nEmail: {$email}\nPhone: " . ($phone !== '' ? $phone : 'Not provided') . "\nRating: {$rating}/5\n\nFeedback:\n{$message}";
$stmt = $conn->prepare('INSERT INTO feedback (user_id, subject, message, category, rating) VALUES (?, ?, ?, ?, ?)');
if (!$stmt) {
    error_log('Farmer feedback prepare failed: ' . $conn->error);
    $_SESSION['feedback_flash'] = ['type' => 'error', 'message' => 'We could not save your feedback right now. Please try again.'];
    header('Location: feedback.php?error=1#feedback-form');
    exit();
}

$stmt->bind_param('isssi', $userId, $subject, $storedMessage, $category, $rating);
$saved = $stmt->execute();
$stmt->close();
if (!$saved) {
    error_log('Farmer feedback insert failed: ' . $conn->error);
    $_SESSION['feedback_flash'] = ['type' => 'error', 'message' => 'We could not save your feedback right now. Please try again.'];
    header('Location: feedback.php?error=1#feedback-form');
    exit();
}

$_SESSION['feedback_flash'] = ['type' => 'success', 'message' => 'Your feedback was received by Meru Farmers Centre. Thank you for helping us improve.'];
header('Location: feedback.php?sent=1');
exit();
