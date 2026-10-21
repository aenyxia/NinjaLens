<?php
// index.php — NinjaLens Landing Page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NinjaLens — Camera & Filming Equipment Rental</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Noise texture overlay */
 

 

    /* Animated hero number */
    .hero-counter {
      display: flex;
      gap: 2.5rem;
      margin-top: 3rem;
      flex-wrap: wrap;
    }
    .counter-item { text-align: left; }
    .counter-num {
      font-size: 2rem;
      font-weight: 800;
      color: var(--white);
      line-height: 1;
    }
    .counter-num span { color: var(--red); }
    .counter-desc {
      font-size: 0.75rem;
      color: var(--gray-mid);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-top: 0.15rem;
    }

    /* Steps section */
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 0;
      position: relative;
    }
    .steps-grid::before {
      content: '';
      position: absolute;
      top: 40px;
      left: 10%;
      right: 10%;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--border), var(--border), transparent);
      z-index: 0;
    }
    .step-item {
      padding: 2rem 1.5rem;
      text-align: center;
      position: relative;
      z-index: 1;
    }
    .step-num {
      width: 48px; height: 48px;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--red);
      margin: 0 auto 1.25rem;
    }
    .step-item h3 { font-size: 1rem; margin-bottom: 0.5rem; }
    .step-item p  { font-size: 0.8rem; }

    /* CTA section */
    .cta-section {
      background: radial-gradient(ellipse at 50% 0%, rgba(255,59,59,0.15), transparent 70%);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      text-align: center;
      padding: 5rem 0;
    }
    .cta-section h2 { margin-bottom: 1rem; }
    .cta-section p  { margin-bottom: 2rem; }

    /* Marquee */
    .marquee-section {
      overflow: hidden;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      padding: 1rem 0;
      background: var(--bg-secondary);
    }
    .marquee-track {
      display: flex;
      gap: 3rem;
      animation: marquee 20s linear infinite;
      white-space: nowrap;
    }
    .marquee-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--gray-dark);
      flex-shrink: 0;
    }
    .marquee-item .dot { color: var(--red); }
    @keyframes marquee {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }

    /* Category pills */
    .cat-pills {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
    }
    .cat-pill {
      padding: 0.35rem 1rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.03);
      color: var(--gray-light);
      cursor: pointer;
      transition: all var(--transition);
    }
    .cat-pill:hover, .cat-pill.active {
      border-color: var(--red);
      color: var(--red);
      background: rgba(255,59,59,0.08);
    }
  </style>
</head>
<body>

<!-- ── NAVIGATION ── -->
<nav class="navbar">
  <div class="navbar-inner">
	<a href="index.php" class="navbar-logo">
     <img src="images/ninjalens_logo.png" alt="Ninja Lens Logo" style="height:60px; width:80px; vertical-align:middle;"  />
     Ninja<span class="accent">Lens</span>
    </a>
     

    <div class="navbar-links" id="navLinks">
      <a href="#equipment" class="active">Equipment</a>
      <a href="#how-it-works">How It Works</a>
      <a href="#about">About</a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn btn-primary btn-sm">My Dashboard</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-outline btn-sm">Log In</a>
        <a href="login.php?tab=register" class="btn btn-primary btn-sm" style="color:white;">Get Started</a>
      <?php endif; ?>
	  
    </div>
	
    <button class="hamburger" id="hamburger" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ── HERO ── -->
<!-- ── HERO ── -->
<section class="hero section">
  <table class="hero-table">
    <tr>
      <!-- LEFT COLUMN (X-Axis: 65%) -->
      <td class="col-hero-left">
        <div class="container"> <!-- Restores your left margin -->
          <div class="hero-content">
            <h1>
              Professional Setup,<br>
              <span class="highlight">Zero Compromise.</span>
            </h1>
            <p>Rent cinema-grade cameras, lenses, drones, and lighting.</p>
            
            <div class="hero-actions">
              <a href="<?= isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php?tab=register' ?>" class="btn btn-primary">Start Renting</a>
              <a href="#equipment" class="btn btn-outline">Browse Equipment</a>
            </div>

            <div class="hero-counter">
              <!-- ... your counter items ... -->
            </div>
          </div>
        </div>
      </td>

      <!-- RIGHT COLUMN (X-Axis: 35%) -->
      <td class="col-hero-right">
        <div class="hero-mascot">
          <img src="images/ninja_lens_mascot.png" alt="NinjaLens Mascot" class="mascot-img" />
        </div>
      </td>
    </tr>
  </table>
