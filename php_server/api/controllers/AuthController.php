<?php

use Dotenv\Dotenv;

define('GOODREQ', true);



require __DIR__ . '/../../vendor/autoload.php';

class AuthController
{
    private $conn;

    private $dotenv;

    public function __construct()
    {
        require_once __DIR__ . '/../connection.php';
        require_once __DIR__ . '/../helpers/helperFunctions.php';
        require_once __DIR__ . '/../helpers/DBhelperFunctions.php';
        $this->conn = $connpdo;

        $this->dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $this->dotenv->load();
    }


    public function welcome()
    {
        echo json_encode(['message' => 'Welcome to the boom API 2']);
    }



    public function test()
    {

        echo 'test';
    }
}
