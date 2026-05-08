# Cleckhuddesfax Online Mart

Cleckhuddesfax Online Mart is a prototype e-commerce platform for a group of independent fresh-food traders in Cleckhuddersfax. The project explores how local shops can compete with large supermarkets by sharing one online marketplace, one customer basket, one checkout, and scheduled collection from a local collection point.

The prototype is built around five pilot trader categories:

- Butchers
- Greengrocers
- Fishmongers
- Bakery
- Delicatessen

Customers can browse products by trader, register and log in, add products from different shops into one cart, choose a collection slot, pay through a normal checkout or PayPal Sandbox flow, and receive an invoice with a trader-by-trader order breakdown.

## Project Background

The system is based on an e-commerce case study where small independent traders want to offer a shared online ordering portal. The pilot has several important constraints:

- Products are collected, not delivered.
- Orders must be linked to collection slots.
- Collection slots are limited to 20 orders each.
- Collection must be at least 24 hours after ordering.
- A customer uses one basket even when buying from multiple traders.
- Traders need order, stock, finance, and sales reporting.
- The platform should support the initial five traders and be designed for up to ten shops.

## Current Implementation

This repository contains three main parts:

- `customer/` - the main PHP customer-facing shop.
- `trader/` - a PHP prototype for trader-facing screens.
- `sql/` - Oracle database schema, triggers, sequences, procedures, and an Oracle APEX export for richer admin/trader/customer management screens.

The customer portal is the most complete part of the PHP application. The trader PHP folder currently represents interface prototypes and static screens, while the Oracle APEX export contains the broader management and reporting application.

## Features

### Customer Portal

- Landing page for the local marketplace.
- Product category navigation for all five trader types.
- Dynamic shop product pages backed by the Oracle `PRODUCT`, `SHOP`, and `CATEGORY` tables.
- Dynamic product detail page using `product.php?id=...`.
- Customer registration with validation.
- Password hashing with PHP `password_hash`.
- Customer login with `password_verify`.
- Customer-only role check so trader/admin accounts cannot log into the customer portal.
- Session-based authentication.
- Logout flow.
- Customer profile page with editable phone/address.
- Loyalty points display.
- Order history display.
- Single cross-trader cart.
- Cart items grouped by trader.
- Cart subtotals per trader and grand total.
- Secure remove-cart-item flow that checks ownership.
- Collection slot selection.
- Slot availability display based on remaining capacity.
- Checkout summary with customer details, selected collection slot, payment method, and cart items grouped by shop.
- Transactional order placement.
- Invoice/order confirmation page.
- PayPal Sandbox redirect flow.
- PayPal cancel handling.
- Basic product review UI placeholder.

### Trader Prototype

The `trader/` folder contains a prototype trader interface with:

- Trader login page.
- Trader registration page.
- Dashboard screen.
- Product table mockup with stock/status labels.
- Add product form.
- Trader profile form.
- Forgot password screen.
- Trader-specific CSS and JavaScript.

Some trader actions are currently static or incomplete in the PHP folder. For example, files such as `login_process.php`, `register_process.php`, `save_profile.php`, `edit_product.php`, and `delete_product.php` are referenced but are not present in the PHP trader folder.

### Oracle APEX Application

The `sql/appfinal.sql` file is an Oracle APEX export for a broader application named `Cleckhuddesfax App`. It includes pages and navigation for:

- Admin dashboard.
- Trader dashboard.
- Customer dashboard.
- Browse products.
- Product details.
- Cart.
- Checkout.
- Order history.
- Customer management.
- Trader management.
- Add trader.
- Product management.
- Orders report.
- Monthly sales report.
- Daily orders report.
- Weekly finance report.
- Product reviews.
- Shop profile.
- Order details.

This APEX export complements the PHP prototype and appears to cover a larger amount of admin/trader reporting functionality.

## Tech Stack

- PHP
- Oracle Database
- OCI8 PHP extension
- Oracle APEX export
- HTML
- CSS
- JavaScript
- XAMPP/Apache local development
- PayPal Payments Standard Sandbox

## Repository Structure

