<?php
/**
 * Framework Bootstrap File
 * 
 * Entry point for the lightweight PHP framework
 * Handles initialization, routing, and request dispatching
 */

// ============================================
// 1. START OUTPUT BUFFERING
// ============================================
ob_start();

// ============================================
// 2. ERROR REPORTING CONFIGURATION
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ============================================
// 3. LOAD COMPOSER AUTOLOADER
// ============================================
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    http_response_code(500);
    die(json_encode(['error' => 'Composer autoload not found. Run: composer install']));
}

// ============================================
// 4. LOAD ENVIRONMENT VARIABLES
// ============================================
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => '.env file not found or invalid']));
}

// ============================================
// 5. DEFINE PATHS
// ============================================
define('ROOT_PATH', dirname(__DIR__));
define('API_PATH', __DIR__);
define('CORE_PATH', API_PATH . '/core');
define('CONTROLLERS_PATH', API_PATH . '/controllers');
define('SERVICES_PATH', API_PATH . '/services');
define('HELPERS_PATH', API_PATH . '/helpers');
define('MIDDLEWARE_PATH', API_PATH . '/middleware');
define('REPOSITORY_PATH', API_PATH . '/repository');
define('ROUTERS_PATH', API_PATH . '/routers');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', API_PATH . '/logs');

// ============================================
// 6. AUTO-LOAD HELPERS
// ============================================
if (file_exists(CORE_PATH . '/_autoload_helpers.php')) {
    require_once CORE_PATH . '/_autoload_helpers.php';
}

// ============================================
// 7. AUTO-LOAD ALL HELPER FILES
// ============================================
if (is_dir(HELPERS_PATH)) {
    foreach (glob(HELPERS_PATH . '/*.php') as $helperFile) {
        require_once $helperFile;
    }
}

// ============================================
// 8. AUTO-LOAD CORE FILES
// ============================================
$coreFiles = [
    CORE_PATH . '/Config.php',
    CORE_PATH . '/Security.php',
    CORE_PATH . '/ExceptionHandler.php',
    CORE_PATH . '/Router.php',
];

foreach ($coreFiles as $coreFile) {
    if (file_exists($coreFile)) {
        require_once $coreFile;
    }
}

// ============================================
// 9. NAMESPACE IMPORTS
// ============================================
use App\Core\Security;
use App\Core\ExceptionHandler;
use App\Core\Router;
use App\Middleware\SecurityMiddleware;

// ============================================
// 10. INITIALIZE SECURITY
// ============================================
if (class_exists('App\Core\Security')) {
    Security::initialize();
}

// ============================================
// 11. APPLY SECURITY HEADERS & CORS
// ============================================
if (class_exists('App\Middleware\SecurityMiddleware')) {
    SecurityMiddleware::applySecurityHeaders();
    SecurityMiddleware::handleCORS();
    
    // Rate limiting: 100 requests per hour
    SecurityMiddleware::rateLimiting(100, 3600);
}

// ============================================
// 12. REGISTER EXCEPTION HANDLER
// ============================================
if (class_exists('App\Core\ExceptionHandler')) {
    ExceptionHandler::register();
}

// ============================================
// 13. CONFIGURE ERROR REPORTING BASED ON ENV
// ============================================
$showErrors = !empty($_ENV['SHOW_ERRORS']) && 
              ($_ENV['SHOW_ERRORS'] === true || 
               $_ENV['SHOW_ERRORS'] === '1' || 
               $_ENV['SHOW_ERRORS'] === 'true');

$logErrors = !empty($_ENV['LOG_ERRORS']) && 
             ($_ENV['LOG_ERRORS'] === true || 
              $_ENV['LOG_ERRORS'] === '1' || 
              $_ENV['LOG_ERRORS'] === 'true');

if ($showErrors) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

if ($logErrors) {
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/php-error.log');
}

// ============================================
// 14. SET TIMEZONE
// ============================================
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'UTC');

// ============================================
// 15. INITIALIZE ROUTER
// ============================================
$router = new Router();

// ============================================
// 16. LOAD ROUTE FILES
// ============================================
$routeFiles = [
    ROUTERS_PATH . '/api.php',
    ROUTERS_PATH . '/web.php',
    ROUTERS_PATH . '/devtools.php',
];

foreach ($routeFiles as $routeFile) {
    if (file_exists($routeFile)) {
        require_once $routeFile;
    }
}

// ============================================
// 17. EXECUTE PERFORMANCE TRACKING (Optional)
// ============================================
$startTime = microtime(true);
$startMemory = memory_get_usage();

// ============================================
// 18. DISPATCH REQUEST
// ============================================
try {
    $router->dispatch();
} catch (Exception $e) {
    ExceptionHandler::handle($e);
}

// ============================================
// 19. PERFORMANCE METRICS (Development Only)
// ============================================
if ($showErrors) {
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    $memoryUsed = round((memory_get_usage() - $startMemory) / 1024, 2);
    
    // Add performance headers
    header("X-Execution-Time: {$executionTime}ms");
    header("X-Memory-Used: {$memoryUsed}KB");
}

// ============================================
// 20. FLUSH OUTPUT BUFFER
// ============================================
ob_end_flush();
