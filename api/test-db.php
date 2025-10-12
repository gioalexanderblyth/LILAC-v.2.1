<?php
// Simple database connection test
header('Content-Type: text/plain');

echo "Testing database connection...\n\n";

try {
    require_once 'config.php';
    
    echo "✓ Config loaded successfully\n";
    
    $pdo = getDatabaseConnection();
    echo "✓ Database connection established\n";
    
    // Test basic query
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✓ Database query successful\n";
    echo "Tables found: " . implode(', ', $tables) . "\n";
    
    // Test if award_analysis table exists
    if (in_array('award_analysis', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM award_analysis");
        $result = $stmt->fetch();
        echo "✓ award_analysis table exists with {$result['count']} records\n";
    } else {
        echo "⚠ award_analysis table does not exist (will be created on first use)\n";
    }
    
    echo "\n✅ All tests passed! Database is working correctly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
