<?php

// START OUTPUT BUFFERING FIRST - before any other code
ob_start();

// Enable error reporting first
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Load autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
use Dotenv\Dotenv;
use App\Router;
use App\Core\ExceptionHandler;
use App\Core\Security;
use App\Middleware\SecurityMiddleware;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Initialize security first
Security::initialize();

// Apply security headers and CORS BEFORE any other output
SecurityMiddleware::applySecurityHeaders();
SecurityMiddleware::handleCORS();

// Basic rate limiting (100 requests per hour) - BEFORE router creation
SecurityMiddleware::rateLimiting(100, 3600);

// Register global exception handler
ExceptionHandler::register();

// Configure error reporting based on environment
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

// **CREATE ROUTER AFTER MIDDLEWARE**
$router = new Router('/api');




// SYSTEM ROUTES
$router->add([
    'method' => 'POST',
    'url' => '/run_migration',
    'controller' => 'InitController@migrateFromFile',
    'desc' => 'Runs DB migration from uploaded SQL file',
    'visible' => true,
    'params' => [
        'form' => ['sql_file' => 'file (required) – SQL file to execute']
    ],
    'group' => ['System']
]);

// Environment routes
$router->add([
    'method' => 'GET',
    'url' => '/env/get',
    'controller' => 'DocsController@getEnvironment',
    'desc' => 'Get environment variables',
    'visible' => false,
    'group' => ['Environment']
]);

$router->add([
    'method' => 'POST',
    'url' => '/env/update',
    'controller' => 'DocsController@updateEnvironment',
    'desc' => 'Update environment variable',
    'visible' => false,
    'params' => [
        'form' => [
            'key' => 'string (required) – Environment variable key',
            'value' => 'string (required) – Environment variable value'
        ]
    ],
    'group' => ['Environment']
]);

$router->add([
    'method' => 'POST',
    'url' => '/env/add',
    'controller' => 'DocsController@addEnvironment',
    'desc' => 'Add new environment variable',
    'visible' => false,
    'params' => [
        'form' => [
            'key' => 'string (required) – Environment variable key',
            'value' => 'string (required) – Environment variable value'
        ]
    ],
    'group' => ['Environment']
]);

// Main API Routes
$router->add([
    'method' => 'GET',
    'url' => '/',
    'controller' => 'AuthController@welcome',
    'desc' => 'API welcome message with framework info',
    'visible' => true,
    'group' => ['Testing']
]);

$router->add([
    'method' => 'GET',
    'url' => '/test',
    'controller' => 'AuthController@test',
    'desc' => 'Testing endpoint with debug information',
    'visible' => true,
    'group' => ['Testing']
]);

// Documentation route
$router->add([
    'method' => 'GET',
    'url' => '/docs',
    'controller' => 'DocsController@index',
    'desc' => 'Interactive API documentation with testing interface',
    'visible' => true,
    'group' => ['Documentation']
]);

// Test routes with different parameter types
$router->add([
    'method' => 'GET',
    'url' => '/test-exception',
    'controller' => 'AuthController@testException',
    'desc' => 'Test exception handling with different error types',
    'visible' => true,
    'params' => [
        'get' => ['type' => 'string (optional) – validation|database|notfound|generic|fatal']
    ],
    'group' => ['Testing']
]);

$router->add([
    'method' => 'POST',
    'url' => '/test-validation',
    'controller' => 'AuthController@testValidation',
    'desc' => 'Test validation with required fields',
    'visible' => true,
    'params' => [
        'form' => [
            'email' => 'string (required) – Valid email address',
            'password' => 'string (required) – Password (min 8 characters)',
            'name' => 'string (required) – Full name'
        ]
    ],
    'group' => ['Testing']
]);

// Example with URL parameters
$router->add([
    'method' => 'GET',
    'url' => '/user/{id}',
    'controller' => 'AuthController@getUserById',
    'desc' => 'Get user by ID with optional status filter',
    'visible' => true,
    'params' => [
        'url' => ['id' => 'integer (required) – User ID'],
        'get' => [
            'status' => 'string (optional) – active|inactive|pending',
            'include_profile' => 'boolean (optional) – Include profile data'
        ]
    ],
    'group' => ['Testing']
]);

// Example with JSON body
$router->add([
    'method' => 'POST',
    'url' => '/user/create',
    'controller' => 'AuthController@createUser',
    'desc' => 'Create new user with JSON payload',
    'visible' => true,
    'params' => [
        'json' => [
            'name' => 'string (required) – Full name',
            'email' => 'string (required) – Valid email',
            'password' => 'string (required) – Min 8 characters',
            'profile' => 'object (optional) – {age: number, bio: string}'
        ]
    ],
    'group' => ['Testing']
]);


// **VALIDATION TEST ROUTE**
$router->add([
    'method' => 'POST',
    'url' => '/test-validation',
    'controller' => 'AuthController@testValidation',
    'desc' => 'Test validation with required fields and sanitization',
    'visible' => true,
    'params' => [
        'form' => [
            'email' => 'string (required) – Valid email address',
            'password' => 'string (required) – Password (min 8 characters)',
            'name' => 'string (required) – Full name (min 2 characters, max 50)'
        ]
    ],
    'group' => ['Testing']
]);









// Set JSON content type for API responses (except docs)
if ($_SERVER['REQUEST_URI'] !== '/api/docs') {
    header('Content-Type: application/json');
}
$router->dispatch();


// Check if this is the docs route (HTML response)
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isDocsRoute = (strpos($requestUri, '/docs') !== false);

if ($isDocsRoute) {
    // For docs page - flush output (show HTML)
    ob_end_flush();
} else {
    // For API routes - clean output (discard debug text)
    if (!headers_sent()) {
        ob_end_clean();
    }
}