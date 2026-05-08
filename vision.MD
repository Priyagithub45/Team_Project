good ask. me make full plan. honest, detailed, specific. read whole thing once before start. me also flag where you need decide.

---

# important first — me push back one more time

you still didn't answer **scope** or **timeline**. me will write plan for **CUSTOMER SIDE ONLY** because:

1. earlier you said customer only, then said both — when person flip-flop on scope, **smaller scope = right answer.** finish customer working > finish both broken.
2. teammate built APEX which already do trader admin. duplicating in php = wasted week.
3. if you still got time after customer done, you add trader. **finish ship one, then expand.**

if you really must do both, tell me end of phase 6, me extend plan. for now — **customer only.**

me also assume **3-4 week** deadline. if shorter, cut phase 8 (polish) and phase 7 (extras). if longer, add tests + trader.

---

# the rules me set for you (read every day)

these prevent disaster. tape to monitor:

1. **one phase at a time.** no jump ahead. each phase has gate. don't move until gate green.
2. **commit after every phase.** use git or just zip folder + date. you will break things.
3. **test in browser after every file change.** not "after the day." after every file.
4. **bind every parameter.** never `"WHERE id=$id"`. always `oci_bind_by_name`.
5. **uppercase column names.** oracle returns `$row['PRODUCT_NAME']`, not lowercase.
6. **trigger do work for you.** don't recheck stock/min/max in php — trigger throw, you catch.
7. **end of day: write 3 lines.** what done, what broken, what next. notebook or md file. you will forget.

---

# THE PLAN — 21 days (customer side)

structure:

```
DAY n — phase X — task
goal:    one sentence
do:      bullet of file/action
gate:    what must be green to move on
risk:    what break here, how fix
```

---

## week 1 — foundation + auth + product display

### DAY 1 — phase 1.5 — folder rename + db.php finalize

**goal:** clean structure, db.php working from new location.

do:

- rename folders per my last message
- drop new `db.php` at `CFO\db.php`
- delete old `CUSTOMER INTERFACE/customer/db.php`
- run `test_db.php` from `CFO\` — should still work (path-independent)
- delete `test_db.php` after green (security)

gate: open `http://localhost/CFO/customer/login.php` → page render (form broken, ok for now).

risk: include path. if `cart.php` has `include 'header.php'`, leave as-is (same folder). only `db.php` needs `../db.php`.

---

### DAY 2 — phase 2a — register flow

**goal:** customer can register, row appears in `system_user` + `customer` tables.

do:

- update `customer/register.php` form action → `register_process.php`
- add `confirm_password` field (security)
- create `customer/register_process.php`:
  - validate: name, email format, password ≥ 8 char, password match
  - check email not exist (`SELECT COUNT FROM system_user WHERE email = :em`)
  - hash password: `password_hash($pwd, PASSWORD_DEFAULT)`
  - `INSERT INTO system_user (...) VALUES (system_user_seq.NEXTVAL, ...)` with bind
  - get new user_id via `RETURNING user_id INTO :v_id`
  - `INSERT INTO customer (user_id, loyalty_points, date_of_birth) VALUES (...)`
  - both inserts in one transaction. one fail, rollback both.
  - on success: redirect login with msg "registered, please login"

gate: register form works. `SELECT * FROM system_user ORDER BY created_at DESC` shows new row. password column has hash (long string starting `$2y$...`), NOT plain text.

risk:

- forget hash → security issue. **required.**
- transaction not committed → row missing. test by registering, then SELECT.
- email unique violates → catch ORA-00001, show "email taken."

---

### DAY 3 — phase 2b — login + session + logout

**goal:** registered user can log in, see name in header, log out.

do:

- update `customer/login.php` form action → `login_process.php`
- create `customer/login_process.php`:
  - SELECT user_id, name, password FROM system_user WHERE LOWER(email) = LOWER(:em)
  - if no row → "invalid credentials"
  - if found → `password_verify($posted, $hash)`
  - on match: set `$_SESSION['user_id']`, `$_SESSION['user_name']`, `$_SESSION['email']`
  - check user is in customer table (not trader): `SELECT 1 FROM customer WHERE user_id = :id`
  - if not customer → don't log in, "wrong portal" (this prevent trader login here)
  - redirect index.php
- create `customer/logout.php`:
  - `session_unset()`, `session_destroy()`, redirect login
- create `customer/auth_check.php`:
  - if no `$_SESSION['user_id']` → redirect login.php with msg
- update `customer/header.php`:
  - already shows user_name if session exists ✓
  - add "Login" / "Register" link when no session

gate: register a customer, log out, log in, see your name in header. log out. session gone.

