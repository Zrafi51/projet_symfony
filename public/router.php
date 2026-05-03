<?php

/**
 * PHP built-in server router.
 *
 * Use it with:
 *     php -S localhost:8000 -t public public/router.php
 *
 * If the URI maps to an existing file inside `public/`, return `false` so the
 * built-in server serves it as a static file (with the correct MIME type).
 * Otherwise, hand the request off to Symfony's front controller.
 *
 * This is what `symfony server:start` does internally — without it, requests
 * for AssetMapper assets (/assets/...) get routed through Symfony, which has
 * no controller for them and returns 404 HTML, which the browser then
 * rejects as a JS module.
 */

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
