<?php
// Suppress PHP errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Clean any existing output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Database connection
    $pdo = new PDO('sqlite:../database/mou_moa.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get award counters
    $stmt = $pdo->query("SELECT award_name, count FROM award_counters ORDER BY award_name");
    $counters = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $counters[$row['award_name']] = $row['count'];
    }
    
    // Get recent analysis history
    $stmt = $pdo->query("
        SELECT a.title, a.file_name, aa.predicted_category, aa.confidence, aa.created_at
        FROM awards a
        JOIN award_analysis aa ON a.id = aa.award_id
        ORDER BY aa.created_at DESC
        LIMIT 10
    ");
    $recentAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'counters' => $counters,
        'recent_analysis' => $recentAnalysis
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
}
?>