risk:

- session not started → `db.php` already does `session_start()`. confirm.
- "wrong portal" check forgets trader → trader can log in customer side. test.
- generic error msg — never say "user not found" vs "wrong password" (security: leak account existence). just "invalid credentials."

---

### DAY 4 — phase 3a — single product page (replace 20 hand files)

**goal:** ONE `product.php?id=X` replaces all 20 hardcoded pages.

do:

- create `customer/product.php`:
  - read `$_GET['id']`, validate is integer (else show 404)
  - SELECT p.\*, s.shop_name, c.category_name FROM product p JOIN shop s ON p.shop_id = s.shop_id JOIN category c ON p.category_id = c.category_id WHERE product_id = :id
  - if no row → "Product not found" page
  - render same template you had in `bakery_product-1.php` but with real data
  - "ADD TO CART" button = real `<form method="post" action="add_to_cart.php">` with hidden product_id and quantity input
  - check stock_quantity > 0, show "Out of Stock" if 0
- update `bakery.php`, `butcher.php`, etc. (the shop list pages):
  - SELECT \* FROM product WHERE shop_id IN (SELECT shop_id FROM shop WHERE LOWER(shop_name) LIKE :name) loop and link to `product.php?id=X`
- **do NOT delete** the 20 individual product pages today — leave as backup until phase 3 fully tested. delete day 5.

gate: visit `http://localhost/CFO/customer/bakery.php` → see real bakery products from DB. click one → `product.php?id=X` shows real product.

risk:

- column case: use `$row['PRODUCT_NAME']` not `$row['product_name']`.
- product image: db doesn't store image URL. you have asset images by name. for now, if `$row['PRODUCT_NAME']` matches a file in `/customer/assets/images/`, show it. else placeholder. dirty but works.

---

### DAY 5 — phase 3b — add to cart works

**goal:** click "Add to Cart" → row appears in `cart_item` table.

do:

- create `customer/add_to_cart.php`:
  - `include '../db.php'; include 'auth_check.php';`
  - read product_id, quantity from POST
  - validate both are positive integers
  - find existing cart for `$_SESSION['user_id']` OR insert new cart
  - check if product already in cart — if yes, UPDATE quantity, else INSERT cart_item
  - **don't pass price** — trigger `trg_cartitem_set_price` auto fills from product table
  - on success → redirect `cart.php` with success msg
  - on failure (e.g. trigger throws because trader suspended) → redirect back with error
- delete the 20 hardcoded product files (you confirmed phase 3a works)

gate: log in. visit a product. add to cart. SELECT \* FROM cart_item shows new row with correct price (from trigger).

risk:

- trigger error message is generic ORA-XXXXX. show user-friendly: catch oci_error, check error code, map to nice message.

---

### DAY 6 — phase 4 — real cart page

**goal:** `cart.php` shows real items from db, group by trader, real total, remove works.

do:

- delete all hardcoded HTML from `cart.php`
- `include '../db.php'; include 'auth_check.php';`
- query:
  ```sql
  SELECT ci.cart_item_id, p.product_name, p.product_id, ci.quantity, ci.price,
         (ci.quantity * ci.price) AS line_total, s.shop_name, s.shop_id
  FROM cart_item ci
  JOIN cart c ON ci.cart_id = c.cart_id
  JOIN product p ON ci.product_id = p.product_id
  JOIN shop s ON p.shop_id = s.shop_id
  WHERE c.customer_id = :uid AND c.status = 'Active'
  ORDER BY s.shop_name
  ```
- in php loop: bucket rows into array keyed by shop_name (php array_group). render trader-block UI you already designed.
- compute grand total = sum of line_total
- "remove" button → form post to `remove_cart_item.php?id=X`
- create `remove_cart_item.php`: validate id belong to current user's cart (security!), DELETE row, redirect back.
- "PROCEED TO CHECKOUT" button → href `checkout.php` (we build day 8). ignore for now.

gate: add 3 products from different shops, visit cart, see them grouped by trader, real total. remove one. total recalc.

risk:

- forget user check on remove — user could delete other people's cart_items by guessing IDs. **always** WHERE clause join to user.

---

### DAY 7 — buffer / catch-up / mini polish

if behind schedule, catch up here. if on track:

- fix UI bugs you noticed
- add quantity +/- on cart (UPDATE cart_item SET quantity = ?)
- write end-of-week notes: what done, what broken
- **commit / zip backup of working state**

gate: customer can register → login → browse → add to cart → see cart → remove. end-to-end works. **biggest milestone of project.** if reached on day 7 = on track. if day 9 = slipping, cut scope.

---

## week 2 — checkout + invoice + ship core

