<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Meru Farmers Centre | Home</title>
    <meta
      name="description"
      content="Meru Farmers Centre supports farmers with training, advisory services, quality inputs, and market access across Meru County."
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
      <section class="page-hero hero">
        <div class="container hero-content">
          <p class="eyebrow">Growing stronger farms</p>
          <h1>Empowering farmers with tools, knowledge, and opportunity.</h1>
          <p>
            We help farmers in Meru County build productive, resilient, and profitable farms through practical agronomy support, training, and access to essential resources.
          </p>

          <div class="hero-actions">
            <a class="primary-button" href="services.php">Explore Our Services</a>
            <a class="secondary-button" href="contact.php">Contact Us</a>
          </div>

          <div class="stats">
            <div class="stat-box">
              <strong>400+</strong>
              <span>Farmers supported</span>
            </div>
            <div class="stat-box">
              <strong>20+</strong>
              <span>Crop advisory programs</span>
            </div>
            <div class="stat-box">
              <strong>15+</strong>
              <span>Years of community impact</span>
            </div>
            <div class="stat-box">
              <strong>24/7</strong>
              <span>Farmer assistance</span>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section-heading">
            <p class="eyebrow accent">Why choose us</p>
            <h2>Solutions that move farming forward</h2>
          </div>

          <div class="card-grid four-col">
            <article class="feature-card">
              <div class="icon-circle">🌱</div>
              <h3>Practical Advice</h3>
              <p>Clear agronomic guidance tailored to your farm conditions and production goals.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">📦</div>
              <h3>Quality Inputs</h3>
              <p>Access to trusted seeds, fertilizers, and tools that support healthy yields.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">🤝</div>
              <h3>Farmer Community</h3>
              <p>Grow through training, mentorship, and collaboration with fellow producers.</p>
            </article>
            <article class="feature-card">
              <div class="icon-circle">📈</div>
              <h3>Market Growth</h3>
              <p>Better access to value chains and market opportunities for sustainable income.</p>
            </article>
          </div>
        </div>
      </section>

      <section class="section alt-bg">
        <div class="container two-column">
          <div>
            <p class="eyebrow accent">Our purpose</p>
            <h2>Helping farmers thrive with confidence and knowledge.</h2>
            <p>
              We believe agriculture is more than cultivation — it is a path to food security, financial stability, and local economic growth. Our work focuses on helping farmers make informed decisions that improve the quality and quantity of their produce while protecting their long-term productivity.
            </p>
            <p>
              Through community-driven programs, expert support, and local partnerships, we continue to create opportunities for rural families to prosper.
            </p>
          </div>

          <div class="image-card purpose-image">
            <div class="image-overlay">
              <span>Community, growth, and resilience</span>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="container">
          <div class="section-heading center">
            <p class="eyebrow accent">What we do</p>
            <h2>Core services for every stage of farming</h2>
          </div>

          <div class="card-grid three-col">
            <article class="service-card">
              <div class="service-icon">🌾</div>
              <h3>Crop Support</h3>
              <p>Guidance on crop planning, crop health, soil management, and seasonal best practices.</p>
            </article>
            <article class="service-card">
              <div class="service-icon">🧑‍🌾</div>
              <h3>Farmer Training</h3>
              <p>Hands-on workshops and training sessions designed to improve farm productivity and efficiency.</p>
            </article>
            <article class="service-card">
              <div class="service-icon">📦</div>
              <h3>Market Access</h3>
              <p>Linking farmers to buyers, local markets, and opportunities that strengthen farm income.</p>
            </article>
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
