<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Increase upload limits to allow lot of .DEC files in one request (default PHP max_file_uploads is 20).
@ini_set('max_file_uploads', '1000');
@ini_set('post_max_size', '256M');
@ini_set('upload_max_filesize', '128M');

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
