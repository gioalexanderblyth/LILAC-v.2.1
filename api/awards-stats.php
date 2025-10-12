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

require_once 'config.php';

try {
    // Get database connection from config
    $pdo = getDatabaseConnection();
    
    // Initialize counters with default values
    $counters = [
        'Global Citizenship Award' => 0,
        'Outstanding International Education Program Award' => 0,
        'Sustainability Award' => 0,
        'Best ASEAN Awareness Initiative Award' => 0,
        'Emerging Leadership Award' => 0,
        'Internationalization Leadership Award' => 0,
        'Best CHED Regional Office for Internationalization Award' => 0,
        'Most Promising Regional IRO Community Award' => 0
    ];
    
    // Check if we're using file-based fallback
    $isFileBased = ($pdo instanceof FileBasedDatabase);
    
    if ($isFileBased) {
        // Provide mock data for demonstration
        $counters = [
            'Global Citizenship Award' => 12,
            'Outstanding International Education Program Award' => 8,
            'Sustainability Award' => 15,
            'Best ASEAN Awareness Initiative Award' => 6,
            'Emerging Leadership Award' => 9,
            'Internationalization Leadership Award' => 4,
            'Best CHED Regional Office for Internationalization Award' => 3,
            'Most Promising Regional IRO Community Award' => 7
        ];
        
        $recentAnalysis = [
            [
                'title' => 'Sample Global Citizenship Initiative',
                'file_name' => 'global_citizenship_doc.pdf',
                'predicted_category' => 'Global Citizenship Award',
                'confidence' => 0.87,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Sustainability Program Proposal',
                'file_name' => 'sustainability_proposal.docx',
                'predicted_category' => 'Sustainability Award',
                'confidence' => 0.92,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
        
        $response = [
            'success' => true,
            'counters' => $counters,
            'recent_analysis' => $recentAnalysis,
            'database_status' => 'file_based_fallback',
            'message' => 'Using file-based fallback. Enable SQLite extension for full functionality.'
        ];
        
        echo json_encode($response);
        exit;
    }
    
    // Get actual counts from award_analysis table
    try {
        $stmt = $pdo->query("
            SELECT 
                CASE 
                    WHEN analysis_results LIKE '%\"Global Citizenship Award\"%' THEN 'Global Citizenship Award'
                    WHEN analysis_results LIKE '%\"Outstanding International Education Program Award\"%' THEN 'Outstanding International Education Program Award'
                    WHEN analysis_results LIKE '%\"Sustainability Award\"%' THEN 'Sustainability Award'
                    WHEN analysis_results LIKE '%\"Best ASEAN Awareness Initiative Award\"%' THEN 'Best ASEAN Awareness Initiative Award'
                    WHEN analysis_results LIKE '%\"Emerging Leadership Award\"%' THEN 'Emerging Leadership Award'
                    WHEN analysis_results LIKE '%\"Internationalization Leadership Award\"%' THEN 'Internationalization Leadership Award'
                    WHEN analysis_results LIKE '%\"Best CHED Regional Office for Internationalization Award\"%' THEN 'Best CHED Regional Office for Internationalization Award'
                    WHEN analysis_results LIKE '%\"Most Promising Regional IRO Community Award\"%' THEN 'Most Promising Regional IRO Community Award'
                    ELSE 'Other'
                END as award_category,
                COUNT(*) as count
            FROM award_analysis 
            WHERE analysis_results IS NOT NULL
            GROUP BY award_category
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            if ($row['award_category'] !== 'Other' && isset($counters[$row['award_category']])) {
                $counters[$row['award_category']] = (int)$row['count'];
            }
        }
    } catch (Exception $e) {
        // If query fails, use default counters
        logActivity('Failed to get award counters: ' . $e->getMessage(), 'WARNING');
    }
    
    // Get recent analysis history
    $recentAnalysis = [];
    try {
        $stmt = $pdo->query("
            SELECT 
                title,
                file_name,
                detected_text,
                analysis_results,
                created_at
            FROM award_analysis 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        $recentAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse analysis results to get predicted category and confidence
        foreach ($recentAnalysis as &$analysis) {
            if ($analysis['analysis_results']) {
                $results = json_decode($analysis['analysis_results'], true);
                if ($results && !empty($results)) {
                    $topResult = $results[0]; // Get highest scoring award
                    $analysis['predicted_category'] = $topResult['award'] ?? 'Unknown';
                    $analysis['confidence'] = ($topResult['score'] ?? 0) / 100;
                } else {
                    $analysis['predicted_category'] = 'Unknown';
                    $analysis['confidence'] = 0;
                }
            } else {
                $analysis['predicted_category'] = 'Unknown';
                $analysis['confidence'] = 0;
            }
        }
    } catch (Exception $e) {
        // If query fails, use empty array
        logActivity('Failed to get recent analysis: ' . $e->getMessage(), 'WARNING');
    }
    
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
    $response = [
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'success' => false
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
}
?>
