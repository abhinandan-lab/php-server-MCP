<?php
// /api/controllers/ServiceTesterController.php

namespace App\Controllers;

class ServiceTesterController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // Clear output buffers to prevent blank page
        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->renderServiceTesterUI();
    }

    public function execute()
    {
        // Get request data first
        $data = $this->getRequestData();

        // Check if debug mode should be enabled for this request
        $debugEnabled = $data['debug_enabled'] ?? false;
        if ($debugEnabled) {
            $_ENV['DEBUG_MODE'] = 'true';
        }

        // Start capturing ALL output including pp() and ppp()
        ob_start();

        try {
            $method = $data['method'] ?? null;

            if (!$method || !method_exists($this, $method)) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['error' => "Test method '{$method}' not found"]);
                exit;
            }

            // Execute test method (returns raw result)
            $result = $this->$method();

            // Get debug output
            $debugOutput = ob_get_clean();

            // If there's debug output, return as plain text with both debug and result
            if (!empty(trim($debugOutput))) {
                header('Content-Type: text/plain; charset=UTF-8');

                // Print debug output first
                echo $debugOutput;

                // Then print the result
                echo "\n\n========================================\n";
                echo "FINAL RESULT:\n";
                echo "========================================\n";
                print_r($result);
                echo "\n";
                exit;
            }

            // No debug output - return clean JSON
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;
        } catch (\Exception $e) {
            $debugOutput = ob_get_clean();

            // If there's debug output, show it with error
            if (!empty(trim($debugOutput))) {
                header('Content-Type: text/plain; charset=UTF-8');

                echo $debugOutput;
                echo "\n\n========================================\n";
                echo "ERROR OCCURRED:\n";
                echo "========================================\n";
                echo "Message: " . $e->getMessage() . "\n";
                echo "Type: " . get_class($e) . "\n";
                echo "File: " . $e->getFile() . "\n";
                echo "Line: " . $e->getLine() . "\n";
                exit;
            }

            // No debug - return JSON error
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ], JSON_PRETTY_PRINT);
            exit;
        }
    }


    /**
     * Toggle DEBUG_MODE
     */
    public function toggleDebug()
    {
        $data = $this->getRequestData();
        $enabled = $data['enabled'] ?? false;

        // Set in $_ENV for current session
        $_ENV['DEBUG_MODE'] = $enabled ? 'true' : 'false';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'debug_mode' => $_ENV['DEBUG_MODE']
        ]);
        exit;
    }

    // ============================================
    // EXAMPLE TEST METHODS
    // ============================================



    /**
     * Test: Create User
     */
    private function testCreateUser()
    {
        $data = $this->getRequestData();

        pp("Incoming request data:");
        pp($data);

        $email = $data['email'] ?? 'test@example.com';
        $password = $data['password'] ?? 'password123';
        $name = $data['name'] ?? 'Test User';

        pp("Extracted parameters:");
        ppp(['email' => $email, 'password' => $password, 'name' => $name]);

        // Initialize UserService
        $userService = new \App\Services\UserService($this->conn);

        pp("Calling createUser service...");

        // Call the service method
        $result = $userService->createUser($email, $password, $name);

        pp("Service returned:");
        ppp($result);

        return $result;
    }

    /**
     * Test: Get User By ID
     */
    private function testGetUserById()
    {
        $data = $this->getRequestData();
        $userId = $data['userId'] ?? 1;

        pp("Getting user with ID: $userId");

        // Initialize UserService
        $userService = new \App\Services\UserService($this->conn);

        // Call the service method
        $user = $userService->getUserById($userId);

        pp("User found:");
        ppp($user);

        return $user;
    }





    private function testSimpleExample()
    {
        pp('Executing simple example');

        $result = [
            'message' => 'Simple test executed successfully',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'user_count' => 100,
                'active_sessions' => 45
            ]
        ];

        pp($result);

        return $result;
    }

    private function testWithParameters()
    {
        $data = $this->getRequestData();

        pp($data);

        $userId = $data['userId'] ?? null;
        $action = $data['action'] ?? 'default';

        $result = [
            'received_params' => [
                'userId' => $userId,
                'action' => $action
            ],
            'processed' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        ppp($result);

        return $result;
    }

    private function testDatabaseQuery()
    {
        pp('Running database query...');

        $result = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT COUNT(*) as total FROM admin_user WHERE 1=1'
        ]);

        ppp($result);

        return $result;
    }

    private function testQueryWithParams()
    {
        $data = $this->getRequestData();
        $userId = $data['userId'] ?? 1;

        pp("Querying user with ID: $userId");

        $result = RunQuery([
            'conn' => $this->conn,
            'query' => 'SELECT * FROM admin_user WHERE id = :id',
            'params' => [':id' => $userId]
        ]);

        ppp($result);

        return $result;
    }




    private function testErrorHandling()
    {
        $data = $this->getRequestData();
        $shouldFail = $data['shouldFail'] ?? false;

        pp(['shouldFail' => $shouldFail]);

        if ($shouldFail) {
            throw new \Exception('Intentional error for testing');
        }

        return [
            'status' => 'passed',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    // ============================================
    // UI RENDERING
    // ============================================

    private function renderServiceTesterUI()
    {
        header('Content-Type: text/html');
        $testMethods = $this->getAvailableTestMethods();
        $debugMode = !empty($_ENV['DEBUG_MODE']) && ($_ENV['DEBUG_MODE'] === 'true' || $_ENV['DEBUG_MODE'] === '1');
        
        $sampleDataJson = json_encode([
            'testSimpleExample' => '{}',
            'testWithParameters' => json_encode(['userId' => 123, 'action' => 'update'], JSON_PRETTY_PRINT),
            'testDatabaseQuery' => '{}',
            'testQueryWithParams' => json_encode(['userId' => 1], JSON_PRETTY_PRINT),
            'testErrorHandling' => json_encode(['shouldFail' => false], JSON_PRETTY_PRINT),

            // UserService tests
            'testCreateUser' => json_encode([
                'email' => 'john.doe@example.com',
                'password' => 'SecurePass123!',
                'name' => 'John Doe'
            ], JSON_PRETTY_PRINT),

            'testGetUserById' => json_encode(['userId' => 1], JSON_PRETTY_PRINT),
        ]);


?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Service Tester</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #0d1117;
                    color: #e6edf3;
                    line-height: 1.6;
                    padding: 20px;
                }

                .container {
                    max-width: 1600px;
                    margin: 0 auto;
                }

                .header {
                    background: linear-gradient(135deg, #3c286a 0%, #5f5972 100%);
                    padding: 20px 30px;
                    border-radius: 8px;
                    margin-bottom: 24px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
                }

                .header-left h1 {
                    font-size: 24px;
                    font-weight: 600;
                    color: #ffffff;
                    margin-bottom: 4px;
                }

                .header-left p {
                    font-size: 14px;
                    color: #e9d5ff;
                }

                .header-right {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }

                .stat-badge {
                    background: rgba(255, 255, 255, 0.15);
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 600;
                }

                /* Debug Toggle Switch */
                .debug-toggle {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .debug-toggle label {
                    font-size: 14px;
                    font-weight: 600;
                }

                .toggle-switch {
                    position: relative;
                    width: 50px;
                    height: 26px;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 13px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .toggle-switch.active {
                    background: #3fb950;
                }

                .toggle-slider {
                    position: absolute;
                    top: 3px;
                    left: 3px;
                    width: 20px;
                    height: 20px;
                    background: white;
                    border-radius: 50%;
                    transition: transform 0.3s ease;
                }

                .toggle-switch.active .toggle-slider {
                    transform: translateX(24px);
                }

                .test-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                    gap: 16px;
                    margin-bottom: 24px;
                }

                .test-card {
                    background: #161b22;
                    border: 1px solid #30363d;
                    padding: 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    position: relative;
                }

                .test-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 3px;
                    height: 100%;
                    background: #8b5cf6;
                    transform: scaleY(0);
                    transition: transform 0.2s ease;
                }

                .test-card:hover {
                    border-color: #8b5cf6;
                }

                .test-card:hover::before {
                    transform: scaleY(1);
                }

                .test-card.active {
                    border-color: #8b5cf6;
                    background: #1a1f27;
                }

                .test-card h3 {
                    font-size: 16px;
                    font-weight: 600;
                    color: #a78bfa;
                    margin-bottom: 6px;
                }

                .test-card p {
                    color: #8b949e;
                    font-size: 13px;
                }

                .content-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 24px;
                }

                .section {
                    background: #161b22;
                    border: 1px solid #30363d;
                    padding: 24px;
                    border-radius: 8px;
                    display: none;
                    height: fit-content;
                }

                .section.show {
                    display: block;
                }

                .section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                }

                .section h3 {
                    font-size: 18px;
                    font-weight: 600;
                    color: #e6edf3;
                }

                .form-group {
                    margin-bottom: 16px;
                }

                .form-group label {
                    display: block;
                    font-weight: 500;
                    margin-bottom: 8px;
                    color: #c9d1d9;
                    font-size: 14px;
                }

                .form-control {
                    width: 100%;
                    padding: 10px 14px;
                    background: #0d1117;
                    border: 1px solid #30363d;
                    border-radius: 6px;
                    color: #e6edf3;
                    font-size: 14px;
                    font-family: 'Monaco', 'Menlo', monospace;
                    transition: all 0.2s ease;
                }

                .form-control:focus {
                    outline: none;
                    border-color: #8b5cf6;
                    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
                }

                textarea.form-control {
                    resize: vertical;
                    min-height: 120px;
                }

                .btn-group {
                    display: flex;
                    gap: 10px;
                    margin-top: 20px;
                }

                .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .btn-primary {
                    background: #8b5cf6;
                    color: #ffffff;
                }

                .btn-primary:hover {
                    background: #7c3aed;
                }

                .btn-secondary {
                    background: #21262d;
                    color: #c9d1d9;
                    border: 1px solid #30363d;
                }

                .btn-secondary:hover {
                    background: #30363d;
                }

                .btn-small {
                    padding: 6px 12px;
                    font-size: 13px;
                }

                .response-container {
                    background: #161b22;
                    border: 1px solid #30363d;
                    border-radius: 8px;
                    overflow: hidden;
                    display: none;
                    height: fit-content;
                }

                .response-container.show {
                    display: block;
                }

                .response-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 14px 20px;
                    background: #0d1117;
                    border-bottom: 1px solid #30363d;
                }

                .response-status {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 4px 10px;
                    border-radius: 4px;
                    font-size: 13px;
                    font-weight: 600;
                }

                .status-success {
                    background: rgba(46, 160, 67, 0.15);
                    color: #3fb950;
                }

                .status-error {
                    background: rgba(248, 81, 73, 0.15);
                    color: #f85149;
                }

                .response-body {
                    padding: 20px;
                }

                .response-content {
                    background: #0d1117;
                    padding: 16px;
                    border-radius: 6px;
                    font-family: 'Monaco', 'Menlo', monospace;
                    font-size: 13px;
                    line-height: 1.6;
                    color: #e6edf3;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    max-height: 600px;
                    overflow-y: auto;
                }

                .loading {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    color: #a78bfa;
                }

                .spinner {
                    width: 14px;
                    height: 14px;
                    border: 2px solid #30363d;
                    border-top-color: #a78bfa;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                }

                @keyframes spin {
                    to {
                        transform: rotate(360deg);
                    }
                }

                .json-key {
                    color: #a78bfa;
                }

                .json-string {
                    color: #c4b5fd;
                }

                .json-number {
                    color: #a78bfa;
                }

                .json-boolean {
                    color: #fbbf24;
                }

                .json-null {
                    color: #8b949e;
                }

                .toast {
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    background: #21262d;
                    color: #e6edf3;
                    padding: 14px 20px;
                    border-radius: 8px;
                    border: 1px solid #30363d;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                    z-index: 9999;
                    opacity: 0;
                    transform: translateY(20px);
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    min-width: 250px;
                }

                .toast.show {
                    opacity: 1;
                    transform: translateY(0);
                }

                .toast.success {
                    border-color: #3fb950;
                }

                .toast.error {
                    border-color: #f85149;
                }

                .toast.warning {
                    border-color: #fbbf24;
                }

                @media (max-width: 1024px) {
                    .content-grid {
                        grid-template-columns: 1fr;
                    }

                    .header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 12px;
                    }

                    .test-grid {
                        grid-template-columns: 1fr;
                    }

                    .btn-group {
                        flex-direction: column;
                    }

                    .toast {
                        bottom: 20px;
                        right: 20px;
                        left: 20px;
                        min-width: auto;
                    }
                }
            </style>
        </head>

        <body>
            <div class="container">
                <div class="header">
                    <div class="header-left">
                        <h1>🧪 Service Tester</h1>
                        <p>Test service methods quickly</p>
                    </div>
                    <div class="header-right">
                        <div class="debug-toggle">
                            <label>Debug Mode</label>
                            <div class="toggle-switch <?= $debugMode ? 'active' : '' ?>" id="debugToggle" onclick="toggleDebugMode()">
                                <div class="toggle-slider"></div>
                            </div>
                        </div>
                        <div class="stat-badge">
                            <?= count($testMethods) ?> Tests
                        </div>
                    </div>
                </div>

                <div class="test-grid">
                    <?php foreach ($testMethods as $method): ?>
                        <div class="test-card" onclick="selectTest('<?= $method ?>')">
                            <h3><?= $method ?></h3>
                            <p>Click to test</p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="content-grid">
                    <div class="section" id="paramsSection">
                        <div class="section-header">
                            <h3>Request</h3>
                            <button class="btn btn-secondary btn-small" onclick="fillSampleData()">
                                📝 Fill Sample Data
                            </button>
                        </div>

                        <div class="form-group">
                            <label>Selected Test Method</label>
                            <input type="text" class="form-control" id="selectedMethod" readonly>
                        </div>

                        <div class="form-group">
                            <label>Custom Parameters (JSON)</label>
                            <textarea class="form-control" id="customParams" placeholder='{"key": "value"}'></textarea>
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="executeTest()">
                                ▶ Execute Test
                            </button>
                            <button class="btn btn-secondary" onclick="clearTest()">
                                ✕ Clear
                            </button>
                        </div>
                    </div>

                    <div class="response-container" id="responseContainer">
                        <div class="response-header">
                            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #e6edf3;">Response</h3>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="response-status" id="responseStatus"></div>
                                <button class="btn btn-secondary btn-small" onclick="copyResponse()">
                                    📋 Copy
                                </button>
                            </div>
                        </div>
                        <div class="response-body">
                            <div class="response-content" id="responseContent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="toast" class="toast"></div>

            <script>
                let currentMethod = null;
                let debugModeEnabled = <?= $debugMode ? 'true' : 'false' ?>;
                const sampleData = <?= $sampleDataJson ?>;

                function showToast(message, type = 'success') {
                    const toast = $('#toast');
                    toast.removeClass('success error warning').addClass(type);
                    toast.html(message);
                    toast.addClass('show');

                    setTimeout(() => {
                        toast.removeClass('show');
                    }, 2000);
                }

                function toggleDebugMode() {
                    debugModeEnabled = !debugModeEnabled;
                    const toggle = $('#debugToggle');

                    if (debugModeEnabled) {
                        toggle.addClass('active');
                    } else {
                        toggle.removeClass('active');
                    }

                    $.ajax({
                        url: '/api/service-test/toggle-debug',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            enabled: debugModeEnabled
                        }),
                        success: function() {
                            showToast(`Debug mode ${debugModeEnabled ? 'enabled' : 'disabled'}`, 'success');
                        },
                        error: function() {
                            showToast('Failed to toggle debug mode', 'error');
                        }
                    });
                }

                function selectTest(method) {
                    document.querySelectorAll('.test-card').forEach(card => {
                        card.classList.remove('active');
                    });

                    event.currentTarget.classList.add('active');

                    currentMethod = method;
                    $('#selectedMethod').val(method);
                    $('#paramsSection').addClass('show');
                    $('#responseContainer').removeClass('show');

                    if (sampleData[method]) {
                        $('#customParams').val(sampleData[method]);
                    } else {
                        $('#customParams').val('{}');
                    }
                }

                function fillSampleData() {
                    if (!currentMethod) {
                        showToast('⚠️ Please select a test method first', 'warning');
                        return;
                    }

                    if (sampleData[currentMethod]) {
                        $('#customParams').val(sampleData[currentMethod]);
                        showToast('✓ Sample data filled', 'success');
                    } else {
                        $('#customParams').val('{}');
                    }
                }

                function executeTest() {
                    if (!currentMethod) {
                        showToast('⚠️ Please select a test method first', 'warning');
                        return;
                    }

                    let params = {};
                    const customParams = $('#customParams').val().trim();

                    if (customParams) {
                        try {
                            params = JSON.parse(customParams);
                        } catch (e) {
                            showResponse('error', JSON.stringify({
                                error: 'Invalid JSON format',
                                message: e.message
                            }, null, 2));
                            showToast('✗ Invalid JSON format', 'error');
                            return;
                        }
                    }

                    params.method = currentMethod;
                    params.debug_enabled = debugModeEnabled;

                    $('#responseContent').html('<div class="loading"><div class="spinner"></div>Executing test...</div>');
                    $('#responseContainer').addClass('show');

                    $.ajax({
                        url: '/api/service-test/execute',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(params),
                        dataType: 'text', // Accept any response type
                        success: function(response, textStatus, xhr) {
                            const contentType = xhr.getResponseHeader('Content-Type');

                            if (contentType && contentType.includes('text/plain')) {
                                // Plain text response with debug output
                                showResponse('success', response, true);
                            } else {
                                // JSON response
                                try {
                                    const jsonResponse = JSON.parse(response);
                                    showResponse('success', JSON.stringify(jsonResponse, null, 2));
                                } catch (e) {
                                    showResponse('success', response);
                                }
                            }
                        },
                        error: function(xhr) {
                            const contentType = xhr.getResponseHeader('Content-Type');

                            if (contentType && contentType.includes('text/plain')) {
                                showResponse('error', xhr.responseText, true);
                            } else {
                                const error = xhr.responseJSON || {
                                    error: 'Request failed',
                                    status: xhr.status,
                                    statusText: xhr.statusText
                                };
                                showResponse('error', JSON.stringify(error, null, 2));
                            }
                        }
                    });
                }

                function showResponse(type, content, isPlainText = false) {
                    const statusEl = $('#responseStatus');
                    statusEl.removeClass('status-success status-error')
                        .addClass('status-' + type);

                    if (type === 'success') {
                        statusEl.html('✓ Success');
                    } else {
                        statusEl.html('✗ Error');
                    }

                    // If plain text (with debug), don't syntax highlight
                    if (isPlainText) {
                        $('#responseContent').text(content);
                    } else {
                        const highlighted = syntaxHighlight(content);
                        $('#responseContent').html(highlighted);
                    }

                    $('#responseContainer').addClass('show');
                }

                function showResponse(type, content) {
                    const statusEl = $('#responseStatus');
                    statusEl.removeClass('status-success status-error')
                        .addClass('status-' + type);

                    if (type === 'success') {
                        statusEl.html('✓ Success');
                    } else {
                        statusEl.html('✗ Error');
                    }

                    const highlighted = syntaxHighlight(content);
                    $('#responseContent').html(highlighted);
                    $('#responseContainer').addClass('show');
                }

                function syntaxHighlight(json) {
                    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(match) {
                        let cls = 'json-number';
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

                function clearTest() {
                    currentMethod = null;
                    $('#selectedMethod').val('');
                    $('#customParams').val('');
                    $('#paramsSection').removeClass('show');
                    $('#responseContainer').removeClass('show');

                    document.querySelectorAll('.test-card').forEach(card => {
                        card.classList.remove('active');
                    });

                    showToast('✓ Cleared', 'success');
                }

                function copyResponse() {
                    const content = $('#responseContent').text();
                    navigator.clipboard.writeText(content).then(() => {
                        showToast('✓ Response copied to clipboard!', 'success');
                    }).catch(() => {
                        showToast('✗ Failed to copy response', 'error');
                    });
                }
            </script>
        </body>

        </html>
<?php
    }

    private function getAvailableTestMethods()
    {
        $methods = get_class_methods($this);
        $testMethods = [];

        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                $testMethods[] = $method;
            }
        }

        return $testMethods;
    }
}
