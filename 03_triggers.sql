create or replace TRIGGER product_bi
BEFORE INSERT ON product
FOR EACH ROW
BEGIN
  IF :NEW.product_id IS NULL THEN
    SELECT products_seq.NEXTVAL
    INTO :NEW.product_id
    FROM dual;
  END IF;
END;
/
create or replace TRIGGER trg_admin_default
BEFORE INSERT ON ADMIN
FOR EACH ROW
BEGIN
    -- Set default role
    IF :NEW.admin_role IS NULL THEN
        :NEW.admin_role := 'Manager';
    END IF;

    -- Set default permissions
    IF :NEW.permissions IS NULL THEN
        :NEW.permissions := 'Full Access';
    END IF;
END;
/
create or replace TRIGGER trg_cartitem_set_price
BEFORE INSERT ON CART_ITEM
FOR EACH ROW
DECLARE
    v_price PRODUCT.price%TYPE;
BEGIN
    SELECT price
    INTO v_price
    FROM PRODUCT
    WHERE product_id = :NEW.product_id;

    :NEW.price := v_price;
END;
/
create or replace TRIGGER trg_cartitem_validate
BEFORE INSERT ON CART_ITEM
FOR EACH ROW
BEGIN
    IF :NEW.quantity IS NULL OR :NEW.quantity <= 0 THEN
        RAISE_APPLICATION_ERROR(-20017, 'Quantity must be greater than 0');
    END IF;

    IF :NEW.price IS NULL OR :NEW.price <= 0 THEN
        RAISE_APPLICATION_ERROR(-20018, 'Price must be greater than 0');
    END IF;
END;
/
create or replace TRIGGER trg_cart_created_date
BEFORE INSERT ON CART
FOR EACH ROW
BEGIN
    IF :NEW.created_date IS NULL THEN
        :NEW.created_date := SYSTIMESTAMP;
    END IF;
END;
/
create or replace TRIGGER trg_category_validate_name
BEFORE INSERT ON CATEGORY
FOR EACH ROW
BEGIN
    IF :NEW.category_name IS NULL THEN
        RAISE_APPLICATION_ERROR(-20007, 'Category name is required');
    END IF;
END;
/
create or replace TRIGGER trg_check_collection_time
BEFORE INSERT ON ORDERS
FOR EACH ROW
DECLARE
    v_collection_date DATE;
BEGIN
    SELECT collection_date INTO v_collection_date
    FROM COLLECTION_SLOT
    WHERE slot_id = :NEW.slot_id;

    IF v_collection_date < SYSDATE + 1 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Collection must be at least 24 hours later');
    END IF;
END;
/
create or replace TRIGGER trg_check_min_max_order
BEFORE INSERT OR UPDATE ON ORDER_ITEM
FOR EACH ROW
DECLARE
    v_min NUMBER;
    v_max NUMBER;
BEGIN
    SELECT min_order, max_order INTO v_min, v_max
    FROM PRODUCT
    WHERE product_id = :NEW.product_id;

    IF :NEW.quantity < v_min OR :NEW.quantity > v_max THEN
        RAISE_APPLICATION_ERROR(-20002, 'Quantity outside allowed range');
    END IF;
END;
/
create or replace TRIGGER trg_check_slot_capacity
BEFORE INSERT ON ORDERS
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count
    FROM ORDERS
    WHERE slot_id = :NEW.slot_id;

    IF v_count >= 20 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Slot is full');
    END IF;
END;
/
create or replace TRIGGER trg_check_stock
BEFORE INSERT OR UPDATE ON ORDER_ITEM
FOR EACH ROW
DECLARE
    v_stock NUMBER;
BEGIN
    SELECT stock_quantity INTO v_stock
    FROM PRODUCT
    WHERE product_id = :NEW.product_id;

    IF :NEW.quantity > v_stock THEN
        RAISE_APPLICATION_ERROR(-20001, 'Not enough stock available');
    END IF;
END;
/
create or replace TRIGGER trg_customer_loyalty
BEFORE INSERT ON CUSTOMER
FOR EACH ROW
BEGIN
    IF :NEW.loyalty_points IS NULL THEN
        :NEW.loyalty_points := 0;
    END IF;
END;
/
create or replace TRIGGER trg_customer_validate_user
BEFORE INSERT ON CUSTOMER
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM SYSTEM_USER
    WHERE user_id = :NEW.user_id;

    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'User does not exist in SYSTEM_USER');
    END IF;
END;
/
create or replace TRIGGER trg_discount_validate_dates
BEFORE INSERT ON DISCOUNT
FOR EACH ROW
BEGIN
    IF :NEW.start_date IS NOT NULL AND :NEW.end_date IS NOT NULL THEN
        IF :NEW.end_date < :NEW.start_date THEN
            RAISE_APPLICATION_ERROR(-20027, 'End date cannot be before start date');
        END IF;
    END IF;
END;
/
create or replace TRIGGER trg_discount_validate_rate
BEFORE INSERT ON DISCOUNT
FOR EACH ROW
BEGIN
    IF :NEW.discount_rate IS NULL OR :NEW.discount_rate < 0 OR :NEW.discount_rate > 100 THEN
        RAISE_APPLICATION_ERROR(-20026, 'Discount rate must be between 0 and 100');
    END IF;
