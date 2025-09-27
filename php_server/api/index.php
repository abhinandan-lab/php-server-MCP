<?php

namespace App;


$allowedOrigins = [
    "http://localhost:3000",
    "http://localhost:5173",
    "http://127.0.0.1:5173",
    "http://localhost:5500",
    "http://127.0.0.1:5500",
    "http://192.168.1.82:5173",
    "https://creator-catalyst-admin.vercel.app",
    "https://creator-catalyst-client-dashboard-d.vercel.app",
    "https://localhost:80"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], haystack: $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

// Always allow these headers & methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, client_request_url");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'Router.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/DocsController.php';
require_once 'controllers/InitController.php';



// Check debug mode
if (!empty($_ENV['SHOW_ERRORS']) && ($_ENV['SHOW_ERRORS'] === 'true' || $_ENV['SHOW_ERRORS'] === '1')) {
    // Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/php-error.log');
} else {
    // Hide errors (but still log if logging is configured in php.ini)
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}



$router = new Router('/api');
// SYSTEM ROUTES
$router->add('POST', '/run_migration', 'InitController@migrateFromFile', 'Runs DB migration', visible: true);


// this routes used to handle env variables in docsController
$router->add('GET', '/env/get', 'DocsController@getEnvironment', 'Get environment variables', false);
$router->add('POST', '/env/update', 'DocsController@updateEnvironment', 'Update environment variable', false);
$router->add('POST', '/env/add', 'DocsController@addEnvironment', 'Add environment variable', false);



// GET ROUTES
$router->add('GET', '/', 'AuthController@welcome', 'API welcome', visible: true, inputs: [], showHeaders: false, tags: ['Testing']);
$router->add('GET', '/test', 'AuthController@test', 'testing api', visible: true, inputs: [], showHeaders: false, tags: ['Testing']);
$router->add('GET', '/docs', 'DocsController@index', 'Interactive API documentation', false);

// Run the router
header('Content-Type: application/json');



$router->dispatch();
