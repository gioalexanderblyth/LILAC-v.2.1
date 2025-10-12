<?php
echo "<h1>Quick API Test</h1>";

// Test awards-stats.php
echo "<h2>Testing awards-stats.php</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/awards-stats.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Check if it's valid JSON
$json = json_decode($response, true);
if ($json !== null) {
    echo "<p style='color: green;'><strong>✓ Valid JSON Response</strong></p>";
    echo "<p><strong>Success:</strong> " . ($json['success'] ? 'true' : 'false') . "</p>";
    if (isset($json['counters'])) {
        echo "<p><strong>Counters found:</strong> " . count($json['counters']) . " awards</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Invalid JSON Response</strong></p>";
}

echo "<hr>";

// Test upload endpoint
echo "<h2>Testing awards-upload.php (POST without file)</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/awards-upload.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, []);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode === 405) {
    echo "<p style='color: orange;'><strong>Expected: 405 Method Not Allowed (no file uploaded)</strong></p>";
} elseif ($httpCode === 400) {
    echo "<p style='color: orange;'><strong>Expected: 400 Bad Request (missing required fields)</strong></p>";
} else {
    echo "<p style='color: green;'><strong>✓ Endpoint responding</strong></p>";
}

echo "<hr>";
echo "<p><strong>Test completed!</strong></p>";
?>
