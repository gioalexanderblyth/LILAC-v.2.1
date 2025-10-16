<?php
// Clean version of analyze-award.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Suppress PHP errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any existing output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set error handler to catch any PHP errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Log the analysis attempt with timestamp
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] FRESH ANALYSIS STARTED - File count: " . count($_FILES) . ", POST count: " . count($_POST));
    
    // Get form data
    $awardName = $_POST['award_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $isReanalyze = isset($_POST['reanalyze']) && $_POST['reanalyze'] === 'true';
    $originalFilePath = $_POST['original_file_path'] ?? '';
    
    // Log analysis type
    if ($isReanalyze) {
        error_log("[$timestamp] ANALYSIS TYPE: Re-analysis of existing file");
    } else {
        error_log("[$timestamp] ANALYSIS TYPE: Fresh upload analysis");
    }
    
    // Handle re-analysis vs new upload
    if ($isReanalyze && !empty($originalFilePath)) {
        // Re-analysis: use existing file
        error_log("Re-analysis request for file: " . $originalFilePath);
        
        // Check if the original file exists
        if (!file_exists($originalFilePath)) {
            throw new Exception('Original file not found: ' . $originalFilePath);
        }
        
        $uploadedFile = [
            'name' => basename($originalFilePath),
            'tmp_name' => $originalFilePath,
            'size' => filesize($originalFilePath),
            'error' => UPLOAD_ERR_OK
        ];
    } else {
        // Normal upload: get uploaded file
        $uploadedFile = $_FILES['award_file'] ?? null;
    }
    
    error_log("Form data - Award name: '$awardName', Description: '$description', File: " . ($uploadedFile ? $uploadedFile['name'] : 'none') . ", Reanalyze: " . ($isReanalyze ? 'yes' : 'no'));

    // Validate required fields
    if ($isReanalyze) {
        // For re-analysis, only need the file
        if (!$uploadedFile) {
            throw new Exception('Missing required fields: file upload is required for re-analysis');
        }
    } else {
        // For new uploads, need all fields
        if (empty($awardName) || empty($description) || !$uploadedFile) {
            throw new Exception('Missing required fields: award name, description, and file upload are required');
        }
    }

    // Load functions and config
    require_once 'award-analysis-functions.php';
    
    // Validate file upload
    error_log("Starting file upload validation");
    validateFileUpload($uploadedFile);
    error_log("File upload validation passed");
    
    // Get file type with fallback
    if (function_exists('mime_content_type')) {
        $fileType = mime_content_type($uploadedFile['tmp_name']);
    } else {
        // Fallback: use file extension
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $fileType = $extensionMap[$extension] ?? 'application/octet-stream';
    }
    error_log("File type detected: " . $fileType);

    // Extract text from uploaded file
    error_log("Starting text extraction for file: " . $uploadedFile['name']);
    $extractedText = extractTextFromFile($uploadedFile['tmp_name'], $fileType);
    error_log("Text extraction completed, length: " . strlen($extractedText));
    error_log("Extracted text preview: " . substr($extractedText, 0, 100) . "...");
    
    if (empty($extractedText)) {
        throw new Exception('Could not extract text from the uploaded file');
    }

    // Load ICONS 2025 awards dataset (updated schema)
    error_log("Loading ICONS 2025 awards dataset");
    $criteriaPath = __DIR__ . '/../assets/icons2025_awards.json';
    if (!file_exists($criteriaPath)) {
        throw new Exception('ICONS 2025 awards dataset not found');
    }
    
    $iconsDataset = json_decode(file_get_contents($criteriaPath), true);
    if (!$iconsDataset || !is_array($iconsDataset)) {
        throw new Exception('Invalid ICONS 2025 dataset format');
    }
    error_log("ICONS 2025 dataset loaded: " . count($iconsDataset) . " categories");

    // Perform analysis with Jaccard + semantic boost + category weights
    error_log("Starting ICONS analysis");
    $analysis = performIconsAnalysis($extractedText, $iconsDataset);
    error_log("Analysis completed: " . count($analysis) . " results");

    // Store the analysis in database (with fallback)
    error_log("Starting to store analysis results");
    $analysisId = storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile, $isReanalyze);
    error_log("Analysis results stored with ID: " . $analysisId);

    // Generate recommendations
    error_log("Starting to generate recommendations");
    $recommendations = generateRecommendations($analysis);
    error_log("Recommendations generated successfully");
    
    // Build counts for eligibility
    $eligibleCount = 0; $partialCount = 0; $notEligibleCount = 0;
    foreach ($analysis as $r) {
        $status = strtolower($r['status'] ?? '');
        if ($status === 'eligible') { $eligibleCount++; }
        elseif ($status === 'partially eligible') { $partialCount++; }
        else { $notEligibleCount++; }
    }
    
    // Log completion
    error_log("[$timestamp] ANALYSIS COMPLETED SUCCESSFULLY - Results count: " . count($analysis));
    
    // Return the analysis results
    $response = [
        'success' => true,
        'analysis_id' => $analysisId,
        'detected_text' => $extractedText,
        'analysis' => $analysis,
        'recommendations' => $recommendations,
        'eligible_count' => $eligibleCount,
        'partial_count' => $partialCount,
        'not_eligible_count' => $notEligibleCount,
        'total_count' => count($analysis),
        'timestamp' => $timestamp,
        'is_fresh_analysis' => !$isReanalyze
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log the error
    error_log("Analyze award error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;
} catch (Error $e) {
    // Catch PHP 7+ errors
    error_log("Analyze award PHP error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Internal server error occurred'
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;
}

?>
