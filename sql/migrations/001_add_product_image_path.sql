ALTER TABLE PRODUCT ADD IMAGE_PATH VARCHAR2(255);

COMMENT ON COLUMN PRODUCT.IMAGE_PATH IS 'Relative path to product image file, for example uploads/products/product_12.webp';

