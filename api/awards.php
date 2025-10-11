<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$dbFile = dirname(__DIR__) . '/database/mou_moa.db';
if (!is_dir(dirname($dbFile))) { mkdir(dirname($dbFile), 0755, true); }

function db() {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents(dirname(__DIR__) . '/database.sql');
    $pdo->exec($sql);
    return $pdo;
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
    $stmt = $pdo->query('SELECT * FROM awards ORDER BY created_at DESC');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function detail($pdo, $id) {
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


