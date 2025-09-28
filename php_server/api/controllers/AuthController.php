<?php

namespace App\Controllers;

use Dotenv\Dotenv;
use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;

class AuthController extends BaseController
{
    private $dotenv;

    public function __construct()
    {
        parent::__construct();

        // Only require the helper files that aren't autoloaded
        // These are loaded via composer files autoload, but we can be explicit
        // require_once __DIR__ . '/../helpers/helperFunctions.php';
        // require_once __DIR__ . '/../helpers/DBhelperFunctions.php';

        // Load environment variables
        $this->dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $this->dotenv->load();
    }

    public function welcome()
    {
        $this->sendSuccess('Welcome to the Enhanced API Framework', [
            'framework' => 'PHP Light Framework',
            'version' => '2.0.0',
            'namespace' => 'App\\Controllers',
            'features' => [
                'PSR-4 autoloading',
                'Global helpers (pp, ppp, sendJsonResponse)',
                'Base controller with error handling',
                'Environment-based configuration',
                'Global exception handler',
                'Custom exception classes',
                'Enhanced router with better error handling',
                'Consolidated security system (GOODREQ)',
                'SQL debugging with interpolateQuery function'
            ]
        ]);
    }

    public function test()
    {
        // pp("Debug test from AuthController - this should show if DEBUG_MODE=true");

        // Test the RunQuery function with returnSql option
        $testSql = RunQuery($this->conn, "SELECT * FROM admin_user WHERE id = :id", [':id' => 1], true, false, true);

        $this->sendSuccess('Test endpoint working correctly', [
            'test' => 'success',
            'timestamp' => time(),
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'debug_mode' => $_ENV['DEBUG_MODE'] ?? 'not set',
            'sql_debug_test' => $testSql
        ]);
    }



    public function test2()
    {
        // This should show HTML-styled debug (no JSON headers set yet)
        pp("Debug BEFORE JSON headers - should be HTML styled");
        ppp(["before_json" => true, "data" => "test"]);

        $testSql = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT * FROM admin_user WHERE id = :id',
            'params' => [':id' => 1],
            'returnSql' => true
        ]);

        // This should show plain text debug (JSON headers will be set by sendSuccess)
        pp("Debug AFTER sendSuccess call - should be plain text");
        ppp($testSql);

        testing2();

        $this->sendSuccess('Test complete', $testSql);
    }




    // Keep your existing testException and testValidation methods...
    public function testException()
    {
        $params = $this->getQueryParams();
        $type = $params['type'] ?? 'validation';

        switch ($type) {
            case 'validation':
                throw new ValidationException('Validation failed', [
                    'email' => 'Email is required',
                    'password' => 'Password must be at least 8 characters'
                ]);

            case 'database':
                throw new DatabaseException('Database connection failed');

            case 'notfound':
                throw new NotFoundException('User not found');

            case 'generic':
                throw new \Exception('This is a generic PHP exception');

            case 'fatal':
                // $undefined->someMethod();
                break;

            default:
                $this->sendError('Invalid exception type. Use: validation, database, notfound, generic, fatal');
        }
    }

    public function testValidation()
    {
        $data = $this->getRequestData();

        $required = ['email', 'password', 'name'];
        $missing = $this->validateRequired($data, $required);

        if (!empty($missing)) {
            throw new ValidationException(
                'Missing required fields',
                array_fill_keys($missing, 'This field is required')
            );
        }

        $this->sendSuccess('Validation passed', $data);
    }
}
