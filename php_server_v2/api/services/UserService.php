<?php

namespace App\Services;

class UserService
{
    private $conn;
    
    public function __construct($conn)
    {
        $this->conn = $conn;
    }
    
    public function createUser(string $email, string $password, string $name): array
    {
        // Check if email exists
        $existing = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT id FROM users WHERE email = :email',
            'params' => [':email' => $email]
        ]);
        
        if (!empty($existing)) {
            throw new \Exception('Email already registered');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $result = RunQuery([
            'conn' => $this->conn,
            'query' => 'INSERT INTO users (email, password, name, created_at) 
                       VALUES (:email, :password, :name, NOW())',
            'params' => [
                ':email' => $email,
                ':password' => $hashedPassword,
                ':name' => $name
            ],
            'withSuccess' => true
        ]);
        
        return [
            'user_id' => $result['id'],
            'email' => $email,
            'name' => $name
        ];
    }
    
    public function getUserById(int $id): ?array
    {
        $users = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT id, email, name, created_at FROM users WHERE id = :id',
            'params' => [':id' => $id]
        ]);
        
        return !empty($users) ? $users[0] : null;
    }
}
