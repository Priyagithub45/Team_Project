<?php
/**
 * Collection Slot Selection Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

    <!-- Collection Slot Section -->
    <section class="slot-page">
        <div class="container">
            <div class="slot-header">
                <h1>SELECT COLLECTION SLOT</h1>
                <p class="slot-subtitle">Choose a convenient time for your pickup. Max 20 pickups per window.</p>
            </div>

            <div class="slot-tabs">
                <div class="slot-tab active">
                    <h3>Wednesday</h3>
                </div>
                <div class="slot-tab">
                    <h3>Thursday</h3>
                </div>
                <div class="slot-tab">
                    <h3>Friday</h3>
                </div>
            </div>

            <div class="slot-cards">
                <!-- Card 1 -->
                <div class="slot-card">
                    <div class="slot-card-top" style="justify-content: flex-end;">
                        <span class="slot-badge">AVAILABLE</span>
                    </div>
                    <div class="slot-time">10:00 - 13:00</div>
                    <div class="slot-name">Morning Collection</div>
                </div>

                <!-- Card 2 (Selected) -->
                <div class="slot-card selected">
                    <div class="slot-card-top" style="justify-content: flex-end;">
                        <span class="slot-badge">AVAILABLE</span>
                    </div>
                    <div class="slot-time">13:00 - 16:00</div>
                    <div class="slot-name">Afternoon Collection</div>
                </div>

                <!-- Card 3 -->
                <div class="slot-card">
                    <div class="slot-card-top" style="justify-content: flex-end;">
                        <span class="slot-badge">AVAILABLE</span>
                    </div>
                    <div class="slot-time">16:00 - 19:00</div>
                    <div class="slot-name">Evening Collection</div>
                </div>
            </div>

            <!-- Confirmation Bar -->
            <div class="confirmation-bar">
                <div class="conf-info">
                    <div class="info-icon">i</div>
                    <div class="conf-text">
                        <p><strong>Selected slot is held for 15 minutes</strong></p>
                    </div>
                </div>
                <div class="conf-actions">
                    <button class="btn-confirm" onclick="window.location.href='cart.php'">CONFIRM SLOT</button>
                </div>
            </div>


        </div>
    </section>

    <?php include 'footer.php'; ?>