END;
/
create or replace TRIGGER trg_orders_default_status
BEFORE INSERT ON ORDERS
FOR EACH ROW
BEGIN
    IF :NEW.status IS NULL THEN
        :NEW.status := 'Pending';
    END IF;
END;
/
create or replace TRIGGER trg_paymentmethod_validate
BEFORE INSERT ON PAYMENT_METHOD
FOR EACH ROW
BEGIN
    IF :NEW.method_name IS NULL THEN
        RAISE_APPLICATION_ERROR(-20022, 'Payment method name is required');
    END IF;
END;
/
create or replace TRIGGER trg_payment_default_status
BEFORE INSERT ON PAYMENT
FOR EACH ROW
BEGIN
    IF :NEW.payment_status IS NULL THEN
        :NEW.payment_status := 'Pending';
    END IF;
END;
/
create or replace TRIGGER trg_payment_set_date
BEFORE INSERT ON PAYMENT
FOR EACH ROW
BEGIN
    IF :NEW.payment_date IS NULL THEN
        :NEW.payment_date := SYSTIMESTAMP;
    END IF;
END;
/
create or replace TRIGGER trg_productdiscount_unique
BEFORE INSERT ON PRODUCT_DISCOUNT
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM PRODUCT_DISCOUNT
    WHERE product_id = :NEW.product_id
      AND discount_id = :NEW.discount_id;

    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20028, 'This product already has this discount');
    END IF;
END;
/
create or replace TRIGGER trg_productdiscount_validate_date
BEFORE INSERT ON PRODUCT_DISCOUNT
FOR EACH ROW
DECLARE
    v_start DATE;
    v_end DATE;
BEGIN
    SELECT start_date, end_date
    INTO v_start, v_end
    FROM DISCOUNT
    WHERE discount_id = :NEW.discount_id;

    IF v_start IS NOT NULL AND v_end IS NOT NULL THEN
        IF SYSDATE < v_start OR SYSDATE > v_end THEN
            RAISE_APPLICATION_ERROR(-20029, 'Discount is not active');
        END IF;
    END IF;
END;
/
create or replace TRIGGER trg_product_allergy
BEFORE INSERT ON PRODUCT
FOR EACH ROW
BEGIN
    -- If allergy_info is empty, assign default
    IF :NEW.allergy_info IS NULL THEN
        :NEW.allergy_info := 'No known allergies';
    END IF;
END;
/
create or replace TRIGGER trg_product_expiry
BEFORE INSERT ON PRODUCT
FOR EACH ROW
BEGIN
    IF :NEW.expiry_date IS NOT NULL AND :NEW.expiry_date < SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20016, 'Expiry date cannot be in the past');
    END IF;
END;
/
create or replace TRIGGER trg_product_validate
BEFORE INSERT ON PRODUCT
FOR EACH ROW
BEGIN
    IF :NEW.product_name IS NULL THEN
        RAISE_APPLICATION_ERROR(-20012, 'Product name is required');
    END IF;

    IF :NEW.price IS NULL OR :NEW.price <= 0 THEN
        RAISE_APPLICATION_ERROR(-20013, 'Price must be greater than 0');
    END IF;

    IF :NEW.stock_quantity IS NULL OR :NEW.stock_quantity < 0 THEN
        RAISE_APPLICATION_ERROR(-20014, 'Stock quantity cannot be negative');
    END IF;

    IF :NEW.min_order IS NOT NULL AND :NEW.max_order IS NOT NULL THEN
        IF :NEW.min_order > :NEW.max_order THEN
            RAISE_APPLICATION_ERROR(-20015, 'Minimum order cannot be greater than maximum order');
        END IF;
    END IF;
END;
/
create or replace TRIGGER trg_reduce_stock
AFTER INSERT ON ORDER_ITEM
FOR EACH ROW
BEGIN
    UPDATE PRODUCT
    SET stock_quantity = stock_quantity - :NEW.quantity
    WHERE product_id = :NEW.product_id;
END;
/
create or replace TRIGGER trg_review_validate_rating
BEFORE INSERT ON REVIEW
FOR EACH ROW
BEGIN
    IF :NEW.rating IS NULL OR :NEW.rating < 1 OR :NEW.rating > 5 THEN
        RAISE_APPLICATION_ERROR(-20025, 'Rating must be between 1 and 5');
    END IF;
END;
/
create or replace TRIGGER trg_shop_unique_name
BEFORE INSERT ON SHOP
FOR EACH ROW
DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM SHOP
    WHERE LOWER(shop_name) = LOWER(:NEW.shop_name);

    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20011, 'Shop name already exists');
    END IF;
END;
/
create or replace TRIGGER trg_system_user_id
BEFORE INSERT ON SYSTEM_USER
FOR EACH ROW
BEGIN
    IF :NEW.user_id IS NULL THEN
        SELECT seq_system_user.NEXTVAL
        INTO :NEW.user_id
        FROM dual;
    END IF;
END;
/
create or replace TRIGGER trg_trader_license_check
BEFORE INSERT ON TRADER
FOR EACH ROW
BEGIN
    IF :NEW.license_no IS NULL THEN
        RAISE_APPLICATION_ERROR(-20005, 'License number is required');
    END IF;
END;
/
create or replace TRIGGER trg_trader_validate_business
BEFORE INSERT ON TRADER
FOR EACH ROW
BEGIN
    IF :NEW.business_name IS NULL THEN
        RAISE_APPLICATION_ERROR(-20006, 'Business name is required');
    END IF;
END;
/