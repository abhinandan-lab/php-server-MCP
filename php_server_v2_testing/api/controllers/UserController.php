<?php

namespace App\Controllers;

use App\Services\UserService;

class UserController extends BaseController
{
    private $userService;
    
    public function __construct()
    {
        parent::__construct();
        
        // Pass database connection to service
        $this->userService = new UserService($this->conn);
    }
    
    public function register()
    {
        // Get input using BaseController method
        $data = $this->getRequestData();
        
        // Validate required fields using BaseController method
        $missing = $this->validateRequired($data, ['email', 'password', 'name']);
        
        if (!empty($missing)) {
            $this->sendValidationError('Missing required fields', $missing);
            return;
        }
        
        try {
            // Call service - all logic happens here
            $user = $this->userService->createUser(
                $data['email'],
                $data['password'],
                $data['name']
            );
            
            // Send success response using BaseController method
            $this->sendSuccess('User registered successfully', $user);
            
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 400);
        }
    }
    
    public function getUser()
    {
        $params = $this->getQueryParams();
        $id = $params['id'] ?? null;
        
        if (!$id) {
            $this->sendError('User ID required', 400);
            return;
        }
        
        try {
            $user = $this->userService->getUserById($id);
            
            if (!$user) {
                $this->sendError('User not found', 404);
                return;
            }
            
            $this->sendSuccess('User found', $user);
            
        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
}
