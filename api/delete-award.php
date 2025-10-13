<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once 'config.php';

try {
    // Get the award ID from query parameters
    $awardId = $_GET['id'] ?? '';
    
    if (empty($awardId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Award ID is required']);
        exit();
    }
    
    // For file-based storage, delete the analysis file and associated files
    $dataDir = __DIR__ . '/../data/';
    $uploadsDir = __DIR__ . '/../uploads/awards/';
    
    $deleted = false;
    $deletedFiles = [];
    
    // Find and delete the analysis file
    if (is_dir($dataDir)) {
        $files = glob($dataDir . 'analysis_*.json');
        foreach ($files as $file) {
            if (strpos(basename($file, '.json'), $awardId) !== false) {
                // Read the file to get the associated upload file
                $content = file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data && isset($data['file_path'])) {
                        // Delete the uploaded file
                        $uploadFile = $data['file_path'];
                        if (file_exists($uploadFile)) {
                            if (unlink($uploadFile)) {
                                $deletedFiles[] = basename($uploadFile);
                            }
                        }
                    }
                }
                
                // Delete the analysis file
                if (unlink($file)) {
                    $deletedFiles[] = basename($file);
                    $deleted = true;
                }
                break;
            }
        }
    }
    
    if ($deleted) {
        logActivity('Award deleted successfully: ' . implode(', ', $deletedFiles), 'INFO');
        echo json_encode([
            'success' => true,
            'message' => 'Award deleted successfully',
            'deleted_files' => $deletedFiles
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Award not found']);
    }
    
} catch (Exception $e) {
    logActivity('Delete award failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete award: ' . $e->getMessage()]);
}
?>
