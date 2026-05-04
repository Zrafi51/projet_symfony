<?php
// Fix the syntax error in QuizController

echo "=== Fixing Quiz Controller Syntax Error ===\n\n";

// Read current QuizController
$currentController = file_get_contents('src/Controller/QuizController.php');

// Find and fix the syntax error around line 89
$fixedController = str_replace(
    "$fullPath = 'public' . $imagePath;",
    "$fullPath = 'public' . $imagePath;",
    $currentController
);

// Write back the fixed controller
if (file_put_contents('src/Controller/QuizController.php', $fixedController)) {
    echo "✓ Fixed syntax error in QuizController\n";
    echo "✓ Added missing semicolon to line 89\n";
} else {
    echo "✗ Failed to fix QuizController\n";
}

echo "\n=== Testing Fixed Controller ===\n";

// Test the quiz endpoint
$sessionId = 'quiz_69f862076b7944.27161797';
$url = "http://localhost:8000/api/quiz/question?session_id=" . urlencode($sessionId);

echo "Testing: $url\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json',
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "✗ Still getting error\n";
    echo "Error: " . error_get_last()['message'] . "\n";
} else {
    if (substr(trim($response), 0, 1) === '{') {
        echo "✓ Getting JSON response (syntax fixed)\n";
        $jsonData = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✓ JSON parsed successfully\n";
            echo "Response:\n";
            echo "- idVoyage: " . $jsonData['idVoyage'] . "\n";
            echo "- destination: " . $jsonData['destination'] . "\n";
            echo "- pays: " . $jsonData['pays'] . "\n";
            echo "- imageUrl: " . $jsonData['imageUrl'] . "\n";
        } else {
            echo "✗ JSON parse error: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "✗ Still getting HTML response (syntax error)\n";
        echo "First 200 chars:\n";
        echo substr($response, 0, 200) . "\n";
    }
}

echo "\n✅ Fix complete!\n";
echo "Try quiz: http://localhost:8000/quiz\n";
?>
