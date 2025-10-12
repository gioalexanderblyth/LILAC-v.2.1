<?php
// Test file-based fallback system
header('Content-Type: application/json');

require_once 'config.php';

try {
    $pdo = getDatabaseConnection();
    
    $response = [
        'success' => true,
        'database_type' => ($pdo instanceof FileBasedDatabase) ? 'file_based_fallback' : 'sqlite',
        'message' => ($pdo instanceof FileBasedDatabase) 
            ? 'Using file-based fallback - SQLite not available' 
            : 'SQLite database connection working',
        'available_extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'sqlite3' => extension_loaded('sqlite3')
        ],
        'pdo_drivers' => PDO::getAvailableDrivers()
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'database_type' => 'error'
    ], JSON_PRETTY_PRINT);
}
?>
