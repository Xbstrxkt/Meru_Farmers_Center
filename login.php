<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT user_id, full_name, password, role, approval_status FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            if ($user['approval_status'] !== 'approved') {
                $approvalError = $user['approval_status'] === 'rejected' ? 'rejected' : 'pending';
                header('Location: login.php?error=' . $approvalError);
                exit();
            } elseif (!in_array($user['role'], MFC_USER_ROLES, true)) {
                header('Location: login.php?error=invalid_role');
                exit();
            } else {
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                $redirect_url = match ($user['role']) {
                    'farmer' => 'dashboard.php',
                    'admin' => 'admin.php',
                    default => 'staff.php',
                };
                header('Location: ' . $redirect_url);
                exit();
            }
        } else {
            header('Location: login.php?error=invalid');
            exit();
        }
    } else {
        header('Location: login.php?error=not_found');
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="form-panel" style="max-width:440px;margin:48px auto;">
        <h1>Login to Your Account</h1>
        <div id="loginError" class="alert alert-error" role="alert" style="display:none;color:#9b1c1c;background:#fff0f0;border:1px solid #efcaca;padding:12px;margin-bottom:16px;border-radius:8px;"></div>
        <form action="login.php" method="POST">
            <div class="field-row">
                <label>Email Address
                    <input type="email" name="email" placeholder="john@example.com" autocomplete="email" required>
                </label>
            </div>
            <div class="field-row">
                <label>Password
                    <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                </label>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <p class="text-center" style="margin-top:16px;">Don't have an account? <a href="register.php">Register here</a></p>
    </main>
    <script>
        const loginError = document.getElementById('loginError');
        const loginErrorMessages = {
            invalid: 'Incorrect password. Please try again.',
            not_found: 'No account was found with that email address.',
            pending: 'Your account is awaiting admin approval. Please try again after it has been approved.',
            rejected: 'Your account was not approved. Please contact the centre for assistance.',
            invalid_role: 'This account has an unsupported role. Please contact the centre.',
            approval_required: 'Your account is not approved for access. Please contact the centre.'
        };
        const loginErrorCode = new URLSearchParams(window.location.search).get('error');
        if (loginErrorMessages[loginErrorCode]) {
            loginError.textContent = loginErrorMessages[loginErrorCode];
            loginError.style.display = 'block';
        }
    </script>
</body>
</html>
