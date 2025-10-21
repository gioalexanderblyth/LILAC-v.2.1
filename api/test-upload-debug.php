<?php
// Simple test to debug upload issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test if we can receive POST data
$response = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'has_files' => isset($_FILES['file']),
    'file_error' => $_FILES['file']['error'] ?? 'no file',
    'file_name' => $_FILES['file']['name'] ?? 'no file',
    'has_title' => isset($_POST['title']),
    'title' => $_POST['title'] ?? 'no title',
    'post_keys' => array_keys($_POST),
    'files_keys' => array_keys($_FILES)
];

// Try to create a test file
try {
    $dataDir = __DIR__ . '/../data/';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $testData = [
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'response' => $response
    ];
    
    $testFile = $dataDir . 'test_upload_' . time() . '.json';
    $result = file_put_contents($testFile, json_encode($testData, JSON_PRETTY_PRINT));
    
    $response['file_created'] = $result !== false;
    $response['test_file'] = $result ? basename($testFile) : 'failed';
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
