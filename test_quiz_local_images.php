<?php
// Test quiz controller with local images

echo "=== Testing Quiz with Local Images ===\n\n";

// Simulate a quiz question request
$sessionId = 'test_' . time();

echo "Session ID: $sessionId\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Getting available voyages...\n";
    
    // Get available voyages (same logic as QuizController)
    $stmt = $pdo->query("SELECT idVoyage, destination, pays FROM voyage WHERE disponible = 1 ORDER BY idVoyage");
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($voyages)) {
        echo "✗ No available voyages found\n";
        exit;
    }
    
    echo "✓ Found " . count($voyages) . " available voyages\n";
    
    // Select random voyage (same logic as QuizController)
    $voyage = $voyages[array_rand($voyages)];
    echo "✓ Selected random voyage: ID {$voyage['idVoyage']} - {$voyage['destination']} ({$voyage['pays']})\n";
    
    echo "\n2. Finding image for this voyage...\n";
    
    // Get image from quiz_images table (same logic as QuizController)
    $stmt = $pdo->prepare("SELECT image_filename FROM quiz_images WHERE voyage_id = :voyage_id AND is_active = 1");
    $stmt->execute([':voyage_id' => $voyage['idVoyage']]);
    $quizImage = $stmt->fetch();
    
    if ($quizImage && $quizImage['image_filename']) {
        $imageFilename = $quizImage['image_filename'];
        $imagePath = '/uploads/quiz_img/' . $imageFilename;
        $fullPath = 'public' . $imagePath;
        
        echo "✓ Found image: $imageFilename\n";
        echo "✓ Image path: $imagePath\n";
        
        if (file_exists($fullPath)) {
            echo "✓ Image file exists locally\n";
            $fileSize = filesize($fullPath);
            echo "✓ Image size: $fileSize bytes\n";
            
            // Simulate the JSON response from QuizController
            echo "\n3. Quiz API Response (simulated):\n";
            echo "{\n";
            echo "  \"idVoyage\": {$voyage['idVoyage']},\n";
            echo "  \"destination\": \"{$voyage['destination']}\",\n";
            echo "  \"pays\": \"{$voyage['pays']}\",\n";
            echo "  \"imageUrl\": \"$imagePath\"\n";
            echo "}\n";
            
            echo "\n✅ Quiz working with local images!\n";
            echo "Image URL: http://localhost:8000$imagePath\n";
            
        } else {
            echo "✗ Image file not found at: $fullPath\n";
        }
    } else {
        echo "✗ No image found for voyage ID {$voyage['idVoyage']}\n";
        
        // Check if there are any images in quiz_images table
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM quiz_images WHERE is_active = 1");
        $count = $stmt->fetch()['count'];
        echo "Total images in quiz_images table: $count\n";
    }
    
    echo "\n4. Testing all quiz images...\n";
    
    // Test all images
    $stmt = $pdo->query("SELECT voyage_id, destination, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allImages as $image) {
        $fullPath = 'public/uploads/quiz_img/' . $image['image_filename'];
        $exists = file_exists($fullPath) ? '✓' : '✗';
        $size = $exists ? ' (' . filesize($fullPath) . ' bytes)' : '';
        echo "  ID {$image['voyage_id']}: {$image['destination']} - {$image['image_filename']} $exists$size\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Instructions ===\n";
echo "1. Start PHP server: php -S localhost:8000 -t public\n";
echo "2. Open browser: http://localhost:8000/quiz\n";
echo "3. Quiz will use images from: public/uploads/quiz_img/\n";
echo "\nProcess completed.\n";
?>
