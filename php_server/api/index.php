<?php

// Enable error reporting first (before autoloader)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$allowedOrigins = [];

// CORS Logic (keep your existing CORS code)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (empty($allowedOrigins)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Credentials: true");
    } else {
        if (in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
            header("Access-Control-Allow-Credentials: true");
        }
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, client_request_url");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load autoloader and environment
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
use Dotenv\Dotenv;
use App\Router;
use App\Core\ExceptionHandler;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Register global exception handler
ExceptionHandler::register();

// Now check debug mode from properly loaded env
if (!empty($_ENV['SHOW_ERRORS']) && ($_ENV['SHOW_ERRORS'] === 'true' || $_ENV['SHOW_ERRORS'] === '1')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/php-error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}



// Add this after loading environment variables and before creating router
use App\Core\Security;

// Initialize security first
Security::initialize();



// Create router with namespace
$router = new Router('/api');

// SYSTEM ROUTES
$router->add('POST', '/run_migration', 'InitController@migrateFromFile', 'Runs DB migration', visible: true);

// Environment routes
$router->add('GET', '/env/get', 'DocsController@getEnvironment', 'Get environment variables', false);
$router->add('POST', '/env/update', 'DocsController@updateEnvironment', 'Update environment variable', false);
$router->add('POST', '/env/add', 'DocsController@addEnvironment', 'Add environment variable', false);

// GET ROUTES
$router->add('GET', '/', 'AuthController@welcome', 'API welcome', visible: true, inputs: [], showHeaders: false, tags: ['Testing']);
$router->add('GET', '/test', 'AuthController@test2', 'testing api', visible: true, inputs: [], showHeaders: false, tags: ['Testing']);

// Add these new test routes after your existing routes
$router->add('GET', '/test-exception', 'AuthController@testException', 'Test exception handling', true, [], false, ['Testing']);
$router->add('POST', '/test-validation', 'AuthController@testValidation', 'Test validation', true, ['email', 'password', 'name'], false, ['Testing']);

$router->add('GET', '/docs', 'DocsController@index', 'Interactive API documentation', false);

// Run the router
// header('Content-Type: application/json');
$router->dispatch();
