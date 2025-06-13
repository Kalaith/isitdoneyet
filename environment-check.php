<?php
// Production Environment Check for Is It Done Yet? API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

$checks = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'None',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'None',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'current_directory' => getcwd(),
    'backend_exists' => file_exists(__DIR__ . '/backend'),
    'vendor_exists' => file_exists(__DIR__ . '/backend/vendor'),
    'autoload_exists' => file_exists(__DIR__ . '/backend/vendor/autoload.php'),
    'env_file_exists' => file_exists(__DIR__ . '/backend/.env'),
    'writable_logs' => is_writable(__DIR__ . '/backend/storage/logs'),
];

// Test database connection if autoload exists
if ($checks['autoload_exists']) {
    try {
        require_once __DIR__ . '/backend/vendor/autoload.php';
        
        // Load environment variables
        if ($checks['env_file_exists']) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/backend/');
            $dotenv->load();
        }
        
        // Test database connection
        $dbService = new App\External\DatabaseService();
        $checks['database_connection'] = $dbService->testConnection() ? 'SUCCESS' : 'FAILED';
        
    } catch (Exception $e) {
        $checks['database_connection'] = 'ERROR: ' . $e->getMessage();
    }
} else {
    $checks['database_connection'] = 'CANNOT_TEST_NO_AUTOLOAD';
}

echo json_encode([
    'status' => 'Environment Check',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => $checks
], JSON_PRETTY_PRINT);
?>
