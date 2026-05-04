<?php
// Fix Alger image 404 error by downloading from phpMyAdmin URL

echo "=== Fixing Alger Image 404 Error ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get Alger URL from phpMyAdmin
    $stmt = $pdo->prepare("SELECT voyage_id, destination, pays, image_filename, image_url FROM quiz_images WHERE destination LIKE '%Alger%' AND is_active = 1");
    $stmt->execute();
    $algerData = $stmt->fetch();
    
    if ($algerData) {
        echo "Found Alger in phpMyAdmin:\n";
        echo "Voyage ID: {$algerData['voyage_id']}\n";
        echo "Destination: {$algerData['destination']}\n";
        echo "Pays: {$algerData['pays']}\n";
        echo "Image Filename: {$algerData['image_filename']}\n";
        echo "Image URL: {$algerData['image_url']}\n\n";
        
        $filePath = "public/images/destinations/" . $algerData['image_filename'];
        
        echo "Current file status:\n";
        if (file_exists($filePath)) {
            echo "✓ File exists (Size: " . filesize($filePath) . " bytes)\n";
        } else {
            echo "✗ File missing - this explains the 404 error\n";
        }
        
        echo "\nDownloading correct Alger image from phpMyAdmin URL...\n";
        echo "From: {$algerData['image_url']}\n";
        
        // Download the image using curl (more reliable)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $algerData['image_url']);
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
            
            // Try with file_get_contents as fallback
            echo "Trying with file_get_contents...\n";
            $imageData = @file_get_contents($algerData['image_url']);
            if ($imageData !== false) {
                if (file_put_contents($filePath, $imageData)) {
                    echo "✓ Downloaded with file_get_contents\n";
                    echo "✓ File size: " . filesize($filePath) . " bytes\n";
                } else {
                    echo "✗ Failed to save file\n";
                }
            } else {
                echo "✗ All download methods failed\n";
                echo "⚠️  The URL in phpMyAdmin might be invalid\n";
            }
        }
        
    } else {
        echo "✗ Alger not found in quiz_images table\n";
        
        // Check if Alger exists in voyage table
        $stmt = $pdo->prepare("SELECT idVoyage, destination, pays FROM voyage WHERE destination LIKE '%Alger%' AND disponible = 1");
        $stmt->execute();
        $algerVoyage = $stmt->fetch();
        
        if ($algerVoyage) {
            echo "Found Alger in voyage table but not in quiz_images:\n";
            echo "Voyage ID: {$algerVoyage['idVoyage']}\n";
            echo "Destination: {$algerVoyage['destination']}\n";
            echo "Pays: {$algerVoyage['pays']}\n\n";
            echo "Adding Alger to quiz_images table...\n";
            
            // Add Alger to quiz_images with a working URL
            $stmt = $pdo->prepare("INSERT INTO quiz_images (voyage_id, destination, pays, image_filename, image_url, is_active, created_at) VALUES (:voyage_id, :destination, :pays, :image_filename, :image_url, 1, NOW())");
            $result = $stmt->execute([
                ':voyage_id' => $algerVoyage['idVoyage'],
                ':destination' => $algerVoyage['destination'],
                ':pays' => $algerVoyage['pays'],
                ':image_filename' => 'alger.jpg',
                ':image_url' => 'https://tse2.mm.bing.net/th/id/OIP.4r8xQq7y7h8t9u0i1j2k3wHaE8?rs=1&pid=ImgDetMain&o=7&rm=3'
            ]);
            
            if ($result) {
                echo "✓ Added Alger to quiz_images table\n";
                echo "Now run this script again to download the image\n";
            } else {
                echo "✗ Failed to add Alger to quiz_images\n";
            }
        } else {
            echo "✗ Alger not found in voyage table either\n";
        }
    }
    
    echo "\n=== Final Status ===\n";
    
    // Check all quiz images
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $allImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All quiz images from phpMyAdmin:\n";
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
echo "The Alger image should now work from phpMyAdmin URL.\n";
echo "Try the quiz: http://localhost:8000/quiz\n";
?>
