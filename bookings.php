<?php
require_once 'db.php';
require_once 'auth_guard.php';
$currentUser = mfcRequireRoles($conn, ['farmer', 'admin', 'service_manager']);
if ($currentUser['role'] !== 'farmer') {
    header('Location: staff.php#services');
    exit();
}
if (empty($_SESSION['commerce_csrf'])) {
    $_SESSION['commerce_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Service | Meru Farmers Centre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="form-panel" style="max-width: 560px;">
        <h2>Book an Agricultural Service</h2>
        <form action="booking_handler.php" method="POST" id="bookingForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['commerce_csrf']); ?>">
            <div class="field-row">
                <label>Select Service
                    <select name="service_type" required>
                        <option value="Soil Testing & Analysis">Soil Testing & Analysis | KSh 2,500</option>
                        <option value="Machinery & Tractor Hire">Machinery & Tractor Hire | KSh 4,500</option>
                        <option value="Agronomy Advisory Session">Agronomy Advisory Session | KSh 1,500</option>
                        <option value="Crop Protection Consultancy">Crop Protection Consultancy | KSh 2,000</option>
                        <option value="Training & Workshops">Training &amp; Workshops | KSh 2,500</option>
                    </select>
                </label>
            </div>

            <div class="field-row">
                <label>Preferred Booking Date
                    <input type="date" name="booking_date" id="bookingDate" required>
                </label>
            </div>

            <div class="field-row">
                <label>M-Pesa phone number
                    <input type="tel" name="payment_phone" inputmode="tel" placeholder="Use registered number" pattern="[+0-9 ()-]{9,16}" title="Enter a valid Kenyan mobile number, or leave blank to use your registered number.">
                </label>
            </div>

            <div class="field-row">
                <label>Payment method
                    <select name="payment_method" required>
                        <option value="mpesa">M-Pesa</option>
                        <option value="cash">Cash at appointment</option>
                    </select>
                </label>
            </div>

            <div class="field-row">
                <label>Additional Details / Requirements
                    <textarea name="notes" rows="4" placeholder="Mention your location in Meru or specific farm needs..."></textarea>
                </label>
            </div>

            <button type="submit" class="btn" data-submit-label="Sending M-Pesa prompt...">Pay and Book</button>
        </form>
        <p class="text-center" style="margin-top: 16px;"><a href="index.php">Back to Home</a></p>
    </div>
    <script>
        const bookingDate = document.getElementById('bookingDate');
        const today = new Date();
        const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 10);
        bookingDate.min = localToday;

        document.getElementById('bookingForm').addEventListener('submit', (event) => {
            const submitButton = event.currentTarget.querySelector('[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.submitLabel;
        });
    </script>
</body>
</html>