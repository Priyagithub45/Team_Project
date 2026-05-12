<?php

function save_product_image_upload(array $file, int $product_id): array
{
    if ($product_id < 1) {
        return ['ok' => false, 'error' => 'Invalid product ID.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Please upload a product image.'];
    }

    $max_bytes = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_bytes) {
        return ['ok' => false, 'error' => 'Product image must be 2MB or smaller.'];
    }

    $tmp_name = $file['tmp_name'] ?? '';
    $mime_type = $tmp_name !== '' ? mime_content_type($tmp_name) : '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime_type])) {
        return ['ok' => false, 'error' => 'Product image must be JPG, PNG, or WEBP.'];
    }

    $upload_dir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        error_log('[IMAGE UPLOAD] Could not create product upload directory: ' . $upload_dir);
        return ['ok' => false, 'error' => 'Could not create product image folder.'];
    }

    $extension = $extensions[$mime_type];
    $relative_path = 'uploads/products/product_' . $product_id . '.' . $extension;
    $target_path = dirname(__DIR__) . '/' . $relative_path;

    if (!move_uploaded_file($tmp_name, $target_path)) {
        error_log('[IMAGE UPLOAD] move_uploaded_file failed: product_id=' . $product_id . ', target=' . $target_path);
        return ['ok' => false, 'error' => 'Could not save product image.'];
    }

    return ['ok' => true, 'path' => $relative_path];
}
