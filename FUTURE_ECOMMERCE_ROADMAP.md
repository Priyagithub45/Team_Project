# Future Ecommerce Roadmap

This document lists the practical improvements that would make Cleckhuddesfax Online Mart easier to use, more complete as an ecommerce product, and closer to the experience customers expect from strong modern ecommerce sites.

The current customer and trader flows are now much clearer. The next work should focus on trust, speed, checkout confidence, product discovery, and operational control.

## 1. Must-Have Improvements

These are the most important features to make the product feel complete and comfortable to use.

### 1. Better product images

Every product should have its own correct image.

Why this matters:

- Customers trust products more when the image matches the item.
- Category fallback images are okay temporarily, but they make the site feel unfinished.
- Product cards look much more professional when images are consistent.

Implementation:

- Use the image list in `PRODUCT_IMAGE_DOWNLOAD_LIST.md`.
- Save each image inside `uploads/products/`.
- Use consistent image size and crop style.
- Avoid blurry, stretched, or unrelated images.

Success check:

- No product should show an unrelated fallback image.
- All product cards should visually look like real ecommerce listings.

### 2. Product filtering and sorting

Customers should be able to filter and sort products inside a shop.

Useful filters:

- Price low to high
- Price high to low
- In stock only
- Category/type
- Allergy-friendly products
- Recently added

Why this matters:

- Customers should not need to scroll through everything.
- A shop with 15 products is manageable now, but the system should scale.
- Top ecommerce sites make product discovery quick and controlled.

### 3. Product detail improvements

Each product page should show more useful buying information.

Add:

- Shop name
- Stock available
- Minimum and maximum order quantity
- Allergy information
- Product freshness/expiry date where relevant
- Collection slot availability hint
- Clear price in GBP
- Related products from the same shop

Why this matters:

- Product pages should answer customer doubts before checkout.
- Food products especially need freshness, allergy, and collection information.

### 4. Guest checkout or frictionless checkout

Checkout should be as short as possible.

Recommended direction:

- Keep registered customer login if required by project rules.
- But reduce unnecessary steps once a user is logged in.
- Do not ask for the same information repeatedly.
- Show cart, collection slot, payment method, and final total clearly on one review page.

Why this matters:

- Checkout friction is one of the biggest reasons customers abandon carts.
- Baymard’s checkout UX research highlights guest checkout, clear forms, and adaptive error messages as major checkout improvements.

### 5. Clear checkout progress

The customer should always know where they are.

Suggested checkout flow:

1. Cart
2. Collection slot
3. Payment
4. Review order
5. Confirmation/invoice

Add:

- A small progress indicator.
- Clear back buttons.
- Clear final total before payment.
- Error messages that explain exactly what went wrong.

### 6. Order tracking for customers

Customers should be able to see order status after placing an order.

Useful statuses:

- Paid
- Preparing
- Ready for collection
- Collected
- Cancelled

Customer profile should show:

- Order ID
- Shop names
- Products
- Collection date/time
- Total paid
- Current status
- Invoice link

Why this matters:

- Customers need reassurance after payment.
- It reduces confusion and support questions.

### 7. Better trader order management

Trader portal should clearly separate order work by shop.

Add:

- Orders by selected shop
- Today’s collection orders
- Products to prepare
- Quantity summary
- Mark order as preparing/ready
- Print daily pick list

Why this matters:

- Traders owning two shops need clean separation.
- The trader portal should be operational, not just informational.

### 8. Admin audit and control

Admin should be able to see what happened and fix issues.

Add:

- Trader approval history
- Product approval or product audit list
- Shop ownership list
- Suspended trader/shop visibility
- Manual product status control
- Order cancellation/refund control

Why this matters:

- Admin is responsible for marketplace health.
- Every important action should be traceable.

## 2. Important Quality Improvements

These are not always the first demo features, but they greatly improve satisfaction.

### 1. Strong search experience

Search should help users find products quickly.

Improve search with:

- Search suggestions while typing
- Typo tolerance, for example "chiken" matching "chicken"
- Search by product, shop, and category
- Empty state suggestions
- Recent searches
- Popular searches

Current note:

