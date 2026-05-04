<?php
// Add missing Alger image to quiz_images table

echo "=== Adding Alger Image ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add Alger to quiz_images table
    $stmt = $pdo->prepare("INSERT INTO quiz_images (voyage_id, destination, pays, image_filename, image_url, is_active, created_at) VALUES (:voyage_id, :destination, :pays, :image_filename, :image_url, 1, NOW())");
    $result = $stmt->execute([
        ':voyage_id' => 9,
        ':destination' => 'Alger',
        ':pays' => 'Algérie',
        ':image_filename' => 'alger.jpg',
        ':image_url' => 'https://tse2.mm.bing.net/th/id/OIP.4r8xQq7y7h8t9u0i1j2k3wHaE8?rs=1&pid=ImgDetMain&o=7&rm=3'
    ]);
    
    if ($result) {
        echo "✓ Added Alger to quiz_images table\n";
        
        // Download the image
        $imageData = @file_get_contents('https://tse2.mm.bing.net/th/id/OIP.4r8xQq7y7h8t9u0i1j2k3wHaE8?rs=1&pid=ImgDetMain&o=7&rm=3');
        if ($imageData !== false) {
            if (file_put_contents('public/images/destinations/alger.jpg', $imageData)) {
                echo "✓ Downloaded alger.jpg\n";
            } else {
                echo "✗ Failed to save alger.jpg\n";
            }
        } else {
            echo "✗ Failed to download Alger image\n";
        }
    } else {
        echo "✗ Failed to add Alger to quiz_images\n";
    }
    
    echo "\n=== Final Status ===\n";
    
    $stmt = $pdo->query("SELECT v.idVoyage, v.destination, v.pays, qi.image_filename 
                          FROM voyage v 
                          LEFT JOIN quiz_images qi ON v.idVoyage = qi.voyage_id 
                          WHERE v.disponible = 1 
                          ORDER BY v.idVoyage");
    $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All available quiz destinations:\n";
    foreach ($destinations as $dest) {
        $imageStatus = $dest['image_filename'] ? "✓ {$dest['image_filename']}" : "✗ No image";
        echo "  ID {$dest['idVoyage']}: {$dest['destination']} ({$dest['pays']}) - $imageStatus\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ Alger image added!\n";
echo "Try the quiz now: http://localhost:8000/quiz\n";
?>
