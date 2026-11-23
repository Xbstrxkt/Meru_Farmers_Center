<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$contactFlash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);
if (empty($_SESSION['contact_csrf'])) {
  $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contact Us | Meru Farmers Centre</title>
    <meta
      name="description"
      content="Contact Meru Farmers Centre for farm support, market linkages, training, and agricultural advisory services."
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <?php include 'navbar.php'; ?>

    <main>
      <section class="page-hero page-hero-small contact-hero">
        <div class="container narrow">
          <p class="eyebrow">Get in touch</p>
          <h1>We’re here to support your farming journey.</h1>
        </div>
      </section>

      <section class="section">
        <div class="container contact-layout">
          <div class="contact-info">
            <p class="eyebrow accent">Contact Details</p>
            <h2>Talk to our team</h2>

            <div class="contact-item">
              <span class="contact-icon">📍</span>
              <div>
                <h3>Location</h3>
                <p>Meru County, Kenya</p>
              </div>
            </div>

            <div class="contact-item">
              <span class="contact-icon">📞</span>
              <div>
                <h3>Phone</h3>
                <p>+254 700 000 000</p>
              </div>
            </div>

            <div class="contact-item">
              <span class="contact-icon">✉️</span>
              <div>
                <h3>Email</h3>
                <p>hello@merufarmerscentre.co.ke</p>
              </div>
            </div>

            <div class="contact-item">
              <span class="contact-icon">🕒</span>
              <div>
                <h3>Hours</h3>
                <p>Mon - Sat: 8:00 AM - 5:00 PM</p>
              </div>
            </div>
          </div>

          <div class="form-panel">
            <?php if ($contactFlash && $contactFlash['type'] === 'error'): ?>
              <div class="alert alert-error" role="alert" style="padding:12px;margin-bottom:16px;border:1px solid #efcaca;background:#fff0f0;color:#9b1c1c;">
                <?php echo htmlspecialchars($contactFlash['message']); ?>
              </div>
            <?php endif; ?>
            <form id="contact-form" class="contact-form" action="contact_handler.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['contact_csrf']); ?>">
              <div class="field-row">
                <label>
                  Full Name
                  <input type="text" name="name" placeholder="Your full name" required />
                </label>
              </div>

              <div class="field-row">
                <label>
                  Email Address
                  <input type="email" name="email" placeholder="you@example.com" required />
                </label>
              </div>

              <div class="field-row">
                <label>
                  Phone Number
                  <input type="tel" name="phone" placeholder="+254 7xx xxx xxx" />
                </label>
              </div>

              <div class="field-row">
                <label>
                  Subject / topic
                  <input type="text" name="subject" maxlength="150" placeholder="Question, support request, visit, or callback" required />
                </label>
              </div>

              <div class="field-row">
                <label>
                  Message
                  <textarea name="message" rows="5" maxlength="10000" placeholder="Tell us how our team can help..." required></textarea>
                </label>
              </div>

              <button type="submit" class="primary-button">Send Message</button>
            </form>
            <p class="text-center" style="margin-top:16px;">Want to rate a service or share a suggestion? <a href="feedback.php">Send farmer feedback</a></p>
          </div>
        </div>
      </section>
    </main>

    <?php if ($contactFlash && $contactFlash['type'] === 'success'): ?>
      <dialog id="contactSuccess" aria-labelledby="contactSuccessTitle" style="max-width:480px;width:calc(100% - 32px);border:0;border-radius:12px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.24);">
        <h2 id="contactSuccessTitle">Message received</h2>
        <p><?php echo htmlspecialchars($contactFlash['message']); ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;">
          <button type="button" class="secondary-button" id="contactSuccessClose">Close</button>
          <button type="button" class="primary-button" id="contactAnother">Send another message</button>
        </div>
      </dialog>
      <script>
        const contactSuccess = document.getElementById('contactSuccess');
        contactSuccess.showModal();
        document.getElementById('contactSuccessClose').addEventListener('click', () => contactSuccess.close());
        document.getElementById('contactAnother').addEventListener('click', () => {
          contactSuccess.close();
          document.getElementById('contact-form').reset();
          document.querySelector('#contact-form [name="name"]').focus();
        });
      </script>
    <?php endif; ?>

    <footer class="site-footer">
      <div class="container footer-wrap">
        <div>
          <a class="brand footer-brand" href="index.php">
            <img class="brand-mark" src="Logo.png" alt="Meru Farmers Centre logo" />
            <span>Meru Farmers Centre</span>
          </a>
          <p>Empowering farmers with knowledge, support, and opportunity.</p>
        </div>

        <div>
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="services.php">Services</a></li>
            <li><a href="contact.php">Contact Us</a></li>
          </ul>
        </div>

        <div>
          <h4>Contact</h4>
          <ul>
            <li>Meru County, Kenya</li>
            <li>+254 700 000 000</li>
            <li>hello@merufarmerscentre.co.ke</li>
          </ul>
        </div>
      </div>
      <div class="container copyright">
        <p>© <span id="year"></span> Meru Farmers Centre. All rights reserved.</p>
      </div>
    </footer>

    <script src="script.js"></script>
  </body>
</html>