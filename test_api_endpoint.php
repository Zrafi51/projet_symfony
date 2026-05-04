<?php
// Test the API endpoint directly

$url = 'http://localhost:8000/api/quiz/start';
$data = ['total_questions' => 5];
$jsonData = json_encode($data);

echo "Testing API endpoint: $url\n";
echo "Request data: $jsonData\n\n";

// Use file_get_contents with context
$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $jsonData,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "✗ Request failed completely\n";
} else {
    echo "Response received:\n";
    echo $response . "\n\n";
    
    // Try to decode JSON
    $decoded = json_decode($response, true);
    if ($decoded !== null) {
        echo "✓ Valid JSON response\n";
        print_r($decoded);
    } else {
        echo "✗ Invalid JSON response (HTML error page likely)\n";
        
        // Check if it's an HTML error page
        if (strpos($response, '<!DOCTYPE html') !== false || strpos($response, '<html') !== false) {
            echo "This appears to be an HTML error page\n";
            
            // Extract some error info
            if (preg_match('/<title>(.*?)<\/title>/', $response, $matches)) {
                echo "Page title: " . $matches[1] . "\n";
            }
            
            if (preg_match('/<h1>(.*?)<\/h1>/', $response, $matches)) {
                echo "Main heading: " . $matches[1] . "\n";
            }
        }
    }
}

echo "\nTest completed.\n";
?>
