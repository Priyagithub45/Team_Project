# 10/10 Improvement Plan for Cleckhuddesfax Online Mart

This document lists the remaining improvements that would help the project score as highly as possible against `ECprojectCaseStudy.md`. Each section explains the problem, the practical fix, and how the improvement benefits the system.

## 1. Correct Order and Payment Statuses

### Current Issue

Cash orders are inserted as `Paid` even though the customer has not paid online yet. This can make trader reports and finance totals inaccurate.

### Fix / Solution

- In `customer/place_order.php`, set order status dynamically:
  - `Paid` for successful PayPal returns.
  - `Pending` for cash-on-collection orders.
- Keep `PAYMENT.PAYMENT_STATUS` aligned with the real payment state.
- Update trader dashboards and reports so they distinguish between pending, paid, collected, and completed orders.

### How This Helps

- Makes reports more trustworthy.
- Prevents cash orders from inflating paid revenue.
- Shows the system understands real e-commerce payment workflows.
- Improves alignment with the case study requirement for accurate trader payment breakdowns.

## 2. Make Weekly Finance Reports Cover Delivered Orders Only

### Current Issue

The case study says weekly finance reports should identify payments from the previous 7 days and only cover orders that have been delivered. The current reports include several active paid/preparation statuses.

### Fix / Solution

- Update `trader/reports_weekly_finance.php` so finance totals only include statuses such as:
  - `Collected`
  - `Completed`
  - `Delivered`, if used
- Exclude:
  - `Pending`
  - `Paid`
  - `Preparing`
  - `Ready for Collection`
- Add a clear note or label in the report showing the status filter used.

### How This Helps

- Matches the case study exactly.
- Prevents traders from being paid for orders not yet collected.
- Makes the management/reporting side more credible.
- Gives assessors a clear reason to award marks for requirement accuracy.

## 3. Replace Manual ID Generation with Sequences

### Current Issue

Some flows use `LOCK TABLE` and `MAX(ID) + 1` to create new IDs, especially for order items and reviews. This works in a small demo but is weak under concurrent use.

### Fix / Solution

- Add proper Oracle sequences:
  - `ORDER_ITEM_SEQ`
  - `REVIEW_SEQ`
- Replace manual ID assignment in:
  - `customer/place_order.php`
  - `customer/submit_review.php`
- Use:

```sql
ORDER_ITEM_SEQ.NEXTVAL
REVIEW_SEQ.NEXTVAL
```

### How This Helps

- Removes unnecessary table locks.
- Allows multiple customers to order or review at the same time.
- Makes the system more scalable and professional.
- Demonstrates stronger database design.

## 4. Clean and Organize SQL Scripts

### Current Issue

Some SQL files include unrelated or experimental procedures, including EMP demo functions. This makes setup less reliable and can reduce confidence in the database deliverables.

### Fix / Solution

- Remove unrelated EMP/sample procedures from `sql/func_ind_seq_proc.sql`.
- Create a clear SQL install order, for example:

```text
1. sql/table_trigger_all.sql
2. sql/func_ind_seq_proc.sql
3. sql/migrations/*.sql in numeric order
4. sql/appfinal.sql through Oracle APEX import
```

- Add a `DATABASE_SETUP.md` file explaining the setup steps.
- Add notes for required Oracle/APEX versions.

### How This Helps

- Makes the project easier to install and assess.
- Reduces setup errors.
- Shows professionalism and clean database ownership.
- Helps the marker understand what is core system logic and what is optional/demo data.

## 5. Move Credentials Out of Source Code

### Current Issue

Database credentials and PayPal Sandbox values are stored directly in source files.

### Fix / Solution

- Move credentials to environment variables or a local ignored config file.
- Example:

```php
$db_user = getenv('CFO_DB_USER');
$db_pass = getenv('CFO_DB_PASS');
$db_tns = getenv('CFO_DB_TNS');
```

- Add an example config file:

```text
.env.example
```

- Make sure real credentials are not committed.

### How This Helps

- Improves security.
- Makes the project safer to share or submit.
- Shows awareness of production deployment practices.
- Avoids exposing database or payment configuration.

## 6. Strengthen Customer Authentication Checks

### Current Issue

The customer portal mainly trusts session values. If a customer account is suspended in the database, an existing session may continue working.

### Fix / Solution

- Update `customer/auth_check.php` to verify the customer still exists and is active.
- Use a short session cache to avoid querying the database on every request.
- If the user is suspended, destroy the session and redirect to login with a clear message.

### How This Helps

- Keeps suspended accounts from placing orders.
- Matches the stronger trader authentication flow.
- Improves security and business-rule enforcement.
- Gives admins better control over customer access.

## 7. Add Login Rate Limiting

### Current Issue

Login forms can be repeatedly submitted without slowdown or lockout.

### Fix / Solution

