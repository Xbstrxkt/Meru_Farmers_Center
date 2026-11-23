<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us | Meru Farmers Centre</title>
    <meta
      name="description"
      content="Learn about the mission, values, and impact of Meru Farmers Centre in supporting farmers across Meru County."
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
      <section class="page-hero page-hero-small about-hero">
        <div class="container narrow">
          <p class="eyebrow">Our Story</p>
          <h1>Growing resilient farms and stronger communities.</h1>
        </div>
      </section>

      <section class="section">
        <div class="container two-column">
          <div>
            <p class="eyebrow accent">Who We Are</p>
            <h2>Meru Farmers Centre is built for farmers, by people who understand the land.</h2>
            <p>
              We are a community-centered agricultural support hub serving farmers in Meru County and surrounding regions. Our work brings together practical agronomy, business support, market access, and community mentorship to help farmers increase productivity and improve livelihoods.
            </p>
            <p>
              From smallholder growers to emerging commercial farmers, we provide the tools, knowledge, and networks needed to farm more sustainably and profitably.
            </p>
          </div>

          <div class="info-panel">
            <h3>Our Mission</h3>
            <p>
              To empower farmers with knowledge, quality inputs, and market opportunities that improve food security, farm profitability, and rural prosperity.
            </p>
          </div>
        </div>
      </section>

      <section class="section alt-bg">
        <div class="container">
          <div class="section-heading center">
            <p class="eyebrow accent">Our Values</p>
            <h2>What guides everything we do</h2>
          </div>

          <div class="card-grid four-col">
            <article class="feature-card">
              <div class="icon-circle">🌱</div>
              <h3>Sustainable Farming</h3>
              <p>We promote growing methods that protect soil health, water, and long-term productivity.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">🤝</div>
              <h3>Community First</h3>
              <p>We work alongside farmers and local leaders to strengthen the entire agricultural ecosystem.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">📈</div>
              <h3>Business Growth</h3>
              <p>We connect farmers to practical solutions that improve efficiency, quality, and returns.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">🎓</div>
              <h3>Knowledge Sharing</h3>
              <p>We equip farmers with training, field support, and easy-to-apply advice for daily decisions.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container two-column reverse">
          <div class="image-card large exist-image">
            <div class="image-overlay">
              <span>Helping farmers thrive</span>
            </div>
          </div>

          <div>
            <p class="eyebrow accent">Why We Exist</p>
            <h2>Farming should be rewarding, resilient, and future-ready.</h2>
            <p>
              Many farmers face challenges such as limited access to information, poor-quality inputs, volatile market prices, and inadequate support structures. We address these issues through tailored advisory services, training events, and direct access to quality farm products and networks.
            </p>
            <p>
              Our goal is to ensure that farmers can grow confidently, earn sustainably, and contribute meaningfully to the local food economy.
            </p>
          </div>
        </div>
      </section>
    </main>

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
