<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help | Meru Farmers Centre</title>
    <meta name="description" content="Answers to common questions about Meru Farmers Centre accounts, bookings, orders, and payments.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <main>
        <section class="page-hero help-hero">
            <div class="container narrow">
                <p class="eyebrow">Farmer help desk</p>
                <h1>Good growing starts with a clear next step.</h1>
                <p class="help-intro">Find a quick answer, get back to your order or booking, or send a note to our team.</p>
            </div>
        </section>
        <section class="section">
            <div class="help-content">
                <nav class="help-quick-links" aria-label="Quick help links">
                    <a href="inventory.php"><span>Shop farm inputs</span><span aria-hidden="true">&rarr;</span></a>
                    <a href="bookings.php"><span>Book a service</span><span aria-hidden="true">&rarr;</span></a>
                    <a href="feedback.php#feedback-form"><span>Send feedback</span><span aria-hidden="true">&rarr;</span></a>
                </nav>
                <div class="section-heading">
                    <p class="eyebrow accent">Answers at a glance</p>
                    <h2>What can we help with?</h2>
                </div>
                <div class="help-list">
                    <details>
                        <summary>Why can’t I log in after registering?</summary>
                        <p>New accounts need approval from the centre. Once approved, sign in with the email address and password you registered with. The login page will tell you if your account is still pending or was not approved.</p>
                    </details>
                    <details>
                        <summary>How do I order farm inputs?</summary>
                        <p>Open the Catalog, choose an item and quantity, then submit the order. For eligible farmer accounts, an M-Pesa payment prompt is sent to the phone number provided or the number on your account.</p>
                        <p><a href="inventory.php">Open the Catalog</a></p>
                    </details>
                    <details>
                        <summary>How do I book an agricultural service?</summary>
                        <p>Open Bookings, choose a service and a date, and submit the form. You can include a different M-Pesa phone number if needed.</p>
                        <p><a href="bookings.php">Make a booking</a></p>
                    </details>
                    <details>
                        <summary>What should I do if a payment is pending or fails?</summary>
                        <p>For a pending request, approve the M-Pesa prompt on your phone and allow the status page to update. If it fails, start a new order or booking and check that the phone number is correct. Contact us if money was deducted but the request is not confirmed.</p>
                    </details>
                    <details>
                        <summary>How do I send feedback or ask for help?</summary>
                        <p>Use the contact form to send a message to the centre. Include your contact details and, for payment issues, the receipt number and a short description of what happened.</p>
                        <p><a href="feedback.php#feedback-form">Send feedback</a> or <a href="contact.php">contact the centre</a>.</p>
                    </details>
                </div>
                <div class="help-actions">
                    <a class="primary-button" href="contact.php">Contact the centre</a>
                    <a class="secondary-button" href="index.php">Back to home</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>