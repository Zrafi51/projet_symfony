<?php
// Check what's in the voyage table that might be causing unexpected images

echo "=== Voyage Table Data ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all data from voyage table
    $stmt = $pdo->query("SELECT * FROM voyage WHERE disponible = 1 ORDER BY idVoyage");
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($voyages) . " available voyages:\n\n";
    
    foreach ($voyages as $voyage) {
        echo "ID: {$voyage['idVoyage']}\n";
        echo "Destination: {$voyage['destination']}\n";
        echo "Pays: {$voyage['pays']}\n";
        echo "Disponible: " . ($voyage['disponible'] ? 'Yes' : 'No') . "\n";
        
        // Check if there's a matching image in quiz_images
        $stmt2 = $pdo->prepare("SELECT image_filename FROM quiz_images WHERE voyage_id = :voyage_id");
        $stmt2->execute([':voyage_id' => $voyage['idVoyage']]);
        $quizImage = $stmt2->fetch();
        
        if ($quizImage) {
            echo "Quiz Image: {$quizImage['image_filename']} ✓\n";
        } else {
            echo "Quiz Image: NOT FOUND ✗\n";
        }
        
        echo "----------------------------------------\n";
    }
    
    echo "\n=== Quiz Images Coverage ===\n";
    
    // Check which voyage_ids have quiz images
    $stmt = $pdo->query("SELECT voyage_id FROM quiz_images WHERE is_active = 1");
    $quizVoyageIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Quiz images exist for voyage IDs: " . implode(', ', $quizVoyageIds) . "\n";
    
    // Check which voyage_ids are missing quiz images
    $stmt = $pdo->query("SELECT idVoyage, destination FROM voyage WHERE disponible = 1 AND idVoyage NOT IN (" . implode(',', $quizVoyageIds) . ")");
    $missingImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($missingImages)) {
        echo "\nMissing quiz images for:\n";
        foreach ($missingImages as $voyage) {
            echo "- Voyage ID {$voyage['idVoyage']}: {$voyage['destination']}\n";
        }
    } else {
        echo "\n✅ All available voyages have quiz images!\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
