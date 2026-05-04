<?php
// Search for map_destinations images in all possible locations

echo "Searching for map_destinations images...\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all image paths from map_destinations
    $stmt = $pdo->query("SELECT image_path FROM map_destinations WHERE is_active = 1 AND image_path IS NOT NULL");
    $imagePaths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($imagePaths) . " image paths in database:\n";
    foreach ($imagePaths as $path) {
        echo "- $path\n";
    }
    
    echo "\nSearching in filesystem...\n";
    
    // Define search locations
    $searchPaths = [
        'public/images/destinations/',
        'public/images/',
        'public/assets/images/',
        'public/uploads/',
        'public/media/',
        'public/lo/',
        'public/',
        'C:/Users/linda/esprim/symfony/final/projet_symfony/public/images/destinations/',
        'C:/Users/linda/esprim/symfony/final/projet_symfony/public/images/',
        'C:/Users/linda/esprim/symfony/final/projet_symfony/public/',
        'C:/Users/linda/esprim/symfony/final/projet_symfony/'
    ];
    
    $foundImages = [];
    
    foreach ($imagePaths as $imagePath) {
        $found = false;
        $foundLocation = null;
        
        foreach ($searchPaths as $searchPath) {
            $fullPath = $searchPath . $imagePath;
            if (file_exists($fullPath)) {
                echo "✓ Found: $imagePath at $searchPath\n";
                $found = true;
                $foundLocation = $searchPath;
                break;
            }
        }
        
        if ($found) {
            $foundImages[] = [
                'path' => $imagePath,
                'location' => $foundLocation,
                'full_path' => $foundLocation . $imagePath
            ];
        } else {
            echo "✗ Missing: $imagePath\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "- Found " . count($foundImages) . " of " . count($imagePaths) . " images\n";
    
    if (!empty($foundImages)) {
        echo "\nFound images:\n";
        foreach ($foundImages as $img) {
            echo "- {$img['path']} ({$img['location']})\n";
        }
        
        // Check if they're in the right directory for the quiz
        $correctDir = 'C:/Users/linda/esprim/symfony/final/projet_symfony/public/images/destinations/';
        $needsCopy = false;
        
        foreach ($foundImages as $img) {
            if ($img['location'] !== $correctDir) {
                $needsCopy = true;
                break;
            }
        }
        
        if ($needsCopy) {
            echo "\n⚠️  Images need to be copied to: $correctDir\n";
            echo "Copying images...\n";
            
            foreach ($foundImages as $img) {
                if ($img['location'] !== $correctDir) {
                    $dest = $correctDir . basename($img['path']);
                    if (copy($img['full_path'], $dest)) {
                        echo "✓ Copied: {$img['path']}\n";
                    } else {
                        echo "✗ Failed to copy: {$img['path']}\n";
                    }
                }
            }
        } else {
            echo "\n✅ Images are in the correct directory!\n";
        }
    } else {
        echo "\n❌ No images found in any location\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
