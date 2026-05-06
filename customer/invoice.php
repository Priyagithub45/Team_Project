<?php
/**
 * Invoice / Order Confirmation Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

<!-- Invoice Section -->
<section class="invoice-page">
    <div class="container container-narrow">
        <div class="invoice-box">
            <div class="invoice-header">
                <h2>ORDER CONFIRMATION</h2>
            </div>

            <p class="invoice-date">Date: <?php echo date('F j, Y'); ?></p>

            <div class="invoice-customer">
                <h4>CUSTOMER DETAILS</h4>
                <p>First name: <br>
                    Last name: <br>
                    Email: <br>
                    Address: </p>
            </div>

            <h4 class="invoice-table-title">TRADER BREAKDOWN</h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>TRADER</th>
                        <th>QTY</th>
                        <th>UNIT PRICE</th>
                        <th>SUBTOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>BUTCHERS</strong><br>
                            <span class="text-italic">Dry Aged Steak</span>
                        </td>
                        <td>1</td>
                        <td>$35.00</td>
                        <td><strong>$35.00</strong></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>GREENGROCERS</strong><br>
                            <span class="text-italic">Fresh Tomatoes</span>
                        </td>
                        <td>1</td>
                        <td>$5.00</td>
                        <td><strong>$5.00</strong></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>DELICATESSEN</strong><br>
                            <span class="text-italic">Cheese</span>
                        </td>
                        <td>1</td>
                        <td>$50.00</td>
                        <td><strong>$50.00</strong></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>FISHMONGERS</strong><br>
                            <span class="text-italic">Coastal Crab</span>
                        </td>
                        <td>1</td>
                        <td>$25.00</td>
                        <td><strong>$25.00</strong></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>BAKERY</strong><br>
                            <span class="text-italic">White Bread</span>
                        </td>
                        <td>1</td>
                        <td>$10.00</td>
                        <td><strong>$10.00</strong></td>
                    </tr>
                </tbody>
            </table>

            <div class="invoice-totals">
                <div class="invoice-total-row">
                    <span>Item Subtotal:</span>
                    <span>$125.00</span>
                </div>
                <div class="invoice-total-row">
                    <span>Service Fee:</span>
                    <span>$0.00</span>
                </div>
                <div class="invoice-total-row">
                    <span>Tax (0%):</span>
                    <span>$0.00</span>
                </div>
                <div class="invoice-total-row grand-total-row">
                    <span>TOTAL PAID:</span>
                    <span>$125.00</span>
                </div>
            </div>

            <div class="invoice-footer-text">
                <p class="thank-you">THANK YOU FOR YOUR BUSINESS.</p>
            </div>
        </div>
    </div>
</section>

    <?php include 'footer.php'; ?>