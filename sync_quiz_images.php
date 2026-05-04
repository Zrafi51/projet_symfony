<?php
// Sync quiz_images data from phpMyAdmin to PHP script
// This script reads current data and generates the PHP array

echo "=== Sync Quiz Images Data ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get current data from quiz_images table
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename, image_url FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current quiz_images data:\n\n";
    
    // Generate PHP array
    $phpArray = "// Sample data matching current phpMyAdmin quiz_images table\n";
    $phpArray .= "\$sampleImages = [\n";
    
    foreach ($quizImages as $image) {
        $phpArray .= "    [\n";
        $phpArray .= "        'voyage_id' => {$image['voyage_id']},\n";
        $phpArray .= "        'destination' => '" . addslashes($image['destination']) . "',\n";
        $phpArray .= "        'pays' => '" . addslashes($image['pays']) . "',\n";
        $phpArray .= "        'image_filename' => '" . addslashes($image['image_filename']) . "',\n";
        $phpArray .= "        'image_url' => '" . addslashes($image['image_url']) . "'\n";
        $phpArray .= "    ],\n";
        
        echo "- {$image['voyage_id']}: {$image['destination']} ({$image['pays']}) - {$image['image_filename']}\n";
    }
    
    $phpArray .= "];\n";
    
    echo "\n=== Generated PHP Array ===\n";
    echo "Copy this to your create_quiz_images_table.php:\n\n";
    echo $phpArray;
    
    // Also show which images exist in filesystem
    echo "\n=== File System Check ===\n";
    foreach ($quizImages as $image) {
        $filePath = "public/images/destinations/" . $image['image_filename'];
        if (file_exists($filePath)) {
            echo "✓ {$image['image_filename']} exists\n";
        } else {
            echo "✗ {$image['image_filename']} missing - downloading...\n";
            
            // Try to download the image
            $imageData = @file_get_contents($image['image_url']);
            if ($imageData !== false) {
                if (file_put_contents($filePath, $imageData)) {
                    echo "  ✓ Downloaded successfully\n";
                } else {
                    echo "  ✗ Failed to save\n";
                }
            } else {
                echo "  ✗ Failed to download from URL\n";
                // Copy default image as fallback
                if (file_exists("public/images/destinations/default.jpg")) {
                    copy("public/images/destinations/default.jpg", $filePath);
                    echo "  ✓ Used default image as fallback\n";
                }
            }
        }
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Sync Complete ===\n";
echo "✅ PHP array generated above\n";
echo "✅ Images checked and downloaded if needed\n";
echo "\nAccess quiz at: http://localhost:8000/quiz\n";
?>
