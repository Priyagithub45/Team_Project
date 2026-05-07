<?php
/**
 * PayPal sandbox settings for the customer checkout.
 *
 * The sandbox account password is intentionally not stored here. The site only
 * needs the merchant business email for a Payments Standard sandbox redirect.
 */
const PAYPAL_SANDBOX = true;
const PAYPAL_BUSINESS_EMAIL = 'sb-k2uif50702606@business.example.com';
const PAYPAL_CURRENCY = 'GBP';
const PAYPAL_CHECKOUT_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