- Add basic session-based rate limiting to customer and trader login.
- After several failed attempts, temporarily block new attempts for a few minutes.
- Optionally log failed attempts by IP/email in the database.

### How This Helps

- Reduces brute-force and credential-stuffing risk.
- Improves security marks.
- Shows awareness of real-world authentication concerns.

## 8. Stop Showing Raw Oracle Errors to Users

### Current Issue

Some pages display raw database errors to users. Oracle errors can expose table names, column names, and constraints.

### Fix / Solution

- Log full Oracle errors with `error_log()`.
- Show generic user-facing messages such as:

```text
Could not complete this action. Please try again.
```

- Review pages such as OTP verification, review submission, profile update, and product/order handlers.

### How This Helps

- Prevents database information leakage.
- Improves security and polish.
- Makes the user experience friendlier.
- Shows separation between developer diagnostics and public UI.

## 9. Enforce the Maximum of 10 Shops

### Current Issue

The case study says the system should support a maximum of 10 shops in the first instance. This rule should be enforced, not only assumed.

### Fix / Solution

- Add validation before approving or creating new shops.
- Add a database trigger or application check:

```sql
SELECT COUNT(*) FROM SHOP
```

- Block new shop creation when the active shop count is already 10.
- Display a clear admin/trader message.

### How This Helps

- Directly satisfies a case-study requirement.
- Prevents uncontrolled scope growth.
- Shows that business constraints are implemented in code.

## 10. Complete the Admin / Management Story

### Current Issue

The project includes an Oracle APEX management app, but the PHP side does not clearly explain whether APEX is the official admin interface.

### Fix / Solution

- Decide and document one of these:
  - APEX is the official admin/management interface.
  - A PHP admin portal will be added.
- If using APEX, document admin features:
  - Trader approval
  - Trader suspension
  - Product management
  - Customer management
  - Reports
  - Order details
- Add screenshots and login details for demonstration.

### How This Helps

- Makes the management interface easier to assess.
- Avoids confusion about where admin features live.
- Strengthens the answer to the case study's management dashboard requirement.

## 11. Add Stripe Exploration

### Current Issue

The case study says Stripe has been suggested and the traders are interested in seeing it explored. The project mainly implements PayPal Sandbox.

### Fix / Solution

- Add a `STRIPE_EXPLORATION.md` document comparing:
  - PayPal Sandbox
  - Stripe Checkout
  - Fees
  - Ease of integration
  - Refunds
  - Webhooks
  - Suitability for local traders
- Optionally add a disabled Stripe payment option in checkout labelled as "Exploration / Future Integration".

### How This Helps

- Covers a specific case-study point.
- Shows technical research and decision-making.
- Avoids needing a full Stripe integration while still demonstrating awareness.

## 12. Add Alternative Design Evidence

### Current Issue

The case study says traders wanted to see alternative designs. The current project has a final design, but alternatives are not clearly documented.

### Fix / Solution

- Add a `DESIGN_ALTERNATIVES.md` file.
- Include 2 or 3 design directions:
  - Heritage/local market style
  - Modern clean supermarket style
  - Minimal mobile-first style
- Explain why the final heritage/local-market design was chosen.
- Add screenshots or simple mockups if possible.

### How This Helps

- Directly answers the design exploration requirement.
- Shows stakeholder-aware design thinking.
- Helps justify visual choices.

## 13. Improve Customer Account Update Confirmation

### Current Issue

Registration uses email OTP, but customer account updates are not fully confirmed through email.

### Fix / Solution

- For email address changes:
  - Store the new email as pending.
  - Send OTP or confirmation link.
  - Only update `SYSTEM_USER.EMAIL` after verification.
- For profile changes:
  - Send a confirmation email after update.

### How This Helps

- Matches the case study's "ideally confirmed through emails" requirement.
- Reduces account takeover risk.
- Improves trust in customer account management.

## 14. Add Out-of-Stock and Low-Stock UX

### Current Issue

Stock checks exist at checkout/database level, but customers should see stock problems earlier.

### Fix / Solution

- Show product availability on `customer/product.php`.
- Disable add-to-cart and buy-now buttons when stock is 0.
- Show a low-stock warning when stock is low.
- Validate stock again when adding to cart and updating cart quantities.

### How This Helps

- Prevents frustrating checkout failures.
- Improves user experience.
- Makes stock handling feel complete.
- Supports fresh-food e-commerce expectations.

## 15. Update README and Documentation

### Current Issue

Some README statements are out of date. For example, it says trader handlers are missing, but many now exist.

### Fix / Solution

- Update `README.md` to reflect current functionality.
- Add:
  - Setup instructions
  - Demo accounts
  - Feature checklist
  - Known limitations
  - Screenshots
  - APEX import notes
