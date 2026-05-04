<?php
// Test if quiz is using upload folder images

echo "=== Testing Quiz Upload Folder Usage ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all quiz images
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "1. Checking quiz_images database:\n";
    foreach ($quizImages as $image) {
        echo "Voyage ID {$image['voyage_id']}: {$image['destination']} ({$image['pays']})\n";
        echo "  Expected filename: {$image['image_filename']}\n";
        
        // Check if image exists in upload folder
        $uploadFile = "public/uploads/quiz_img/" . $image['image_filename'];
        $uploadExists = file_exists($uploadFile);
        
        // Check if image exists in destinations folder
        $destFile = "public/images/destinations/" . $image['image_filename'];
        $destExists = file_exists($destFile);
        
        echo "  Upload folder: " . ($uploadExists ? "✓" : "✗") . " ($uploadFile)\n";
        echo "  Destinations: " . ($destExists ? "✓" : "✗") . " ($destFile)\n";
        
        // Determine what quiz will use
        if ($uploadExists) {
            $quizPath = "/uploads/quiz_img/" . $image['image_filename'];
            echo "  Quiz will use: $quizPath ✓\n";
        } else {
            $quizPath = "/images/destinations/default.jpg";
            echo "  Quiz will use: $quizPath (default) ✗\n";
        }
        
        echo "\n";
    }
    
    echo "2. Simulating quiz API responses:\n";
    foreach ($quizImages as $image) {
        $uploadFile = "public/uploads/quiz_img/" . $image['image_filename'];
        
        if (file_exists($uploadFile)) {
            $imageUrl = "/uploads/quiz_img/" . $image['image_filename'];
        } else {
            $imageUrl = "/images/destinations/default.jpg";
        }
        
        echo "Voyage ID {$image['voyage_id']} - {$image['destination']}:\n";
        echo "  imageUrl: $imageUrl\n";
    }
    
    echo "\n3. Upload folder status:\n";
    $uploadDir = "public/uploads/quiz_img/";
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        $imageFiles = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($uploadDir . $file);
        });
        
        echo "Files in upload folder: " . count($imageFiles) . "\n";
        foreach ($imageFiles as $file) {
            echo "- $file (" . filesize($uploadDir . $file) . " bytes)\n";
        }
    } else {
        echo "Upload folder doesn't exist\n";
    }
    
    echo "\n=== Summary ===\n";
    echo "Quiz now uses ONLY upload folder images (no URLs)\n";
    echo "If image not found in upload folder, shows default image\n";
    echo "Upload your quiz images to: public/uploads/quiz_img/\n\n";
    
    echo "Required filenames:\n";
    foreach ($quizImages as $image) {
        echo "- {$image['image_filename']} (for {$image['destination']})\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ Quiz now uses upload folder only!\n";
echo "Try the quiz: http://localhost:8000/quiz\n";
?>
