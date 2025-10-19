<?php
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

require_once 'config.php';

try {
    // Get database connection
    $pdo = getDatabaseConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $analysisId = $input['analysis_id'] ?? null;
    $exportFormat = $input['format'] ?? 'pdf'; // pdf or docx
    $includeExtractedText = $input['include_extracted_text'] ?? true;
    
    if (!$analysisId) {
        throw new Exception('Analysis ID is required');
    }
    
    // Get analysis data from database
    $stmt = $pdo->prepare("SELECT * FROM award_analysis WHERE id = ?");
    $stmt->execute([$analysisId]);
    $analysis = $stmt->fetch();
    
    if (!$analysis) {
        throw new Exception('Analysis not found');
    }
    
    $analysisResults = json_decode($analysis['analysis_results'], true);
    
    if ($exportFormat === 'pdf') {
        $filePath = generatePDFReport($analysis, $analysisResults, $includeExtractedText);
        $mimeType = 'application/pdf';
        $extension = 'pdf';
    } else {
        $filePath = generateDOCXReport($analysis, $analysisResults, $includeExtractedText);
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $extension = 'docx';
    }
    
    // Return download URL
    echo json_encode([
        'success' => true,
        'download_url' => $filePath,
        'filename' => 'award-analysis-' . $analysisId . '.' . $extension,
        'mime_type' => $mimeType
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate PDF report
 */
function generatePDFReport($analysis, $analysisResults, $includeExtractedText) {
    // Create a simple HTML report and convert to PDF
    $html = generateReportHTML($analysis, $analysisResults, $includeExtractedText);
    
    // For production, use a proper PDF library like mPDF or TCPDF
    // For now, we'll create a simple HTML file that can be printed as PDF
    
    $filename = 'analysis-report-' . $analysis['id'] . '-' . time() . '.html';
    $filepath = __DIR__ . '/../uploads/reports/' . $filename;
    
    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($filepath, $html);
    
    return '../uploads/reports/' . $filename;
}

/**
 * Generate DOCX report
 */
function generateDOCXReport($analysis, $analysisResults, $includeExtractedText) {
    // For production, use PhpOffice\PhpWord
    // For now, create a simple text file with .docx extension
    
    $filename = 'analysis-report-' . $analysis['id'] . '-' . time() . '.txt';
    $filepath = __DIR__ . '/../uploads/reports/' . $filename;
    
    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $content = generateReportText($analysis, $analysisResults, $includeExtractedText);
    file_put_contents($filepath, $content);
    
    return '../uploads/reports/' . $filename;
}

/**
 * Generate HTML report
 */
function generateReportHTML($analysis, $analysisResults, $includeExtractedText) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Award Analysis Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #137fec; padding-bottom: 20px; }
        .section { margin-bottom: 30px; }
        .section h2 { color: #137fec; border-left: 4px solid #137fec; padding-left: 10px; }
        .award-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .eligible { border-left: 4px solid #10b981; }
        .partial { border-left: 4px solid #f59e0b; }
        .not-eligible { border-left: 4px solid #ef4444; }
        .score-bar { background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .score-fill { height: 100%; transition: width 0.3s; }
        .score-green { background: #10b981; }
        .score-yellow { background: #f59e0b; }
        .score-red { background: #ef4444; }
        .criteria-badge { display: inline-block; background: #e5e7eb; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin: 2px; }
        .extracted-text { background: #f9fafb; padding: 15px; border-radius: 8px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Award Analysis Report</h1>
        <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
    </div>
    
    <div class="section">
        <h2>Document Information</h2>
        <p><strong>Title:</strong> ' . htmlspecialchars($analysis['title']) . '</p>
        <p><strong>Description:</strong> ' . htmlspecialchars($analysis['description']) . '</p>
        <p><strong>File:</strong> ' . htmlspecialchars($analysis['file_name']) . '</p>
        <p><strong>Analysis Date:</strong> ' . date('F j, Y \a\t g:i A', strtotime($analysis['created_at'])) . '</p>
    </div>';
    
    if ($includeExtractedText && !empty($analysis['detected_text'])) {
        $html .= '
    <div class="section">
        <h2>Extracted Text</h2>
        <div class="extracted-text">' . htmlspecialchars($analysis['detected_text']) . '</div>
    </div>';
    }
    
    $html .= '
    <div class="section">
        <h2>Award Analysis Results</h2>';
    
    if (!empty($analysisResults)) {
        foreach ($analysisResults as $award) {
            $statusClass = '';
            $scoreClass = '';
            
            switch ($award['status']) {
                case 'Eligible':
                    $statusClass = 'eligible';
                    $scoreClass = 'score-green';
                    break;
                case 'Partially Eligible':
                    $statusClass = 'partial';
                    $scoreClass = 'score-yellow';
                    break;
                default:
                    $statusClass = 'not-eligible';
                    $scoreClass = 'score-red';
            }
            
            $criteriaBadges = '';
            if (!empty($award['criteria_met'])) {
                foreach ($award['criteria_met'] as $criteria) {
                    $criteriaBadges .= '<span class="criteria-badge">' . htmlspecialchars($criteria) . '</span>';
                }
            }
            
            $html .= '
        <div class="award-card ' . $statusClass . '">
            <h3>' . htmlspecialchars($award['award']) . '</h3>
            <p><strong>Category:</strong> ' . htmlspecialchars($award['category']) . '</p>
            <p><strong>Status:</strong> ' . htmlspecialchars($award['status']) . '</p>
            <p><strong>Score:</strong> ' . $award['score'] . '%</p>
            <div class="score-bar">
                <div class="score-fill ' . $scoreClass . '" style="width: ' . min($award['score'], 100) . '%"></div>
            </div>
            <p><strong>Criteria Met:</strong> ' . $criteriaBadges . '</p>
            <p><strong>Recommendation:</strong> ' . htmlspecialchars($award['recommendation']) . '</p>
        </div>';
        }
    } else {
        $html .= '<p>No award matches found.</p>';
    }
    
    $html .= '
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Generate text report
 */
function generateReportText($analysis, $analysisResults, $includeExtractedText) {
    $content = "AWARD ANALYSIS REPORT\n";
    $content .= "Generated on " . date('F j, Y \a\t g:i A') . "\n";
    $content .= str_repeat("=", 50) . "\n\n";
    
    $content .= "DOCUMENT INFORMATION\n";
    $content .= str_repeat("-", 20) . "\n";
    $content .= "Title: " . $analysis['title'] . "\n";
    $content .= "Description: " . $analysis['description'] . "\n";
    $content .= "File: " . $analysis['file_name'] . "\n";
    $content .= "Analysis Date: " . date('F j, Y \a\t g:i A', strtotime($analysis['created_at'])) . "\n\n";
    
    if ($includeExtractedText && !empty($analysis['detected_text'])) {
        $content .= "EXTRACTED TEXT\n";
        $content .= str_repeat("-", 15) . "\n";
        $content .= $analysis['detected_text'] . "\n\n";
    }
    
    $content .= "AWARD ANALYSIS RESULTS\n";
    $content .= str_repeat("-", 25) . "\n";
    
    if (!empty($analysisResults)) {
        foreach ($analysisResults as $award) {
            $content .= "\n" . $award['award'] . "\n";
            $content .= "Category: " . $award['category'] . "\n";
            $content .= "Status: " . $award['status'] . "\n";
            $content .= "Score: " . $award['score'] . "%\n";
            
            if (!empty($award['criteria_met'])) {
                $content .= "Criteria Met: " . implode(', ', $award['criteria_met']) . "\n";
            }
            
            $content .= "Recommendation: " . $award['recommendation'] . "\n";
            $content .= str_repeat("-", 30) . "\n";
        }
    } else {
        $content .= "No award matches found.\n";
    }
    
    return $content;
}
?>
