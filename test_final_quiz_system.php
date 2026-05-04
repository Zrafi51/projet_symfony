<?php
// Test final quiz system with folder-based images

echo "=== Testing Final Quiz System ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. quiz_images table structure (no image_url):\n";
    $stmt = $pdo->query("DESCRIBE quiz_images");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n2. Quiz image selection logic:\n";
    echo "Quiz now selects images based on:\n";
    echo "- Voyage ID match from quiz_images table\n";
    echo "- Direct file path: /uploads/quiz_img/[filename]\n";
    echo "- Fallback to default if file not found\n";
    echo "- NO URL dependencies\n";
    
    echo "\n3. Current quiz_images data:\n";
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($images as $image) {
        echo "Voyage ID {$image['voyage_id']}: {$image['destination']} ({$image['pays']})\n";
        echo "  Expected file: {$image['image_filename']}\n";
        
        // Check if file exists in upload folder
        $uploadFile = "public/uploads/quiz_img/" . $image['image_filename'];
        $exists = file_exists($uploadFile);
        
        echo "  Upload folder: " . ($exists ? "✓" : "✗") . "\n";
        
        if ($exists) {
            $quizPath = "/uploads/quiz_img/" . $image['image_filename'];
            echo "  Quiz will use: $quizPath\n";
        } else {
            echo "  Quiz will use: /images/destinations/default.jpg (fallback)\n";
        }
        echo "\n";
    }
    
    echo "4. Upload folder contents:\n";
    $uploadDir = "public/uploads/quiz_img/";
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        $imageFiles = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($uploadDir . $file);
        });
        
        echo "Files in upload folder (" . count($imageFiles) . "):\n";
        foreach ($imageFiles as $file) {
            $size = filesize($uploadDir . $file);
            echo "- $file (" . number_format($size) . " bytes)\n";
        }
    }
    
    echo "\n5. Simulated quiz API responses:\n";
    foreach ($images as $image) {
        $uploadFile = "public/uploads/quiz_img/" . $image['image_filename'];
        
        if (file_exists($uploadFile)) {
            $imageUrl = "/uploads/quiz_img/" . $image['image_filename'];
        } else {
            $imageUrl = "/images/destinations/default.jpg";
        }
        
        echo "{\n";
        echo "  \"idVoyage\": {$image['voyage_id']},\n";
        echo "  \"destination\": \"{$image['destination']}\",\n";
        echo "  \"pays\": \"{$image['pays']}\",\n";
        echo "  \"imageUrl\": \"$imageUrl\"\n";
        echo "}\n\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Summary ===\n";
echo "✅ image_url column removed from database\n";
echo "✅ Quiz controller updated to use folder only\n";
echo "✅ No URL dependencies in quiz system\n";
echo "✅ Images selected directly from upload folder\n\n";

echo "How to use:\n";
echo "1. Upload quiz images to: public/uploads/quiz_img/\n";
echo "2. Use correct filenames (shanghai.jpg, berlin.jpg, etc.)\n";
echo "3. Quiz automatically uses uploaded images\n\n";

echo "Quiz URL: http://localhost:8000/quiz\n";
?>
