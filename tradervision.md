# Trader Portal Vision

This document is the guided plan for completing the trader side of Cleckhuddesfax Online Mart. The customer portal is now mostly complete, so the next major goal is to make the trader area functional, secure, and consistent with the case study.

The trader portal must not feel like a separate demo. It should feel like the operational back office for each shop: traders log in, manage their own products and shop details, upload product images, monitor stock, and see the orders they need to prepare for collection slots.

## Core Principle

Each trader must only access their own data.

A trader should never be able to view, edit, delete, or report on another trader's products, shop details, orders, or revenue. Every trader query must be scoped through:

```sql
SHOP.TRADER_ID = $_SESSION['user_id']
```

or through joins from `PRODUCT -> SHOP -> TRADER`.

## Current State

The `trader/` folder currently contains mostly prototype/static screens:

- `dashboard.php`
- `add_product.php`
- `login.php`
- `register.php`
- `profile.php`
- `forgot_password.php`
- `trader.css`
- `trader.js`

Some referenced handler files are missing or incomplete, such as:

- `login_process.php`
- `register_process.php`
- `save_profile.php`
- `edit_product.php`
- `delete_product.php`

There is already a useful helper for future product image uploads:

```text
trader/product_image_upload.php
```

This should be reused when implementing real product creation.

## Business Scope

The trader side must support the case study requirements:

- Traders can manage their products.
- Traders can update their own shop/account details.
- Traders can log in and only access their own details.
- Traders can see daily order reports.
- Traders can see which products and quantities are needed for collection slots.
- Traders can see weekly finance reports.
- Traders can see monthly product sales reports.
- Admin can access and manage trader accounts through the Oracle/Admin Portal.

## Trader Application Requirement

A new wannabe trader must not immediately become an active trader.

When a new trader visits the trader site, they should fill an application form. That application should go to the admin side, which is handled through Oracle/APEX Admin Portal. Admin reviews the application and either approves or rejects it.

### Application Flow

1. Wannabe trader opens trader registration/application page.
2. They fill in:
   - Owner/full name
   - Email
   - Phone number
   - Address
   - Proposed shop name
   - Trader category
   - Business description
   - Optional notes/message
3. PHP validates the form.
4. Application is stored in Oracle with status `PENDING`.
5. Admin views pending applications in Oracle/APEX Admin Portal.
6. Admin approves or rejects.
7. If approved:
   - Create/activate a `SYSTEM_USER` row.
   - Create a `TRADER` row.
   - Create a `SHOP` row.
   - Mark application as `APPROVED`.
8. If rejected:
   - Mark application as `REJECTED`.
   - Keep the record for audit/history.

### Important Rule

Trader applicants should not be able to log in until admin approval.

## Recommended New Table

Add a trader application table instead of inserting unapproved applicants directly into `TRADER`.

Suggested table:

```sql
CREATE TABLE TRADER_APPLICATION (
    APPLICATION_ID NUMBER PRIMARY KEY,
    OWNER_NAME VARCHAR2(100) NOT NULL,
    EMAIL VARCHAR2(100) NOT NULL,
    PHONE_NO VARCHAR2(20),
    ADDRESS VARCHAR2(200),
    PROPOSED_SHOP_NAME VARCHAR2(100) NOT NULL,
    CATEGORY_ID NUMBER,
    BUSINESS_DESCRIPTION VARCHAR2(500),
    NOTES VARCHAR2(500),
    STATUS VARCHAR2(20) DEFAULT 'PENDING' NOT NULL,
    ADMIN_NOTE VARCHAR2(500),
    CREATED_AT TIMESTAMP DEFAULT SYSTIMESTAMP,
    REVIEWED_AT TIMESTAMP,
    REVIEWED_BY NUMBER,
    APPROVED_USER_ID NUMBER,
    CONSTRAINT FK_TRADER_APP_CATEGORY FOREIGN KEY (CATEGORY_ID) REFERENCES CATEGORY(CATEGORY_ID),
    CONSTRAINT FK_TRADER_APP_ADMIN FOREIGN KEY (REVIEWED_BY) REFERENCES ADMIN(USER_ID),
    CONSTRAINT FK_TRADER_APP_USER FOREIGN KEY (APPROVED_USER_ID) REFERENCES SYSTEM_USER(USER_ID),
    CONSTRAINT CK_TRADER_APP_STATUS CHECK (STATUS IN ('PENDING', 'APPROVED', 'REJECTED'))
);
```

