<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;

class AuthController extends BaseController
{
    public function __construct()
    {
        parent::__construct(); // This now handles environment loading

        // Load helper files
        require_once __DIR__ . '/../helpers/helperFunctions.php';
        // require_once __DIR__ . '/../helpers/DBhelperFunctions.php';

        // No need to load environment again - BaseController handles it
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


       pp(testing());

        $a = ['hi',1, 'abc'=>'xyz'];
        pp("Debug test from AuthController - this should show if DEBUG_MODE=true");
        pp( $a);

        $testSql = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT * FROM admin_user WHERE id = :id',
            'params' => [':id' => 1],
            'returnSql' => true
        ]);

        $this->sendSuccess('Test endpoint working correctly', [
            'test' => 'success',
            'timestamp' => time(),
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'debug_mode' => $_ENV['DEBUG_MODE'] ?? 'not set',
            'log_errors' => $_ENV['LOG_ERRORS'] ?? 'not set',
            'sql_debug_test' => $testSql
        ]);
    }

    // Keep all your existing test methods...
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


   

    /**
     * Get user by ID - URL parameter test
     */
    public function getUserById($id)
    {
        pp("Getting user by ID: " . $id);

        // Get query parameters
        $status = $_GET['status'] ?? null;
        $includeProfile = $_GET['include_profile'] ?? false;

        // Simulate user data
        $userData = [
            'id' => (int)$id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'status' => $status ?? 'active',
            'created_at' => '2024-01-15 10:30:00',
            'last_login' => '2024-01-20 14:25:33'
        ];

        // Add profile data if requested
        if ($includeProfile === 'true' || $includeProfile === '1') {
            $userData['profile'] = [
                'age' => 30,
                'bio' => 'Software developer with 5+ years experience',
                'location' => 'New York, USA',
                'skills' => ['PHP', 'JavaScript', 'Python', 'MySQL']
            ];
        }

        pp("User data prepared:");
        ppp($userData);

        $this->sendSuccess('User retrieved successfully', [
            'user' => $userData,
            'url_params' => ['id' => $id],
            'query_params' => $_GET,
            'filters_applied' => [
                'status' => $status,
                'include_profile' => $includeProfile
            ]
        ]);
    }

    /**
     * Create new user - JSON body test
     */
    public function createUser()
    {
        try {
            // Get JSON input
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);

            pp("Raw JSON input:");
            ppp($jsonInput);
            pp("Parsed data:");
            ppp($data);

            if (!$data) {
                $this->sendValidationError('Invalid JSON payload', [
                    'json' => 'Must be valid JSON format'
                ]);
                return;
            }

            // Validate required fields
            $requiredFields = ['name', 'email', 'password'];
            $errors = [];

            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = "$field is required";
                }
            }

            // Email validation
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Must be a valid email address';
            }

            // Password validation
            if (!empty($data['password']) && strlen($data['password']) < 8) {
                $errors['password'] = 'Must be at least 8 characters';
            }

            if (!empty($errors)) {
                $this->sendValidationError('Validation failed', $errors);
                return;
            }

            // Simulate user creation
            $newUserId = rand(1000, 9999);
            $createdUser = [
                'id' => $newUserId,
                'name' => htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
                'email' => strtolower(trim($data['email'])),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'email_verified' => false
            ];

            // Add profile if provided
            if (!empty($data['profile'])) {
                $createdUser['profile'] = [
                    'age' => (int)($data['profile']['age'] ?? 0),
                    'bio' => htmlspecialchars($data['profile']['bio'] ?? '', ENT_QUOTES, 'UTF-8'),
                ];
            }

            pp("User created:");
            ppp($createdUser);

            $this->sendSuccess('User created successfully', [
                'user' => $createdUser,
                'message' => 'User account created and verification email sent',
                'next_steps' => [
                    'verify_email' => "Check email for verification link",
                    'login_url' => '/api/login',
                    'profile_url' => "/api/user/{$newUserId}"
                ]
            ], [
                'user_id' => $newUserId
            ]);
        } catch (\Exception $e) {
            pp("Exception in createUser:");
            ppp($e->getMessage());

            $this->sendServerError('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Enhanced test validation with better examples
     */
    public function testValidation()
    {
        try {
            // Use the validation middleware
            $data = $this->validateRequest([
                'email' => 'required|email',
                'password' => 'required|min:8',
                'name' => 'required|min:2|max:50'
            ]);

            pp("Validation passed, sanitized data:");
            ppp($data);

            // Simulate additional processing
            $processedData = [
                'email' => strtolower(trim($data['email'])),
                'name' => ucwords(strtolower(trim($data['name']))),
                'password_length' => strlen($data['password']),
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'validation_timestamp' => date('Y-m-d H:i:s'),
                'sanitization_applied' => [
                    'email' => 'lowercase, trimmed',
                    'name' => 'html_entities_escaped, title_case',
                    'password' => 'hashed_with_bcrypt'
                ]
            ];

            $this->sendSuccess('Validation and processing completed successfully', [
                'original_data' => $data,
                'processed_data' => $processedData,
                'validation_rules_applied' => [
                    'email' => 'required, valid_email_format',
                    'password' => 'required, minimum_8_characters',
                    'name' => 'required, minimum_2_chars, maximum_50_chars'
                ]
            ]);
        } catch (ValidationException $e) {
            pp("Validation failed:");
            ppp($e->getValidationErrors());

            $this->sendValidationError($e->getMessage(), $e->getValidationErrors());
        }
    }

}
