<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// Include config for database connection with fallback
require_once 'config.php';

function db() {
    try {
        $pdo = getDatabaseConnection();
        
        // Check if we're using file-based fallback
        if ($pdo instanceof FileBasedDatabase) {
            // Return the fallback database object
            return $pdo;
        }
        
        // For SQLite, try to create tables if they don't exist
        $sqlFile = dirname(__DIR__) . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
        
        return $pdo;
    } catch (Exception $e) {
        // Return file-based fallback if SQLite fails
        logActivity('Database connection failed in awards.php: ' . $e->getMessage(), 'WARNING');
        return new FileBasedDatabase();
    }
}

function requireAuth($requireAdmin = false) {
    $user = $_SERVER['HTTP_X_USER'] ?? '';
    $role = strtolower($_SERVER['HTTP_X_ROLE'] ?? '');
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    if ($requireAdmin && $role !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    return [$user, $role];
}

function jsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function storeUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) { return [null, null]; }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $y = date('Y'); $m = date('m');
    $baseDir = dirname(__DIR__) . "/assets/awards/$y/$m";
    if (!is_dir($baseDir)) { mkdir($baseDir, 0755, true);
    }
    $basename = uniqid('award_', true) . '.' . $ext;
    $dest = "$baseDir/$basename";
    if (!move_uploaded_file($file['tmp_name'], $dest)) { return [null, null]; }
    $rel = "assets/awards/$y/$m/$basename";
    return [$basename, $rel];
}

function classify($text, $meta) {
    require_once __DIR__ . '/classify.php';
    return classify_text($text, $meta);
}

