<?php
const MFC_USER_ROLES = [
	'farmer',
	'admin',
	'finance_manager',
	'inventory_manager',
	'supplier',
	'dispatch_manager',
	'driver',
	'service_manager',
	'trainer',
];

function mfcRequireRoles(mysqli $conn, array $allowedRoles): array
{
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
	if (!$userId || $userId < 1) {
		header('Location: login.php');
		exit();
	}

	$stmt = $conn->prepare('SELECT user_id, full_name, role, approval_status FROM users WHERE user_id = ? LIMIT 1');
	if (!$stmt) {
		http_response_code(500);
		exit('Account authorization is temporarily unavailable.');
	}
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$user = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$user || $user['approval_status'] !== 'approved' || !in_array($user['role'], MFC_USER_ROLES, true)) {
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$cookie = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
		}
		session_destroy();
		header('Location: login.php?error=approval_required');
		exit();
	}

	$_SESSION['role'] = $user['role'];
	$_SESSION['full_name'] = $user['full_name'];

	if ($allowedRoles !== [] && !in_array($user['role'], $allowedRoles, true)) {
		http_response_code(403);
		exit('You do not have permission to access this page.');
	}

	return $user;
}

function mfcRequirePost(): void
{
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		http_response_code(405);
		header('Allow: POST');
		exit('Method not allowed.');
	}
}