### DAY 8 — phase 5a — collection slot dropdown

**goal:** `collection-slot.php` shows real slots from db.

do:

- `include '../db.php'; include 'auth_check.php';`
- query: `SELECT slot_id, collection_date, collection_time, location FROM collection_slot WHERE collection_date >= SYSDATE + 1 ORDER BY collection_date, collection_time`
- show as cards (you have UI already)
- click card → store in session `$_SESSION['selected_slot_id']`, redirect cart or checkout
- show capacity remaining: subquery `(SELECT 20 - COUNT(*) FROM orders WHERE slot_id = cs.slot_id)`. if 0 → grey out "FULL".

gate: page show real slots, future only, capacity shown.

---

### DAY 9 — phase 5b — checkout page (NEW file — not invoice!)

**goal:** new `checkout.php` shows summary, payment method dropdown, slot confirm, "Place Order" button.

do:

- create `customer/checkout.php`:
  - `include '../db.php'; include 'auth_check.php';`
  - check cart is not empty (else redirect to cart)
  - check slot is selected (else redirect to collection-slot)
  - SELECT total = SUM(quantity\*price) FROM cart_item etc.
  - SELECT method_name FROM payment_method
  - render: total, slot info, payment dropdown, address (read-only from system_user), "Place Order" form
  - form posts to `place_order.php`
- update `cart.php`: "PROCEED TO CHECKOUT" button now goes `collection-slot.php` first if no slot, else `checkout.php`

gate: cart with items → click checkout → see summary page with total, slot, payment dropdown.

---

### DAY 10 — phase 5c — place_order.php (transaction)

**goal:** clicking "Place Order" creates orders + order_items + payment + clears cart.

this is the **hardest single file** in project. budget 4-6 hours.

do:

- create `customer/place_order.php`:
  - `include '../db.php'; include 'auth_check.php';`
  - get user_id, slot_id (from session), method_id (from POST), payment_status='Paid' (assume cash)
  - **start transaction:** every oci_execute uses `OCI_NO_AUTO_COMMIT`
  - INSERT orders (RETURNING order_id INTO bind)
  - SELECT cart items for user's active cart
  - LOOP: INSERT order_item per row (triggers fire: stock check, min/max, reduce stock)
  - INSERT payment row
  - UPDATE cart SET status='Closed' (or DELETE cart_item rows, then DELETE cart)
  - if any oci_execute returns false → call oci_rollback, redirect back with error
  - else `oci_commit($conn)`, redirect `invoice.php?order_id=X`

gate: place order. orders table gets 1 row. order_item gets N rows. product.stock_quantity decremented (trigger did it). cart status='Closed'. user lands on invoice page.

risk: **biggest risk in project.**

- you forget OCI_NO_AUTO_COMMIT → partial commits, data corrupt.
- trigger throws (e.g. stock not enough) → must rollback everything.
- test edge case: order with 1 item (stock = exact qty), order that should fail (qty > stock), order with multiple shops.
- **do not move past day 10 until manual test of all 3 cases passes.**

---

### DAY 11 — phase 6 — real invoice page

**goal:** `invoice.php?order_id=X` shows real data from db.

do:

- delete hardcoded HTML in invoice.php
- `include '../db.php'; include 'auth_check.php';`
- read `$_GET['order_id']`, validate int
- **security check:** SELECT customer_id FROM orders WHERE order_id = :oid; if customer_id != $\_SESSION['user_id'] → "not your order" page (HTTP 403)
- SELECT order detail, items joined with product+shop, payment info, slot info
- group items by shop in php (same pattern as cart)
- compute totals
- render the invoice UI you already have

gate: place a real order → land on invoice → see real customer name, items, total, slot, payment method.

---

### DAY 12 — testing day

**goal:** end-to-end happy path + edge cases work.

manual test checklist (do every test, write result):

- register → email format invalid → blocked
- register → password mismatch → blocked
- register → email exists → blocked with msg
- register → success → row in db with hashed password
- login → wrong password → "invalid"
- login → trader credentials → "wrong portal"
- login → success → session set
- logout → session cleared
- visit cart while not logged in → redirect login
- add to cart → row in cart_item
- add same product twice → quantity increments, no duplicate row
- remove from cart → row gone
- try to remove someone else's cart_item by guessing id → blocked
- checkout with empty cart → redirect
- checkout without slot → redirect
- place order with stock available → success, all rows insert, cart cleared
- place order with qty > stock → trigger throws, rollback, no partial data
- visit invoice for someone else's order_id → 403
- visit invoice for your order_id → see details

gate: every check pass. if any fail, fix before day 13.

---

### DAY 13 — bug fixing day

