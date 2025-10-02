<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$dbFile = '../database/mou_moa.db';
$dbDir = dirname($dbFile);

// Create database directory if it doesn't exist
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Initialize database
function initDatabase() {
    global $dbFile;
    
    try {
        $pdo = new PDO("sqlite:$dbFile");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read and execute the SQL schema
        $sql = file_get_contents('../database.sql');
        $pdo->exec($sql);
        
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit();
    }
}

// Get all MOU/MOA entries
function getAllEntries($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM mou_moa ORDER BY created_at DESC");
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update status based on current date
        foreach ($entries as &$entry) {
            $entry['status'] = calculateStatus($entry['end_date']);
        }
        
        return $entries;
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch entries: ' . $e->getMessage());
    }
}

// Add new MOU/MOA entry
function addEntry($pdo, $data) {
    try {
        // Validate required fields
        $required = ['institution', 'location', 'contact_email', 'term', 'sign_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Calculate status
        $status = calculateStatus($data['end_date']);
        
        $stmt = $pdo->prepare("
            INSERT INTO mou_moa (institution, location, contact_email, term, sign_date, end_date, status, file_name, file_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['institution'],
            $data['location'],
            $data['contact_email'],
            $data['term'],
            $data['sign_date'],
            $data['end_date'],
            $status,
            $data['file_name'] ?? null,
            $data['file_path'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        throw new Exception('Failed to add entry: ' . $e->getMessage());
    }
}

// Update MOU/MOA entry
function updateEntry($pdo, $id, $data) {
    try {
        // Calculate status
        $status = calculateStatus($data['end_date']);
        
        $stmt = $pdo->prepare("
            UPDATE mou_moa 
            SET institution = ?, location = ?, contact_email = ?, term = ?, 
                sign_date = ?, end_date = ?, status = ?, file_name = ?, file_path = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['institution'],
            $data['location'],
            $data['contact_email'],
            $data['term'],
            $data['sign_date'],
            $data['end_date'],
            $status,
            $data['file_name'] ?? null,
            $data['file_path'] ?? null,
            $id
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception('Failed to update entry: ' . $e->getMessage());
    }
}

// Delete MOU/MOA entry
function deleteEntry($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM mou_moa WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception('Failed to delete entry: ' . $e->getMessage());
    }
}

// Calculate status based on end date
function calculateStatus($endDate) {
    $today = new DateTime();
    $end = new DateTime($endDate);
    $today->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);
    
    $daysUntilExpiry = $today->diff($end)->days;
    
    if ($end < $today) {
        return 'Expired';
    } elseif ($daysUntilExpiry <= 30) {
        return 'Expires Soon';
    } else {
        return 'Active';
    }
}

// Main request handling
try {
    $pdo = initDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            $entries = getAllEntries($pdo);
            echo json_encode($entries);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $id = addEntry($pdo, $input);
            echo json_encode(['id' => $id, 'message' => 'Entry added successfully']);
            break;
            
        case 'PUT':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for update');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $success = updateEntry($pdo, $id, $input);
            if ($success) {
                echo json_encode(['message' => 'Entry updated successfully']);
            } else {
                throw new Exception('Entry not found');
            }
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for deletion');
            }
            
            $success = deleteEntry($pdo, $id);
            if ($success) {
                echo json_encode(['message' => 'Entry deleted successfully']);
            } else {
                throw new Exception('Entry not found');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 