function uploadAward($pdo) {
    list($authUser,) = requireAuth(false);
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? null;
    $description = $_POST['description'] ?? '';
    $ocrText = $_POST['ocr_text'] ?? '';
    $createdBy = $authUser ?: ($_POST['created_by'] ?? 'web');
    $meta = $_POST['meta'] ?? '';
    if (is_string($meta)) { $meta = json_decode($meta, true) ?? []; }
    list($fileName, $filePath) = storeUpload($_FILES['file'] ?? null);
    
    // Check if we're using file-based fallback
    if ($pdo instanceof FileBasedDatabase) {
        // Generate mock ID and result
        $awardId = 'fallback_' . time();
        $text = trim($ocrText);
        $result = classify($text, $meta);
        
        // Store in file system
        $uploadData = [
            'id' => $awardId,
            'title' => $title,
            'date' => $date,
            'description' => $description,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'ocr_text' => $ocrText,
            'created_by' => $createdBy,
            'analysis' => $result,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $dataDir = __DIR__ . '/../data/';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $uploadFile = $dataDir . 'award_upload_' . time() . '_' . uniqid() . '.json';
        file_put_contents($uploadFile, json_encode($uploadData));
        
        logActivity('Award upload stored in file-based fallback: ' . $uploadFile, 'INFO');
        
        echo json_encode(['id' => $awardId, 'analysis' => $result]);
        return;
    }
    
    // Normal database operations for SQLite
    $stmt = $pdo->prepare('INSERT INTO awards (title, date, description, file_name, file_path, ocr_text, created_by) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$title, $date, $description, $fileName, $filePath, $ocrText, $createdBy]);
    $awardId = (int)$pdo->lastInsertId();
    $text = trim($ocrText);
    $result = classify($text, $meta);
    $stmt2 = $pdo->prepare('INSERT INTO award_analysis (award_id, predicted_category, confidence, matched_categories_json, checklist_json, recommendations_text, evidence_json, manual_overridden, final_category) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt2->execute([
        $awardId,
        $result['predicted_category'],
        $result['confidence'],
        json_encode($result['matched_categories']),
        json_encode($result['checklist']),
        $result['recommendations'],
        json_encode($result['evidence']),
        0,
        $result['predicted_category'],
    ]);
    echo json_encode(['id' => $awardId, 'analysis' => $result]);
}

function listAwards($pdo) {
    // Check if we're using file-based fallback
    if ($pdo instanceof FileBasedDatabase) {
        // Read from actual stored analysis files
        $dataDir = __DIR__ . '/../data/';
        $awards = [];
        
        if (is_dir($dataDir)) {
            // Look for both analysis_*.json and upload_analysis_*.json files
            $files = array_merge(
                glob($dataDir . 'analysis_*.json'),
                glob($dataDir . 'upload_analysis_*.json')
            );
            
            // Debug: Log how many files we found
            error_log("Award list: Found " . count($files) . " analysis files in " . $dataDir);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data) {
                        // Parse analysis results to get the best match
                        $analysisResults = json_decode($data['analysis_results'] ?? '[]', true);
                        $bestMatch = null;
                        $highestScore = 0;
                        
                        if (is_array($analysisResults)) {
                            foreach ($analysisResults as $result) {
                                if (isset($result['score']) && $result['score'] > $highestScore) {
                                    $highestScore = $result['score'];
                                    $bestMatch = $result;
                                }
                            }
                        }
                        
                        // Create award entry
                        $award = [
                            'id' => 'file_' . basename($file, '.json'),
                            'title' => $data['title'] ?? 'Untitled Award',
                            'date' => date('Y-m-d', strtotime($data['created_at'] ?? 'now')),
                            'description' => $data['description'] ?? '',
                            'file_name' => $data['file_name'] ?? '',
                            'file_path' => $data['file_path'] ?? '',
                            'ocr_text' => $data['detected_text'] ?? '',
                            'created_by' => 'admin',
                            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
                            'analysis' => [
                                'confidence' => ($bestMatch['score'] ?? 0) / 100,
                                'predicted_category' => $bestMatch['title'] ?? $bestMatch['category'] ?? 'Not Analyzed',
                                'matched_criteria' => $bestMatch['matched_keywords'] ?? $bestMatch['matched_criteria'] ?? [],
                                'status' => $bestMatch['status'] ?? 'Not Analyzed'
                            ]
                        ];
                        
                        $awards[] = $award;
                    }
                }
            }
        }
        
        // Sort by creation date (newest first)
        usort($awards, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // If no real data, return mock data for demonstration (unless deleted)
        if (empty($awards)) {
            // Check if mock award has been deleted
            $deletedMockFile = __DIR__ . '/../data/deleted_mock_awards.json';
            $showMockAward = true;
            
            if (file_exists($deletedMockFile)) {
                $content = file_get_contents($deletedMockFile);
                if ($content) {
                    $deletedMocks = json_decode($content, true) ?: [];
                    // Check if mock award with ID 1 has been deleted
                    foreach ($deletedMocks as $deleted) {
                        if ($deleted['id'] == 1 || $deleted['id'] === '1') {
                            $showMockAward = false;
                            break;
                        }
                    }
                }
            }
            
            if ($showMockAward) {
                $awards = [
                    [
                        'id' => 1,
                        'title' => 'Sample Global Citizenship Initiative',
                        'date' => '2025-01-15',
                        'description' => 'Comprehensive program promoting intercultural understanding',
                        'file_name' => 'global_citizenship_proposal.pdf',
                        'file_path' => '/uploads/awards/global_citizenship_proposal.pdf',
                        'ocr_text' => 'Global citizenship award document with intercultural understanding and sustainability initiatives.',
                        'created_by' => 'admin',
                        'created_at' => date('Y-m-d H:i:s'),
                        'analysis' => [
                            'confidence' => 0.85,
                            'predicted_category' => 'Global Citizenship Award',
                            'matched_criteria' => ['Intercultural understanding', 'Global participation'],
                            'status' => 'Pending Review'
                        ]
                    ]
                ];
            } else {
                $awards = []; // No awards to show
            }
        }
        
        // Debug: Log final award count and sample
        error_log("Award list: Returning " . count($awards) . " awards");
        if (count($awards) > 0) {
            error_log("Sample award: " . json_encode($awards[0]));
        }
        
        echo json_encode($awards);
        return;
    }
    
    // Normal database query for SQLite
    $stmt = $pdo->query('SELECT * FROM awards ORDER BY created_at DESC');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function detail($pdo, $id) {
    // Check if we're using file-based fallback
    if ($pdo instanceof FileBasedDatabase) {
        // Read from actual stored analysis files
        $dataDir = __DIR__ . '/../data/';
        $analysisFile = null;
        
        // Extract the file ID from the request ID
        $fileId = str_replace('file_', '', $id);
        
        if (is_dir($dataDir)) {
            // Look for both analysis_*.json and upload_analysis_*.json files
            $files = array_merge(
                glob($dataDir . 'analysis_*.json'),
                glob($dataDir . 'upload_analysis_*.json')
            );
            foreach ($files as $file) {
                if (strpos(basename($file, '.json'), $fileId) !== false) {
                    $analysisFile = $file;
                    break;
                }
            }
        }
        
        if ($analysisFile && file_exists($analysisFile)) {
            $content = file_get_contents($analysisFile);
            $data = json_decode($content, true);
            
            if ($data) {
                $analysisResults = json_decode($data['analysis_results'] ?? '[]', true);
                
                // Find the best match
                $bestMatch = null;
                $highestScore = 0;
                
                if (is_array($analysisResults)) {
                    foreach ($analysisResults as $result) {
                        if (isset($result['score']) && $result['score'] > $highestScore) {
                            $highestScore = $result['score'];
                            $bestMatch = $result;
                        }
                    }
                }
                
                $analysis = [
                    'id' => 1,
                    'award_id' => $id,
                    'predicted_category' => $bestMatch['title'] ?? $bestMatch['category'] ?? 'Not Analyzed',
                    'confidence' => ($bestMatch['score'] ?? 0) / 100,
                    'matched_categories_json' => json_encode($bestMatch['matched_keywords'] ?? $bestMatch['matched_criteria'] ?? []),
                    'checklist_json' => json_encode($bestMatch['matched_keywords'] ?? $bestMatch['matched_criteria'] ?? []),
                    'recommendations_text' => $bestMatch['recommendation'] ?? 'No recommendations available.',
                    'evidence_json' => json_encode($bestMatch['matched_keywords'] ?? $bestMatch['matched_criteria'] ?? []),
                    'manual_overridden' => 0,
                    'final_category' => $bestMatch['title'] ?? $bestMatch['category'] ?? 'Not Analyzed',
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
                    // Include the full analysis results for the view modal
                    'analysis_results' => $data['analysis_results'] ?? '[]',
                    'extracted_text' => $data['detected_text'] ?? ''
                ];
                
                echo json_encode([
                    'award' => [
                        'id' => $id,
                        'title' => $data['title'] ?? 'Untitled',
                        'description' => $data['description'] ?? '',
                        'file_name' => $data['file_name'] ?? '',
                        'file_path' => $data['file_path'] ?? '',
                        'created_by' => 'admin',
                        'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
                    ],
                    'analysis' => $analysis
                ]);
                return;
            }
        }
        
        // Fallback to mock data if file not found
        $mockAward = [
            'id' => $id,
            'title' => 'Sample Award Document',
            'date' => '2025-01-15',
            'description' => 'This is a sample award document for demonstration purposes.',
            'file_name' => '68ec798b2e9b4_1760328075.docx',
            'file_path' => __DIR__ . '/../uploads/awards/68ec798b2e9b4_1760328075.docx',
            'ocr_text' => 'Sample OCR extracted text from award document.',
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $mockAnalysis = [
            'id' => 1,
            'award_id' => $id,
            'predicted_category' => 'Global Citizenship Award',
            'confidence' => 0.85,
            'matched_categories_json' => json_encode(['Global Citizenship', 'Sustainability']),
            'checklist_json' => json_encode(['Intercultural understanding', 'Student empowerment']),
            'recommendations_text' => 'Strong alignment with Global Citizenship Award criteria.',
            'evidence_json' => json_encode(['Sample evidence text']),
            'manual_overridden' => 0,
            'final_category' => 'Global Citizenship Award',
            'created_at' => date('Y-m-d H:i:s'),
            'analysis_results' => json_encode([
                [
                    'category' => 'Global Citizenship Award',
                    'score' => 85,
                    'status' => 'Eligible',
                    'matched_criteria' => ['Intercultural understanding', 'Global participation'],
                    'recommendation' => 'Strong alignment with Global Citizenship Award criteria. Consider highlighting specific community engagement metrics.'
                ],
                [
                    'category' => 'Outstanding International Education Program Award',
                    'score' => 72,
                    'status' => 'Partially Eligible',
                    'matched_criteria' => ['International collaboration', 'Educational excellence'],
                    'recommendation' => 'Good potential for International Education Program Award. Focus on academic program details and student outcomes.'
                ]
            ]),
            'extracted_text' => 'Sample OCR extracted text from award document demonstrating global citizenship initiatives and international education programs.'
        ];
        
        echo json_encode(['award' => $mockAward, 'analysis' => $mockAnalysis]);
        return;
    }
    
    // Normal database query for SQLite
    $stmt = $pdo->prepare('SELECT * FROM awards WHERE id = ?');
    $stmt->execute([$id]);
    $award = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$award) { http_response_code(404); echo json_encode(['error'=>'Not found']); return; }
    $stmt2 = $pdo->prepare('SELECT * FROM award_analysis WHERE award_id = ? ORDER BY id DESC LIMIT 1');
    $stmt2->execute([$id]);
    $analysis = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['award'=>$award,'analysis'=>$analysis]);
}