</section>

<!-- ── MARQUEE ── -->
<div class="marquee-section" aria-hidden="true">
  <div class="marquee-track">
    <!-- Duplicated for infinite loop -->
    <span class="marquee-item"><span class="dot">●</span> Sony A7 III</span>
    <span class="marquee-item"><span class="dot">●</span> Canon EOS R5</span>
    <span class="marquee-item"><span class="dot">●</span> DJI Ronin-S</span>
    <span class="marquee-item"><span class="dot">●</span> Sigma Art Lenses</span>
    <span class="marquee-item"><span class="dot">●</span> Aputure Lighting</span>
    <span class="marquee-item"><span class="dot">●</span> DJI Mavic 3 Pro</span>
    <span class="marquee-item"><span class="dot">●</span> Rode Microphones</span>
    <span class="marquee-item"><span class="dot">●</span> Atomos Recorders</span>
    <span class="marquee-item"><span class="dot">●</span> Sony A7 III</span>
    <span class="marquee-item"><span class="dot">●</span> Canon EOS R5</span>
    <span class="marquee-item"><span class="dot">●</span> DJI Ronin-S</span>
    <span class="marquee-item"><span class="dot">●</span> Sigma Art Lenses</span>
    <span class="marquee-item"><span class="dot">●</span> Aputure Lighting</span>
    <span class="marquee-item"><span class="dot">●</span> DJI Mavic 3 Pro</span>
    <span class="marquee-item"><span class="dot">●</span> Rode Microphones</span>
    <span class="marquee-item"><span class="dot">●</span> Atomos Recorders</span>
  </div>
</div>

<!-- ── FEATURES ── -->
<section class="section">
  <div class="container">
    <div class="section-header centered">
      <span class="section-label">Why NinjaLens</span>
      <h2>Everything a filmmaker needs</h2>
      <p>From single-day shoots to multi-week productions, we've got the gear and the process.</p>
    </div>

    <div class="features-grid">
      <div class="glass-card feature-card">
        <div class="feature-icon">🔍</div>
        <h3>Browse Equipment</h3>
        <p>Explore our full catalog of cameras, lenses, drones, audio gear, and accessories.</p>
      </div>
      <div class="glass-card feature-card">
        <div class="feature-icon">🎬</div>
        <h3>Rent with Ease</h3>
        <p>Select your dates, confirm your booking, and pay securely. No paperwork, no waiting in line.</p>
      </div>
      <div class="glass-card feature-card">
        <div class="feature-icon">📦</div>
        <h3>Track Rentals</h3>
        <p>Monitor your active rentals, return deadlines, and payment history right from your dashboard.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── EQUIPMENT SHOWCASE ── -->
<section class="section" id="equipment" style="padding-top:0;">
  <div class="container">
    <div class="section-header">
      <span class="section-label">Featured Gear</span>
      <h2>Available Equipment</h2>
      <p>Browse our most popular items. Log in to rent any piece of gear.</p>
    </div>

   <?php
// Fetch featured equipment from database
require_once 'config.php';
$sql = "SELECT * FROM equipment WHERE status = 'Available' ORDER BY created_at DESC LIMIT 8";
$result = mysqli_query($conn, $sql);

// Fallback Unsplash images per category (used when no uploaded image exists)
$cat_fallbacks = [
    'Camera Body' => 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=600&q=80',
    'Lens'        => 'https://images.unsplash.com/photo-1495707902641-75cac588d2e9?w=600&q=80',
    'Drone'       => 'https://images.unsplash.com/photo-1579829366248-204fe8413f31?w=600&q=80',
    'Stabilizer'  => 'https://images.unsplash.com/photo-1588702547923-7093a6c3ba33?w=600&q=80',
    'Lighting'    => 'https://images.unsplash.com/photo-1542567455-cd733f23fbb1?w=600&q=80',
    'Audio'       => 'https://images.unsplash.com/photo-1598550476439-6847785fcea6?w=600&q=80',
    'Tripod'      => 'https://images.unsplash.com/photo-1617396900799-f4ec2b43c7d3?w=600&q=80',
    'Monitor/Rec' => 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?w=600&q=80',
    'Accessories' => 'https://images.unsplash.com/photo-1452780212940-6f5c0d14d848?w=600&q=80',
];

