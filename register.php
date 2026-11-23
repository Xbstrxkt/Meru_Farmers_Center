<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'farmer';
    $self_register_roles = ['farmer', 'trainer', 'driver', 'supplier'];

    $phone_digits = preg_replace('/\D/', '', $phone);
    $valid_phone = preg_match('/^\+?[0-9\s().-]+$/', $phone)
        && strlen($phone_digits) >= 7
        && strlen($phone_digits) <= 15;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$valid_phone || !in_array($role, $self_register_roles, true)) {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invalid Registration Details</title><link rel="stylesheet" href="style.css"></head><body><header style="padding:12px 20px;background:#2e7d32"><a href="index.php" style="display:flex;align-items:center;gap:10px;color:white;text-decoration:none;font-weight:700"><img src="Logo.png" alt="Meru Farmers Centre logo" style="width:54px;height:54px;object-fit:contain;border-radius:50%;background:white">Meru Farmers Centre</a></header><div class="form-panel"><h2>Check Your Details</h2><p>Enter a valid email address and phone number (7 to 15 digits).</p><p><a href="register.php">Return to registration</a></p></div></body></html>';
        exit();
    }

    $password = password_hash($password_input, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (full_name, email, phone, password, role, approval_status) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $full_name, $email, $phone, $password, $role);

    if ($stmt->execute()) {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registration Submitted | Meru Farmers Centre</title>
            <link rel="stylesheet" href="style.css">
            <style>
                body { display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; background: linear-gradient(180deg, #f4f8f5 0%, #edf5ee 100%); }
                .success-card {
                    width: min(520px, calc(100% - 24px));
                    background: #fff;
                    border-radius: 24px;
                    box-shadow: 0 20px 45px rgba(18, 76, 45, 0.12);
                    border: 1px solid rgba(18, 76, 45, 0.08);
                    padding: 32px 28px;
                    text-align: center;
                }
                .success-card h2 {
                    margin-bottom: 14px;
                    color: #124c2d;
                }
                .success-card p {
                    margin-bottom: 22px;
                    color: #5d6d61;
                }
                .success-card .btn {
                    width: 100%;
                }
                .success-card .secondary {
                    margin-top: 14px;
                    display: inline-block;
                    color: #124c2d;
                    font-weight: 700;
                }
            </style>
        </head>
        <body>
            <header style="position:absolute;top:0;left:0;right:0;padding:12px 20px;background:#2e7d32"><a href="index.php" style="display:flex;align-items:center;gap:10px;color:white;text-decoration:none;font-weight:700"><img src="Logo.png" alt="Meru Farmers Centre logo" style="width:54px;height:54px;object-fit:contain;border-radius:50%;background:white">Meru Farmers Centre</a></header>
            <div class="success-card">
                <h2>Registration Submitted</h2>
                <p>Thank you, <strong>' . htmlspecialchars($full_name) . '</strong>. Your account is awaiting admin approval. You can log in once it has been approved.</p>
                <a href="login.php" class="btn">Return to Login</a>
                <div><a href="index.php" class="secondary">Return Home</a></div>
            </div>
        </body>
        </html>';
    } else {
        echo '<header style="padding:12px 20px;background:#2e7d32"><a href="index.php" style="display:flex;align-items:center;gap:10px;color:white;text-decoration:none;font-weight:700"><img src="Logo.png" alt="Meru Farmers Centre logo" style="width:54px;height:54px;object-fit:contain;border-radius:50%;background:white">Meru Farmers Centre</a></header>';
        echo '<div style="text-align:center; margin-top:50px; font-family:sans-serif;">';
        echo '<h2 style="color:red;">Registration failed.</h2>';
        echo '<p>' . htmlspecialchars($stmt->error) . '</p>';
        echo '<p><a href="register.php">Try again</a></p>';
        echo '</div>';
    }

    $stmt->close();
    $conn->close();
}
?>
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main class="form-panel" style="max-width:520px;margin:32px auto;">
        <h1>Create an Account</h1>
        <form action="register.php" method="POST" id="registrationForm">
            <div class="field-row">
                <label>Full Name
                    <input type="text" name="full_name" placeholder="John Doe" autocomplete="name" required>
                </label>
            </div>
            <div class="field-row">
                <label>Email Address
                    <input type="email" name="email" placeholder="john@example.com" autocomplete="email" required>
                </label>
            </div>
            <div class="field-row">
                <label>Phone Number
                    <input type="tel" name="phone" id="phone" placeholder="+254 7xx xxx xxx" autocomplete="tel" title="Enter a valid phone number with 7 to 15 digits." required aria-describedby="phoneFeedback">
                    <small id="phoneFeedback" aria-live="polite" style="display:block;min-height:1.2em;color:#5d6d61;"></small>
                </label>
            </div>
            <div class="field-row">
                <label>Password
                    <input type="password" name="password" placeholder="Password" autocomplete="new-password" required>
                </label>
            </div>
            <div class="field-row">
                <label>Account Role
                    <select name="role" required>
                        <option value="farmer">Farmer</option>
                        <option value="trainer">Trainer</option>
                        <option value="driver">Driver</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </label>
            </div>
            <button type="submit" class="btn" data-submit-label="Creating account...">Register</button>
        </form>
        <p class="text-center" style="margin-top:16px;">Already have an account? <a href="login.php">Log in here</a></p>
    </main>
    <script>
        const registrationForm = document.getElementById('registrationForm');
        const phoneInput = document.getElementById('phone');
        const phoneFeedback = document.getElementById('phoneFeedback');

        phoneInput.addEventListener('input', () => {
            const phoneValue = phoneInput.value.trim();
            const digitCount = phoneValue.replace(/\D/g, '').length;
            const validFormat = /^\+?[0-9\s().-]+$/.test(phoneValue);
            const isValid = !phoneValue || (validFormat && digitCount >= 7 && digitCount <= 15);

            phoneInput.setCustomValidity(isValid ? '' : 'Enter a phone number containing 7 to 15 digits.');
            phoneFeedback.textContent = !phoneValue
                ? ''
                : isValid
                    ? 'Phone number looks valid.'
                    : 'Enter 7 to 15 digits; spaces and +, parentheses, dots, or hyphens are allowed.';
        });

        registrationForm.addEventListener('submit', () => {
            const submitButton = registrationForm.querySelector('[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.submitLabel;
        });
    </script>
</body>
</html>
<?php endif; ?>