Recommended sequence:

```sql
CREATE SEQUENCE TRADER_APPLICATION_SEQ START WITH 1 INCREMENT BY 1;
```

## Admin Portal Scope

The admin side is expected to live in Oracle/APEX.

Admin Portal should include:

- Pending trader applications.
- Application detail page.
- Approve button.
- Reject button.
- Admin note field.
- Trader status management.
- Shop creation/editing.
- Ability to suspend trader accounts.

Admin approval should create or activate records in:

- `SYSTEM_USER`
- `TRADER`
- `SHOP`

If the existing APEX app already has trader management pages, extend those pages to include `TRADER_APPLICATION`.

## Trader Authentication

Trader login must be separate from customer login.

### Login Rules

Trader login should:

1. Read email and password.
2. Fetch user from `SYSTEM_USER`.
3. Verify password with `password_verify`.
4. Confirm user exists in `TRADER`.
5. Confirm trader status is active/approved.
6. Regenerate session ID.
7. Store:

```php
$_SESSION['user_id']
$_SESSION['user_name']
$_SESSION['role'] = 'trader'
```

8. Redirect to trader dashboard.

### Blocked Cases

- Customer credentials must not log into trader portal.
- Admin credentials must not log into trader portal unless intentionally supported.
- Suspended traders must not log in.
- Pending applicants must not log in.

## Trader Auth Files

Build or complete:

```text
trader/login_process.php
trader/logout.php
trader/auth_check.php
trader/register_process.php
```

`trader/auth_check.php` should protect every trader-only page.

It should check:

```php
isset($_SESSION['user_id'])
$_SESSION['role'] === 'trader'
```

Then confirm the trader still exists and is active in Oracle.

## Trader Registration/Application Page

The existing `trader/register.php` should become an application page, not immediate registration.

Recommended language:

```text
Apply to become a trader
Your application will be reviewed by the Cleckhuddesfax admin team.
You will be able to log in after approval.
```

Form should post to:

```text
trader/register_process.php
```

`register_process.php` should insert into `TRADER_APPLICATION`.

Do not create `SYSTEM_USER`, `TRADER`, or `SHOP` directly from public application form unless admin approval is being skipped intentionally. For this project, approval must be required.

## Dashboard Scope

`trader/dashboard.php` should become a live database dashboard.

Show:

- Shop name
- Trader name
- Trader status
- Total active products
- Low stock products
- Today's/next collection-slot orders
- This week revenue
- This month quantity sold
- Quick actions:
  - Add product
  - Manage products
  - View daily orders
  - View weekly finance report
  - View monthly sales report
  - Edit shop profile

## Product Management

Traders must be able to manage their own products.

Pages:

```text
trader/products.php
trader/add_product.php
trader/save_product.php
trader/edit_product.php
trader/update_product.php
trader/delete_product.php
```

### Product Fields

Product form must support:

- Product name
- Description
- Price
- Stock quantity
- Expiry date, if relevant
- Quantity per item
- Minimum order
- Maximum order
- Allergy information
- Category
- Product image

Use the existing `PRODUCT` table fields:

- `PRODUCT_ID`
- `PRODUCT_NAME`
- `DESCRIPTION`
- `PRICE`
- `STOCK_QUANTITY`
- `EXPIRY_DATE`
- `SHOP_ID`
- `CATEGORY_ID`
- `QUANTITY_PER_ITEM`
- `MIN_ORDER`
- `MAX_ORDER`
- `ALLERGY_INFO`
- `IMAGE_PATH`

### Add Product Flow

1. Trader opens add product page.
2. Form loads the trader's shop ID.
3. Trader fills product fields.
4. Trader uploads image.
5. PHP validates input.
6. Insert product using `PRODUCTS_SEQ.NEXTVAL` or existing product sequence.
7. Save image using `trader/product_image_upload.php`.
8. Update `PRODUCT.IMAGE_PATH`.
9. Redirect to product list with success message.

### Product Image Rule

Every product should have an image.

Uploaded images should be stored here:

```text
uploads/products/
```

Recommended filename:

```text
product_<PRODUCT_ID>.jpg
```

Database path:

```text
uploads/products/product_<PRODUCT_ID>.jpg
```

Allowed image types:

- JPG
- PNG
- WEBP

Maximum file size:

```text
2MB
```

### Edit Product Flow

Trader can edit only products from their own shop.

Every edit query must include ownership check:

