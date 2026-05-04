<?php
// Check Toronto image details and fix any mismatches

echo "=== Checking Toronto Image Issue ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check what's in quiz_images table for Toronto
    $stmt = $pdo->prepare("SELECT * FROM quiz_images WHERE destination LIKE '%Toronto%' OR destination LIKE '%toronto%'");
    $stmt->execute();
    $torontoImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Toronto entries in quiz_images table:\n";
    foreach ($torontoImages as $image) {
        echo "- ID {$image['id']}: Voyage ID {$image['voyage_id']} - {$image['destination']} ({$image['pays']})\n";
        echo "  Image Filename: {$image['image_filename']}\n";
        echo "  Image URL: {$image['image_url']}\n";
        echo "  Is Active: " . ($image['is_active'] ? 'Yes' : 'No') . "\n\n";
    }
    
    // Check what's in voyage table for Toronto
    $stmt = $pdo->prepare("SELECT * FROM voyage WHERE destination LIKE '%Toronto%' OR destination LIKE '%toronto%'");
    $stmt->execute();
    $torontoVoyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Toronto entries in voyage table:\n";
    foreach ($torontoVoyages as $voyage) {
        echo "- ID {$voyage['idVoyage']}: {$voyage['destination']} ({$voyage['pays']})\n";
        echo "  Disponible: " . ($voyage['disponible'] ? 'Yes' : 'No') . "\n\n";
    }
    
    // Check if the actual image file exists
    echo "=== Image File Check ===\n";
    foreach ($torontoImages as $image) {
        $filePath = "public/images/destinations/" . $image['image_filename'];
        if (file_exists($filePath)) {
            echo "✓ {$image['image_filename']} exists in filesystem\n";
            
            // Check file size to see if it's the default image
            $fileSize = filesize($filePath);
            $defaultSize = filesize("public/images/destinations/default.jpg");
            
            if ($fileSize == $defaultSize) {
                echo "  ⚠️  This appears to be the default image (same size as default.jpg)\n";
                echo "  Downloading correct image from URL...\n";
                
                // Download the correct image
                $imageData = @file_get_contents($image['image_url']);
                if ($imageData !== false) {
                    if (file_put_contents($filePath, $imageData)) {
                        echo "  ✓ Downloaded correct image\n";
                    } else {
                        echo "  ✗ Failed to save image\n";
                    }
                } else {
                    echo "  ✗ Failed to download from URL\n";
                }
            } else {
                echo "  ✓ This appears to be a unique image\n";
            }
        } else {
            echo "✗ {$image['image_filename']} missing - downloading...\n";
            
            // Download the image
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
    
    echo "\n=== Current Toronto Quiz Image Status ===\n";
    if (!empty($torontoImages)) {
        $torontoImage = $torontoImages[0]; // Get first Toronto image
        $filePath = "public/images/destinations/" . $torontoImage['image_filename'];
        
        echo "Current setup:\n";
        echo "- Destination: {$torontoImage['destination']}\n";
        echo "- Image file: {$torontoImage['image_filename']}\n";
        echo "- Image URL: {$torontoImage['image_url']}\n";
        echo "- File exists: " . (file_exists($filePath) ? 'Yes' : 'No') . "\n";
        
        if (file_exists($filePath)) {
            echo "- File size: " . filesize($filePath) . " bytes\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
echo "Try the quiz now: http://localhost:8000/quiz\n";
?>
