<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbFile = '../database/mou_moa.db';
$dbDir = dirname($dbFile);
if (!is_dir($dbDir)) { mkdir($dbDir, 0755, true); }

function db() {
    global $dbFile;
    try {
        $pdo = new PDO("sqlite:$dbFile");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents('../database.sql');
        $pdo->exec($sql);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB failed: '.$e->getMessage()]);
        exit();
    }
}

function getEligible($pdo) {
    $today = (new DateTime('today'))->format('Y-m-d');
    // Upcoming eligible first (date >= today), then recent past eligible
    $sql = "(
              SELECT * FROM events 
              WHERE eligible_for_awards = 1 AND date >= :today 
              ORDER BY date ASC, start_time ASC 
              LIMIT 2
            )
            UNION ALL
            (
              SELECT * FROM events 
              WHERE eligible_for_awards = 1 AND date < :today 
              ORDER BY date DESC, start_time DESC 
              LIMIT 2
            )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Ensure we only return two items max in the priority order above
    $result = array_slice($items, 0, 2);
    echo json_encode($result);
}

function listAll($pdo) {
    $stmt = $pdo->query('SELECT * FROM events ORDER BY date DESC, start_time DESC');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function create($pdo, $data) {
    $required = ['title','type','location','start_time','end_time','date','category'];
    foreach ($required as $f) { if (empty($data[$f])) { throw new Exception("Field '$f' is required"); } }
    $stmt = $pdo->prepare('INSERT INTO events (title,type,location,start_time,end_time,date,category,thumbnail_url,eligible_for_awards) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $data['title'],
        $data['type'],
        $data['location'],
        $data['start_time'],
        $data['end_time'],
        $data['date'],
        $data['category'],
        $data['thumbnail_url'] ?? null,
        !empty($data['eligible_for_awards']) ? 1 : 0,
    ]);
    echo json_encode(['id' => $pdo->lastInsertId()]);
}

function details($pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); return; }
    echo json_encode($row);
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    if ($method === 'GET' && $action === 'eligible') { getEligible($pdo); return; }
    if ($method === 'GET' && isset($_GET['id'])) { details($pdo, (int)$_GET['id']); return; }
    if ($method === 'GET') { listAll($pdo); return; }
    if ($method === 'POST') { $data = json_decode(file_get_contents('php://input'), true) ?? []; create($pdo, $data); return; }
    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
}
?>


