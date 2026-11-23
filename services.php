<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Our Services | Meru Farmers Centre</title>
    <meta
      name="description"
      content="Explore the services offered by Meru Farmers Centre, including advisory support, farm inputs, training, and market access."
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
      <section class="page-hero page-hero-small services-hero">
        <div class="container narrow">
          <p class="eyebrow">What We Offer</p>
          <h1>Practical services that help farmers succeed.</h1>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section-heading center">
            <p class="eyebrow accent">Our Solutions</p>
            <h2>Support designed around real farm needs</h2>
          </div>

          <div class="card-grid three-col">
            <article class="service-card">
              <div class="service-icon">🌾</div>
              <h3>Crop Advisory</h3>
              <p>Seasonal guidance on crop selection, planting techniques, pest and disease control, and soil management.</p>
            </article>

            <article class="service-card">
              <div class="service-icon">🚜</div>
              <h3>Farm Inputs</h3>
              <p>Access to quality seeds, fertilizers, pesticides, and farm essentials suited to your specific crops and region.</p>
            </article>

            <article class="service-card">
              <div class="service-icon">🎓</div>
              <h3>Training & Workshops</h3>
              <p>Hands-on learning for farmers covering modern agronomy, irrigation, record keeping, and business planning.</p>
            </article>

            <article class="service-card">
              <div class="service-icon">📦</div>
              <h3>Market Linkages</h3>
              <p>Connections to buyers, produce aggregators, and local market opportunities that help improve farm income.</p>
            </article>

            <article class="service-card">
              <div class="service-icon">💧</div>
              <h3>Irrigation Support</h3>
              <p>Advice on water management and efficient irrigation practices to improve yields and conserve resources.</p>
            </article>

            <article class="service-card">
              <div class="service-icon">📊</div>
              <h3>Farm Business Guidance</h3>
              <p>Support with budgeting, crop planning, pricing, and smart farm decision-making for sustainable profits.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section alt-bg">
        <div class="container process-grid">
          <div>
            <p class="eyebrow accent">How It Works</p>
            <h2>Simple support, measurable results.</h2>
          </div>

          <div class="steps">
            <div class="step-item">
              <span>01</span>
              <div>
                <h3>Consult</h3>
                <p>We begin by understanding your farm goals, challenges, and available resources.</p>
              </div>
            </div>
            <div class="step-item">
              <span>02</span>
              <div>
                <h3>Plan</h3>
                <p>We recommend practical solutions tailored to your crops, farm size, and season.</p>
              </div>
            </div>
            <div class="step-item">
              <span>03</span>
              <div>
                <h3>Grow</h3>
                <p>With guidance and support, you can strengthen production and improve earnings.</p>
              </div>
            </div>
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
