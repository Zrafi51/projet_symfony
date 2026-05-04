<?php
// Create placeholder images for quiz destinations

echo "=== Creating Placeholder Images ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all quiz images
    $stmt = $pdo->query("SELECT id, voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($quizImages) . " destinations to create images for:\n";
    
    $createdCount = 0;
    
    foreach ($quizImages as $image) {
        $filename = $image['image_filename'];
        $filePath = "public/images/destinations/" . $filename;
        
        // Create a simple colored rectangle with destination name
        $width = 800;
        $height = 600;
        
        // Check if GD library is available
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor($width, $height);
            
            // Set colors based on destination
            $colors = [
                1 => [100, 150, 200], // Blue for Addis Ababa
                2 => [255, 150, 100], // Orange for Shanghai
                3 => [50, 200, 100],  // Green for Berlin
                4 => [200, 100, 50],  // Brown for Toronto
                5 => [150, 100, 200], // Purple for Algiers
                6 => [100, 200, 200], // Cyan for Rio
                7 => [200, 150, 100], // Tan for Casablanca
                8 => [200, 100, 100], // Red for second Algiers
            ];
            
            $color = $colors[$image['voyage_id']] ?? [100, 100, 100];
            
            // Fill background
            $bgColor = imagecolorallocate($img, $color[0], $color[1], $color[2]);
            imagefill($img, 0, 0, $bgColor);
            
            // Add text
            $textColor = imagecolorallocate($img, 255, 255, 255);
            
            // Add destination name
            $text = $image['destination'];
            $fontSize = 5; // Built-in font size
            
            // Calculate text position
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textHeight = imagefontheight($fontSize);
            
            $x = ($width - $textWidth) / 2;
            $y = ($height - $textHeight) / 2;
            
            imagestring($img, $fontSize, $x, $y, $text, $textColor);
            
            // Add country name below
            $countryText = $image['pays'];
            $countryWidth = imagefontwidth($fontSize) * strlen($countryText);
            $countryX = ($width - $countryWidth) / 2;
            $countryY = $y + $textHeight + 20;
            
            imagestring($img, $fontSize, $countryX, $countryY, $countryText, $textColor);
            
            // Save image
            if (imagejpeg($img, $filePath, 90)) {
                echo "✓ Created placeholder: $filename ({$image['destination']})\n";
                $createdCount++;
            } else {
                echo "✗ Failed to create: $filename\n";
            }
            
            imagedestroy($img);
        } else {
            // Fallback: create a simple text file as placeholder
            $placeholderText = "Placeholder for {$image['destination']} ({$image['pays']})";
            if (file_put_contents($filePath . ".txt", $placeholderText)) {
                echo "✓ Created text placeholder: $filename.txt\n";
                $createdCount++;
            }
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "- Created $createdCount placeholder images\n";
    echo "- Images stored in: public/images/destinations/\n";
    echo "\n✅ Quiz images are ready!\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
