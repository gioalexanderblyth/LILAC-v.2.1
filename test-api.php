<?php
/**
 * Simple API test script to verify endpoints are working
 */

echo "<h1>LILAC API Test</h1>";

// Test awards-stats endpoint
echo "<h2>Testing awards-stats.php</h2>";
echo "<p>Testing GET request to awards-stats.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/awards-stats.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<p><strong>Headers:</strong></p><pre>" . htmlspecialchars($headers) . "</pre>";
echo "<p><strong>Response Body:</strong></p><pre>" . htmlspecialchars($body) . "</pre>";

// Check if response is valid JSON
$jsonData = json_decode($body, true);
if ($jsonData === null) {
    echo "<p style='color: red;'><strong>Error:</strong> Response is not valid JSON</p>";
} else {
    echo "<p style='color: green;'><strong>Success:</strong> Response is valid JSON</p>";
    echo "<p><strong>Response data:</strong></p><pre>" . print_r($jsonData, true) . "</pre>";
}

echo "<hr>";

// Test file upload endpoint (simulate with empty POST)
echo "<h2>Testing awards-upload.php</h2>";
echo "<p>Testing POST request to awards-upload.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/awards-upload.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, []);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<p><strong>Headers:</strong></p><pre>" . htmlspecialchars($headers) . "</pre>";
echo "<p><strong>Response Body:</strong></p><pre>" . htmlspecialchars($body) . "</pre>";

if ($httpCode === 405) {
    echo "<p style='color: orange;'><strong>Expected:</strong> 405 Method Not Allowed (no file uploaded)</p>";
} else {
    echo "<p style='color: green;'><strong>Success:</strong> Endpoint is responding</p>";
}

echo "<hr>";

// Test analyze-award endpoint
echo "<h2>Testing analyze-award.php</h2>";
echo "<p>Testing POST request to analyze-award.php...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/analyze-award.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, []);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
echo "<p><strong>Headers:</strong></p><pre>" . htmlspecialchars($headers) . "</pre>";
echo "<p><strong>Response Body:</strong></p><pre>" . htmlspecialchars($body) . "</pre>";

if ($httpCode === 400) {
    echo "<p style='color: orange;'><strong>Expected:</strong> 400 Bad Request (missing required fields)</p>";
} else {
    echo "<p style='color: green;'><strong>Success:</strong> Endpoint is responding</p>";
}

echo "<hr>";
echo "<p><strong>Test completed!</strong></p>";
?>
