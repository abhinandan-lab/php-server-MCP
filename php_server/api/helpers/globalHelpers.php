<?php

/**
 * Global helper functions that should be available everywhere
 * These functions are auto-loaded via composer.json
 */

/**
 * Smart debug print function - detects JSON vs HTML output
 */
function pp($ke)
{
    if (!empty($_ENV['DEBUG_MODE']) && ($_ENV['DEBUG_MODE'] === 'true' || $_ENV['DEBUG_MODE'] === '1')) {
        
        $backtrace = debug_backtrace()[0];
        $file = $backtrace['file'];
        $line = $backtrace['line'];
        
        // Check if Content-Type is JSON
        $headers = headers_list();
        $isJson = false;
        
        foreach ($headers as $header) {
            if (stripos($header, 'content-type') !== false && stripos($header, 'application/json') !== false) {
                $isJson = true;
                break;
            }
        }
        
        if ($isJson) {
            // JSON output - plain text
            echo "\n\n--- pp ---\n";
            echo "File: " . $file . " | ";
            echo "Line: " . $line . "\n-------------------------------\n";
            // echo "Data: ";
            print_r($ke);
            echo "\n------------------------------\n--- /pp ---\n\n";
        } else {
            // HTML output - styled
            echo "Page: " . $file .
                " __ <span style='color:red'>" . $line . "</span> <pre>";
            print_r($ke);
            echo "</pre>";
        }
    }
}

/**
 * Smart debug var_dump function - detects JSON vs HTML output
 */
function ppp($ke)
{
    if (!empty($_ENV['DEBUG_MODE']) && ($_ENV['DEBUG_MODE'] === 'true' || $_ENV['DEBUG_MODE'] === '1')) {
        
        $backtrace = debug_backtrace()[0];
        $file = $backtrace['file'];
        $line = $backtrace['line'];
        
        // Check if Content-Type is JSON
        $headers = headers_list();
        $isJson = false;
        
        foreach ($headers as $header) {
            if (stripos($header, 'content-type') !== false && stripos($header, 'application/json') !== false) {
                $isJson = true;
                break;
            }
        }
        
        if ($isJson) {
            // JSON output - plain text
            echo "\n\n--- ppp ---\n";
            echo "File: " . $file . " | ";
            echo "Line: " . $line . "\n-------------------------------\n";
            // echo "Data: ";
            var_dump($ke);
            echo "\n------------------------------\n--- /ppp ---\n\n";
        } else {
            // HTML output - styled  
            echo "Page: " . $file .
                " __ <span style='color:red'>" . $line . "</span> <pre>";
            var_dump($ke);
            echo "</pre>";
        }
    }
}

/**
 * Send a standardized JSON response and exit.
 */
function sendJsonResponse(
    int $statusCode,
    string $status,
    string $message,
    $data = null,
    array $extra = null
): void {
    http_response_code($statusCode);

    $response = [
        'status' => $status,
        'message' => $message
    ];

    if (!is_null($data)) {
        $response['data'] = $data;
    }

    if (!is_null($extra) && is_array($extra)) {
        $response = array_merge($response, $extra);
    }

    // Set JSON content type BEFORE any debug output
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
