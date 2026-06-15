<?php

// Check if running on Vercel or similar read-only serverless environment
$sourceDb = __DIR__ . '/../database/database.sqlite';
$targetDb = '/tmp/database.sqlite';

if (!file_exists($targetDb)) {
    if (file_exists($sourceDb)) {
        copy($sourceDb, $targetDb);
        // Also make sure it's writable
        chmod($targetDb, 0666);
    } else {
        // Create an empty database file if it doesn't exist
        touch($targetDb);
        chmod($targetDb, 0666);
    }
}

// Helper to set environment variables in putenv, $_ENV, and $_SERVER
function set_env_var($key, $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Set up serverless defaults
if (!getenv('APP_KEY') && empty($_ENV['APP_KEY']) && empty($_SERVER['APP_KEY'])) {
    set_env_var('APP_KEY', 'base64:11B1hgXGTh4/ddJSRda8d16pffA6kidviLr0iVMYRws=');
}

set_env_var('APP_ENV', 'production');
set_env_var('APP_DEBUG', 'true');
set_env_var('DB_CONNECTION', 'sqlite');
set_env_var('DB_DATABASE', $targetDb);
set_env_var('SESSION_DRIVER', 'cookie');
set_env_var('CACHE_STORE', 'array');
set_env_var('LOG_CHANNEL', 'stderr');
set_env_var('VIEW_COMPILED_PATH', '/tmp');

// Redirect all bootstrap caches to /tmp since /var/task/user/bootstrap/cache is read-only
set_env_var('APP_SERVICES_CACHE', '/tmp/services.php');
set_env_var('APP_PACKAGES_CACHE', '/tmp/packages.php');
set_env_var('APP_CONFIG_CACHE', '/tmp/config.php');
set_env_var('APP_ROUTES_CACHE', '/tmp/routes-v7.php');
set_env_var('APP_EVENTS_CACHE', '/tmp/events.php');

// Forward Vercel requests to Laravel's public/index.php
try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    echo "API BOOTSTRAP EXCEPTION:\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
