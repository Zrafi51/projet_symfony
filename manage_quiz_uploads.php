<?php
// Manage quiz images uploaded to public/uploads/quiz_img/

echo "=== Quiz Images Upload Manager ===\n\n";

$uploadDir = 'public/uploads/quiz_img/';
$quizImagesDir = 'public/images/destinations/';

echo "Upload directory: $uploadDir\n";
echo "Quiz images directory: $quizImagesDir\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Current uploaded quiz images:\n";
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        $imageFiles = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($uploadDir . $file);
        });
        
        if (!empty($imageFiles)) {
            foreach ($imageFiles as $file) {
                $filePath = $uploadDir . $file;
                echo "- $file (" . filesize($filePath) . " bytes)\n";
            }
        } else {
            echo "- No uploaded quiz images found\n";
        }
    } else {
        echo "- Upload directory doesn't exist\n";
    }
    
    echo "\n2. Current quiz_images database entries:\n";
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename, image_url FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($quizImages as $image) {
        $hasUpload = file_exists($uploadDir . $image['image_filename']);
        $hasDest = file_exists($quizImagesDir . $image['image_filename']);
        
        echo "Voyage ID {$image['voyage_id']}: {$image['destination']}\n";
        echo "  Filename: {$image['image_filename']}\n";
        echo "  Upload exists: " . ($hasUpload ? '✓' : '✗') . "\n";
        echo "  Destinations exists: " . ($hasDest ? '✓' : '✗') . "\n";
        echo "  Current URL: {$image['image_url']}\n\n";
    }
    
    echo "3. How to use uploaded images:\n\n";
    echo "To use uploaded images instead of URLs:\n";
    echo "1. Upload your images to: public/uploads/quiz_img/\n";
    echo "2. Make sure filenames match what's in phpMyAdmin (e.g., toronto.jpg, rio_de_janeiro.jpg)\n";
    echo "3. Run this script again to copy uploads to quiz_images directory\n\n";
    
    echo "4. Copy uploaded images to quiz_images directory? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) === 'y') {
        echo "\nCopying uploaded images...\n";
        
        $copied = 0;
        foreach ($quizImages as $image) {
            $uploadFile = $uploadDir . $image['image_filename'];
            $destFile = $quizImagesDir . $image['image_filename'];
            
            if (file_exists($uploadFile)) {
                if (copy($uploadFile, $destFile)) {
                    echo "✓ Copied {$image['image_filename']}\n";
                    $copied++;
                    
                    // Update database to use local path instead of URL
                    $stmt = $pdo->prepare("UPDATE quiz_images SET image_url = :local_url WHERE voyage_id = :voyage_id");
                    $stmt->execute([
                        ':local_url' => '/uploads/quiz_img/' . $image['image_filename'],
                        ':voyage_id' => $image['voyage_id']
                    ]);
                } else {
                    echo "✗ Failed to copy {$image['image_filename']}\n";
                }
            } else {
                echo "- {$image['image_filename']} not found in uploads\n";
            }
        }
        
        echo "\n✅ Copied $copied images to quiz_images directory\n";
        echo "✅ Updated database to use local paths\n";
        
        // Show updated URLs
        echo "\nUpdated quiz_images URLs:\n";
        $stmt = $pdo->query("SELECT voyage_id, destination, image_filename, image_url FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
        $updatedImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($updatedImages as $image) {
            echo "Voyage ID {$image['voyage_id']}: {$image['destination']} - {$image['image_url']}\n";
        }
        
    } else {
        echo "\nSkipped copying images.\n";
    }
    
    fclose($handle);
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Instructions ===\n";
echo "1. Upload quiz images to: public/uploads/quiz_img/\n";
echo "2. Use filenames that match your database (toronto.jpg, rio_de_janeiro.jpg, etc.)\n";
echo "3. Run this script to copy them to the quiz system\n";
echo "4. Quiz will then use local images instead of URLs\n\n";

echo "Quiz URL: http://localhost:8000/quiz\n";
?>