function get_equip_image($eq, $fallbacks) {
    if (!empty($eq['image']) && file_exists(__DIR__ . '/uploads/equipment/' . $eq['image'])) {
        return 'uploads/equipment/' . htmlspecialchars($eq['image']);
    }
    return $fallbacks[$eq['category']]
        ?? 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=600&q=80';
}
?>

    <div class="equipment-grid">
      <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($eq = mysqli_fetch_assoc($result)): ?>
          <?php
            $icon = $cat_icons[$eq['category']] ?? '📦';
            $status_class = strtolower(str_replace(' ', '-', $eq['status']));
          ?>
			<div class="glass-card equip-card">
				<div class="equip-card-img">
					<img
						src="<?= get_equip_image($eq, $cat_fallbacks) ?>"
						alt="<?= htmlspecialchars($eq['name']) ?>"
						loading="lazy"
					>
				</div>
            <div class="equip-body">
              <div class="equip-name"><?= htmlspecialchars($eq['name']) ?></div>
              <div class="equip-category"><?= htmlspecialchars($eq['category']) ?></div>
              <div class="equip-rate">
                ₱<?= number_format($eq['rental_rate'], 2) ?>
                <span>/ day</span>
              </div>
              <?php if ($eq['description']): ?>
                <p style="font-size:0.8rem;line-height:1.5;color:var(--gray-mid);margin-bottom:0.5rem;">
                  <?= htmlspecialchars(substr($eq['description'], 0, 70)) ?>...
                </p>
              <?php endif; ?>
              <div class="equip-footer">
                <span class="badge badge-<?= $status_class ?>">
                  <?= htmlspecialchars($eq['status']) ?>
                </span>
                <a href="login.php" class="btn btn-primary btn-sm">Rent Now</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-icon">📷</div>
          <h3>No equipment available at the moment</h3>
        </div>
      <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:2.5rem;">
      <a href="login.php" class="btn btn-outline">View All Equipment →</a>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="section" id="how-it-works" style="background:var(--bg-secondary);">
  <div class="container">
    <div class="section-header centered">
      <span class="section-label">Simple Process</span>
      <h2>How It Works</h2>
      <p>Get your gear in four easy steps.</p>
    </div>

    <div class="steps-grid">
      <div class="step-item">
        <div class="step-num">01</div>
        <h3>Create Account</h3>
        <p>Sign up for free in under a minute. No ID required upfront.</p>
      </div>
      <div class="step-item">
        <div class="step-num">02</div>
        <h3>Browse & Select</h3>
        <p>Find the gear you need and choose your rental dates.</p>
      </div>
      <div class="step-item">
        <div class="step-num">03</div>
        <h3>Pay Securely</h3>
        <p>Complete your payment — cash, card, or online transfer.</p>
      </div>
      <div class="step-item">
        <div class="step-num">04</div>
        <h3>Shoot & Return</h3>
        <p>Pick up your gear, do your thing, and return it on time.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section" id="about">
  <div class="container">
    <span class="section-label">Ready to Film?</span>
    <h2>Stop Buying. Start Renting.</h2>
    <p>Access professional gear at a fraction of the cost. Perfect for filmmakers, content creators, and photographers.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
      <a href="login.php?tab=register" class="btn btn-primary">Create Free Account</a>
      <a href="login.php" class="btn btn-outline">Sign In</a>
    </div>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer>
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="navbar-logo" style="margin-bottom:0.75rem;">
	 <img src="images/ninjalens_logo.png" alt="Ninja Lens Logo" 		style="height:50px; width:70px; vertical-align:middle;"  />
          Ninja<span class="accent">Lens</span>
        </div>
        <p>Professional camera and filming equipment rental for creators who take their craft seriously.</p>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#equipment">Equipment</a></li>
          <li><a href="#how-it-works">How It Works</a></li>
          <li><a href="login.php">Login</a></li>
          <li><a href="login.php?tab=register">Register</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <ul>
          <li><a href="#">📍 Cebu City, Philippines</a></li>
          <li><a href="#">📞 +63 917 000 0000</a></li>
          <li><a href="#">✉️ hello@ninjalens.com</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2025 NinjaLens. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
// ── Mobile Navigation Toggle ──
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');

hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
});

// ── Smooth scroll offset for sticky nav ──
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      window.scrollTo({ top: target.offsetTop - 70, behavior: 'smooth' });
    }
  });
});

// ── Scroll-triggered fade-in for cards ──
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.glass-card, .step-item, .counter-item').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
  observer.observe(el);
});
</script>

</body>
</html>
