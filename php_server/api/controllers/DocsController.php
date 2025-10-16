<?php

namespace App\Controllers;

use Dotenv\Dotenv;

class DocsController extends BaseController
{
    private $router;
    private $dotenv;

    public function __construct()
    {
        parent::__construct();

        $this->dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $this->dotenv->load();
    }

    public function setRouter($router)
    {
        $this->router = $router;
    }

    public function index()
    {
        if (!$this->router) {
            $this->sendError('Router not set', 500);
            return;
        }

        $groupedRoutes = $this->router->getGroupedRoutes();
        $totalRoutes = count(array_filter($this->router->getRoutes(), fn($r) => $r['visible']));
        $basePath = method_exists($this->router, 'getBasePath') ? $this->router->getBasePath() : '';

        $this->renderEnhancedApiDocs($groupedRoutes, $totalRoutes, $basePath);
    }

    // Environment variable management methods
    private function getAllowedEnvironmentVariables()
    {
        return [
            'DEBUG_MODE',
            'SHOW_ERRORS',
            'LOG_ERRORS',
            'APP_ENV',
            'API_VERSION',
            'TIMEZONE',
        ];
    }

    public function getEnvironment()
    {
        try {
            $allowedVariables = $this->getAllowedEnvironmentVariables();
            $filteredEnvVars = [];

            foreach ($allowedVariables as $key) {
                if (isset($_ENV[$key])) {
                    $filteredEnvVars[$key] = $_ENV[$key];
                }
            }

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

                        if (in_array($key, $allowedVariables) && !isset($filteredEnvVars[$key])) {
                            $filteredEnvVars[$key] = $value;
                        }
                    }
                }
            }

            ksort($filteredEnvVars);

            $this->sendSuccess('Environment variables retrieved', [
                'environment' => $filteredEnvVars,
                'count' => count($filteredEnvVars)
            ]);
        } catch (\Exception $e) {
            $this->sendServerError('Failed to get environment: ' . $e->getMessage());
        }
    }

    public function updateEnvironment()
    {
        $data = $this->getRequestData();

        $required = ['key', 'value'];
        $missing = $this->validateRequired($data, $required);

        if (!empty($missing)) {
            $this->sendValidationError('Missing required fields', array_fill_keys($missing, 'This field is required'));
            return;
        }

        $key = $data['key'];
        $value = $data['value'];

        $allowedVariables = $this->getAllowedEnvironmentVariables();
        if (!in_array($key, $allowedVariables)) {
            $this->sendError('This environment variable is not allowed to be modified', 403);
            return;
        }

        try {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            $this->updateEnvFile($key, $value);
            $this->applySpecialSettings($key, $value);

            $this->sendSuccess("Environment variable '$key' updated successfully", [
                'key' => $key,
                'value' => $value
            ]);
        } catch (\Exception $e) {
            $this->sendServerError('Failed to update environment: ' . $e->getMessage());
        }
    }

    public function addEnvironment()
    {
        $data = $this->getRequestData();

        $required = ['key', 'value'];
        $missing = $this->validateRequired($data, $required);

        if (!empty($missing)) {
            $this->sendValidationError('Missing required fields', array_fill_keys($missing, 'This field is required'));
            return;
        }

        $key = trim($data['key']);
        $value = $data['value'];

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
            $this->sendValidationError('Invalid key format. Use UPPERCASE_WITH_UNDERSCORES', [
                'key' => 'Invalid format'
            ]);
            return;
        }

        try {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            $this->updateEnvFile($key, $value);

            $this->sendSuccess("New environment variable '$key' added successfully", [
                'key' => $key,
                'value' => $value
            ]);
        } catch (\Exception $e) {
            $this->sendServerError('Failed to add environment variable: ' . $e->getMessage());
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

    private function renderEnhancedApiDocs($groupedRoutes, $totalRoutes, $basePath = '')
    {
        header('Content-Type: text/html');
        $allowedEnvVars = $this->getAllowedEnvironmentVariables();
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>🚀 Enhanced API Documentation</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
            <style>
                :root {
                    --bg-primary: #f8f9fa;
                    --bg-secondary: #ffffff;
                    --bg-tertiary: #f1f3f4;
                    --bg-form: #f8f9fa;
                    --bg-response: #1e1e1e;
                    --text-primary: #212529;
                    --text-secondary: #6c757d;
                    --text-inverse: #ffffff;
                    --border-color: #dee2e6;
                    --btn-primary: #004a99;
                    --btn-primary-hover: #0056b3;
                    --btn-success: #28a745;
                    --btn-warning: #ffc107;
                    --btn-danger: #dc3545;
                    --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                [data-theme="dark"] {
                    --bg-primary: #121212;
                    --bg-secondary: #1e1e1e;
                    --bg-tertiary: #2d2d2d;
                    --bg-form: #252525;
                    --bg-response: #000000;
                    --text-primary: #e0e0e0;
                    --text-secondary: #b0b0b0;
                    --text-inverse: #ffffff;
                    --border-color: #444444;
                    --shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: var(--bg-primary);
                    color: var(--text-primary);
                    line-height: 1.6;
                    transition: all 0.3s ease;
                }

                .container {
                    max-width: 1400px;
                    margin: 0 auto;
                }

                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                    padding: 0 12px;
                    background: var(--bg-secondary);
                    border-radius: 12px;
                    box-shadow: var(--shadow);
                }

                .header h1 {
                    margin: 0;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    font-size: 32px;
                }

                .header h1 span {
                    -webkit-background-clip: border-box;
                    -webkit-text-fill-color: initial;
                    background: none;
                }

                .header-controls {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }

                .theme-toggle,
                .env-toggle {
                    padding: 8px 16px;
                    background: var(--btn-primary);
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-size: 14px;
                }

                .theme-toggle:hover,
                .env-toggle:hover {
                    background: var(--btn-primary-hover);
                    transform: translateY(-2px);
                }

                /* Search Section */
                .search-section {
                    background: var(--bg-secondary);
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    box-shadow: var(--shadow);
                }

                .search-box {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid var(--border-color);
                    border-radius: 8px;
                    font-size: 16px;
                    background: var(--bg-form);
                    color: var(--text-primary);
                    transition: all 0.3s ease;
                }

                .search-box:focus {
                    outline: none;
                    border-color: var(--btn-primary);
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                }

                .search-filters {
                    display: flex;
                    gap: 10px;
                    margin-top: 15px;
                    flex-wrap: wrap;
                }

                .filter-btn {
                    padding: 6px 12px;
                    background: var(--bg-tertiary);
                    color: var(--text-secondary);
                    border: 2px solid transparent;
                    border-radius: 20px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-size: 12px;
                    text-transform: uppercase;
                    font-weight: 600;
                }

                .filter-btn:hover,
                .filter-btn.active {
                    background: var(--btn-primary);
                    color: white;
                    transform: translateY(-2px);
                }

                /* Environment Section */
                .env-section {
                    background: var(--bg-secondary);
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    box-shadow: var(--shadow);
                    display: none;
                }

                .env-section.show {
                    display: block;
                }

                .env-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 15px;
                    margin-top: 15px;
                }

                .env-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px;
                    background: var(--bg-form);
                    border-radius: 6px;
                    border: 1px solid var(--border-color);
                }

                .env-item label {
                    font-weight: 600;
                    min-width: 120px;
                    color: var(--text-primary);
                }

                .env-item input {
                    flex: 1;
                    padding: 6px 10px;
                    border: 1px solid var(--border-color);
                    border-radius: 4px;
                    background: var(--bg-secondary);
                    color: var(--text-primary);
                }

                /* API Groups */
                .group-section {
                    margin-bottom: 25px;
                    border-radius: 4px;
                    overflow: hidden;
                    box-shadow: var(--shadow);
                    transition: all 0.3s ease;
                }

                .group-header {
                    background: linear-gradient(135deg, #192d87, #764ba2);
                    color: white;
                    padding: 0.1rem 0.4rem;
                    font-size: 18px;
                    font-weight: 600;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                    user-select: none;
                }

                .group-header:hover {
                    background: linear-gradient(135deg, #5a6fd8, #6a4190);
                }

                .group-content {
                    background: var(--bg-secondary);
                    padding: 6px;
                }

                .group-toggle {
                    transition: transform 0.3s ease;
                    font-size: 0.8rem;
                }

                .collapsed .group-content {
                    display: none;
                }

                .collapsed .group-toggle {
                    /* transform: scale(0.7); */
                    font-size: 0.8rem;
                    transform: rotate(180deg);
                }

                /* API Items */
                .api-item {
                    background: var(--bg-form);
                    margin: 15px 0;
                    border-radius: 8px;
                    border: 1px solid var(--border-color);
                    transition: all 0.3s ease;
                    overflow: hidden;
                }

                .api-item:hover {
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }

                .api-header {
                    padding: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    gap: 15px;
                }

                .api-info {
                    flex: 1;
                }

                .api-method {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 700;
                    margin-right: 10px;
                    text-transform: uppercase;
                }

                .method-get {
                    background: #28a745;
                    color: white;
                }

                .method-post {
                    background: #007bff;
                    color: white;
                }

                .method-put {
                    background: #ffc107;
                    color: #212529;
                }

                .method-delete {
                    background: #dc3545;
                    color: white;
                }

                .api-url {
                    font-family: 'Monaco', 'Menlo', monospace;
                    font-size: 16px;
                    font-weight: 600;
                    color: var(--text-primary);
                    margin: 5px 0;
                }

                .api-description {
                    color: var(--text-secondary);
                    font-size: 14px;
                }

                .test-btn {
                    padding: 8px 16px;
                    background: var(--btn-success);
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-size: 14px;
                    white-space: nowrap;
                }

                .test-btn:hover {
                    background: #218838;
                }

                /* Parameter Sections */
                .test-section {
                    padding: 20px;
                    background: var(--bg-tertiary);
                    border-top: 1px solid var(--border-color);
                    display: none;
                }

                .param-section {
                    margin-bottom: 20px;
                }

                .param-section h4 {
                    margin: 0 0 10px 0;
                    color: var(--text-primary);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .param-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 15px;
                }

                .param-item {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                .param-item label {
                    font-weight: 500;
                    color: var(--text-primary);
                    font-size: 14px;
                }

                .param-item input,
                .param-item textarea {
                    padding: 10px;
                    border: 1px solid var(--border-color);
                    border-radius: 6px;
                    background: var(--bg-secondary);
                    color: var(--text-primary);
                    transition: all 0.3s ease;
                }

                .param-item input:focus,
                .param-item textarea:focus {
                    outline: none;
                    border-color: var(--btn-primary);
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                }

                .param-item textarea {
                    resize: vertical;
                    min-height: 80px;
                    font-family: 'Monaco', 'Menlo', monospace;
                }

                .param-desc {
                    font-size: 12px;
                    color: var(--text-secondary);
                    margin-top: 2px;
                }

                /* Buttons */
                .btn-group {
                    display: flex;
                    gap: 10px;
                    margin: 20px 0;
                }

                .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-weight: 500;
                }

                .btn-primary {
                    background: var(--btn-primary);
                    color: white;
                }

                .btn-primary:hover {
                    background: var(--btn-primary-hover);
                    transform: translateY(-2px);
                }

                .btn-secondary {
                    background: var(--text-secondary);
                    color: white;
                }

                .btn-secondary:hover {
                    background: #5a6268;
                }

                /* Response Section */
                .response-section {
                    margin-top: 15px;
                    display: none;
                }

                .response-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 10px;
                }

                .response-status {
                    font-weight: 600;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                }

                .status-success {
                    background: #d4edda;
                    color: #155724;
                }

                .status-error {
                    background: #f8d7da;
                    color: #721c24;
                }

                .copy-btn {
                    padding: 6px 12px;
                    background: var(--btn-warning);
                    color: #212529;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.3s ease;
                }

                .copy-btn:hover {
                    background: #e0a800;
                    transform: translateY(-1px);
                }

                .response-body {
                    background: var(--bg-response);
                    border: 1px solid var(--border-color);
                    border-radius: 6px;
                    overflow: hidden;
                    position: relative;
                }

                .response-content {
                    padding: 20px;
                    font-family: 'Monaco', 'Menlo', monospace;
                    font-size: 13px;
                    line-height: 1.5;
                    color: #f8f8f2;
                    background: #1e1e1e;
                    overflow-x: auto;
                    max-height: 400px;
                    overflow-y: auto;
                }

                /* Loading States */
                .loading {
                    color: var(--btn-primary);
                    font-style: italic;
                }

                /* Animations */
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .fade-in {
                    animation: fadeIn 0.3s ease;

                }

                /* Responsive */
                @media (max-width: 768px) {
                    body {
                        padding: 10px;
                    }

                    .header {
                        flex-direction: column;
                        gap: 15px;
                    }

                    .search-filters {
                        justify-content: center;
                    }

                    .param-grid {
                        grid-template-columns: 1fr;
                    }

                    .btn-group {
                        flex-direction: column;
                    }
                }

                /* Hide when searching */
                .hidden {
                    display: none !important;
                }
            </style>
        </head>

        <body data-theme="light">
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div>
                        <h1><span>🚀</span> Enhanced API Documentation</h1>
                        <p>Total APIs: <strong><?= $totalRoutes ?></strong> | Groups: <strong><?= count($groupedRoutes) ?></strong></p>
                    </div>
                    <div class="header-controls">
                        <button class="env-toggle" onclick="toggleEnvSection()">⚙️ Environment</button>
                        <button class="theme-toggle" onclick="toggleTheme()">🌙 Dark Mode</button>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="search-section">
                    <input type="text" class="search-box" placeholder="🔍 Search APIs by method, URL, or description..." id="searchInput">
                    <div class="search-filters">
                        <button class="filter-btn active" data-method="all">All Methods</button>
                        <button class="filter-btn" data-method="GET">GET</button>
                        <button class="filter-btn" data-method="POST">POST</button>
                        <button class="filter-btn" data-method="PUT">PUT</button>
                        <button class="filter-btn" data-method="DELETE">DELETE</button>
                    </div>
                </div>

                <!-- Environment Section -->
                <div class="env-section" id="envSection">
                    <h3>⚙️ Environment Variables Management</h3>
                    <p>Manage your application environment variables. Changes are applied immediately.</p>
                    <div class="env-grid" id="envGrid">
                        <!-- Environment variables will be loaded here -->
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="loadEnvironmentVariables()">🔄 Refresh</button>
                        <button class="btn btn-secondary" onclick="saveAllEnvironmentChanges()">💾 Save All Changes</button>
                    </div>
                </div>

                <!-- API Groups -->
                <?php foreach ($groupedRoutes as $groupName => $routes): ?>
                    <div class="group-section fade-in" data-group="<?= strtolower($groupName) ?>">
                        <div class="group-header" onclick="toggleGroup('<?= $this->slugify($groupName) ?>')">
                            <div>
                                <span><?= $this->getGroupIcon($groupName) ?> <?= htmlspecialchars($groupName) ?></span>
                                <small style="opacity: 0.8; margin-left: 10px;">(<?= count($routes) ?> endpoints)</small>
                            </div>
                            <span class="group-toggle">▲</span>
                        </div>
                        <div class="group-content">
                            <?php foreach ($routes as $index => $route):
                                $globalIndex = $this->slugify($groupName) . '_' . $index;
                                $hasUrlParams = !empty($route['params']['url']);
                                $hasGetParams = !empty($route['params']['get']);
                                $hasFormParams = !empty($route['params']['form']);
                                $hasJsonParams = !empty($route['params']['json']);
                            ?>
                                <div class="api-item"
                                    data-method="<?= $route['method'] ?>"
                                    data-url="<?= $route['pattern'] ?>"
                                    data-description="<?= htmlspecialchars($route['description']) ?>">
                                    <div class="api-header">
                                        <div class="api-info">
                                            <div>
                                                <span class="api-method method-<?= strtolower($route['method']) ?>"><?= $route['method'] ?></span>
                                                <span class="api-url"><?= htmlspecialchars($basePath . $route['pattern']) ?></span>
                                            </div>
                                            <div class="api-description"><?= htmlspecialchars($route['description']) ?></div>
                                        </div>
                                        <button class="test-btn" onclick="toggleTest('<?= $globalIndex ?>')">🧪 Test API</button>
                                    </div>

                                    <div id="test-<?= $globalIndex ?>" class="test-section">
                                        <form class="api-test-form" data-method="<?= $route['method'] ?>" data-url="<?= $route['pattern'] ?>" data-index="<?= $globalIndex ?>">

                                            <?php if ($hasUrlParams): ?>
                                                <div class="param-section">
                                                    <h4>🔗 URL Parameters</h4>
                                                    <div class="param-grid">
                                                        <?php foreach ($route['params']['url'] as $param => $desc): ?>
                                                            <div class="param-item">
                                                                <label><?= htmlspecialchars($param) ?></label>
                                                                <input type="text" name="url_<?= htmlspecialchars($param) ?>" placeholder="Enter <?= htmlspecialchars($param) ?>">
                                                                <div class="param-desc"><?= htmlspecialchars($desc) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($hasGetParams): ?>
                                                <div class="param-section">
                                                    <h4>❓ GET Parameters (Query String)</h4>
                                                    <div class="param-grid">
                                                        <?php foreach ($route['params']['get'] as $param => $desc): ?>
                                                            <div class="param-item">
                                                                <label><?= htmlspecialchars($param) ?></label>
                                                                <input type="text" name="get_<?= htmlspecialchars($param) ?>" placeholder="Enter <?= htmlspecialchars($param) ?>">
                                                                <div class="param-desc"><?= htmlspecialchars($desc) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($hasFormParams): ?>
                                                <div class="param-section">
                                                    <h4>📝 POST/Form Parameters</h4>
                                                    <div class="param-grid">
                                                        <?php foreach ($route['params']['form'] as $param => $desc): ?>
                                                            <div class="param-item">
                                                                <label><?= htmlspecialchars($param) ?></label>
                                                                <input type="text" name="form_<?= htmlspecialchars($param) ?>" placeholder="Enter <?= htmlspecialchars($param) ?>">
                                                                <div class="param-desc"><?= htmlspecialchars($desc) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($hasJsonParams): ?>
                                                <div class="param-section">
                                                    <h4>🔧 JSON Body</h4>
                                                    <div class="param-item">
                                                        <label>JSON Payload</label>
                                                        <textarea name="json_body" placeholder='{"key": "value"}' rows="6"></textarea>
                                                        <div class="param-desc">
                                                            <strong>Expected fields:</strong><br>
                                                            <?php foreach ($route['params']['json'] as $param => $desc): ?>
                                                                • <?= htmlspecialchars($param) ?>: <?= htmlspecialchars($desc) ?><br>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="btn-group">
                                                <button type="submit" class="btn btn-primary">🚀 Send Request</button>
                                                <button type="button" class="btn btn-secondary" onclick="clearResponse('<?= $globalIndex ?>')">🗑️ Clear</button>
                                            </div>
                                        </form>

                                        <div id="response-<?= $globalIndex ?>" class="response-section">
                                            <div class="response-header">
                                                <div class="response-status" id="status-<?= $globalIndex ?>"></div>
                                                <button class="copy-btn" onclick="copyResponse('<?= $globalIndex ?>')">📋 Copy</button>
                                            </div>
                                            <div class="response-body">
                                                <div class="response-content" id="content-<?= $globalIndex ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>




            <script>
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

                // Group Management
                function toggleGroup(groupId) {
                    const groupSection = document.getElementById('group-' + groupId) ||
                        document.querySelector(`[data-group="${groupId}"]`);
                    if (groupSection) {
                        groupSection.classList.toggle('collapsed');
                        const collapsed = groupSection.classList.contains('collapsed');
                        localStorage.setItem('group-' + groupId + '-collapsed', collapsed);
                    }
                }

                // Test Section Management
                function toggleTest(index) {
                    const testSection = document.getElementById('test-' + index);
                    if (testSection.style.display === 'none' || testSection.style.display === '') {
                        testSection.style.display = 'block';
                        testSection.classList.add('fade-in');
                    } else {
                        testSection.style.display = 'none';
                    }
                }

                function clearResponse(index) {
                    const responseSection = document.getElementById('response-' + index);
                    const statusEl = document.getElementById('status-' + index);
                    const contentEl = document.getElementById('content-' + index);

                    responseSection.style.display = 'none';
                    statusEl.textContent = '';
                    contentEl.innerHTML = '';
                }

                // Copy Response
                function copyResponse(index) {
                    const contentEl = document.getElementById('content-' + index);
                    const text = contentEl.textContent;

                    navigator.clipboard.writeText(text).then(() => {
                        const copyBtn = event.target;
                        const originalText = copyBtn.textContent;
                        copyBtn.textContent = '✅ Copied!';
                        copyBtn.style.background = '#28a745';

                        setTimeout(() => {
                            copyBtn.textContent = originalText;
                            copyBtn.style.background = '#ffc107';
                        }, 2000);
                    });
                }

                // Environment Management
                function toggleEnvSection() {
                    const envSection = document.getElementById('envSection');
                    envSection.classList.toggle('show');
                    if (envSection.classList.contains('show')) {
                        loadEnvironmentVariables();
                    }
                }

                function loadEnvironmentVariables() {
                    fetch('<?= $basePath ?>/env/get')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                renderEnvironmentVariables(data.data.environment);
                            }
                        })
                        .catch(error => console.error('Error loading environment variables:', error));
                }

                function renderEnvironmentVariables(envVars) {
                    const envGrid = document.getElementById('envGrid');
                    envGrid.innerHTML = '';

                    Object.entries(envVars).forEach(([key, value]) => {
                        const envItem = document.createElement('div');
                        envItem.className = 'env-item';
                        envItem.innerHTML = `
                <label>${key}</label>
                <input type="text" value="${value}" data-key="${key}" onchange="updateEnvironmentVariable('${key}', this.value)">
            `;
                        envGrid.appendChild(envItem);
                    });
                }

                function updateEnvironmentVariable(key, value) {
                    fetch('<?= $basePath ?>/env/update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `key=${encodeURIComponent(key)}&value=${encodeURIComponent(value)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                console.log(`Environment variable ${key} updated successfully`);
                            } else {
                                console.error('Error updating environment variable:', data.message);
                            }
                        });
                }

                // Search Functionality
                function initializeSearch() {
                    const searchInput = document.getElementById('searchInput');
                    const filterBtns = document.querySelectorAll('.filter-btn');

                    searchInput.addEventListener('input', performSearch);

                    filterBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            filterBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            performSearch();
                        });
                    });
                }

                function performSearch() {
                    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                    const activeFilter = document.querySelector('.filter-btn.active').dataset.method;
                    const apiItems = document.querySelectorAll('.api-item');
                    const groupSections = document.querySelectorAll('.group-section');

                    groupSections.forEach(group => {
                        let hasVisibleItems = false;
                        const items = group.querySelectorAll('.api-item');

                        items.forEach(item => {
                            const method = item.dataset.method;
                            const url = item.dataset.url.toLowerCase();
                            const description = item.dataset.description.toLowerCase();

                            const matchesSearch = !searchTerm ||
                                url.includes(searchTerm) ||
                                description.includes(searchTerm) ||
                                method.toLowerCase().includes(searchTerm);

                            const matchesFilter = activeFilter === 'all' || method === activeFilter;

                            if (matchesSearch && matchesFilter) {
                                item.classList.remove('hidden');
                                hasVisibleItems = true;
                            } else {
                                item.classList.add('hidden');
                            }
                        });

                        if (hasVisibleItems) {
                            group.classList.remove('hidden');
                        } else {
                            group.classList.add('hidden');
                        }
                    });
                }

                // API Testing
                function initializeApiTesting() {
                    document.querySelectorAll('.api-test-form').forEach(form => {
                        form.addEventListener('submit', handleApiTest);
                    });
                }

                function handleApiTest(e) {
                    e.preventDefault();
                    const form = e.target;
                    const method = form.dataset.method;
                    const baseUrl = form.dataset.url;
                    const index = form.dataset.index;

                    let url = '<?= $basePath ?>' + baseUrl;
                    let queryParams = {};
                    let formData = {};
                    let jsonData = null;

                    // Collect URL parameters
                    const urlParams = {};
                    form.querySelectorAll('input[name^="url_"]').forEach(input => {
                        if (input.value) {
                            const paramName = input.name.replace('url_', '');
                            urlParams[paramName] = input.value;
                            url = url.replace(`{${paramName}}`, input.value);
                        }
                    });

                    // Collect GET parameters
                    form.querySelectorAll('input[name^="get_"]').forEach(input => {
                        if (input.value) {
                            const paramName = input.name.replace('get_', '');
                            queryParams[paramName] = input.value;
                        }
                    });

                    // Collect Form parameters
                    form.querySelectorAll('input[name^="form_"]').forEach(input => {
                        if (input.value) {
                            const paramName = input.name.replace('form_', '');
                            formData[paramName] = input.value;
                        }
                    });

                    // Collect JSON data
                    const jsonTextarea = form.querySelector('textarea[name="json_body"]');
                    if (jsonTextarea && jsonTextarea.value.trim()) {
                        try {
                            jsonData = JSON.parse(jsonTextarea.value);
                        } catch (e) {
                            showResponse(index, 'error', 'Invalid JSON format: ' + e.message, null);
                            return;
                        }
                    }

                    // Add query parameters to URL
                    if (Object.keys(queryParams).length > 0) {
                        const urlObj = new URL(url, window.location.origin);
                        Object.entries(queryParams).forEach(([key, value]) => {
                            urlObj.searchParams.append(key, value);
                        });
                        url = urlObj.toString();
                    }

                    // Show loading
                    showResponse(index, 'loading', 'Sending request...', null);

                    // Prepare request options
                    const requestOptions = {
                        method: method,
                        headers: {}
                    };

                    if (jsonData) {
                        requestOptions.headers['Content-Type'] = 'application/json';
                        requestOptions.body = JSON.stringify(jsonData);
                    } else if (Object.keys(formData).length > 0 && method !== 'GET') {
                        requestOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                        requestOptions.body = new URLSearchParams(formData).toString();
                    }

                    // **FIXED: Send request with better error handling**
                    fetch(url, requestOptions)
                        .then(async response => {
                            const contentType = response.headers.get('content-type');
                            let data;

                            try {
                                // First get the raw response text
                                const responseText = await response.text();

                                // Try to parse as JSON if content-type suggests it should be JSON
                                if (contentType && contentType.includes('application/json')) {
                                    try {
                                        data = JSON.parse(responseText);
                                    } catch (jsonError) {
                                        // If JSON parsing fails, show the raw response instead of error
                                        data = responseText;
                                    }
                                } else {
                                    // For non-JSON responses, show as-is
                                    data = responseText;
                                }

                                showResponse(index, response.ok ? 'success' : 'error', data, response.status);

                            } catch (fetchError) {
                                // Only catch actual fetch/network errors
                                showResponse(index, 'error', `Network Error: ${fetchError.message}`, null);
                            }
                        })
                        .catch(error => {
                            // This catches network-level errors (no internet, server down, etc.)
                            showResponse(index, 'error', `Connection Error: ${error.message}`, null);
                        });
                }

                function showResponse(index, type, data, status) {
                    const responseSection = document.getElementById('response-' + index);
                    const statusEl = document.getElementById('status-' + index);
                    const contentEl = document.getElementById('content-' + index);

                    responseSection.style.display = 'block';
                    responseSection.classList.add('fade-in');

                    // Set status
                    statusEl.className = `response-status status-${type}`;
                    if (type === 'loading') {
                        statusEl.textContent = 'Loading...';
                    } else {
                        statusEl.textContent = status ? `${type.toUpperCase()} ${status}` : type.toUpperCase();
                    }

                    // Set content with syntax highlighting
                    if (typeof data === 'object') {
                        const jsonString = JSON.stringify(data, null, 2);
                        contentEl.innerHTML = `<pre><code class="language-json">${escapeHtml(jsonString)}</code></pre>`;
                    } else {
                        // Show raw text/HTML responses as-is
                        contentEl.innerHTML = `<pre><code>${escapeHtml(data)}</code></pre>`;
                    }

                    // Apply syntax highlighting if available
                    if (window.hljs) {
                        hljs.highlightAll();
                    }
                }

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // Initialize everything when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    // Load saved theme
                    const savedTheme = localStorage.getItem('theme') || 'light';
                    const toggleBtn = document.querySelector('.theme-toggle');

                    if (savedTheme === 'dark') {
                        document.body.setAttribute('data-theme', 'dark');
                        toggleBtn.textContent = '☀️ Light Mode';
                    }

                    // Load saved group states
                    document.querySelectorAll('.group-section').forEach((group, index) => {
                        const groupId = group.dataset.group || `group-${index}`;
                        const isCollapsed = localStorage.getItem('group-' + groupId + '-collapsed') === 'true';
                        if (isCollapsed) {
                            group.classList.add('collapsed');
                        }
                    });

                    // Initialize functionality
                    initializeSearch();
                    initializeApiTesting();

                    // Initialize syntax highlighting
                    if (window.hljs) {
                        hljs.highlightAll();
                    }
                });
            </script>

        </body>

        </html>
<?php
    }

    private function slugify($text)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    private function getGroupIcon($groupName)
    {
        $icons = [
            'Authentication' => '🔐',
            'Users' => '👥',
            'Testing' => '🧪',
            'Documentation' => '📚',
            'Environment' => '⚙️',
            'System' => '🔧',
        ];

        return $icons[$groupName] ?? '📁';
    }
}
