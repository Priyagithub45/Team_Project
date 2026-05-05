<?php
/**
 * Shopping Cart Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

<!-- Cart Section -->
<section class="cart-page">
    <div class="container container-narrow">
        <h2 class="cart-header-title">SHOPPING CART</h2>

        <!-- Butcher Trader Block -->
        <div class="cart-trader-block">
            <div class="cart-trader-header">
                <h3>Trader: Butcher</h3>
                <span class="item-count">1 Item</span>
            </div>
            <div class="cart-item-row">
                <div class="cart-item-img">
                    <img src="assets/images/Dry Aged Steak.png" alt="Dry Aged Steak">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">Dry Aged Steak</h4>
                    <div class="cart-qty-wrapper">
                        <span class="cart-qty-box">Qty: 1</span>
                        <span class="cart-remove-link">REMOVE</span>
                    </div>
                </div>
                <div class="cart-item-price">$35.00</div>
            </div>
            <div class="cart-trader-subtotal">
                <span class="subtotal-label">SUB-TOTAL (BUTCHER):</span>
                <span class="subtotal-amount">$35.00</span>
            </div>
        </div>

        <!-- Greengrocers Trader Block -->
        <div class="cart-trader-block">
            <div class="cart-trader-header">
                <h3>Trader: Greengrocers</h3>
                <span class="item-count">1 Item</span>
            </div>
            <div class="cart-item-row">
                <div class="cart-item-img">
                    <img src="assets/images/Fresh Tomatoes.png" alt="Fresh Tomatoes">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">Fresh Tomatoes</h4>
                    <div class="cart-qty-wrapper">
                        <span class="cart-qty-box">Qty: 1</span>
                        <span class="cart-remove-link">REMOVE</span>
                    </div>
                </div>
                <div class="cart-item-price">$5.00</div>
            </div>
            <div class="cart-trader-subtotal">
                <span class="subtotal-label">SUB-TOTAL (GREENGROCERS):</span>
                <span class="subtotal-amount">$5.00</span>
            </div>
        </div>

        <!-- Delicatessen Trader Block -->
        <div class="cart-trader-block">
            <div class="cart-trader-header">
                <h3>Trader: Delicatessen</h3>
                <span class="item-count">1 Item</span>
            </div>
            <div class="cart-item-row">
                <div class="cart-item-img">
                    <img src="assets/images/Cheese.png" alt="Cheese">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">Cheese</h4>
                    <div class="cart-qty-wrapper">
                        <span class="cart-qty-box">Qty: 1</span>
                        <span class="cart-remove-link">REMOVE</span>
                    </div>
                </div>
                <div class="cart-item-price">$50.00</div>
            </div>
            <div class="cart-trader-subtotal">
                <span class="subtotal-label">SUB-TOTAL (DELICATESSEN):</span>
                <span class="subtotal-amount">$50.00</span>
            </div>
        </div>

        <!-- Fishmongers Trader Block -->
        <div class="cart-trader-block">
            <div class="cart-trader-header">
                <h3>Trader: Fishmongers</h3>
                <span class="item-count">1 Item</span>
            </div>
            <div class="cart-item-row">
                <div class="cart-item-img">
                    <img src="assets/images/Coastal Crab.png" alt="Coastal Crab">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">Coastal Crab</h4>
                    <div class="cart-qty-wrapper">
                        <span class="cart-qty-box">Qty: 1</span>
                        <span class="cart-remove-link">REMOVE</span>
                    </div>
                </div>
                <div class="cart-item-price">$25.00</div>
            </div>
            <div class="cart-trader-subtotal">
                <span class="subtotal-label">SUB-TOTAL (FISHMONGERS):</span>
                <span class="subtotal-amount">$25.00</span>
            </div>
        </div>

        <!-- Bakery Trader Block -->
        <div class="cart-trader-block">
            <div class="cart-trader-header">
                <h3>Trader: Bakery</h3>
                <span class="item-count">1 Item</span>
            </div>
            <div class="cart-item-row">
                <div class="cart-item-img">
                    <img src="assets/images/White Bread.png" alt="White Bread">
                </div>
                <div class="cart-item-info">
                    <h4 class="cart-item-name">White Bread</h4>
                    <div class="cart-qty-wrapper">
                        <span class="cart-qty-box">Qty: 1</span>
                        <span class="cart-remove-link">REMOVE</span>
                    </div>
                </div>
                <div class="cart-item-price">$10.00</div>
            </div>
            <div class="cart-trader-subtotal">
                <span class="subtotal-label">SUB-TOTAL (BAKERY):</span>
                <span class="subtotal-amount">$10.00</span>
            </div>
        </div>

        <div class="cart-separator"></div>

        <div class="cart-summary">
            <div class="cart-summary-row cart-grand-total">
                <span>GRAND TOTAL:</span>
                <span class="amount">$125.00</span>
            </div>

            <div class="cart-actions">
                <button class="btn-slot" onclick="window.location.href='collection-slot.php'">SELECT COLLECTION
                    SLOT</button>
                <button class="btn-checkout" onclick="window.location.href='invoice.php'">PROCEED TO CHECKOUT</button>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>