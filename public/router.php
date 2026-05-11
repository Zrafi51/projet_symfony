<?php

/**
 * Router script for PHP built-in server
 * This ensures all requests are properly routed through Symfony's front controller
 */

// Decode the URL to handle encoded characters
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Check if the request is for a real file (CSS, JS, images, etc.)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Log file access for debugging
    error_log(sprintf('[Router] Serving static file: %s', $uri));
    return false; // Serve the file directly
}

// Log routing through Symfony
error_log(sprintf('[Router] Routing to Symfony: %s %s', $_SERVER['REQUEST_METHOD'], $uri));

// Otherwise, route through Symfony's front controller
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/index.php';
