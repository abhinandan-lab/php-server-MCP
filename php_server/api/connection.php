<?php

if (!defined('GOODREQ')) {
    die('Access denied');
}

// utf8mb4_general_ci  | use this for full Unicode support

// Get the current hostname
$currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

// Set connection parameters based on the hostname
if (strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false) {
    // Development connection
    // echo '<br>Using PRODUCTION environment';
    $servername = "db";
    $port = "3306"; // Default MySQL port for development
    $username = "root";
    $password = "abcd";
    $dbname = "testdb"; // Use your development database name
    // echo "<br>Using DEVELOPMENT environment";
} else {
    // Production connection (for backend or any other domain)
    $servername = "phpmyadmin.coolify.vps.boomlive.in";
    $port = "3303";
    $username = "root";
    $password = "abcd";
    $dbname = "testdb";
    // echo "<br>Using PRODUCTION environment";
}

// PDO connection
try {
    $dsn = "mysql:host=$servername;port=$port;dbname=$dbname";
    $connpdo = new PDO($dsn, $username, $password);
    $connpdo->exec("SET time_zone = 'Asia/Kolkata'");
    // set the PDO error mode to exception
    $connpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "<br>Connected successfully to: $servername";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}



function RunQuery_oldWorking($conn, $query, $parameterArray = [], $dataAsASSOC = true, $withSUCCESS = false)
{
    try {
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmt = $conn->prepare($query);

        if (!$stmt->execute($parameterArray)) {
            return $stmt->errorInfo();
        }

        $rows = $stmt->fetchAll($dataAsASSOC ? PDO::FETCH_ASSOC : PDO::FETCH_NUM);

        if ($withSUCCESS) {
            $result = [
                'success' => $stmt->rowCount(),
                'id' => null
            ];

            // If it's an INSERT, return last inserted ID
            if (stripos(trim($query), 'insert') === 0) {
                $result['id'] = $conn->lastInsertId();
            }

            // If it's an UPDATE and :id parameter is passed
            if (stripos(trim($query), 'update') === 0 && isset($parameterArray[':id'])) {
                $result['id'] = $parameterArray[':id'];
            }

            return $result;
        }

        return $rows;
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}






function RunQuery(
    PDO $conn,
    string $query,
    array $parameterArray = [],
    bool $dataAsASSOC = true,
    bool $withSUCCESS = false,
    bool $returnSql = false
) {
    try {
        if ($returnSql) {
            return interpolateQuery($conn, $query, $parameterArray);
        }

        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmt = $conn->prepare($query);

        if (!$stmt->execute($parameterArray)) {
            return $stmt->errorInfo();
        }

        $rows = $stmt->fetchAll($dataAsASSOC ? PDO::FETCH_ASSOC : PDO::FETCH_NUM);

        if ($withSUCCESS) {
            $result = [
                'success' => $stmt->rowCount(),
                'id' => null
            ];

            if (stripos(trim($query), 'insert') === 0) {
                $result['id'] = $conn->lastInsertId();
            }

            if (stripos(trim($query), 'update') === 0 && isset($parameterArray[':id'])) {
                $result['id'] = $parameterArray[':id'];
            }

            return $result;
        }

        return $rows;
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Build a runnable SQL string by inlining parameters for debugging (phpMyAdmin-friendly).
 * Handles both named (:id) and positional (?) placeholders.
 * Do not use the returned SQL for execution; it's for inspection/debug only.
 */
function interpolateQuery(PDO $conn, string $query, array $params): string
{
    // Normalize parameter keys (support both ':id' and 'id')
    $normalized = [];
    $isPositional = true;
    $i = 0;
    foreach ($params as $k => $v) {
        if (is_string($k)) {
            $isPositional = false;
            $key = $k === ':' ? $k : ':' . $k;
            $normalized[$key] = $v;
        } else {
            // preserve order for positional
            $normalized[$i++] = $v;
        }
    }

    if ($isPositional) {
        return interpolatePositional($conn, $query, array_values($normalized));
    }

    return interpolateNamed($conn, $query, $normalized);
}

function interpolateNamed(PDO $conn, string $query, array $named): string
{
    // Sort keys by length desc to avoid partial replacements (e.g., :id before :id2)
    uksort($named, function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });

    foreach ($named as $token => $value) {
        $replacement = valueToSql($conn, $value);
        // Replace all occurrences of the exact token
        $query = str_replace($token, $replacement, $query);
    }
    return $query;
}

function interpolatePositional(PDO $conn, string $query, array $values): string
{
    $out = '';
    $len = strlen($query);
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    $vi = 0;

    for ($i = 0; $i < $len; $i++) {
        $ch = $query[$i];

        if ($escape) {
            $out .= $ch;
            $escape = false;
            continue;
        }

        if ($ch === '\\') {
            // Keep track of escapes inside quoted strings
            if ($inSingle || $inDouble) {
                $escape = true;
            }
            $out .= $ch;
            continue;
        }

        if ($ch === "'" && !$inDouble) {
            $inSingle = !$inSingle;
            $out .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle) {
            $inDouble = !$inDouble;
            $out .= $ch;
            continue;
        }

        if ($ch === '?' && !$inSingle && !$inDouble) {
            $val = array_key_exists($vi, $values) ? $values[$vi++] : null;
            $out .= valueToSql($conn, $val);
            continue;
        }

        $out .= $ch;
    }

    return $out;
}

function valueToSql(PDO $conn, $value): string
{
    if (is_null($value)) {
        return 'NULL';
    }
    if ($value instanceof DateTimeInterface) {
        return $conn->quote($value->format('Y-m-d H:i:s'));
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        // Ensure dot decimal for floats
        return (string) (is_float($value) ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') : $value);
    }
    if (is_array($value)) {
        // Useful for IN () debugging output only; assumes the SQL has something like IN (:ids)
        $parts = [];
        foreach ($value as $v) {
            $parts[] = valueToSql($conn, $v);
        }
        return implode(', ', $parts);
    }
    // Fallback to PDO::quote for strings
    $q = $conn->quote((string) $value);
    // If driver doesn't support quote, fallback minimally
    if ($q === false) {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
    return $q;
}







function dd($ke)
{
    if (!empty($_ENV['DEBUG_MODE']) && ($_ENV['DEBUG_MODE'] === 'true' || $_ENV['DEBUG_MODE'] === '1')) {
        echo "Page: " . debug_backtrace()[0]['file'] .
            " __ <span style='color:red'>" . debug_backtrace()[0]['line'] . "</span> <pre>";
        print_r($ke);
        echo "</pre>";
    }
}

function ddd($ke)
{
    if (!empty($_ENV['DEBUG_MODE']) && ($_ENV['DEBUG_MODE'] === 'true' || $_ENV['DEBUG_MODE'] === '1')) {
        echo "<pre>";
        var_dump($ke);
        echo "</pre>";
    }
}



?>