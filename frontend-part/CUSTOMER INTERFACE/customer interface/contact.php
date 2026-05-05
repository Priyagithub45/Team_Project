<?php
/**
 * Contact Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

    <!-- Contact Section -->
    <section class="contact-page">
        <div class="container">
            <div class="contact-header">
                <h1>CONTACT US</h1>
                <p>Get in touch with us for any inquiries or visit our central pickup point.</p>
            </div>

            <div class="contact-grid">
                <!-- Left Column: Form -->
                <div class="contact-col">
                    <div class="contact-col-title">SEND A MESSAGE</div>
                    
                    <form action="#" method="POST">
                        <div class="contact-form-group">
                            <label class="contact-label">FULL NAME</label>
                            <input type="text" class="contact-input" required>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-label">EMAIL ADDRESS</label>
                            <input type="email" class="contact-input" required>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-label">SUBJECT</label>
                            <select class="contact-input" required>
                                <option>General Inquiry</option>
                                <option>Order Support</option>
                                <option>Trader Application</option>
                                <option>Other</option>
                            </select>
                        </div>

                        <div class="contact-form-group">
                            <label class="contact-label">MESSAGE</label>
                            <textarea class="contact-input" required></textarea>
                        </div>

                        <button type="submit" class="btn-contact">SUBMIT MESSAGE</button>
                    </form>
                </div>

                <!-- Right Column: Map and Info -->
                <div class="contact-col">
                    <div class="contact-col-title">CENTRAL PICKUP POINT</div>
                    
                    <div class="contact-map-placeholder" style="display: block; padding: 0;">
                        <iframe width="100%" height="100%" style="border:0;" loading="lazy" src="https://maps.google.com/maps?q=Kathmandu%20Valley&t=&z=11&ie=UTF8&iwloc=&output=embed"></iframe>
                    </div>

                    <div class="contact-info-block">
                        <div class="contact-info-title">ADDRESS</div>
                        <div class="contact-info-text">Kathmandu Valley</div>
                    </div>

                    <div class="contact-info-block">
                        <div class="contact-info-title">PICKUP HOURS</div>
                        <div class="contact-info-text">Mon - Fri: 09:00 AM - 06:00 PM<br>Sat: 10:00 AM - 02:00 PM</div>
                    </div>

                    <div class="contact-info-block">
                        <div class="contact-info-title">PHONE</div>
                        <div class="contact-info-text">+977 (000) 000-0000</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>

