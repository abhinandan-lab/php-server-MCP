<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');

use Dotenv\Dotenv;

class DocsController
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

    public function index()
    {
        global $router;
        $groupedRoutes = $router->getGroupedRoutes();
        $totalRoutes = count(array_filter($router->getRoutes(), fn($r) => $r['visible']));

        // Get base path from router (with fallback)
        $basePath = method_exists($router, 'getBasePath') ? $router->getBasePath() : '';

        $this->renderApiDocs($groupedRoutes, $totalRoutes, $basePath);
    }

    // Environment variable management methods
    private function getAllowedEnvironmentVariables()
    {
        // Define which environment variables to show and allow editing
        return [
            // 'SHOW_ERRORS',
            // 'APP_ENV',
            // 'API_VERSION',
            // 'TIMEZONE',
            'DEBUG_MODE',
            // 'CLIENT_DOMAIN',
            // 'LOG_LEVEL',
            // 'CACHE_ENABLED',
            // 'MAINTENANCE_MODE',
            // 'RATE_LIMIT',
            // 'SESSION_TIMEOUT',
            // 'MAX_UPLOAD_SIZE',
            // 'GOOGLE_CLIENT_ID',
            // 'FIREBASE_PROJECT_ID',
            // Add more variables as needed
        ];
    }

    public function getEnvironment()
    {
        header('Content-Type: application/json');

        try {
            $allowedVariables = $this->getAllowedEnvironmentVariables();
            $filteredEnvVars = [];

            // Get only the allowed variables from $_ENV
            foreach ($allowedVariables as $key) {
                if (isset($_ENV[$key])) {
                    $filteredEnvVars[$key] = $_ENV[$key];
                }
            }

            // Also check .env file for allowed variables that might not be in $_ENV
            $envFilePath = __DIR__ . '/../../.env';
            if (file_exists($envFilePath)) {
                $envFileContent = file_get_contents($envFilePath);
                $lines = explode("\n", $envFileContent);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || (strlen($line) > 0 && $line[0] === '#'))
                        continue;

                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value, '"\'');

                        // Only include if it's in the allowed list and not already set
                        if (in_array($key, $allowedVariables) && !isset($filteredEnvVars[$key])) {
                            $filteredEnvVars[$key] = $value;
                        }
                    }
                }
            }

            // Sort variables alphabetically
            ksort($filteredEnvVars);

            echo json_encode([
                'success' => true,
                'environment' => $filteredEnvVars,
                'count' => count($filteredEnvVars)
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get environment: ' . $e->getMessage()]);
        }
    }

    public function updateEnvironment()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $key = $input['key'] ?? '';
        $value = $input['value'] ?? '';

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Environment key is required']);
            return;
        }

        // Check if key is allowed
        $allowedVariables = $this->getAllowedEnvironmentVariables();
        if (!in_array($key, $allowedVariables)) {
            http_response_code(403);
            echo json_encode(['error' => 'This environment variable is not allowed to be modified']);
            return;
        }

        try {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            $this->updateEnvFile($key, $value);
            $this->applySpecialSettings($key, $value);

            echo json_encode([
                'success' => true,
                'message' => "Environment variable '$key' updated successfully",
                'key' => $key,
                'value' => $value
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update environment: ' . $e->getMessage()]);
        }
    }

    public function addEnvironment()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $key = trim($input['key'] ?? '');
        $value = $input['value'] ?? '';

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Environment key is required']);
            return;
        }

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid key format. Use UPPERCASE_WITH_UNDERSCORES']);
            return;
        }

        try {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            $this->updateEnvFile($key, $value);

            echo json_encode([
                'success' => true,
                'message' => "New environment variable '$key' added successfully",
                'key' => $key,
                'value' => $value
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add environment variable: ' . $e->getMessage()]);
        }
    }

    private function updateEnvFile($key, $value)
    {
        $envFilePath = __DIR__ . '/../../.env';

        if (!file_exists($envFilePath)) {
            file_put_contents($envFilePath, "$key=$value\n");
            return;
        }

        $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated = false;
        $newLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || (strlen($line) > 0 && $line[0] === '#')) {
                $newLines[] = $line;
                continue;
            }

            if (strpos($line, '=') === false) {
                $newLines[] = $line;
                continue;
            }

            list($currentKey, $currentValue) = explode('=', $line, 2);
            $currentKey = trim($currentKey);

            if ($currentKey === $key) {
                $newLines[] = "$key=$value";
                $updated = true;
            } else {
                $newLines[] = $line;
            }
        }

        if (!$updated) {
            $newLines[] = "$key=$value";
        }

        file_put_contents($envFilePath, implode("\n", $newLines) . "\n");
    }

    private function applySpecialSettings($key, $value)
    {
        switch ($key) {
            case 'SHOW_ERRORS':
                if ($value === 'true' || $value === '1') {
                    error_reporting(E_ALL);
                    ini_set('display_errors', 1);
                    ini_set('display_startup_errors', 1);
                    ini_set('log_errors', '1');
                } else {
                    error_reporting(0);
                    ini_set('display_errors', 0);
                    ini_set('display_startup_errors', 0);
                }
                break;

            case 'TIMEZONE':
                if (!empty($value)) {
                    date_default_timezone_set($value);
                }
                break;
        }
    }

    // NEW: Add method to get important documentation notes
    // warning, info, success, error
    private function getDocumentationNotes()
    {
        // YOU CAN EDIT THIS CONTENT TO ADD YOUR IMPORTANT NOTES
        return [
            'title' => '📝 Important Documentation Notes',
            'description' => 'Please read these important notes before using the API',
            'notes' => [
                [
                    'type' => 'success',
                    'title' => 'Environment Variables',
                    'content' => 'You can manage environment variables directly from this documentation interface. Changes are applied immediately and saved to the .env file.'
                ],
                [
                    'type' => 'info',
                    'title' => 'Response Format',
                    'content' => 'All API responses are in JSON format. Successful responses return HTTP 200 status code, errors return appropriate HTTP error codes.'
                ],
                [
                    'type' => 'info',
                    'title' => 'Input Field Usage [Body Parameters]',
                    'content' => 'Body Parameters are values sent in the request body, typically using POST form data.'
                ],

                [
                    'type' => 'info',
                    'title' => 'Input Field Usage [URL Parameters]',
                    'content' => 'URL Parameters are values passed directly in the URL path. For example: domain/user/{id}, where "id" is a dynamic value captured by the router.'
                ],

                [
                    'type' => 'info',
                    'title' => 'Input Field Usage [Query Parameters]',
                    'content' => 'Query Parameters are values sent in the URL as key-value pairs, typically with GET requests. For example: domain/user?id=123&status=active.'
                ],

                [
                    'type' => 'error',
                    'title' => 'CORS Configuration',
                    'content' => 'Cross-origin requests are only allowed from whitelisted domains. Contact administrator to add your domain to the CORS whitelist.'
                ]
            ]
        ];
    }

    private function renderApiDocs($groupedRoutes, $totalRoutes, $basePath = '')
    {
        header('Content-Type: text/html');
        $documentationNotes = $this->getDocumentationNotes(); // Get documentation notes
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>API Documentation</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <style>
                /* Your existing CSS styles here - keeping them as they are */
                :root {
                    --bg-primary: #f5f5f5;
                    --bg-secondary: white;
                    --bg-form: #f8f9fa;
                    --bg-response: #e9ecef;
                    --text-primary: #333;
                    --text-secondary: #666;
                    --border-color: #007cba;
                    --btn-primary: #007bff;
                    --btn-primary-hover: #0056b3;
                }

                [data-theme="dark"] {
                    --bg-primary: #1a1a1a;
                    --bg-secondary: #2d2d2d;
                    --bg-form: #3a3a3a;
                    --bg-response: #4a4a4a;
                    --text-primary: #e0e0e0;
                    --text-secondary: #b0b0b0;
                    --border-color: #007cba6d;
                    --btn-primary: #0d6efd;
                    --btn-primary-hover: #0b5ed7;
                }

                body {
                    font-family: Arial;
                    margin: 20px;
                    background: var(--bg-primary);
                    color: var(--text-primary);
                    transition: all 0.3s ease;
                }

                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                }

                .theme-toggle {
                    padding: 8px 12px;
                    background: var(--btn-primary);
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .theme-toggle:hover {
                    background: var(--btn-primary-hover);
                }

                /* NEW: API Search Styles */
                .api-search-section {
                    background: var(--bg-secondary);
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border-left: 4px solid #17a2b8;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .api-search {
                    width: -webkit-fill-available;
                    padding: 12px 16px;
                    font-size: 16px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    background: var(--bg-form);
                    color: var(--text-primary);
                    transition: all 0.3s ease;
                }

                .api-search:focus {
                    outline: none;
                    border-color: var(--btn-primary);
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                }

                .api-search-results {
                    margin-top: 15px;
                    display: none;
                }

                .search-result-item {
                    background: var(--bg-form);
                    padding: 12px;
                    margin: 8px 0;
                    border-radius: 5px;
                    border-left: 4px solid var(--btn-primary);
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .search-result-item:hover {
                    background: var(--bg-secondary);
                    transform: translateX(2px);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .search-result-method {
                    display: inline-block;
                    padding: 2px 6px;
                    color: white;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: bold;
                    margin-right: 8px;
                }

                .search-result-url {
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    margin-right: 8px;
                }

                .search-result-desc {
                    color: var(--text-secondary);
                    font-size: 12px;
                    margin-top: 4px;
                }

                /* NEW: Documentation Notes Styles */
                .doc-notes {
                    background: var(--bg-secondary);
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border-left: 4px solid #28a745;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .note-item {
                    background: var(--bg-form);
                    padding: 15px;
                    margin: 10px 0;
                    border-radius: 5px;
                    border-left: 4px solid #ddd;
                }

                .note-item.warning {
                    border-left-color: #ffc107;
                    background: #fff3cd;
                }

                .note-item.info {
                    border-left-color: #17a2b8;
                    background: #d1ecf1;
                }

                .note-item.success {
                    border-left-color: #28a745;
                    background: #d4edda;
                }

                .note-item.error {
                    border-left-color: #dc3545;
                    background: #f8d7da;
                }

                [data-theme="dark"] .note-item.warning {
                    background: #3d3c1a;
                }

                [data-theme="dark"] .note-item.info {
                    background: #1a3c42;
                }

                [data-theme="dark"] .note-item.success {
                    background: #1e3a1e;
                }

                [data-theme="dark"] .note-item.error {
                    background: #3a1e1e;
                }

                .note-title {
                    font-weight: bold;
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .note-content {
                    color: var(--text-secondary);
                    line-height: 1.4;
                }

                .note-content code {
                    background: var(--bg-response);
                    padding: 2px 4px;
                    border-radius: 3px;
                    font-family: 'Courier New', monospace;
                    font-size: 12px;
                }

                .group-section {
                    margin-bottom: 30px;
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    transition: all 0.3s ease;
                }

                .group-header {
                    background: linear-gradient(135deg, darkblue, teal);
                    color: white;
                    padding: 5px 20px;
                    font-size: 18px;
                    font-weight: bold;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                    user-select: none;
                    transition: all 0.3s ease;
                }

                .group-header:hover {
                    background: linear-gradient(135deg, var(--btn-primary-hover), var(--btn-primary));
                }

                .group-content {
                    padding: 10px;
                    background: var(--bg-primary);
                }

                .group-toggle {
                    font-size: 14px;
                    opacity: 0.8;
                    transition: transform 0.3s ease;
                }

                .group-stats {
                    font-size: 12px;
                    opacity: 0.9;
                    font-weight: normal;
                    margin-left: 10px;
                }

                .collapsed .group-content {
                    display: none;
                }

                .collapsed .group-toggle {
                    transform: rotate(180deg);
                }

                .group-toggle::after {
                    content: " ▲";
                }

                .toc {
                    background: var(--bg-secondary);
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border-left: 4px solid var(--border-color);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .toc h3 {
                    margin-top: 0;
                    color: var(--text-primary);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .toc ul {
                    list-style: none;
                    padding: 0;
                    margin: 15px 0 0 0;
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 10px;
                }

                .toc li {
                    margin: 0;
                }

                .toc a {
                    color: var(--btn-primary);
                    text-decoration: none;
                    padding: 8px 12px;
                    border-radius: 5px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    transition: all 0.3s ease;
                    border: 1px solid transparent;
                }

                .toc a:hover {
                    background: var(--btn-primary);
                    color: white;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                }

                .toc-count {
                    background: var(--bg-form);
                    color: var(--text-secondary);
                    padding: 2px 6px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: bold;
                }

                .toc a:hover .toc-count {
                    background: rgba(255, 255, 255, 0.2);
                    color: white;
                }

                .api-item {
                    background: var(--bg-secondary);
                    margin: 10px 0;
                    padding: 15px;
                    border-radius: 5px;
                    border-left: 4px solid var(--border-color);
                    transition: all 0.3s ease;
                }

                .api-item:hover {
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    transform: translateX(0px);
                }

                .method {
                    display: inline-block;
                    padding: 3px 8px;
                    color: white;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                }

                .get {
                    background: #28a745;
                }

                .post {
                    background: #007bff;
                }

                .put {
                    background: #ffc107;
                    color: black;
                }

                .delete {
                    background: #dc3545;
                }

                .test-form {
                    margin-top: 10px;
                    padding: 10px;
                    background: var(--bg-form);
                    border-radius: 3px;
                    transition: all 0.3s ease;
                }

                .form-group {
                    margin: 8px 0;
                }

                .form-group label {
                    display: inline-block;
                    width: 100px;
                    font-weight: bold;
                    color: var(--text-primary);
                }

                .form-group input {
                    width: 300px;
                    padding: 5px;
                    background: var(--bg-secondary);
                    border: 1px solid #ddd;
                    color: var(--text-primary);
                    border-radius: 3px;
                }

                [data-theme="dark"] .form-group input {
                    border-color: #555;
                }

                .kv-pair {
                    display: flex;
                    align-items: center;
                    margin: 5px 0;
                }

                .kv-pair input {
                    width: 140px;
                    margin-right: 5px;
                    padding: 5px;
                    background: var(--bg-secondary);
                    border: 1px solid #ddd;
                    color: var(--text-primary);
                    border-radius: 3px;
                }

                [data-theme="dark"] .kv-pair input {
                    border-color: #555;
                }

                .kv-container {
                    margin-left: 105px;
                }

                .btn {
                    padding: 8px 15px;
                    background: var(--btn-primary);
                    color: white;
                    border: none;
                    border-radius: 3px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .btn:hover {
                    background: var(--btn-primary-hover);
                }

                .btn-small {
                    padding: 4px 8px;
                    font-size: 12px;
                }

                .btn-add {
                    background: #28a745;
                }

                .btn-add:hover {
                    background: #1e7e34;
                }

                .btn-remove {
                    background: #dc3545;
                }

                .btn-remove:hover {
                    background: #c82333;
                }

                .btn-example {
                    background: #17a2b8;
                }

                .btn-example:hover {
                    background: #138496;
                }

                .response {
                    margin-top: 10px;
                    padding: 15px;
                    background: var(--bg-response);
                    border-radius: 5px;
                    font-family: 'Courier New', monospace;
                    border: 1px solid #ddd;
                    transition: all 0.3s ease;
                    max-height: 400px;
                    overflow-y: auto;
                }

                [data-theme="dark"] .response {
                    border-color: #555;
                }

                .json-key {
                    color: #0066cc;
                    font-weight: bold;
                }

                .json-string {
                    color: #009900;
                }

                .json-number {
                    color: #cc6600;
                }

                .json-boolean {
                    color: #990099;
                }

                .json-null {
                    color: #999999;
                }

                [data-theme="dark"] .json-key {
                    color: #66b3ff;
                }

                [data-theme="dark"] .json-string {
                    color: #66ff66;
                }

                [data-theme="dark"] .json-number {
                    color: #ffcc66;
                }

                [data-theme="dark"] .json-boolean {
                    color: #ff66ff;
                }

                [data-theme="dark"] .json-null {
                    color: #cccccc;
                }

                .toggle-test {
                    color: var(--btn-primary);
                    cursor: pointer;
                    text-decoration: underline;
                    font-size: 12px;
                }

                .description {
                    margin-top: 5px;
                    color: var(--text-secondary);
                }

                .example-hint {
                    background: #e3f2fd;
                    padding: 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    margin-bottom: 10px;
                    border-left: 3px solid #2196f3;
                }

                [data-theme="dark"] .example-hint {
                    background: #1e3a5f;
                    border-left-color: #64b5f6;
                }

                .param-required {
                    color: #dc3545;
                    font-weight: bold;
                }

                .auth-section {
                    background: #e8f4fd;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    border-left: 3px solid #007bff;
                }

                [data-theme="dark"] .auth-section {
                    background: #1e3a5f;
                    border-left-color: #0d6efd;
                }

                .quick-auth {
                    margin-bottom: 10px;
                }

                .quick-auth input {
                    width: 250px;
                }

                .header-section {
                    background: #fff3cd;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    border-left: 3px solid #ffc107;
                }

                [data-theme="dark"] .header-section {
                    background: #3d3c1a;
                    border-left-color: #ffc107;
                }

                .query-section {
                    background: #d1ecf1;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    border-left: 3px solid #17a2b8;
                }

                [data-theme="dark"] .query-section {
                    background: #1a3c42;
                    border-left-color: #17a2b8;
                }

                /* Environment Variables Styles */
                .env-controls {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                    flex-wrap: wrap;
                }

                .env-search {
                    flex: 1;
                    min-width: 200px;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    background: var(--bg-secondary);
                    color: var(--text-primary);
                }

                .env-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }

                .env-table th,
                .env-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                    vertical-align: top;
                }

                .env-table th {
                    background: var(--bg-form);
                    font-weight: bold;
                }

                .env-key {
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    color: var(--btn-primary);
                    word-break: break-word;
                }

                .env-value {
                    font-family: 'Courier New', monospace;
                    background: var(--bg-form);
                    padding: 4px 8px;
                    border-radius: 3px;
                    word-break: break-all;
                    max-width: 300px;
                    cursor: pointer;
                    border: 1px dashed #ccc;
                    transition: all 0.3s ease;
                }

                .env-value:hover {
                    border-color: var(--btn-primary);
                    background: var(--bg-secondary);
                }

                .env-value input {
                    width: 100%;
                    padding: 4px;
                    border: none;
                    outline: none;
                    font-family: 'Courier New', monospace;
                    background: transparent;
                }

                .add-env-form {
                    display: none;
                    background: var(--bg-form);
                    padding: 15px;
                    border-radius: 5px;
                    margin-top: 15px;
                    border: 1px solid #ddd;
                }

                .add-env-form.active {
                    display: block;
                }

                .form-row {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 10px;
                }

                .form-row input {
                    flex: 1;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    background: var(--bg-secondary);
                    color: var(--text-primary);
                }

                [data-theme="dark"] .env-search,
                [data-theme="dark"] .form-row input,
                [data-theme="dark"] .api-search {
                    border-color: #555;
                }

                [data-theme="dark"] .env-table th,
                [data-theme="dark"] .env-table td {
                    border-color: #555;
                }

                [data-theme="dark"] .add-env-form {
                    border-color: #555;
                }

                @media (max-width: 768px) {
                    .header {
                        flex-direction: column;
                        gap: 10px;
                        text-align: center;
                    }

                    .toc ul {
                        grid-template-columns: 1fr;
                    }

                    .form-group input {
                        width: 100%;
                        max-width: 300px;
                    }

                    .kv-pair input {
                        width: 45%;
                    }
                }
            </style>
        </head>

        <body data-theme="light">
            <div class="header">
                <div>
                    <h1>🚀 API Documentation</h1>
                    <p>Total APIs: <strong><?= $totalRoutes ?></strong> | Groups: <strong><?= count($groupedRoutes) ?></strong>
                    </p>
                </div>
                <button class="theme-toggle" onclick="toggleTheme()">🌙 Dark Mode</button>
            </div>

            <!-- NEW: API Search Section -->
            <div class="api-search-section">
                <h3><span>🔍</span> Search APIs</h3>
                <input type="text" id="api-search" class="api-search"
                    placeholder="Search APIs by method, URL, or description...">
                <div id="api-search-results" class="api-search-results">
                    <!-- Search results will appear here -->
                </div>
            </div>

            <!-- NEW: Important Documentation Notes -->
            <div class="group-section" id="group-documentation-notes">
                <div class="group-header" onclick="toggleGroup('documentation-notes')">
                    <div>
                        <span><?= htmlspecialchars($documentationNotes['title']) ?></span>
                        <span class="group-stats">(<?= count($documentationNotes['notes']) ?> notes)</span>
                    </div>
                    <span class="group-toggle"></span>
                </div>
                <div class="group-content">
                    <p style="margin-top: 0; color: var(--text-secondary);">
                        <?= htmlspecialchars($documentationNotes['description']) ?>
                    </p>

                    <?php foreach ($documentationNotes['notes'] as $note): ?>
                        <div class="note-item <?= htmlspecialchars($note['type']) ?>">
                            <div class="note-title">
                                <?php
                                $icons = [
                                    'warning' => '⚠️',
                                    'info' => 'ℹ️',
                                    'success' => '✅',
                                    'error' => '❌'
                                ];
                                echo $icons[$note['type']] ?? '📝';
                                ?>
                                <?= htmlspecialchars($note['title']) ?>
                            </div>
                            <div class="note-content">
                                <?= $note['content'] // Allow HTML for formatting 
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table of Contents -->
            <div class="toc">
                <h3><span>📋</span> API Groups Navigation</h3>
                <ul>
                    <!-- Environment Variables in TOC -->
                    <li>
                        <a href="#group-environment">
                            <span>⚙️ Environment Variables</span>
                            <span class="toc-count" id="env-toc-count">0</span>
                        </a>
                    </li>
                    <?php foreach ($groupedRoutes as $groupName => $routes): ?>
                        <li>
                            <a href="#group-<?= $this->slugify($groupName) ?>">
                                <span><?= $this->getGroupIcon($groupName) ?> <?= htmlspecialchars($groupName) ?></span>
                                <span class="toc-count"><?= count($routes) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Environment Variables - Collapsible Section -->
            <div class="group-section" id="group-environment">
                <div class="group-header" onclick="toggleGroup('environment')">
                    <div>
                        <span>⚙️ Environment Variables</span>
                        <span class="group-stats" id="env-count">(Loading...)</span>
                    </div>
                    <span class="group-toggle"></span>
                </div>
                <div class="group-content">
                    <div class="env-controls">
                        <input type="text" id="env-search" class="env-search" placeholder="🔍 Search environment variables...">
                        <button class="btn btn-add" onclick="showAddForm()">+ Add Variable</button>
                        <button class="btn" onclick="loadEnvironment()" style="background: #17a2b8;">🔄 Refresh</button>
                    </div>

                    <div id="add-env-form" class="add-env-form">
                        <h4>Add New Environment Variable</h4>
                        <div class="form-row">
                            <input type="text" id="new-env-key" placeholder="VARIABLE_NAME">
                            <input type="text" id="new-env-value" placeholder="Variable value">
                        </div>
                        <div class="form-row">
                            <button class="btn btn-add" onclick="addEnvironmentVariable()">Add Variable</button>
                            <button class="btn" onclick="hideAddForm()" style="background: #6c757d;">Cancel</button>
                        </div>
                    </div>

                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="env-table">
                            <thead>
                                <tr>
                                    <th>Variable Name</th>
                                    <th>Value</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="env-table-body">
                                <!-- Environment variables will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Grouped Routes -->
            <?php foreach ($groupedRoutes as $groupName => $routes): ?>
                <div class="group-section" id="group-<?= $this->slugify($groupName) ?>">
                    <div class="group-header" onclick="toggleGroup('<?= $this->slugify($groupName) ?>')">
                        <div>
                            <span><?= $this->getGroupIcon($groupName) ?> <?= htmlspecialchars($groupName) ?></span>
                            <span class="group-stats">(<?= count($routes) ?> endpoints)</span>
                        </div>
                        <span class="group-toggle"></span>
                    </div>
                    <div class="group-content">
                        <?php foreach ($routes as $index => $route):
                            $globalIndex = $this->slugify($groupName) . '_' . $index;
                        ?>
                            <div class="api-item" data-method="<?= strtolower($route['method']) ?>"
                                data-url="<?= htmlspecialchars($basePath . $route['pattern']) ?>"
                                data-description="<?= htmlspecialchars($route['description']) ?>"
                                data-group="<?= htmlspecialchars($groupName) ?>">
                                <div>
                                    <span class="method <?= strtolower($route['method']) ?>"><?= $route['method'] ?></span>
                                    <strong><?= htmlspecialchars($basePath . $route['pattern']) ?></strong>
                                    <span class="toggle-test" onclick="toggleTest('<?= $globalIndex ?>')">🧪 Test API</span>
                                </div>
                                <div class="description">
                                    <?= htmlspecialchars($route['description']) ?>
                                </div>

                                <div id="test-<?= $globalIndex ?>" class="test-form" style="display: none;">
                                    <form class="api-test-form" data-method="<?= $route['method'] ?>"
                                        data-url="<?= $route['pattern'] ?>" data-index="<?= $globalIndex ?>">

                                        <!-- AUTHENTICATION & HEADERS SECTION -->
                                        <?php if (isset($route['showHeaders']) && $route['showHeaders']): ?>
                                            <div class="auth-section">
                                                <h4>🔐 Authentication:</h4>
                                                <div class="quick-auth">
                                                    <label>Bearer Token:</label>
                                                    <input type="text" class="bearer-token" placeholder="Enter your JWT/API token">
                                                    <button type="button" class="btn btn-small"
                                                        onclick="applyBearerToken('<?= $globalIndex ?>')">Apply</button>
                                                </div>
                                            </div>

                                            <div class="header-section">
                                                <h4>📋 Headers:</h4>
                                                <div class="kv-container" data-container="headers-<?= $globalIndex ?>">
                                                    <div class="kv-pair">
                                                        <input type="text" placeholder="Header Name (e.g. Content-Type)" class="kv-key">
                                                        <input type="text" placeholder="Header Value (e.g. application/json)"
                                                            class="kv-value">
                                                        <button type="button" class="btn btn-small btn-add"
                                                            onclick="addKVPair('headers-<?= $globalIndex ?>')">+</button>
                                                        <button type="button" class="btn btn-small btn-remove" onclick="removeKVPair(this)"
                                                            style="display:none;">-</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- URL PARAMETERS -->
                                        <?php if (preg_match_all('/\{(\w+)\}/', $route['pattern'], $matches)): ?>
                                            <h4>🔗 URL Parameters <span class="param-required">*</span>:</h4>
                                            <?php foreach ($matches[1] as $param): ?>
                                                <div class="form-group">
                                                    <label>{<?= $param ?>}:</label>
                                                    <input type="text" name="url_<?= $param ?>" placeholder="Enter <?= $param ?> (e.g. 123)">
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- REQUEST DATA (FORM DATA & GET QUERY PARAMETERS) -->
                                        <?php if (!empty($route['inputs'])): ?>
                                            <h4>📝 Request Data:</h4>
                                            <?php foreach ($route['inputs'] as $inputType => $inputData): ?>

                                                <?php if ($inputType === 'get'): ?>
                                                    <!-- Handle GET Query Parameters -->
                                                    <div class="query-section">
                                                        <h4>🔍 Query Parameters:</h4>
                                                        <?php if (is_array($inputData) && !empty($inputData)): ?>
                                                            <div class="example-hint">
                                                                <strong>🔍 Available Query Parameters:</strong><br>
                                                                <?php foreach ($inputData as $key => $example): ?>
                                                                    • <strong><?= htmlspecialchars($key) ?></strong>: <?= htmlspecialchars($example) ?><br>
                                                                <?php endforeach; ?>
                                                                <button type="button" class="btn btn-small btn-example"
                                                                    onclick="fillExample('query-<?= $globalIndex ?>', <?= htmlspecialchars(json_encode($inputData)) ?>)">📋
                                                                    Fill Example</button>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="kv-container" data-container="query-<?= $globalIndex ?>">
                                                            <div class="kv-pair">
                                                                <input type="text" placeholder="Parameter (e.g. page)" class="kv-key">
                                                                <input type="text" placeholder="Value (e.g. 1)" class="kv-value">
                                                                <button type="button" class="btn btn-small btn-add"
                                                                    onclick="addKVPair('query-<?= $globalIndex ?>')">+</button>
                                                                <button type="button" class="btn btn-small btn-remove" onclick="removeKVPair(this)"
                                                                    style="display:none;">-</button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                <?php elseif ($inputType === 'form'): ?>
                                                    <!-- Existing form data handling -->
                                                    <div class="form-group">
                                                        <label>Body Parameters:</label>

                                                        <?php if (is_array($inputData) && !empty($inputData)): ?>
                                                            <div class="example-hint">
                                                                <strong>📝 Expected Parameters:</strong><br>
                                                                <?php foreach ($inputData as $key => $example): ?>
                                                                    • <strong><?= htmlspecialchars($key) ?></strong>: <?= htmlspecialchars($example) ?><br>
                                                                <?php endforeach; ?>
                                                                <button type="button" class="btn btn-small btn-example"
                                                                    onclick="fillExample('form-<?= $globalIndex ?>', <?= htmlspecialchars(json_encode($inputData)) ?>)">📋
                                                                    Fill Example</button>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="kv-container" data-container="form-<?= $globalIndex ?>">
                                                            <div class="kv-pair">
                                                                <input type="text" placeholder="Key (e.g. email)" class="kv-key">
                                                                <input type="text" placeholder="Value (e.g. user@example.com)" class="kv-value">
                                                                <button type="button" class="btn btn-small btn-add"
                                                                    onclick="addKVPair('form-<?= $globalIndex ?>')">+</button>
                                                                <button type="button" class="btn btn-small btn-remove" onclick="removeKVPair(this)"
                                                                    style="display:none;">-</button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                <?php elseif (is_string($inputData)): ?>
                                                    <!-- Existing string input handling -->
                                                    <div class="form-group">
                                                        <label><?= htmlspecialchars($inputType) ?>:</label>
                                                        <input type="text" name="<?= htmlspecialchars($inputType) ?>"
                                                            placeholder="<?= htmlspecialchars($inputData) ?>">
                                                    </div>
                                                <?php endif; ?>

                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <div class="form-group">
                                            <button type="submit" class="btn">Send Request</button>
                                            <button type="button" class="btn" style="background: #6c757d;"
                                                onclick="clearResponse('<?= $globalIndex ?>')">Clear</button>
                                        </div>
                                    </form>

                                    <div id="response-<?= $globalIndex ?>" class="response" style="display: none;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
                // Environment Variables Management
                let environmentData = {};
                let allApiData = []; // Store all API data for searching

                $(document).ready(function() {
                    // Load environment variables
                    loadEnvironment();

                    // Build API search data
                    buildApiSearchData();

                    // Set up API search functionality
                    $('#api-search').on('input', function() {
                        const searchTerm = $(this).val().toLowerCase().trim();
                        if (searchTerm.length >= 2) {
                            searchApis(searchTerm);
                        } else {
                            $('#api-search-results').hide();
                        }
                    });

                    // Environment search functionality
                    $('#env-search').on('input', function() {
                        const searchTerm = $(this).val().toLowerCase();
                        const rows = $('#env-table-body tr');

                        if (!searchTerm) {
                            rows.show();
                            return;
                        }

                        rows.each(function() {
                            const key = $(this).data('key').toLowerCase();
                            const value = $(this).find('.env-value').text().toLowerCase();

                            if (key.includes(searchTerm) || value.includes(searchTerm)) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    });
                });

                // NEW: Build API search data
                function buildApiSearchData() {
                    allApiData = [];
                    $('.api-item').each(function() {
                        const $item = $(this);
                        allApiData.push({
                            method: $item.data('method'),
                            url: $item.data('url'),
                            description: $item.data('description'),
                            group: $item.data('group'),
                            element: $item
                        });
                    });
                }

                // NEW: Search APIs functionality
                function searchApis(searchTerm) {
                    const results = allApiData.filter(api => {
                        return api.method.includes(searchTerm) ||
                            api.url.toLowerCase().includes(searchTerm) ||
                            api.description.toLowerCase().includes(searchTerm) ||
                            api.group.toLowerCase().includes(searchTerm);
                    });

                    displaySearchResults(results);
                }

                // NEW: Display search results
                function displaySearchResults(results) {
                    const $resultsContainer = $('#api-search-results');

                    if (results.length === 0) {
                        $resultsContainer.html('<p style="color: var(--text-secondary); text-align: center; padding: 20px;">No APIs found matching your search.</p>').show();
                        return;
                    }

                    let html = '';
                    results.slice(0, 10).forEach(api => { // Limit to 10 results
                        const methodClass = api.method.toLowerCase();
                        html += `
                            <div class="search-result-item" onclick="scrollToApi(this)" data-element-id="${api.element.closest('.group-section').attr('id')}" data-api-element="${api.element.index()}">
                                <div>
                                    <span class="search-result-method method ${methodClass}">${api.method.toUpperCase()}</span>
                                    <span class="search-result-url">${api.url}</span>
                                </div>
                                <div class="search-result-desc">${api.description} • Group: ${api.group}</div>
                            </div>
                        `;
                    });

                    if (results.length > 10) {
                        html += `<p style="color: var(--text-secondary); text-align: center; padding: 10px; font-size: 12px;">Showing first 10 of ${results.length} results. Try a more specific search.</p>`;
                    }

                    $resultsContainer.html(html).show();
                }

                // NEW: Scroll to API when search result is clicked
                function scrollToApi(element) {
                    const $element = $(element);
                    const groupId = $element.data('element-id');
                    const $targetGroup = $('#' + groupId);

                    // Expand the target group if collapsed
                    $targetGroup.removeClass('collapsed');
                    const groupSlug = groupId.replace('group-', '');
                    localStorage.setItem('group-' + groupSlug + '-collapsed', false);

                    // Hide search results
                    $('#api-search-results').hide();
                    $('#api-search').val('');

                    // Smooth scroll to the group
                    $('html, body').animate({
                        scrollTop: $targetGroup.offset().top - 20
                    }, 500);

                    // Highlight the specific API briefly
                    setTimeout(() => {
                        const $apiItems = $targetGroup.find('.api-item');
                        const targetIndex = $element.data('api-element');
                        if ($apiItems.eq(targetIndex).length) {
                            $apiItems.eq(targetIndex).css('background', 'var(--btn-primary)').css('color', 'white');
                            setTimeout(() => {
                                $apiItems.eq(targetIndex).css('background', '').css('color', '');
                            }, 2000);
                        }
                    }, 600);
                }

                function loadEnvironment() {
                    $('#env-count').text('(Loading...)');
                    $('#env-toc-count').text('0');

                    $.ajax({
                        url: '<?= $basePath ?>/env/get',
                        method: 'GET',
                        success: function(response) {
                            if (response.success) {
                                environmentData = response.environment;
                                renderEnvironmentTable(environmentData);
                                $('#env-count').text(`(${response.count} variables)`);
                                $('#env-toc-count').text(response.count);
                            }
                        },
                        error: function(xhr) {
                            console.error('Failed to load environment:', xhr);
                            $('#env-count').text('(Error loading)');
                            $('#env-toc-count').text('!');
                        }
                    });
                }

                function renderEnvironmentTable(envData) {
                    const tbody = $('#env-table-body');
                    tbody.empty();

                    Object.keys(envData).sort().forEach(key => {
                        const value = envData[key];

                        const row = $(`
                            <tr data-key="${key}">
                                <td>
                                    <div class="env-key">${escapeHtml(key)}</div>
                                </td>
                                <td>
                                    <div class="env-value" 
                                         data-key="${key}" 
                                         data-original="${escapeHtml(value)}"
                                         onclick="editValue(this)">
                                        ${escapeHtml(value || '(empty)')}
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-small" onclick="copyValue('${key}')" title="Copy">📋</button>
                                </td>
                            </tr>
                        `);

                        tbody.append(row);
                    });
                }

                function editValue(element) {
                    const $element = $(element);
                    const key = $element.data('key');
                    const originalValue = $element.data('original');

                    if ($element.find('input').length > 0) return;

                    const input = $(`<input type="text" value="${escapeHtml(originalValue)}">`);
                    $element.html(input);
                    input.focus().select();

                    input.on('keypress blur', function(e) {
                        if (e.type === 'keypress' && e.which !== 13) return;

                        const newValue = $(this).val();
                        saveEnvironmentVariable(key, newValue, $element);
                    });

                    input.on('keydown', function(e) {
                        if (e.which === 27) { // Escape
                            $element.html(escapeHtml(originalValue || '(empty)'));
                        }
                    });
                }

                function saveEnvironmentVariable(key, value, $element) {
                    $.ajax({
                        url: '<?= $basePath ?>/env/update',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify({
                            key: key,
                            value: value
                        }),
                        success: function(response) {
                            if (response.success) {
                                $element.data('original', value);
                                $element.html(escapeHtml(value || '(empty)'));
                                environmentData[key] = value;
                                alert(`✅ ${key} updated successfully`);
                            } else {
                                alert('❌ Update failed: ' + response.error);
                                $element.html(escapeHtml($element.data('original') || '(empty)'));
                            }
                        },
                        error: function(xhr) {
                            alert('❌ Failed to update environment variable');
                            $element.html(escapeHtml($element.data('original') || '(empty)'));
                        }
                    });
                }

                function addEnvironmentVariable() {
                    const key = $('#new-env-key').val().trim().toUpperCase();
                    const value = $('#new-env-value').val().trim();

                    if (!key) {
                        alert('Please enter a variable name');
                        return;
                    }

                    if (!/^[A-Z_][A-Z0-9_]*$/.test(key)) {
                        alert('Invalid variable name. Use UPPERCASE_WITH_UNDERSCORES format');
                        return;
                    }

                    $.ajax({
                        url: '<?= $basePath ?>/env/add',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify({
                            key: key,
                            value: value
                        }),
                        success: function(response) {
                            if (response.success) {
                                environmentData[key] = value;
                                renderEnvironmentTable(environmentData);
                                $('#env-count').text(`(${Object.keys(environmentData).length} variables)`);
                                $('#env-toc-count').text(Object.keys(environmentData).length);
                                hideAddForm();
                                alert(`✅ ${key} added successfully`);
                            } else {
                                alert('❌ Failed to add variable: ' + response.error);
                            }
                        },
                        error: function(xhr) {
                            alert('❌ Failed to add environment variable');
                        }
                    });
                }

                function showAddForm() {
                    $('#add-env-form').addClass('active');
                    $('#new-env-key').focus();
                }

                function hideAddForm() {
                    $('#add-env-form').removeClass('active');
                    $('#new-env-key').val('');
                    $('#new-env-value').val('');
                }

                function copyValue(key) {
                    const value = environmentData[key];

                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(value).then(() => {
                            alert(`✅ ${key} value copied to clipboard`);
                        });
                    } else {
                        const textArea = document.createElement('textarea');
                        textArea.value = value;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        alert(`✅ ${key} value copied to clipboard`);
                    }
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // Theme Management
                function toggleTheme() {
                    const body = document.body;
                    const toggleBtn = document.querySelector('.theme-toggle');
                    const currentTheme = body.getAttribute('data-theme');

                    if (currentTheme === 'light') {
                        body.setAttribute('data-theme', 'dark');
                        toggleBtn.textContent = '☀️ Light Mode';
                        localStorage.setItem('theme', 'dark');
                    } else {
                        body.setAttribute('data-theme', 'light');
                        toggleBtn.textContent = '🌙 Dark Mode';
                        localStorage.setItem('theme', 'light');
                    }
                }

                // Group toggling functionality
                function toggleGroup(groupId) {
                    const groupSection = document.getElementById('group-' + groupId);
                    groupSection.classList.toggle('collapsed');

                    // Save group state to localStorage
                    const collapsed = groupSection.classList.contains('collapsed');
                    localStorage.setItem('group-' + groupId + '-collapsed', collapsed);
                }

                // Load saved theme and group states
                $(document).ready(function() {
                    // Load theme
                    const savedTheme = localStorage.getItem('theme') || 'light';
                    const toggleBtn = document.querySelector('.theme-toggle');

                    if (savedTheme === 'dark') {
                        document.body.setAttribute('data-theme', 'dark');
                        toggleBtn.textContent = '☀️ Light Mode';
                    }

                    // Load group collapse states
                    $('.group-section').each(function() {
                        const groupId = $(this).attr('id').replace('group-', '');
                        const isCollapsed = localStorage.getItem('group-' + groupId + '-collapsed') === 'true';
                        if (isCollapsed) {
                            $(this).addClass('collapsed');
                        }
                    });

                    // Add smooth scrolling for TOC links
                    $('.toc a').click(function(e) {
                        e.preventDefault();
                        const target = $($(this).attr('href'));
                        if (target.length) {
                            // Expand the target group if collapsed
                            target.removeClass('collapsed');
                            const groupId = target.attr('id').replace('group-', '');
                            localStorage.setItem('group-' + groupId + '-collapsed', false);

                            // Smooth scroll
                            $('html, body').animate({
                                scrollTop: target.offset().top - 20
                            }, 500);
                        }
                    });
                });

                // Apply Bearer Token to Headers
                function applyBearerToken(index) {
                    const tokenInput = $(`.api-test-form[data-index="${index}"] .bearer-token`);
                    const token = tokenInput.val().trim();
                    if (!token) {
                        alert('Please enter a bearer token first');
                        return;
                    }

                    const headerContainer = $(`[data-container="headers-${index}"]`);

                    // Check if Authorization header already exists
                    let authExists = false;
                    headerContainer.find('.kv-pair').each(function() {
                        const key = $(this).find('.kv-key').val().toLowerCase();
                        if (key === 'authorization') {
                            $(this).find('.kv-value').val(`Bearer ${token}`);
                            authExists = true;
                            return false;
                        }
                    });

                    // If no Authorization header exists, add one
                    if (!authExists) {
                        const firstPair = headerContainer.find('.kv-pair:first');
                        if (firstPair.find('.kv-key').val() === '' && firstPair.find('.kv-value').val() === '') {
                            firstPair.find('.kv-key').val('Authorization');
                            firstPair.find('.kv-value').val(`Bearer ${token}`);
                        } else {
                            addKVPair(`headers-${index}`);
                            const lastPair = headerContainer.find('.kv-pair:last');
                            lastPair.find('.kv-key').val('Authorization');
                            lastPair.find('.kv-value').val(`Bearer ${token}`);
                        }
                    }

                    // Visual feedback
                    tokenInput.css('background-color', '#d4edda');
                    setTimeout(() => {
                        tokenInput.css('background-color', '');
                    }, 1000);
                }

                // Fill Example Data
                function fillExample(containerName, exampleData) {
                    const container = $('[data-container="' + containerName + '"]');

                    // Clear existing pairs except first one
                    container.find('.kv-pair:not(:first)').remove();
                    container.find('.kv-pair:first .btn-remove').hide();
                    container.find('.kv-pair:first .btn-add').show();

                    // Fill first pair and add more if needed
                    let isFirst = true;
                    Object.entries(exampleData).forEach(([key, value]) => {
                        if (isFirst) {
                            container.find('.kv-key:first').val(key);
                            container.find('.kv-value:first').val(value);
                            isFirst = false;
                        } else {
                            addKVPair(containerName);
                            const lastPair = container.find('.kv-pair:last');
                            lastPair.find('.kv-key').val(key);
                            lastPair.find('.kv-value').val(value);
                        }
                    });
                }

                // JSON Syntax Highlighting
                function syntaxHighlight(json) {
                    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(match) {
                        var cls = 'json-number';
                        if (/^"/.test(match)) {
                            if (/:$/.test(match)) {
                                cls = 'json-key';
                            } else {
                                cls = 'json-string';
                            }
                        } else if (/true|false/.test(match)) {
                            cls = 'json-boolean';
                        } else if (/null/.test(match)) {
                            cls = 'json-null';
                        }
                        return '<span class="' + cls + '">' + match + '</span>';
                    });
                }

                function toggleTest(index) {
                    $('#test-' + index).toggle();
                }

                function clearResponse(index) {
                    $('#response-' + index).hide().html('');
                }

                function addKVPair(containerName) {
                    const container = $('[data-container="' + containerName + '"]');
                    const newPair = $(`
                        <div class="kv-pair">
                            <input type="text" placeholder="Key" class="kv-key">
                            <input type="text" placeholder="Value" class="kv-value">
                            <button type="button" class="btn btn-small btn-add" onclick="addKVPair('${containerName}')">+</button>
                            <button type="button" class="btn btn-small btn-remove" onclick="removeKVPair(this)">-</button>
                        </div>
                    `);

                    container.find('.kv-pair:last .btn-add').hide();
                    container.find('.kv-pair:last .btn-remove').show();
                    container.append(newPair);
                }

                function removeKVPair(btn) {
                    const pair = $(btn).closest('.kv-pair');
                    const container = pair.closest('.kv-container');

                    pair.remove();

                    const lastPair = container.find('.kv-pair:last');
                    lastPair.find('.btn-add').show();

                    if (container.find('.kv-pair').length === 1) {
                        lastPair.find('.btn-remove').hide();
                    }
                }

                // Enhanced AJAX Request Handler
                $(document).ready(function() {
                    $('.api-test-form').submit(function(e) {
                        e.preventDefault();

                        const form = $(this);
                        const method = form.data('method');
                        const baseUrl = form.data('url');
                        const index = form.data('index');

                        let url = '<?= $basePath ?>' + baseUrl;

                        // Handle URL parameters
                        form.find('input[name^="url_"]').each(function() {
                            const paramName = $(this).attr('name').replace('url_', '');
                            const paramValue = $(this).val();
                            if (paramValue) {
                                url = url.replace('{' + paramName + '}', paramValue);
                            }
                        });

                        // Handle GET query parameters
                        let queryParams = [];
                        form.find(`[data-container="query-${index}"] .kv-pair`).each(function() {
                            const key = $(this).find('.kv-key').val().trim();
                            const value = $(this).find('.kv-value').val().trim();
                            if (key && value) {
                                queryParams.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
                            }
                        });

                        // Append query parameters to URL for GET requests
                        if (queryParams.length > 0) {
                            url += '?' + queryParams.join('&');
                        }

                        // Collect headers
                        let headers = {};
                        form.find(`[data-container="headers-${index}"] .kv-pair`).each(function() {
                            const key = $(this).find('.kv-key').val().trim();
                            const value = $(this).find('.kv-value').val().trim();
                            if (key && value) {
                                headers[key] = value;
                            }
                        });

                        // Collect form data (for POST/PUT requests)
                        let requestData = {};
                        if (method !== 'GET') {
                            form.find(`[data-container="form-${index}"] .kv-pair`).each(function() {
                                const key = $(this).find('.kv-key').val().trim();
                                const value = $(this).find('.kv-value').val().trim();
                                if (key && value) {
                                    requestData[key] = value;
                                }
                            });

                            // Handle other input fields
                            form.find('input:not([name^="url_"]):not(.kv-key):not(.kv-value):not(.bearer-token)').each(function() {
                                const name = $(this).attr('name');
                                const value = $(this).val();
                                if (name && value) {
                                    requestData[name] = value;
                                }
                            });
                        }

                        const responseDiv = $('#response-' + index);
                        responseDiv.show().html('<div style="color: #007bff;">⏳ Sending request...</div>');

                        // Debug log
                        console.log('Request Details:', {
                            url: url,
                            method: method,
                            headers: headers,
                            data: requestData
                        });

                        $.ajax({
                            url: url,
                            method: method,
                            headers: headers,
                            data: method !== 'GET' ? requestData : undefined,
                            success: function(response) {
                                let formattedResponse;
                                try {
                                    const jsonString = JSON.stringify(response, null, 2);
                                    formattedResponse = '<div style="color: #28a745; margin-bottom: 10px;"><strong>✅ Success Response:</strong></div><pre>' + syntaxHighlight(jsonString) + '</pre>';
                                } catch (e) {
                                    formattedResponse = '<div style="color: #28a745; margin-bottom: 10px;"><strong>✅ Success Response:</strong></div><pre>' + JSON.stringify(response, null, 2) + '</pre>';
                                }
                                responseDiv.html(formattedResponse);
                            },
                            error: function(xhr) {
                                let errorResponse;
                                try {
                                    const errorJson = JSON.parse(xhr.responseText);
                                    const jsonString = JSON.stringify(errorJson, null, 2);
                                    errorResponse = '<div style="color: #dc3545; margin-bottom: 10px;"><strong>❌ Error ' + xhr.status + ':</strong></div><pre>' + syntaxHighlight(jsonString) + '</pre>';
                                } catch (e) {
                                    errorResponse = '<div style="color: #dc3545; margin-bottom: 10px;"><strong>❌ Error ' + xhr.status + ':</strong></div><pre>' + (xhr.responseText || 'Network error') + '</pre>';
                                }
                                responseDiv.html(errorResponse);
                            }
                        });
                    });
                });
            </script>
        </body>

        </html>
<?php
    }

    // Helper functions
    private function slugify($text)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    private function getGroupIcon($groupName)
    {
        $icons = [
            'Authentication' => '🔐',
            'Users' => '👥',
            'Products' => '📦',
            'Orders' => '🛒',
            'Payments' => '💳',
            'Admin auth' => '⚙️',
            'Admin challenges' => '⚙️',
            'Admin modules' => '⚙️',
            'Admin badges' => '⚙️',
            'Admin Firebase' => '⚙️',
            'Admin Content Moderation' => '⚙️',
            'Admin Creators Management' => '⚙️',
            'Admin Analytics' => '⚙️',
            'Reports' => '📊',
            'Settings' => '⚙️',
            'Files' => '📁',
            'Notifications' => '🔔',
            'Analytics' => '📈',
            'Security' => '🛡️',
            'Creator Challenges' => '🏆',
            'Creator Modules' => '🎨',
            'Creator Rewards' => '🎁',
            'Creator Submissions' => '📤',
            'Testing' => '📝',
            'Other' => '📋',
            'Firebase notifications' => '🔥',
            'Admin Events' => '⚙️',
            'Creators Events' => '📅',
            'CronJobs' => '⚙️',
        ];

        return $icons[$groupName] ?? '📁';
    }
}
?>