```text
CFO/
├── customer/
│   ├── assets/
│   │   ├── css/style.css
│   │   ├── js/script.js
│   │   └── images/
│   ├── index.php
│   ├── category.php
│   ├── bakery.php
│   ├── butcher.php
│   ├── greengrocers.php
│   ├── fishmongers.php
│   ├── delicatessen.php
│   ├── product.php
│   ├── cart.php
│   ├── collection-slot.php
│   ├── checkout.php
│   ├── place_order.php
│   ├── invoice.php
│   ├── paypal_*.php
│   ├── register.php
│   ├── register_process.php
│   ├── login.php
│   ├── login_process.php
│   ├── logout.php
│   └── profile.php
├── trader/
│   ├── dashboard.php
│   ├── add_product.php
│   ├── login.php
│   ├── register.php
│   ├── profile.php
│   ├── forgot_password.php
│   ├── trader.css
│   └── trader.js
├── sql/
│   ├── appfinal.sql
│   ├── table_trigger_all.sql
│   └── func_ind_seq_proc.sql
├── db.php
├── ECprojectCaseStudy.md
├── vision.MD
├── repomix-output.xml
└── README.md
```

## Database Design

The Oracle schema includes the main entities required by the case study:

- `SYSTEM_USER` - shared user table for customers, traders, and admins.
- `CUSTOMER` - customer-specific profile data and loyalty points.
- `TRADER` - trader-specific business data and status.
- `ADMIN` - admin account information.
- `SHOP` - shop/trader information.
- `CATEGORY` - product categories.
- `PRODUCT` - product catalogue, price, stock, allergy info, min/max order quantities.
- `CART` - active or closed customer basket.
- `CART_ITEM` - items in a basket.
- `COLLECTION_SLOT` - available collection dates, times, and location.
- `ORDERS` - placed customer orders.
- `ORDER_ITEM` - ordered products.
- `PAYMENT` - payment records.
- `PAYMENT_METHOD` - available payment options.
- `REVIEW` - product review records.
- `DISCOUNT` and `PRODUCT_DISCOUNT` - discount support.

## Database Logic

The SQL layer contains triggers for important business rules:

- Default cart created date.
- Default customer loyalty points.
- Default order status.
- Default payment status and payment date.
- Product price copying into cart items.
- Cart item validation.
- Product validation.
- Product allergy defaulting.
- Product expiry validation.
- Stock validation before ordering.
- Stock reduction after order item insertion.
- Min/max order quantity enforcement.
- Collection slot capacity enforcement.
- Collection time rule enforcement.
- Suspended trader checks for products, cart items, and order items.
- Trader suspension cleanup for open carts.
- Review rating validation.
- Unique shop name validation.
- Discount date/rate validation.

This means the application relies on both PHP validation and Oracle-side enforcement.

## Customer Workflow

1. A customer browses products by trader category.
2. The customer opens a dynamic product page.
3. The customer registers or logs in.
4. The customer adds products to one shared cart.
5. The cart groups products by trader and calculates trader subtotals.
6. The customer selects a collection slot.
7. The customer reviews checkout details.
8. The customer places the order or goes through PayPal Sandbox.
9. The order is inserted in one transaction.
10. Order items are created and product stock is reduced.
11. Payment is recorded.
12. The active cart is closed.
13. The customer receives an invoice/order confirmation.

## Payment Flow

The app supports two checkout paths:

- Standard checkout using a selected payment method from the database.
- PayPal Sandbox redirect using `customer/paypal_start.php`.

PayPal configuration is stored in:

```text
customer/paypal_config.php
```

The PayPal flow creates a temporary session token, sends cart line items to PayPal Sandbox, and returns to `place_order.php` after successful verification.

## Setup

### Requirements

- XAMPP with Apache and PHP.
- Oracle Database, such as Oracle 23ai Free or Oracle XE.
- PHP OCI8 extension enabled.
- Oracle user/schema for the project.
- Browser for local testing.

### 1. Clone or copy the project

Place the project under the XAMPP web root:

```text
C:\xampp\htdocs\CFO
```

### 2. Configure Oracle connection

Edit `db.php`:

```php
$db_user = "YOUR_ORACLE_USERNAME";
$db_pass = "YOUR_ORACLE_PASSWORD";
$db_tns = "localhost:1521/FREEPDB1";
$db_charset = "AL32UTF8";
```

