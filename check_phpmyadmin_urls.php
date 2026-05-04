<?php
// Check exact URLs stored in phpMyAdmin for Toronto and Rio

echo "=== Checking phpMyAdmin URLs ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get Toronto and Rio URLs from phpMyAdmin
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename, image_url FROM quiz_images WHERE (destination LIKE '%Toronto%' OR destination LIKE '%Rio%') AND is_active = 1 ORDER BY voyage_id");
    $problemImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current URLs in phpMyAdmin:\n\n";
    
    foreach ($problemImages as $image) {
        echo "=== {$image['destination']} ({$image['pays']}) ===\n";
        echo "Voyage ID: {$image['voyage_id']}\n";
        echo "Image Filename: {$image['image_filename']}\n";
        echo "Image URL: {$image['image_url']}\n";
        echo "Current File: http://localhost:8000/images/destinations/{$image['image_filename']}\n";
        
        // Check if current file exists and its size
        $filePath = "public/images/destinations/" . $image['image_filename'];
        if (file_exists($filePath)) {
            echo "File Exists: Yes (Size: " . filesize($filePath) . " bytes)\n";
        } else {
            echo "File Exists: No\n";
        }
        
        echo "\n";
    }
    
    echo "=== Downloading Correct Images ===\n";
    
    foreach ($problemImages as $image) {
        echo "Downloading correct {$image['destination']} image...\n";
        echo "From: {$image['image_url']}\n";
        
        // Delete old file
        $filePath = "public/images/destinations/" . $image['image_filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
            echo "✓ Deleted old file\n";
        }
        
        // Download new image
        $imageData = @file_get_contents($image['image_url']);
        if ($imageData !== false) {
            if (file_put_contents($filePath, $imageData)) {
                echo "✓ Downloaded new file (Size: " . filesize($filePath) . " bytes)\n";
            } else {
                echo "✗ Failed to save file\n";
            }
        } else {
            echo "✗ Failed to download from URL\n";
            
            // Try with curl as fallback
            echo "Trying with curl...\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image['image_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData) {
                if (file_put_contents($filePath, $imageData)) {
                    echo "✓ Downloaded with curl (Size: " . filesize($filePath) . " bytes)\n";
                } else {
                    echo "✗ Failed to save curl data\n";
                }
            } else {
                echo "✗ Curl failed (HTTP $httpCode)\n";
            }
        }
        
        echo "\n";
    }
    
    echo "=== Verification ===\n";
    
    foreach ($problemImages as $image) {
        $url = "http://localhost:8000/images/destinations/" . $image['image_filename'];
        echo "Check this URL: $url\n";
    }
    
    echo "\n=== All Quiz Images Status ===\n";
    
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allImages as $image) {
        $url = "http://localhost:8000/images/destinations/" . $image['image_filename'];
        $filePath = "public/images/destinations/" . $image['image_filename'];
        $status = file_exists($filePath) ? "✓" : "✗";
        $size = file_exists($filePath) ? filesize($filePath) . " bytes" : "Missing";
        
        echo "$status {$image['destination']}: $url ($size)\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ Process completed!\n";
echo "Check the URLs above to verify the correct images are now loading.\n";
echo "Try the quiz: http://localhost:8000/quiz\n";
?>
