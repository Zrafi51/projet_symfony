<?php

$projectDir = dirname(__DIR__);
$appEnv = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$sessionPath = $projectDir.'/var/sessions/'.$appEnv;

if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}

if (is_dir($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}