Common Oracle service names:

- Oracle 23ai/Free: `FREEPDB1`
- Oracle XE 18c/21c: `XEPDB1`
- Oracle XE 11g: `XE`

Do not commit real production database credentials to GitHub.

### 3. Import the database

Use the SQL files in this order as appropriate for your environment:

```text
sql/table_trigger_all.sql
sql/func_ind_seq_proc.sql
sql/appfinal.sql
```

`table_trigger_all.sql` contains the core tables, constraints, and triggers.

`func_ind_seq_proc.sql` contains sequences, indexes, functions, and procedures.

`appfinal.sql` is the Oracle APEX application export.

Depending on your Oracle/APEX setup, the APEX export may need to be imported through the Oracle APEX application import interface rather than run as a normal schema script.

### 4. Start Apache

Start Apache through the XAMPP control panel.

### 5. Open the customer portal

```text
http://localhost/CFO/customer/
```

### 6. Open the trader prototype

```text
http://localhost/CFO/trader/
```

## Important URLs

```text
Customer home:       http://localhost/CFO/customer/index.php
Categories:          http://localhost/CFO/customer/category.php
Cart:                http://localhost/CFO/customer/cart.php
Collection slots:    http://localhost/CFO/customer/collection-slot.php
Checkout:            http://localhost/CFO/customer/checkout.php
Customer profile:    http://localhost/CFO/customer/profile.php
Trader prototype:    http://localhost/CFO/trader/dashboard.php
```

## Security Notes

Implemented or partially implemented:

- Passwords are hashed before storage.
- Login uses password verification.
- Session ID is regenerated after successful login/registration.
- Customer-only role checks protect the customer portal.
- Authenticated pages include `auth_check.php`.
- SQL statements use bound parameters in the main customer flows.
- Invoice access checks order ownership.
- Cart item removal checks ownership.
- Order placement uses a database transaction.
- Oracle triggers enforce stock, slot, and trader-status rules.

Areas to improve before production:

- Disable `display_errors` in production.
- Move database credentials and PayPal settings to environment variables.
- Add CSRF tokens to forms.
- Add server-side validation to the trader PHP prototype.
- Complete trader authentication and CRUD handlers.
- Add automated tests.
- Add real PayPal IPN/Webhook verification or migrate to a modern PayPal API.
- Add email verification and password reset handling.
- Review all SQL scripts and remove any experimental/demo procedures that are not needed.

## Known Limitations

- The PHP customer portal is the main functional web app.
- The PHP trader folder is currently a prototype and contains static/demo data in places.
- Trader PHP forms reference some handler files that are not currently present.
- Product images are matched by product name against files in `customer/assets/images/`.
- Product review UI exists, but review submission is not fully wired in the customer PHP portal.
- The project is designed for local development and academic demonstration, not production deployment.
- PayPal is configured for Sandbox testing only.
- The case study mentions Stripe as a possible option, but Stripe is not implemented in this repository.

## Development Roadmap

Useful next steps:

- Finish trader PHP login, registration, product CRUD, and profile update flows.
- Connect trader dashboard metrics to live database queries.
- Add trader reports for daily collection-slot orders.
- Add weekly finance report by delivered orders.
- Add monthly sales report sorting by product name, order count, and income.
- Wire customer reviews to the `REVIEW` table.
- Add admin screens or rely on the included Oracle APEX app.
- Add test users and seed data documentation.
- Clean sensitive configuration before publishing to GitHub.

## Project Documentation

Additional context lives in:

- `ECprojectCaseStudy.md` - original project brief and requirements.
- `vision.MD` - implementation roadmap and development notes.
- `repomix-output.xml` - generated merged representation of the repository for analysis.

## Summary

Cleckhuddesfax Online Mart demonstrates a shared local e-commerce platform for independent fresh-food traders. It combines a PHP customer storefront, Oracle-backed data model, database triggers for business rules, PayPal Sandbox checkout, and an Oracle APEX export for management/reporting workflows.

The project shows how multiple small shops can act like one online marketplace while still keeping trader-specific product, stock, order, and finance breakdowns.
