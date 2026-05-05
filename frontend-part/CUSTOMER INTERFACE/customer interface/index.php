<?php
/**
 * Main Landing Page - Cleckhuddesfax Online Mart
 * This is the entry point of the website.
 */
include 'header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container hero-container">
        <div class="hero-content">
            <h1 class="hero-title">
                FRESH<br>
                LOCAL<br>
                <span class="text-orange">GOODS.</span>
            </h1>
            <p class="hero-text">
                Support the heart of Cleckhuddesfax. High-quality produce delivered from our town's finest independent
                traders straight to your door.
            </p>
            <div class="hero-buttons">
                <a href="category.php" class="btn btn-primary">BROWSE ALL SHOPS</a>
                <a href="about.php" class="btn btn-outline">OUR STORY</a>
            </div>
        </div>
        <div class="hero-image-wrapper">
            <div class="image-container">
                <img src="assets/images/hero.png" alt="Fresh Local Produce">
            </div>
        </div>
    </div>
</section>

<!-- Browse Traders Section -->
<section class="traders-section">
    <div class="container">
        <div class="section-header">
            <div class="header-left">
                <span class="motto">EXPLORE</span>
                <h2>BROWSE TRADERS</h2>
            </div>
            <a href="category.php" class="view-all-link">VIEW ALL CATEGORIES <span>→</span></a>
        </div>

        <div class="traders-grid">
            <!-- Card 1 -->
            <a href="butcher.php" class="trader-card" style="display: block;">
                <img src="assets/images/homeimg1.png" alt="Butchers">
                <div class="card-overlay">
                    <h3>BUTCHERS</h3>
                    <p>FRESH CUTS & MEATS</p>
                </div>
            </a>
            <!-- Card 2 -->
            <a href="greengrocers.php" class="trader-card" style="display: block;">
                <img src="assets/images/homeimg2.png" alt="Greengrocers">
                <div class="card-overlay">
                    <h3>GREENGROCERS</h3>
                    <p>FRUIT & VEGETABLES</p>
                </div>
            </a>
            <!-- Card 3 -->
            <a href="fishmongers.php" class="trader-card" style="display: block;">
                <img src="assets/images/homeimg3.png" alt="Fishmongers">
                <div class="card-overlay">
                    <h3>FISHMONGERS</h3>
                    <p>DAILY CATCH</p>
                </div>
            </a>
            <!-- Card 4 -->
            <a href="bakery.php" class="trader-card" style="display: block;">
                <img src="assets/images/homeimg4.png" alt="Bakeries">
                <div class="card-overlay">
                    <h3>BAKERIES</h3>
                    <p>BREADS & PASTRIES</p>
                </div>
            </a>
            <!-- Card 5 -->
            <a href="delicatessen.php" class="trader-card" style="display: block;">
                <img src="assets/images/homeimg5.png" alt="Delicatessens">
                <div class="card-overlay">
                    <h3>DELICATESSENS</h3>
                    <p>SPECIALTY CHEESES</p>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- Process Section -->
<section class="process-section">
    <div class="container container-narrow">
        <div class="section-header center">
            <span class="motto align-center">SIMPLE PROCESS</span>
            <h2 class="text-orange">HOW IT WORKS</h2>
        </div>
        <div class="process-grid">
            <!-- Step 1 -->
            <div class="process-card border-orange">
                <div class="step-number bg-orange">1</div>
                <h4>CHOOSE SHOP</h4>
                <p>Select your favorite local trader from our curated marketplace.</p>
            </div>
            <!-- Step 2 -->
            <div class="process-card border-blue-light bg-blue-lightest">
                <div class="step-number bg-blue-light">2</div>
                <h4>FILL BASKET</h4>
                <p>Add fresh products to your cart. One checkout for multiple shops.</p>
            </div>
            <!-- Step 3 -->
            <div class="process-card border-navy">
                <div class="step-number bg-navy">3</div>
                <h4>FAST DELIVERY</h4>
                <p>We collect and deliver everything in a single carbon-neutral drop.</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section">
    <div class="container center cta-content">
        <h2>READY TO SUPPORT LOCAL?</h2>
        <div style="display: inline-block; padding: 0.8rem 1.5rem; font-weight: 800; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; background-color: #FFFFFF; color: #ED5C2B; cursor: default;">START SHOPPING</div>
    </div>
</section>

<?php include 'footer.php'; ?>