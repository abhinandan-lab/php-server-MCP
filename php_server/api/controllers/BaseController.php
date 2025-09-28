<?php

namespace App\Controllers;

use App\Core\Security;

abstract class BaseController
{
    protected $conn;

    public function __construct()
    {
        
        // Security is already initialized in index.php, just ensure it's secure
        Security::ensureSecure();
        
        // Initialize database connection
        require_once __DIR__ . '/../connection.php';
        $this->conn = $connpdo;
    }

    // Keep all your existing methods exactly the same
    protected function sendSuccess(string $message, $data = null, array $extra = null): void
    {
        sendJsonResponse(200, 'success', $message, $data, $extra);
    }

    protected function sendError(string $message, int $statusCode = 400, $data = null): void
    {
        sendJsonResponse($statusCode, 'error', $message, $data);
    }

    protected function sendValidationError(string $message, array $errors = []): void
    {
        $data = !empty($errors) ? ['validation_errors' => $errors] : null;
        sendJsonResponse(422, 'error', $message, $data);
    }

    protected function sendServerError(string $message = 'Internal server error'): void
    {
        sendJsonResponse(500, 'error', $message);
    }

    protected function validateRequired(array $data, array $required): array
    {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

    protected function sanitizeInput($input)
    {
        if (is_string($input)) {
            return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
        
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        return $input;
    }

    protected function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = $_POST;
        }
        
        return $this->sanitizeInput($data ?? []);
    }

    protected function getQueryParams(): array
    {
        return $this->sanitizeInput($_GET);
    }
}