- Search should remain a search-only experience.
- Category/shop browsing should not force users into search results.

### 2. Wishlist or save for later

Customers should be able to save products.

Useful features:

- Add to wishlist
- Save cart item for later
- Move saved item back to cart

Why this matters:

- Customers often browse before buying.
- It encourages return visits.

### 3. Product reviews and ratings

Reviews should be visible and moderated.

Add:

- Star rating average
- Review count
- Verified purchase label
- Admin/trader moderation
- Report inappropriate review

Why this matters:

- Reviews create trust.
- For food products, customer feedback matters a lot.

### 4. Stock warnings

Show when stock is low.

Examples:

- "Only 3 left"
- "Out of stock"
- Disable add-to-cart for unavailable products
- Notify trader when stock is low

Why this matters:

- Prevents checkout disappointment.
- Helps traders manage inventory.

### 5. Cart improvements

Cart should be interactive and forgiving.

Add:

- Quantity stepper buttons
- Update quantity without removing/re-adding
- Clear item-level stock errors
- Shop subtotal
- Grand total
- Collection slot reminder

Baymard’s checkout research specifically calls out better cart quantity updating as a common ecommerce UX improvement.

### 6. Better form validation

All customer/trader/admin forms should have clear validation.

Improve:

- Required field labels
- Inline validation
- Useful error messages
- Keep entered data after validation errors
- Show success messages after saves

Accessibility note:

- WCAG 2.2 includes input assistance guidance such as error identification, labels/instructions, and error suggestions. Following this improves accessibility and general usability.

### 7. Responsive mobile experience

The whole site should be tested on mobile.

Check:

- Header navigation
- Search bar
- Product cards
- Cart
- Checkout
- Trader dashboard tables
- Admin views if used on smaller screens

Why this matters:

- Many ecommerce customers browse and buy on mobile.
- A weak mobile checkout can lose otherwise interested customers.

## 3. Marketplace Trust Features

These features make customers feel safe buying from multiple traders.

### 1. Shop profile pages

Each shop should have a proper profile.

Add:

- Shop image/banner
- Shop description
- Trader name
- Contact info
- Product list
- Average rating
- Collection rules

Why this matters:

- Customers are buying from local traders, not a faceless database.
- It makes the 5-trader/10-shop model easier to understand.

### 2. Clear collection policy

Show collection information before checkout.

Add:

- Available days
- Available time slots
- Slot capacity
- Cutoff rules
- Collection location
- What to bring for collection

Why this matters:

- Your system depends on collection slots, so this must be very clear.

### 3. Payment confidence

Checkout should clearly show:

- Payment method
- Currency as GBP
- Total before confirmation
- Payment status
- Invoice after order

Optional later:

- Real payment gateway integration
- Refund status
- Payment failure handling

Stripe’s ecommerce guidance emphasizes reducing checkout friction, supporting appropriate payment methods, and optimizing checkout for conversion.

### 4. Email notifications

Add emails for key actions.

Customer emails:

- Order confirmation
- Payment confirmation
- Ready for collection
- Order cancelled

Trader emails:

- New order received
- Low stock alert
- Product approved/rejected if product moderation exists

Admin emails:

- New trader application
- Suspicious failed login attempts

## 4. Trader Portal Improvements

The trader portal should help traders run their shop, not only add products.

### 1. Shop switcher persistence

The selected shop should remain selected across trader pages.

Example:

- Trader selects `Poultry Shop`.
- Dashboard, products, reports, and orders remain filtered to `Poultry Shop`.

### 2. Product bulk actions

Add:

- Bulk activate/inactivate
- Bulk price update
- Bulk stock update
- Bulk image upload

Why this matters:

- Traders should not need to edit 15 products one by one.

### 3. Trader dashboard KPIs

Show useful numbers:

- Today’s orders
- This week’s revenue
- Products low in stock
- Best-selling products
- Upcoming collection slots

### 4. Product image management

Trader should be able to upload/change product images.

Rules:

- Validate file type
- Limit file size
- Store with safe generated filename
- Show preview before saving
- Replace old image cleanly

## 5. Admin Portal Improvements

### 1. Better trader approval workflow

Admin should see:

