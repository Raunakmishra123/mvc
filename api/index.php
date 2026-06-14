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

// Set up serverless defaults
if (!getenv('APP_KEY')) {
    putenv('APP_KEY=base64:11B1hgXGTh4/ddJSRda8d16pffA6kidviLr0iVMYRws=');
}
putenv('APP_ENV=production');
putenv('APP_DEBUG=true');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $targetDb);
putenv('SESSION_DRIVER=cookie');
putenv('CACHE_STORE=array');
putenv('LOG_CHANNEL=stderr');
putenv('VIEW_COMPILED_PATH=/tmp');

// Forward Vercel requests to Laravel's public/index.php
require __DIR__ . '/../public/index.php';
