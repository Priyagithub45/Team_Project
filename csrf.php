<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(string $context = 'default'): string
{
    if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (empty($_SESSION['csrf_tokens'][$context])) {
        $_SESSION['csrf_tokens'][$context] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_tokens'][$context];
}

function csrf_field(string $context = 'default'): string
{
    return '<input type="hidden" name="csrf_context" value="' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token($context), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_is_valid(?string $context = null): bool
{
    $context = $context ?: (string)($_POST['csrf_context'] ?? 'default');
    $posted = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_tokens'][$context] ?? '');

    return $posted !== '' && $expected !== '' && hash_equals($expected, $posted);
}

function csrf_require_post(?string $context = null): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!csrf_is_valid($context)) {
        http_response_code(403);
        exit('Security check failed. Please go back, refresh the page, and try again.');
    }
}