```sql
WHERE p.PRODUCT_ID = :product_id
AND s.TRADER_ID = :current_trader_id
```

Do not trust hidden form fields for ownership.

### Delete Product Scope

Preferred approach: soft delete or status field.

If the schema does not have product status, options:

1. Add `STATUS` column to `PRODUCT`.
2. Use stock quantity `0` as unavailable.
3. Hard delete only if there are no order history references.

Recommended:

```sql
ALTER TABLE PRODUCT ADD STATUS VARCHAR2(20) DEFAULT 'ACTIVE';
```

Then use:

```text
ACTIVE
INACTIVE
DISCONTINUED
```

Hard delete is risky because old invoices and order history need product references.

## Stock Management

Traders should be able to update stock daily.

Features:

- Stock quantity visible in product table.
- Low stock warning.
- Out of stock warning.
- Quick stock update action.

Suggested low stock rule:

```text
STOCK_QUANTITY <= 5
```

or configurable later.

## Shop Profile

`trader/profile.php` should allow trader to update their own visible shop/business information.

Editable:

- Shop name, if allowed
- Shop description
- Contact phone
- Address
- Trader personal details

Not editable by trader:

- Trader status
- Role
- Approval state
- Shop ownership

Those belong to admin.

## Reports

The reports are the main operational value of the trader portal.

### Daily Collection Orders Report

Purpose: trader logs in each morning and sees what to prepare.

Filters:

- Date
- Collection slot

Columns:

- Order ID
- Customer ID
- Product name
- Quantity
- Collection date
- Collection time
- Shop name
- Order status

Query shape:

```sql
SELECT o.ORDER_ID,
       o.CUSTOMER_ID,
       p.PRODUCT_NAME,
       oi.QUANTITY,
       cs.COLLECTION_DATE,
       cs.COLLECTION_TIME,
       o.STATUS
FROM ORDERS o
JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
WHERE s.TRADER_ID = :current_trader_id
  AND TRUNC(cs.COLLECTION_DATE) = TO_DATE(:selected_date, 'YYYY-MM-DD')
ORDER BY cs.COLLECTION_TIME, o.ORDER_ID, p.PRODUCT_NAME;
```

### Collection Label View

For each order/product, show enough information to label goods:

- Customer ID
- Order ID
- Product name
- Quantity
- Collection slot

This is explicitly required by the case study.

### Weekly Finance Report

Purpose: money owed to trader from previous 7 days.

Report should include only completed/delivered/paid orders depending on final status model.

Current project uses `Paid`, but future enhancement should separate:

- Payment status
- Order fulfillment status

Columns:

- Product name
- Total quantity sold
- Gross revenue
- Order count

### Monthly Product Sales Report

Required sorting options:

- Alphabetically
- Total number of orders per product
- Total income per product

Filters:

- Month
- Year

Columns:

- Product name
- Total orders
- Total quantity sold
- Total income

## Order Status and Fulfillment

Current customer checkout marks orders as `Paid`.

For trader operations, consider adding a clearer fulfillment status later:

```text
Paid
Preparing
Ready for Collection
Collected
Cancelled
```

This would let finance reports only include collected/delivered orders as required by the case study.

If changing status model is too risky now, keep simple:

- `Paid` means customer has paid.
- Trader reports use paid orders.

## Product Reviews for Traders

Trader should be able to view reviews for their own products only.

Page:

```text
trader/reviews.php
```

Actions:

- View product reviews.
- See rating summary.
- Report suspicious review to admin, optional.

Do not let traders directly delete customer reviews unless admin approval is intended.

## Security Requirements

Every trader POST handler must:

- Include `trader/auth_check.php`.
- Use `filter_input` or strict validation.
- Use OCI bind variables.
- Check ownership in SQL.
- Use transactions for multi-step changes.
- Never trust product ID alone.
- Never let trader set `SHOP_ID` manually.
- Never let trader set `TRADER_ID` manually.
- Validate uploaded files by MIME type and size.
- Store only relative image paths in DB.

## Recommended File Structure

```text
trader/
  auth_check.php
  login.php
  login_process.php
  logout.php
  register.php
  register_process.php
  dashboard.php
  products.php
  add_product.php
  save_product.php
  edit_product.php
  update_product.php
  delete_product.php
  product_image_upload.php
  profile.php
  save_profile.php
  reports_daily.php
  reports_weekly_finance.php
  reports_monthly_sales.php
  reviews.php
  trader.css
  trader.js
```

