<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dbFile = dirname(__DIR__) . '/database/mou_moa.db';
$dbDir = dirname($dbFile);
if (!is_dir($dbDir)) { mkdir($dbDir, 0755, true); }

function db() {
    global $dbFile;
    try {
        $pdo = new PDO("sqlite:$dbFile");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents(dirname(__DIR__) . '/database.sql');
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

function getEventsForCalendar($pdo) {
    $stmt = $pdo->query('SELECT * FROM events ORDER BY date ASC, start_time ASC');
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transform events to match the frontend format
    $transformedEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'date' => $event['date'],
            'timeRange' => $event['start_time'] . ' - ' . $event['end_time'],
            'location' => $event['location'],
            'description' => $event['description'],
            'imageUrl' => $event['image_url'],
            'createdAt' => $event['created_at'],
            'type' => $event['type'],
            'category' => $event['category'],
            'eligible_for_awards' => $event['eligible_for_awards']
        ];
    }, $events);
    
    echo json_encode($transformedEvents);
}

function create($pdo, $data) {
    $required = ['title','location','date'];
    foreach ($required as $f) { if (empty($data[$f])) { throw new Exception("Field '$f' is required"); } }
    
    // Set defaults for optional fields
    $type = $data['type'] ?? 'event';
    $start_time = $data['start_time'] ?? '09:00:00';
    $end_time = $data['end_time'] ?? '17:00:00';
    $category = $data['category'] ?? 'General';
    $description = $data['description'] ?? '';
    $image_url = $data['image_url'] ?? null;
    $thumbnail_url = $data['thumbnail_url'] ?? null;
    $eligible_for_awards = !empty($data['eligible_for_awards']) ? 1 : 0;
    
    $stmt = $pdo->prepare('INSERT INTO events (title,type,location,start_time,end_time,date,category,description,image_url,thumbnail_url,eligible_for_awards) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $data['title'],
        $type,
        $data['location'],
        $start_time,
        $end_time,
        $data['date'],
        $category,
        $description,
        $image_url,
        $thumbnail_url,
        $eligible_for_awards,
    ]);
    echo json_encode(['id' => $pdo->lastInsertId(), 'success' => true]);
}

function details($pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); return; }
    echo json_encode($row);
}

function deleteEvent($pdo, $id) {
    $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        return;
    }
    echo json_encode(['success' => true]);
}

function clearAllEvents($pdo) {
    $pdo->exec('DELETE FROM events');
    echo json_encode(['success' => true, 'cleared' => true]);
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    if ($method === 'GET' && $action === 'eligible') { getEligible($pdo); return; }
    if ($method === 'POST' && $action === 'clear') { clearAllEvents($pdo); return; }
    if ($method === 'GET' && $action === 'calendar') { getEventsForCalendar($pdo); return; }
    if ($method === 'GET' && isset($_GET['id'])) { details($pdo, (int)$_GET['id']); return; }
    if ($method === 'GET') { listAll($pdo); return; }
    if ($method === 'POST') { $data = json_decode(file_get_contents('php://input'), true) ?? []; create($pdo, $data); return; }
    if ($method === 'DELETE') { $id = isset($_GET['id']) ? (int)$_GET['id'] : 0; if ($id) { deleteEvent($pdo, $id); return; } http_response_code(400); echo json_encode(['error'=>'ID is required']); return; }
    
    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
}
?>


