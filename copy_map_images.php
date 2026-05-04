<?php
// Find and copy map_destinations images to public directory

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n\n";
    
    // Get all active destinations with images
    $stmt = $pdo->query("SELECT id, city, country, image_path FROM map_destinations WHERE is_active = 1 AND image_path IS NOT NULL ORDER BY display_order");
    
    $sourceDir = null;
    $copiedCount = 0;
    
    while ($row = $stmt->fetch()) {
        $imagePath = $row['image_path'];
        if (!empty($imagePath)) {
            // Try to find the image in common locations
            $possibleLocations = [
                'public/lo/' . $imagePath,
                'public/uploads/' . $imagePath,
                'public/media/' . $imagePath,
                'public/assets/images/' . $imagePath
            ];
            
            $found = false;
            foreach ($possibleLocations as $location) {
                if (file_exists($location)) {
                    $sourceDir = dirname($location);
                    $sourceFile = $location;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                // Copy to public/images/destinations/
                $destination = 'public/images/destinations/' . basename($imagePath);
                
                if (copy($sourceFile, $destination)) {
                    echo "✓ Copied: $imagePath\n";
                    $copiedCount++;
                } else {
                    echo "✗ Failed to copy: $imagePath\n";
                }
            } else {
                echo "✗ Not found: $imagePath\n";
            }
        }
    }
    
    echo "\nSummary:\n";
    echo "- Copied $copiedCount images to public/images/destinations/\n";
    
    if ($copiedCount > 0) {
        echo "- Images are now available for the quiz!\n";
    } else {
        echo "- No images were copied. Please check your image locations.\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
