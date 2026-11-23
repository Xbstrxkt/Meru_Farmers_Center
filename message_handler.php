<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';

$currentUser = mfcRequireRoles($conn, ['admin', 'service_manager']);
$role = $currentUser['role'];
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
if (empty($_SESSION['message_csrf']) || !hash_equals($_SESSION['message_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid request token.');
}

$messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$category = $_POST['category'] ?? '';
$status = $_POST['review_status'] ?? '';
$reply = trim($_POST['staff_reply'] ?? '');
if ($messageId < 1 || !in_array($category, ['contact', 'feedback'], true)
    || !in_array($status, ['new', 'reviewed', 'resolved'], true) || strlen($reply) > 5000) {
    http_response_code(400);
    exit('Invalid message update.');
}

$exists = $conn->prepare('SELECT feedback_id FROM feedback WHERE feedback_id = ? AND category = ?');
$exists->bind_param('is', $messageId, $category);
$exists->execute();
$messageExists = (bool) $exists->get_result()->fetch_assoc();
$exists->close();
if (!$messageExists) {
    http_response_code(404);
    exit('Message not found.');
}

$stmt = $conn->prepare('UPDATE feedback SET review_status = ?, reviewed_by = IF(? = \'new\', NULL, ?), reviewed_at = IF(? = \'new\', NULL, NOW()), resolved_at = IF(? = \'resolved\', NOW(), NULL), staff_reply = IF(? = \'\', staff_reply, ?), replied_by = IF(? = \'\', replied_by, ?), replied_at = IF(? = \'\', replied_at, NOW()) WHERE feedback_id = ? AND category = ?');
if (!$stmt) {
    error_log('Message update prepare failed: ' . $conn->error);
    http_response_code(500);
    exit('Message update is temporarily unavailable.');
}
$userId = (int) $currentUser['user_id'];
$stmt->bind_param('ssisssssisis', $status, $status, $userId, $status, $status, $reply, $reply, $reply, $userId, $reply, $messageId, $category);
$updated = $stmt->execute();
$stmt->close();
if (!$updated) {
    http_response_code(500);
    exit('Message update failed.');
}

$destination = $category === 'contact' ? 'contact_queue' : 'feedback_queue';
header('Location: ' . ($role === 'admin' ? 'admin.php#' : 'staff.php#') . $destination);
exit();
