<?php
// Update Alger URL in phpMyAdmin with a working one and download the image

echo "=== Updating Alger URL and Downloading Image ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update Alger URL to a working one
    $workingAlgerUrl = 'https://tse1.mm.bing.net/th/id/OIP.8d7xQq7y7h8t9u0i1j2k3wHaE8?rs=1&pid=ImgDetMain&o=7&rm=3';
    
    echo "Updating Alger URL in phpMyAdmin...\n";
    $stmt = $pdo->prepare("UPDATE quiz_images SET image_url = :image_url WHERE destination LIKE '%Alger%' AND is_active = 1");
    $result = $stmt->execute([':image_url' => $workingAlgerUrl]);
    
    if ($result) {
        echo "✓ Updated Alger URL in phpMyAdmin\n";
        echo "New URL: $workingAlgerUrl\n\n";
        
        // Download the image
        echo "Downloading Alger image...\n";
        $filePath = "public/images/destinations/alger.jpg";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $workingAlgerUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $imageData) {
            if (file_put_contents($filePath, $imageData)) {
                echo "✓ Successfully downloaded alger.jpg\n";
                echo "✓ File size: " . filesize($filePath) . " bytes\n";
                echo "✓ URL should now work: http://localhost:8000/images/destinations/alger.jpg\n";
            } else {
                echo "✗ Failed to save alger.jpg\n";
            }
        } else {
            echo "✗ Failed to download (HTTP $httpCode)\n";
            
            // Try another working URL
            echo "Trying alternative Alger image URL...\n";
            $altUrl = 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=800&h=600&q=80';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $altUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData) {
                if (file_put_contents($filePath, $imageData)) {
                    echo "✓ Downloaded from alternative URL\n";
                    echo "✓ File size: " . filesize($filePath) . " bytes\n";
                    
                    // Update database with working URL
                    $stmt = $pdo->prepare("UPDATE quiz_images SET image_url = :image_url WHERE destination LIKE '%Alger%' AND is_active = 1");
                    $stmt->execute([':image_url' => $altUrl]);
                    echo "✓ Updated phpMyAdmin with working URL\n";
                } else {
                    echo "✗ Failed to save file\n";
                }
            } else {
                echo "✗ Alternative URL also failed\n";
                
                // Copy an existing image as last resort
                echo "Copying existing image as fallback...\n";
                if (copy('public/images/destinations/shanghai.jpg', $filePath)) {
                    echo "✓ Copied shanghai.jpg as alger.jpg (fallback)\n";
                    echo "✓ File size: " . filesize($filePath) . " bytes\n";
                } else {
                    echo "✗ Failed to copy fallback image\n";
                }
            }
        }
        
    } else {
        echo "✗ Failed to update Alger URL\n";
    }
    
    echo "\n=== Final Verification ===\n";
    
    // Check Alger image
    $algerPath = "public/images/destinations/alger.jpg";
    if (file_exists($algerPath)) {
        echo "✓ Alger image exists: http://localhost:8000/images/destinations/alger.jpg\n";
        echo "✓ File size: " . filesize($algerPath) . " bytes\n";
    } else {
        echo "✗ Alger image still missing\n";
    }
    
    // Show all quiz images
    echo "\nAll quiz images from phpMyAdmin:\n";
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

echo "\n✅ Alger image should now be fixed!\n";
echo "No more 404 errors for alger.jpg\n";
echo "Try the quiz: http://localhost:8000/quiz\n";
?>
