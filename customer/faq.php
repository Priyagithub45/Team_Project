<?php
$page_title = 'FAQ - Cleckhuddesfax Online Mart';
include 'header.php';

$faq_groups = [
    [
        'eyebrow' => 'Shopping',
        'title' => 'Orders and Products',
        'items' => [
            [
                'question' => 'How do I place an order?',
                'answer' => 'Browse by category or search for a product, add the items you want to your cart, choose a collection slot, and complete checkout from the cart page.',
            ],
            [
                'question' => 'Can I buy from more than one shop at once?',
                'answer' => 'Yes. You can add products from different local shops to the same cart. Your order summary keeps each item tied to its trader and product.',
            ],
            [
                'question' => 'Why does a product show as unavailable?',
                'answer' => 'A product may be out of stock, inactive, discontinued, or temporarily hidden by the trader while they update stock and pricing.',
            ],
        ],
    ],
    [
        'eyebrow' => 'Collection',
        'title' => 'Pickup and Timing',
        'items' => [
            [
                'question' => 'How do collection slots work?',
                'answer' => 'You choose an available collection date and time during checkout. Traders use that slot to prepare your items for pickup.',
            ],
            [
                'question' => 'Can I change my collection slot after checkout?',
                'answer' => 'If the order has not been prepared yet, contact support as soon as possible. Slot changes depend on availability and trader preparation status.',
            ],
            [
                'question' => 'What should I bring when collecting my order?',
                'answer' => 'Bring your order number or invoice, plus the account details used to place the order if the trader needs to confirm it.',
            ],
        ],
    ],
    [
        'eyebrow' => 'Payments',
        'title' => 'Checkout and Support',
        'items' => [
            [
                'question' => 'Which payment options are supported?',
                'answer' => 'The checkout flow supports the payment methods enabled in the mart, including PayPal where available and local collection payment flows when configured.',
            ],
            [
                'question' => 'When is my payment counted for trader finance?',
                'answer' => 'Trader finance reports count orders after collection or delivery is completed, so pending and preparing orders do not appear as payable revenue too early.',
            ],
            [
                'question' => 'How do refunds or order issues work?',
                'answer' => 'Use the contact page with your order number and issue details. The support team can check the order status and coordinate with the trader.',
            ],
        ],
    ],
    [
        'eyebrow' => 'Account',
        'title' => 'Customers, Reviews, and Traders',
        'items' => [
            [
                'question' => 'Do I need an account to order?',
                'answer' => 'You can browse freely, but an account gives you a saved cart, checkout access, order history, invoices, and review eligibility.',
            ],
            [
                'question' => 'Who can leave a product review?',
                'answer' => 'Customers can review products they have purchased. Reviews help other shoppers understand product quality and trader service.',
            ],
            [
                'question' => 'How does a trader join the mart?',
                'answer' => 'Traders can apply from the trader registration page. The admin team reviews applications before portal access is activated.',
            ],
        ],
    ],
];
?>

<main class="faq-page">
    <section class="faq-hero">
        <div class="container">
            <span class="faq-kicker">Help Centre</span>
            <h1>Frequently Asked Questions</h1>
            <p>Clear answers for shopping, collection, payments, reviews, and trader accounts at Cleckhuddesfax Online Mart.</p>
        </div>
    </section>

    <section class="faq-content">
        <div class="container faq-layout">
            <aside class="faq-contact-card" aria-label="Support options">
                <span class="material-icons" aria-hidden="true">support_agent</span>
                <h2>Need more help?</h2>
                <p>Send us your order number, account email, and a short description of the issue.</p>
                <a href="contact.php" class="faq-contact-link">CONTACT SUPPORT</a>
            </aside>

            <div class="faq-groups">
                <?php foreach ($faq_groups as $group): ?>
                    <section class="faq-group">
                        <div class="faq-group-head">
                            <span><?= htmlspecialchars($group['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                            <h2><?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>

                        <div class="faq-list">
                            <?php foreach ($group['items'] as $item): ?>
                                <details class="faq-item">
                                    <summary>
                                        <span><?= htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <i class="material-icons" aria-hidden="true">expand_more</i>
                                    </summary>
                                    <p><?= htmlspecialchars($item['answer'], ENT_QUOTES, 'UTF-8') ?></p>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
