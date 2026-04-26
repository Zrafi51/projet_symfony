<?php
/**
 * Router script for PHP built-in server.
 * Serves static files directly, routes everything else through Symfony.
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $uri;

// Serve actual files (CSS, JS, images, etc.) directly
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Point SCRIPT_FILENAME to index.php so Symfony Runtime works correctly
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';

require $_SERVER['SCRIPT_FILENAME'];
