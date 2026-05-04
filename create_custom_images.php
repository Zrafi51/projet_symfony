<?php
// Create custom images from any URL for quiz destinations

echo "=== Custom Image Creator for Quiz ===\n\n";

// Get destinations from voyage table
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT idVoyage, destination, pays FROM voyage WHERE disponible = 1 ORDER BY idVoyage");
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($destinations) . " destinations in voyage table:\n";
    foreach ($destinations as $dest) {
        echo "- {$dest['idVoyage']}: {$dest['destination']} ({$dest['pays']})\n";
    }
    
    echo "\n";
    
    // Interactive mode for adding images
    echo "Choose an option:\n";
    echo "1. Add images from URLs (Google, Pinterest, etc.)\n";
    echo "2. Use default placeholder images\n";
    echo "3. Exit\n";
    
    echo "\nEnter your choice (1-3): ";
    $choice = trim(fgets(STDIN));
    
    if ($choice === '1') {
        echo "\n=== Add Images from URLs ===\n";
        
        foreach ($destinations as $dest) {
            echo "\nDestination: {$dest['destination']} ({$dest['pays']})\n";
            echo "Enter image URL (or press Enter for placeholder): ";
            $url = trim(fgets(STDIN));
            
            if (empty($url)) {
                $filename = "destination_" . $dest['idVoyage'] . ".jpg";
                $imagePath = "/images/destinations/" . $filename;
                echo "Using placeholder: $filename\n";
            } else {
                // Download image
                echo "Downloading: $url\n";
                $imageData = file_get_contents($url);
                
                if ($imageData !== false) {
                    $filename = "destination_" . $dest['idVoyage'] . ".jpg";
                    $filePath = "public/images/destinations/" . $filename;
                    
                    if (file_put_contents($filePath, $imageData)) {
                        echo "✓ Saved: $filename\n";
                        $imagePath = "/images/destinations/" . $filename;
                    } else {
                        echo "✗ Failed to download image\n";
                        $filename = "destination_" . $dest['idVoyage'] . ".jpg";
                        $imagePath = "/images/destinations/" . $filename;
                    }
                } else {
                    echo "✗ Failed to download image\n";
                    $filename = "destination_" . $dest['idVoyage'] . ".jpg";
                    $imagePath = "/images/destinations/" . $filename;
                }
            }
            
            // Update map_destinations table
            $updateStmt = $pdo->prepare("UPDATE map_destinations SET image_path = :image_path WHERE city = :city");
            $updateStmt->execute([
                ':image_path' => $filename,
                ':city' => $dest['destination']
            ]);
            
            echo "Updated database with image path: $filename\n";
        }
        
    } elseif ($choice === '2') {
        echo "\n=== Create Default Placeholder Images ===\n";
        
        // Create simple colored placeholder images
        foreach ($destinations as $dest) {
            $filename = "destination_" . $dest['idVoyage'] . ".jpg";
            $filePath = "public/images/destinations/" . $filename;
            
            // Create a simple colored rectangle with destination name
            $width = 800;
            $height = 600;
            $image = imagecreatetruecolor($width, $height);
            
            // Set colors based on destination
            $colors = [
                1 => [100, 150, 200], // Blue for Addis Ababa
                2 => [255, 100, 100], // Orange for Shanghai
                3 => [50, 100, 200],  // Green for Berlin
                4 => [200, 100, 50],  // Red for Toronto
                5 => [100, 150, 200], // Yellow for Algiers
                6 => [0, 100, 200],  // Cyan for Rio
                7 => [150, 100, 50],  // Purple for Casablanca
                8 => [200, 150, 100], // Pink for second Algiers
            ];
            
            $color = $colors[$dest['idVoyage']] ?? [100, 100, 100];
            
            // Fill background
            imagefill($image, $color[0], $color[1], $color[2]);
            
            // Add text
            $textColor = imagecolorallocate($image, 255, 255, 255);
            $fontSize = 30;
            $font = 5;
            
            // Add destination name
            $text = $dest['destination'];
            $textBox = imagettfbbox($fontSize, 0, $font, $text);
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[7];
            
            $x = ($width - $textWidth) / 2;
            $y = ($height - $textHeight) / 2;
            
            imagettftext($image, $textColor, $fontSize, 0, $x, $y, $text, $font);
            
            // Save image
            imagejpeg($image, $filePath, 90);
            imagedestroy($image);
            
            echo "✓ Created placeholder: $filename\n";
            
            // Update map_destinations table
            $updateStmt = $pdo->prepare("UPDATE map_destinations SET image_path = :image_path WHERE city = :city");
            $updateStmt->execute([
                ':image_path' => $filename,
                ':city' => $dest['destination']
            ]);
            
            echo "Updated database with image path: $filename\n";
        }
        
    } else {
        echo "\nExiting...\n";
        exit;
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Process Complete ===\n";
echo "Images are ready for quiz!\n";
echo "Access quiz at: http://localhost:8000/quiz\n";
?>
