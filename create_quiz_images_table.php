<?php
// Create quiz_images table and populate with sample data

echo "=== Creating quiz_images Table ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Create quiz_images table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `quiz_images` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `voyage_id` int(11) NOT NULL,
      `destination` varchar(100) NOT NULL,
      `pays` varchar(100) NOT NULL,
      `image_filename` varchar(255) NOT NULL,
      `image_url` text DEFAULT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `UNIQ_quiz_images_voyage_id` (`voyage_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($createTableSQL);
    echo "✓ Created quiz_images table\n\n";
    
    // Sample data matching current phpMyAdmin quiz_images table
    $sampleImages = [
        [
            'voyage_id' => 2,
            'destination' => 'Shanghai',
            'pays' => 'Chine',
            'image_filename' => 'shanghai.jpg',
            'image_url' => 'https://tse4.mm.bing.net/th/id/OIP.hFFgLxZqP_F9lPWPHaD9MAHaE8?rs=1&pid=ImgDetMain&o=7&rm=3'
        ],
        [
            'voyage_id' => 3,
            'destination' => 'Berlin',
            'pays' => 'Allemagne',
            'image_filename' => 'berlin.jpg',
            'image_url' => 'https://tse4.mm.bing.net/th/id/OIP.gzzxP-3ZwFycOgz6zKeleAHaE7?rs=1&pid=ImgDetMain&o=7&rm=3'
        ],
        [
            'voyage_id' => 4,
            'destination' => 'Toronto',
            'pays' => 'Canada',
            'image_filename' => 'toronto.jpg',
            'image_url' => 'https://tse1.mm.bing.net/th/id/OIP.J8nLU6nSK-j68lHOJ1TeQAHaE5?rs=1&pid=ImgDetMain&o=7&rm=3'
        ],
        [
            'voyage_id' => 6,
            'destination' => 'Rio de Janeiro',
            'pays' => 'Brésil',
            'image_filename' => 'rio_de_janeiro.jpg',
            'image_url' => 'https://cdn.mos.cms.futurecdn.net/dxiLtztp2NSCZMRY3SBZne.jpg'
        ],
        [
            'voyage_id' => 7,
            'destination' => 'Casablanca',
            'pays' => 'Maroc',
            'image_filename' => 'casablanca.jpg',
            'image_url' => 'https://images.tourscanner.com/wp-content/uploads/2023/08/things-to-do-in-Casablanca.jpg'
        ],
        [
            'voyage_id' => 8,
            'destination' => 'Tunis',
            'pays' => 'Tunisie',
            'image_filename' => 'tunis.jpg',
            'image_url' => 'https://tse2.mm.bing.net/th/id/OIP.a9Cb5TFKtMEmto-2v_67bQHaEp?rs=1&pid=ImgDetMain&o=7&rm=3'
        ],
        [
            'voyage_id' => 9,
            'destination' => 'Alger',
            'pays' => 'Algérie',
            'image_filename' => 'alger.jpg',
            'image_url' => 'https://wallpapercave.com/wp/wp1860667.jpg'
        ],
        [
            'voyage_id' => 10,
            'destination' => 'Paris',
            'pays' => 'France',
            'image_filename' => 'paris.jpg',
            'image_url' => 'https://tse2.mm.bing.net/th/id/OIP.ggGnTtztXU71CtsyNJIdUQHaFB?rs=1&pid=ImgDetMain&o=7&rm=3'
        ],
        [
            'voyage_id' => 11,
            'destination' => 'London',
            'pays' => 'Royaume-Uni',
            'image_filename' => 'london.jpg',
            'image_url' => 'https://tse4.mm.bing.net/th/id/OIP.n0fV_f3UHhvfRJ-yfZc7YQHaE6?rs=1&pid=ImgDetMain&o=7&rm=3'
        ]
    ];
    
    // Clear existing data
    $pdo->exec("DELETE FROM quiz_images");
    echo "Cleared existing data\n\n";
    
    // Insert sample data
    $insertStmt = $pdo->prepare("
        INSERT INTO quiz_images (voyage_id, destination, pays, image_filename, image_url, is_active, created_at)
        VALUES (:voyage_id, :destination, :pays, :image_filename, :image_url, :is_active, NOW())
    ");
    
    $addedCount = 0;
    $downloadedCount = 0;
    
    foreach ($sampleImages as $image) {
        $params = [
            ':voyage_id' => $image['voyage_id'],
            ':destination' => $image['destination'],
            ':pays' => $image['pays'],
            ':image_filename' => $image['image_filename'],
            ':image_url' => $image['image_url'],
            ':is_active' => 1
        ];
        
        if ($insertStmt->execute($params)) {
            echo "✓ Added to quiz_images: {$image['destination']} ({$image['pays']})\n";
            $addedCount++;
            
            // Download image
            echo "  Downloading: {$image['image_url']}\n";
            $imageData = @file_get_contents($image['image_url']);
            
            if ($imageData !== false) {
                $filePath = "public/images/destinations/" . $image['image_filename'];
                if (file_put_contents($filePath, $imageData)) {
                    echo "  ✓ Image saved: {$image['image_filename']}\n";
                    $downloadedCount++;
                } else {
                    echo "  ✗ Failed to save image\n";
                }
            } else {
                echo "  ✗ Failed to download image (URL may be invalid)\n";
            }
        } else {
            echo "✗ Failed to add: {$image['destination']}\n";
        }
        echo "\n";
    }
    
    echo "=== Summary ===\n";
    echo "- Added $addedCount records to quiz_images table\n";
    echo "- Downloaded $downloadedCount images to public/images/destinations/\n";
    echo "\n✅ quiz_images table is ready!\n";
    
    // Show SQL for manual creation if needed
    echo "\n=== SQL for Manual Creation ===\n";
    echo "If you want to create this table manually in phpMyAdmin, use this SQL:\n\n";
    echo $createTableSQL . "\n\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
