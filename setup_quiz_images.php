<?php
// Setup quiz_images table with local images from uploads/quiz_img/

echo "=== Setup Quiz Images with Local Files ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n\n";
    
    // Get available voyages
    $stmt = $pdo->query("SELECT idVoyage, destination, pays FROM voyage WHERE disponible = 1 ORDER BY idVoyage");
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available voyages:\n";
    foreach ($voyages as $voyage) {
        echo "- ID {$voyage['idVoyage']}: {$voyage['destination']} ({$voyage['pays']})\n";
    }
    
    echo "\n1. Creating/updating quiz_images table...\n";
    
    // Create table without image_url column
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `quiz_images` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `voyage_id` int(11) NOT NULL,
      `destination` varchar(100) NOT NULL,
      `pays` varchar(100) NOT NULL,
      `image_filename` varchar(255) NOT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `UNIQ_quiz_images_voyage_id` (`voyage_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($createTableSQL);
    echo "✓ quiz_images table ready\n";
    
    echo "\n2. Mapping images to destinations...\n";
    
    // Map filenames to destinations based on your available images
    $imageMapping = [
        'alger.jpg' => ['destination' => 'Alger', 'pays' => 'Algérie'],
        'berlin.jpg' => ['destination' => 'Berlin', 'pays' => 'Allemagne'],
        'casablanca.jpg' => ['destination' => 'Casablanca', 'pays' => 'Maroc'],
        'london.jpg' => ['destination' => 'London', 'pays' => 'Royaume-Uni'],
        'paris.jpg' => ['destination' => 'Paris', 'pays' => 'France'],
        'rio de janeiro.jpg' => ['destination' => 'Rio de Janeiro', 'pays' => 'Brésil'],
        'shanghai.jpg' => ['destination' => 'Shanghai', 'pays' => 'Chine'],
        'toronto.jpg' => ['destination' => 'Toronto', 'pays' => 'Canada'],
        'tunis.jpg' => ['destination' => 'Tunis', 'pays' => 'Tunisie']
    ];
    
    // Clear existing data
    $pdo->exec("DELETE FROM quiz_images");
    echo "✓ Cleared existing quiz_images data\n";
    
    $addedCount = 0;
    $uploadDir = 'public/uploads/quiz_img/';
    
    foreach ($imageMapping as $filename => $data) {
        // Check if image exists in uploads
        if (file_exists($uploadDir . $filename)) {
            // Find matching voyage ID
            $voyageId = null;
            foreach ($voyages as $voyage) {
                if (strtolower($voyage['destination']) === strtolower($data['destination'])) {
                    $voyageId = $voyage['idVoyage'];
                    break;
                }
            }
            
            if ($voyageId) {
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_images (voyage_id, destination, pays, image_filename, is_active, created_at)
                    VALUES (:voyage_id, :destination, :pays, :image_filename, 1, NOW())
                ");
                
                if ($stmt->execute([
                    ':voyage_id' => $voyageId,
                    ':destination' => $data['destination'],
                    ':pays' => $data['pays'],
                    ':image_filename' => $filename
                ])) {
                    echo "✓ Added: {$data['destination']} -> $filename (Voyage ID: $voyageId)\n";
                    $addedCount++;
                } else {
                    echo "✗ Failed to add: {$data['destination']}\n";
                }
            } else {
                echo "⚠️  No matching voyage found for: {$data['destination']}\n";
            }
        } else {
            echo "⚠️  Image not found: $filename\n";
        }
    }
    
    echo "\n3. Verification...\n";
    
    // Show what was added
    $stmt = $pdo->query("SELECT voyage_id, destination, pays, image_filename FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quiz images in database:\n";
    foreach ($quizImages as $image) {
        $hasFile = file_exists($uploadDir . $image['image_filename']) ? '✓' : '✗';
        echo "  ID {$image['voyage_id']}: {$image['destination']} - {$image['image_filename']} $hasFile\n";
    }
    
    echo "\n=== Summary ===\n";
    echo "- Added $addedCount quiz image mappings\n";
    echo "- Images are located in: $uploadDir\n";
    echo "- Quiz will use local images from uploads/quiz_img/\n";
    
    echo "\n✅ Setup complete!\n";
    echo "Try the quiz: http://localhost:8000/quiz\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