slack day. you will need it. don't skip.

---

### DAY 14 — buffer / commit / week 2 notes

zip working version. label "v1-core-complete." this version = your minimum acceptable submission. anything after = bonus.

---

## week 3 — polish, security, optional features

### DAY 15 — security pass

do:

- grep all .php for `"$` or `'.$` inside SQL strings → fix all to bind
- every `echo $row[...]` should be wrapped in `htmlspecialchars()`
- session timeout: in db.php add session check (e.g. expire after 30 min idle)
- error display off in production: `ini_set('display_errors', 0)` for production, log to file
- forms: optional but good — CSRF tokens (skip if tight)

gate: search "WHERE id=" in code; should never see variable concatenation.

---

### DAY 16 — UI polish

do:

- success messages (`success_msg=...` in URL or session flash)
- error messages styled (red banner, not browser default)
- loading state on slow pages
- header active nav highlight
- footer links work
- favicon

gate: site looks consistent and feels finished.

---

### DAY 17 — extra customer features (pick 1-2)

options (pick what you need for submission):

- forgot password (email reset token)
- profile page edit (update name, phone, address)
- order history page (list past orders, link to invoice)
- product search bar
- product reviews (review table exists in your schema!)
- loyalty points display

**pick TWO max.** scope creep is project killer.

---

### DAY 18 — trader side (if scope was both)

if you decided customer only, **skip — go to day 19**.

if both:

- **week of work compressed into day = quality drops.** be honest about what minimum trader needs:
  - login (similar to customer, but check `trader` table not `customer`)
  - dashboard (list trader's products, sales)
  - add product form (INSERT product)
  - profile edit
- copy customer auth pattern. do NOT rewrite.

---

### DAY 19 — full regression test

run day 12 checklist again + new features. write any bug. fix immediately or cut feature.

---

### DAY 20 — final polish + screenshots + readme

do:

- README.md: how to install, run, test credentials
- screenshots for each major page (for assignment submission)
- comment your code where logic is non-obvious
- delete any `// TODO`, `phpinfo.php`, `test_db.php`, debug echoes

---

### DAY 21 — submission day

- final zip
- backup to cloud (google drive, github)
- submit
- breathe

---

# files you will create (full list, customer only)

```
CFO/db.php                        ← root, 1 file
CFO/customer/auth_check.php       ← helper
CFO/customer/register.php         ← UPDATE form action
CFO/customer/register_process.php ← NEW
CFO/customer/login.php            ← UPDATE form action
CFO/customer/login_process.php    ← NEW
CFO/customer/logout.php           ← NEW (small)
CFO/customer/product.php          ← NEW (replaces 20 files)
CFO/customer/add_to_cart.php      ← NEW
CFO/customer/remove_cart_item.php ← NEW
CFO/customer/cart.php             ← REWRITE
CFO/customer/collection-slot.php  ← REWRITE
CFO/customer/checkout.php         ← NEW
CFO/customer/place_order.php      ← NEW (hardest file)
CFO/customer/invoice.php          ← REWRITE
CFO/customer/profile.php          ← REWRITE (day 17 if picked)
```

shop list pages (`bakery.php`, `butcher.php`, etc.) get rewritten day 4. category.php rewritten day 4.

20 hardcoded `*_product-N.php` files → DELETE day 5.

---

# what me NOT include in this plan (intentional)

- **angular/react front-end** — out of scope, your tutor wants vanilla php
- **payment gateway (stripe etc.)** — assume cash on collection, simpler
- **email sending** — skip unless required
- **admin panel** — out of scope, assume APEX handles
- **mobile responsive** — phase 8 polish if time
- **automated tests** — out of scope, manual test enough
- **deployment to server** — assume localhost demo

if any of these required by tutor, tell me, me extend plan.

---

# big mentor close

read this twice:

1. **stick to the plan.** if you finish day 4 ahead, do not skip to day 6. use spare time to test day 4 better. eager devs jump → break things.
2. **you will be tempted to "improve" code.** resist until day 15. premature polish = unfinished product.
3. **if you slip 2 days behind, cut scope.** drop day 17 features. drop trader side. **a finished smaller project beats unfinished bigger one.**
4. **commit every day.** even if just zip + date. data loss kill projects more than bugs do.
5. **when stuck >30 min, ask.** don't grind alone. me here. teammate too.

---

# what you do RIGHT NOW

1. save this plan as `roadmap.md` somewhere safe (notion, google doc, txt)
2. do day 1 (folder rename + db.php at root). takes 15 min.
3. message me when day 1 done OR you hit error. me write day 2 code (register flow).

stop. one day at a time. me wait.