## Implementation Phases

### Phase 1: Trader Application

Goal: wannabe traders can apply; admin can review in Oracle/APEX.

Tasks:

- Add `TRADER_APPLICATION` table.
- Add sequence.
- Convert `trader/register.php` into application form.
- Create `trader/register_process.php`.
- Add success page/message.
- Add Admin Portal/APEX page for pending applications.

Gate:

```text
Submit trader application -> row appears in TRADER_APPLICATION with STATUS='PENDING'
```

### Phase 2: Trader Login

Goal: approved traders can log in.

Tasks:

- Create `trader/login_process.php`.
- Create `trader/auth_check.php`.
- Create `trader/logout.php`.
- Protect trader pages.
- Block customer accounts from trader portal.
- Block suspended/pending traders.

Gate:

```text
Approved trader logs in -> dashboard opens
Customer account tries trader login -> blocked
Suspended trader tries login -> blocked
```

### Phase 3: Live Dashboard

Goal: replace static dashboard values with database data.

Tasks:

- Load trader's shop.
- Count products.
- Count low stock items.
- Show upcoming collection orders.
- Show quick revenue summary.

Gate:

```text
Dashboard values change when database changes
```

### Phase 4: Product CRUD

Goal: trader can manage their own products.

Tasks:

- Build products list.
- Implement add product.
- Implement image upload.
- Implement edit product.
- Implement soft delete/inactivate.
- Validate ownership everywhere.

Gate:

```text
Trader adds product -> product appears on customer category page with image
Trader cannot edit another trader's product
```

### Phase 5: Shop/Profile Management

Goal: trader can update their own non-sensitive details.

Tasks:

- Update trader profile form.
- Update shop profile form.
- Prevent status/role manipulation.

Gate:

```text
Trader updates phone/address/shop text -> customer/admin views reflect it
```

### Phase 6: Daily Orders Report

Goal: trader can prepare goods for collection.

Tasks:

- Build date/slot filters.
- Show product quantities grouped by collection slot.
- Include order ID and customer ID for labels.

Gate:

```text
Paid order appears only for the relevant trader
```

### Phase 7: Finance and Sales Reports

Goal: satisfy periodic reporting requirements.

Tasks:

- Weekly finance report.
- Monthly sales report.
- Sorting by product name, order count, income.

Gate:

```text
Monthly report totals match ORDER_ITEM data
```

### Phase 8: Trader UI Polish

Goal: trader portal feels operational and professional.

Tasks:

- Consistent layout.
- Clear empty states.
- Success/error messages.
- Responsive tables.
- Accessible forms.
- Better dashboard cards.

Gate:

```text
Trader can complete daily workflow without confusion
```

## Testing Checklist

### Application

- Empty required fields blocked.
- Duplicate email handled.
- Duplicate shop name handled.
- Application creates `PENDING` row.
- Applicant cannot log in before approval.

### Admin Approval

- Admin can see pending applications.
- Admin can approve.
- Admin can reject.
- Approved trader gets user/trader/shop records.
- Rejected applicant remains unable to log in.

### Login

- Approved trader login works.
- Wrong password blocked.
- Customer blocked from trader portal.
- Suspended trader blocked.

### Products

- Add product works.
- Image upload works.
- Edit product works.
- Invalid price/stock blocked.
- Product appears on customer side.
- Trader cannot edit another shop's product.

### Reports

- Daily report shows correct collection-slot products.
- Weekly finance report totals are correct.
- Monthly sales sorting works.
- Reports are scoped to current trader.

## Definition of Done

Trader side is complete enough when:

- Wannabe trader can apply.
- Admin can approve/reject in Oracle/APEX.
- Approved trader can log in.
- Trader dashboard uses live database data.
- Trader can add/edit/inactivate products.
- Product image upload works.
- Trader can update profile/shop details.
- Trader can view daily collection orders.
- Trader can view weekly finance report.
- Trader can view monthly sales report.
- Every trader page is scoped to the logged-in trader.
- Customer portal shows trader-added products correctly.

## Final Priority Order

Build in this order:

1. Trader application form.
2. Admin approval data flow.
3. Trader login/auth protection.
4. Product CRUD with image upload.
5. Live dashboard.
6. Daily orders report.
7. Weekly finance report.
8. Monthly sales report.
9. Profile/shop update.
10. UI polish and final testing.

The highest-risk parts are authentication, ownership checks, and product CRUD. Finish those before spending too much time on visual polish.