function overrideAnalysis($pdo, $data) {
    requireAuth(true);
    $id = (int)($data['award_id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'award_id required']); return; }
    $final = $data['final_category'] ?? null;
    $checklist = $data['checklist'] ?? null;
    $recs = $data['recommendations'] ?? null;
    $stmt = $pdo->prepare('INSERT INTO award_analysis (award_id, predicted_category, confidence, matched_categories_json, checklist_json, recommendations_text, evidence_json, manual_overridden, final_category) SELECT award_id, predicted_category, confidence, matched_categories_json, ?, ?, evidence_json, 1, ? FROM award_analysis WHERE award_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([json_encode($checklist), $recs, $final, $id]);
    echo json_encode(['success'=>true]);
}

function linkEvent($pdo, $data) {
    requireAuth(true);
    $awardId = (int)($data['award_id'] ?? 0);
    $eventId = (int)($data['event_id'] ?? 0);
    if (!$awardId || !$eventId) { http_response_code(400); echo json_encode(['error'=>'award_id and event_id required']); return; }
    $stmt = $pdo->prepare('INSERT INTO award_links (award_id, event_id) VALUES (?,?)');
    $stmt->execute([$awardId, $eventId]);
    echo json_encode(['success'=>true]);
}

function stats($pdo) {
    // Count each matched category once per award, using latest analysis per award
    $sql = "SELECT aa.* FROM award_analysis aa 
            JOIN (
              SELECT award_id, MAX(id) AS max_id FROM award_analysis GROUP BY award_id
            ) latest ON latest.max_id = aa.id";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tally = [];
    foreach ($rows as $r) {
        $matched = json_decode($r['matched_categories_json'] ?? '[]', true) ?: [];
        foreach ($matched as $m) {
            $name = $m['name'] ?? $m['key'] ?? 'Unknown';
            if (!isset($tally[$name])) { $tally[$name] = 0; }
            $tally[$name] += 1;
        }
    }
    $out = [];
    foreach ($tally as $k=>$v) { $out[] = ['category'=>$k, 'count'=>$v]; }
    echo json_encode($out);
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    if ($method === 'POST' && $action === 'upload') { uploadAward($pdo); return; }
    if ($method === 'GET' && $action === 'list') { listAwards($pdo); return; }
    if ($method === 'GET' && $action === 'detail' && isset($_GET['id'])) { detail($pdo, (int)$_GET['id']); return; }
    if ($method === 'POST' && $action === 'override') { overrideAnalysis($pdo, jsonInput()); return; }
    if ($method === 'POST' && $action === 'link-event') { linkEvent($pdo, jsonInput()); return; }
    if ($method === 'GET' && $action === 'stats') { stats($pdo); return; }
    http_response_code(405); echo json_encode(['error'=>'Method/Action not allowed']);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
}
?>