- Applicant name
- Proposed shop name
- Category
- License number
- Contact info
- Uploaded proof/license if added later
- Accept/reject reason

After approval:

- Trader account is created.
- Shop is created.
- Admin can see the created shop link.

### 2. Admin dashboard

Show:

- Total customers
- Total active traders
- Total active shops
- Total active products
- Orders today
- Revenue this week
- Pending trader requests

### 3. Data health checks

Admin should be able to detect:

- Shops with fewer than required products
- Products without images
- Duplicate product names
- Products with missing price
- Traders with no shops
- Shops with no trader

This is especially useful because your database has several workflows that depend on matching `SYSTEM_USER`, `TRADER`, `SHOP`, and `PRODUCT`.

## 6. Technical Improvements

These make the project easier to maintain and safer to extend.

### 1. Central money formatting

Create one helper for money formatting.

Example:

```php
function money_gbp($value): string {
    return 'GBP ' . number_format((float)$value, 2);
}
```

Use it everywhere instead of writing `GBP ...` manually in each file.

### 2. Central product card component

Product cards appear in multiple places.

Create one reusable renderer for:

- Product image
- Product name
- Price
- Stock label
- View product button

Why this matters:

- Fewer inconsistencies.
- Easier to change the design later.

### 3. Migration tracking

Create a `MIGRATIONS_APPLIED` table.

Purpose:

- Track which SQL migrations were already run.
- Avoid running the same migration accidentally.
- Make moving to another machine/server easier.

### 4. Test checklist

Create a manual test checklist for:

- Customer registration/login
- Browse shops
- Search products
- Add to cart
- Select collection slot
- Checkout
- View invoice
- Trader login
- Trader add/edit product
- Trader reports
- Admin approve/reject trader

### 5. Error logging

Add a simple error log for:

- Oracle connection errors
- Failed checkout
- Failed product save
- Failed trader approval
- Image upload failures

Do not show raw database errors to users.

## 7. Security and Safety

### 1. Password hashing

Passwords should be securely hashed.

Use PHP password helpers:

- `password_hash()`
- `password_verify()`

Avoid storing plain text passwords.

### 2. Role checks

Every protected page should verify the correct role.

Examples:

- Customer pages require customer login where needed.
- Trader pages require trader login.
- Admin pages require admin login.

### 3. CSRF protection

Forms that change data should include CSRF tokens.

Important forms:

- Add to cart
- Remove cart item
- Place order
- Add/edit product
- Save profile
- Admin approve/reject trader

### 4. File upload safety

For product images:

- Accept only image types.
- Rename files safely.
- Limit file size.
- Store outside executable PHP paths if possible.
- Never trust original file names.

## 8. Suggested Build Priority

Recommended next order:

1. Add missing product images.
2. Add product filtering/sorting inside `shop.php`.
3. Add better cart quantity controls.
4. Add customer order tracking status.
5. Improve trader order management by selected shop.
6. Add admin data health checks.
7. Add centralized money/product-card helpers.
8. Add email notifications.
9. Add wishlist.
10. Add review/rating improvements.

## 9. Demo And Tutor Talking Points

Use these points when explaining future improvements:

- The project already supports a marketplace structure: one trader can own multiple shops.
- The customer journey is now cleaner: category browsing shows shops, and search is only for searching.
- The trader portal supports shop-specific product management.
- Future work focuses on reducing customer friction, improving trust, and making trader/admin operations easier.
- The most important next step is improving visual trust through correct product images and richer product details.

## 10. References Used

- Baymard Institute, Checkout UX 2025: https://baymard.com/blog/current-state-of-checkout-ux
- Baymard Institute, Desktop Ecommerce UX 2025: https://baymard.com/blog/desktop-ux-ecommerce
- Baymard Institute, Product Page UX collection: https://baymard.com/blog/collections/product-page
- Stripe, Ecommerce checkout best practices: https://stripe.com/resources/more/ecommerce-checkout-best-practices
- Stripe, Checkout optimization tips: https://stripe.com/us/resources/more/checkout-optimization-tips-to-improve-conversion-rates
- W3C, WCAG 2.2: https://www.w3.org/TR/WCAG22/
