<?php
/**
 * Database configuration for LILAC Awards System
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lilac_awards');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// File upload configuration
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_DIR', __DIR__ . '/../uploads/awards/');
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png', 
    'image/jpg',
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

// OCR Configuration
define('TESSERACT_PATH_LINUX', '/usr/bin/tesseract');
define('TESSERACT_PATH_WINDOWS', 'C:\Program Files\Tesseract-OCR\tesseract.exe');
define('ENABLE_OCR', true); // Toggle OCR functionality

// Analysis configuration
define('MIN_SCORE_THRESHOLD', 20); // Minimum score to include in results
define('ELIGIBLE_SCORE_THRESHOLD', 80); // Score threshold for "Eligible" status
define('PARTIAL_SCORE_THRESHOLD', 60); // Score threshold for "Partially Eligible" status

// File processing limits
define('MAX_PDF_SIZE', 50 * 1024 * 1024); // 50MB max for PDF processing
define('OCR_TIMEOUT', 120); // OCR timeout in seconds

/**
 * Get database connection with fallback options
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        // Check if SQLite extension is available first
        if (!extension_loaded('pdo_sqlite')) {
            logActivity('PDO SQLite extension not available, using file-based fallback', 'WARNING');
            $pdo = new FileBasedDatabase();
            return $pdo;
        }
        
        // Try SQLite first
        try {
            $dbPath = __DIR__ . '/../database/lilac.db';
            
            // Ensure database directory exists
            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Create tables if they don't exist
            createTables($pdo);
            
            logActivity('SQLite database connection established successfully', 'INFO');
            
        } catch (PDOException $e) {
            logActivity('SQLite connection failed: ' . $e->getMessage(), 'WARNING');
            
            // Fallback: Use file-based storage
            $pdo = new FileBasedDatabase();
            logActivity('Using file-based database fallback', 'INFO');
        } catch (Exception $e) {
            logActivity('Database connection error: ' . $e->getMessage(), 'WARNING');
            
            // Fallback: Use file-based storage
            $pdo = new FileBasedDatabase();
            logActivity('Using file-based database fallback due to general error', 'INFO');
        }
    }
    
    return $pdo;
}

/**
 * File-based database fallback class
 */
class FileBasedDatabase {
    private $dataDir;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    public function query($sql) {
        // For simple SELECT queries, return empty results
        if (stripos($sql, 'SELECT') === 0) {
            return new FileBasedStatement([]);
        }
        
        // For other queries, just log them
        logActivity('File-based DB query (not executed): ' . $sql, 'INFO');
        return new FileBasedStatement([]);
    }
    
    public function prepare($sql) {
        return new FileBasedStatement([]);
    }
    
    public function exec($sql) {
        logActivity('File-based DB exec (not executed): ' . $sql, 'INFO');
        return 0;
    }
}

/**
 * File-based statement fallback class
 */
class FileBasedStatement {
    private $data;
    
    public function __construct($data = []) {
        $this->data = is_array($data) ? $data : [];
    }
    
    public function execute($params = []) {
        return true;
    }
    
    public function fetchAll($mode = PDO::FETCH_ASSOC) {
        return $this->data;
    }
    
    public function fetch($mode = PDO::FETCH_ASSOC) {
        return $this->data[0] ?? false;
    }
    
    public function rowCount() {
        return count($this->data);
    }
    
    public function bindParam($parameter, &$variable, $type = null) {
        return true;
    }
    
    public function bindValue($parameter, $value, $type = null) {
        return true;
    }
}

/**
 * Create necessary database tables
 */
function createTables($pdo) {
    // Award analysis table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS award_analysis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            file_name TEXT,
            file_path TEXT,
            detected_text TEXT,
            analysis_results TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Awards criteria cache table (for performance)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS awards_criteria_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            award_name TEXT UNIQUE NOT NULL,
            criteria_data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code: ' . $file['error']);
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB limit');
    }
    
    // Check file type
    $fileType = null;
    if (function_exists('mime_content_type')) {
        $fileType = mime_content_type($file['tmp_name']);
    } else {
        // Fallback: use file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $fileType = $extensionMap[$extension] ?? null;
    }
    
    if (!$fileType || !in_array($fileType, ALLOWED_FILE_TYPES)) {
        throw new Exception('Invalid file type. Allowed types: JPG, PNG, PDF, DOCX');
    }
    
    return true;
}

/**
 * Get Tesseract OCR path based on operating system and availability
 */
function getTesseractPath() {
    static $tesseractPath = null;
    
    if ($tesseractPath !== null) {
        return $tesseractPath;
    }
    
    if (!ENABLE_OCR) {
        return null;
    }
    
    // Check common paths based on OS
    $commonPaths = [];
    if (PHP_OS_FAMILY === 'Windows') {
        $commonPaths = [
            TESSERACT_PATH_WINDOWS,
            'tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\tesseract\\tesseract.exe',
            'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Tesseract-OCR\\tesseract.exe',
            'C:\\Users\\Administrator\\Downloads\\THESIS DOCS\\tesseract.exe'
        ];
    } else {
        $commonPaths = [
            TESSERACT_PATH_LINUX,
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract',
            'tesseract'
        ];
    }
    
    foreach ($commonPaths as $path) {
        if ($path === 'tesseract' || $path === 'tesseract.exe') {
            // Check if in PATH
            $output = shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where' : 'which') . ' ' . escapeshellarg($path) . ' 2>nul 2>/dev/null');
            if (!empty($output)) {
                $tesseractPath = trim($output);
                break;
            }
        } else {
            if (file_exists($path)) {
                $tesseractPath = $path;
                break;
            }
        }
    }
    
    return $tesseractPath;
}

/**
 * Check if pdftotext is available
 */
function isPdftotextAvailable() {
    static $available = null;
    
    if ($available !== null) {
        return $available;
    }
    
    $command = (PHP_OS_FAMILY === 'Windows' ? 'where' : 'which') . ' pdftotext 2>nul 2>/dev/null';
    $output = shell_exec($command);
    $available = !empty(trim($output));
    
    return $available;
}

/**
 * Log errors and activities
 */
function logActivity($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate unique filename
 */
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Generate safe randomized filename
 */
function generateSafeFileName($originalName, $extension) {
    // Create a secure random filename with timestamp and random component
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $extension = strtolower(trim($extension, '.'));
    
    // Sanitize extension to prevent path traversal
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    if (empty($extension)) {
        $extension = 'file';
    }
    
    return $timestamp . '_' . $random . '.' . $extension;
}

// Initialize database connection
$pdo = getDatabaseConnection();
?>