- Link to supporting documents:
  - `DATABASE_SETUP.md`
  - `TESTING.md`
  - `DESIGN_ALTERNATIVES.md`
  - `STRIPE_EXPLORATION.md`

### How This Helps

- Prevents the project from underselling itself.
- Makes assessment easier.
- Shows professionalism and completeness.

## 16. Add Requirement Traceability

### Current Issue

The project implements many requirements, but they are not mapped clearly back to the case study.

### Fix / Solution

- Add a `REQUIREMENTS_TRACEABILITY.md` file.
- Use a table like:

| Case Study Requirement | Implementation | Status |
|---|---|---|
| Browse by shop | `customer/shop.php`, shop category pages | Complete |
| Single basket across traders | `customer/cart.php` | Complete |
| 3 collection slots | `collection_slot_rules.php` | Complete |
| Max 20 orders per slot | Oracle trigger + PHP slot checks | Complete |
| Weekly finance report | `trader/reports_weekly_finance.php` | Needs delivered-only filter |

### How This Helps

- Makes it easy for a marker to award marks.
- Shows that implementation decisions were based on the brief.
- Highlights completed work and honest limitations.

## 17. Add Manual Testing Evidence

### Current Issue

There is no clear testing document showing that major workflows have been verified.

### Fix / Solution

- Add `TESTING.md`.
- Include manual test cases for:
  - Customer registration and OTP
  - Login/logout
  - Browse by shop and category
  - Add products from multiple traders to one cart
  - Collection slot selection
  - 24-hour rule
  - Wednesday/Thursday/Friday-only slots
  - 20-order slot capacity
  - Cash checkout
  - PayPal Sandbox checkout
  - Trader product CRUD
  - Daily orders report
  - Weekly finance report
  - Monthly sales sorting

### How This Helps

- Demonstrates reliability.
- Gives evidence that core flows work.
- Helps catch regressions before submission.
- Makes the project feel complete rather than just implemented.

## 18. Improve Mobile and Visual Polish

### Current Issue

The app is responsive, but some pages still use inline styles and inconsistent layouts.

### Fix / Solution

- Move inline styles from checkout and OTP pages into CSS files.
- Check mobile layouts for:
  - Cart
  - Checkout
  - Product detail
  - Trader reports
  - Product management
- Ensure buttons, tables, and forms remain readable on small screens.

### How This Helps

- Better satisfies the mobile/browser requirement.
- Makes the app feel more finished.
- Improves user confidence and assessor impression.

## 19. Add Demo Accounts and Walkthrough

### Current Issue

The system has many features, but an assessor may not know how to access or demonstrate them quickly.

### Fix / Solution

- Add demo login details for:
  - Customer
  - Each pilot trader
  - Admin/APEX user
- Add a short walkthrough:

```text
1. Log in as a customer.
2. Add products from two shops.
3. Choose a collection slot.
4. Complete checkout.
5. Log in as trader.
6. View daily order report.
7. View weekly finance report.
```

### How This Helps

- Makes marking easier.
- Shows confidence in the system.
- Helps demonstrate the complete end-to-end workflow.

## 20. Add Screenshots

### Current Issue

The project has useful UI, but documentation does not clearly show it.

### Fix / Solution

- Add screenshots for:
  - Homepage
  - Shop/category browsing
  - Product detail
  - Cart grouped by trader
  - Collection slot page
  - Checkout
  - Invoice
  - Trader dashboard
  - Product management
  - Daily report
  - Weekly finance
  - Monthly sales
  - APEX/admin dashboard

### How This Helps

- Makes the project easier to review.
- Shows the visual and functional breadth quickly.
- Supports the design and usability marks.

## Suggested Priority Order

| Priority | Improvement | Reason |
|---|---|---|
| Critical | Fix order/payment statuses | Core business correctness |
| Critical | Weekly finance delivered-only filter | Direct case-study requirement |
| Critical | Replace manual ID generation | Scalability and database quality |
| High | Clean SQL scripts | Setup reliability |
| High | Move credentials out of code | Security |
| High | Requirement traceability | Easier marking |
| High | Testing document | Evidence of reliability |
| Medium | Admin/APEX documentation | Management requirement clarity |
| Medium | Stripe exploration | Covers suggested payment option |
| Medium | Alternative designs | Covers stakeholder design request |
| Medium | Mobile/UX polish | Better user experience |
| Low | Screenshots and walkthrough | Better presentation |

## Final Target

Completing these improvements would make the system stronger in three ways:

1. **Better functionality**: Orders, payments, reports, stock, and admin flows become more accurate.
2. **Better security and reliability**: Credentials, authentication, database errors, and ID generation are handled more professionally.
3. **Better assessment evidence**: Documentation, testing, screenshots, and requirement mapping make it much easier to prove the project satisfies the case study.

With these fixes, the project would be much closer to a 10/10 academic submission because it would not only implement the required features, but also explain, test, and justify them clearly.
