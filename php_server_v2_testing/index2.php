<?php 

echo "This is server entry point | not the framework";
// exit;

 
https://www.perplexity.ai/search/lets-update-the-framework-code-w0HC80eKQaK3hhhrXovbaQ



// Get the current hostname
$currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

// Set connection parameters based on the hostname
if (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false) {
    // Development connection
    // $servername = "db";
    $servername = "pgbouncer";
    // $servername = "postgres";
    $port = "5432"; // Default MySQL port for development
    $username = "abh";
    $password = "abcd";
    $dbname = "testdb";
    // echo "<br>Using DEVELOPMENT environment";
} else {
    // Production connection (for backend or any other domain)
    $servername = "phpmyadmin.coolify.vps.boomlive.in";
    $port = "3303";
    $username = "root";
    $password = "abcd";
    $dbname = "connection_pingnetwork";
    // echo "<br>Using PRODUCTION environment";
}

// PDO connection
try {
    // $dsn = "mysql:host=$servername;port=$port;dbname=$dbname";
    $dsn = "pgsql:host=$servername;port=$port;dbname=$dbname";
    $connpdo = new PDO($dsn, $username, $password);
    // set the PDO error mode to exception
    $connpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<br>Connected successfully to: $servername";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}


?>