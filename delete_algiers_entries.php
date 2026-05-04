<?php
// Find and delete all Algiers-related entries that shouldn't be there

echo "=== Finding and Deleting Algiers Entries ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking voyage table for Algiers entries...\n";
    
    // Find all Algiers entries in voyage table
    $stmt = $pdo->query("SELECT idVoyage, destination, pays, disponible FROM voyage WHERE pays LIKE '%Algérie%' OR destination LIKE '%Algiers%' OR destination LIKE '%Alger%'");
    $algiersVoyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($algiersVoyages) . " Algiers-related entries in voyage table:\n";
    foreach ($algiersVoyages as $voyage) {
        echo "- ID {$voyage['idVoyage']}: {$voyage['destination']} ({$voyage['pays']}) - " . ($voyage['disponible'] ? 'Available' : 'Unavailable') . "\n";
    }
    
    echo "\nChecking quiz_images table for Algiers entries...\n";
    
    // Find all Algiers entries in quiz_images table
    $stmt = $pdo->query("SELECT id, voyage_id, destination, pays, image_filename FROM quiz_images WHERE pays LIKE '%Algérie%' OR destination LIKE '%Algiers%' OR destination LIKE '%Alger%'");
    $algiersQuizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($algiersQuizImages) . " Algiers-related entries in quiz_images table:\n";
    foreach ($algiersQuizImages as $image) {
        echo "- ID {$image['id']}: Voyage ID {$image['voyage_id']} - {$image['destination']} ({$image['pays']}) - {$image['image_filename']}\n";
    }
    
    echo "\n=== Deleting Problematic Entries ===\n";
    
    // Delete voyage entries that shouldn't exist (the ones without proper quiz_images)
    foreach ($algiersVoyages as $voyage) {
        // Check if this voyage has a proper quiz image
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_images WHERE voyage_id = :voyage_id AND is_active = 1");
        $stmt->execute([':voyage_id' => $voyage['idVoyage']]);
        $count = $stmt->fetch()['count'];
        
        if ($count == 0) {
            // Delete this voyage entry
            $stmt = $pdo->prepare("DELETE FROM voyage WHERE idVoyage = :voyage_id");
            $stmt->execute([':voyage_id' => $voyage['idVoyage']]);
            echo "✓ Deleted voyage ID {$voyage['idVoyage']}: {$voyage['destination']}\n";
        } else {
            echo "- Kept voyage ID {$voyage['idVoyage']}: {$voyage['destination']} (has quiz image)\n";
        }
    }
    
    // Delete any quiz_images that have wrong mappings
    foreach ($algiersQuizImages as $image) {
        // Check if the corresponding voyage exists and matches
        $stmt = $pdo->prepare("SELECT destination, pays FROM voyage WHERE idVoyage = :voyage_id AND disponible = 1");
        $stmt->execute([':voyage_id' => $image['voyage_id']]);
        $voyage = $stmt->fetch();
        
        if (!$voyage || $voyage['destination'] !== $image['destination'] || $voyage['pays'] !== $image['pays']) {
            // Delete this quiz_image entry
            $stmt = $pdo->prepare("DELETE FROM quiz_images WHERE id = :id");
            $stmt->execute([':id' => $image['id']]);
            echo "✓ Deleted quiz_image ID {$image['id']}: {$image['destination']} (mismatch)\n";
        } else {
            echo "- Kept quiz_image ID {$image['id']}: {$image['destination']} (matches)\n";
        }
    }
    
    echo "\n=== Final Verification ===\n";
    
    // Show remaining Algiers entries
    $stmt = $pdo->query("SELECT idVoyage, destination, pays, disponible FROM voyage WHERE pays LIKE '%Algérie%' OR destination LIKE '%Algiers%' OR destination LIKE '%Alger%' ORDER BY idVoyage");
    $remainingVoyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT id, voyage_id, destination, pays, image_filename FROM quiz_images WHERE pays LIKE '%Algérie%' OR destination LIKE '%Algiers%' OR destination LIKE '%Alger%' ORDER BY voyage_id");
    $remainingQuizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Remaining Algiers entries:\n";
    echo "Voyage table: " . count($remainingVoyages) . " entries\n";
    echo "Quiz_images table: " . count($remainingQuizImages) . " entries\n";
    
    if (!empty($remainingVoyages)) {
        echo "\nVoyage table:\n";
        foreach ($remainingVoyages as $voyage) {
            echo "- ID {$voyage['idVoyage']}: {$voyage['destination']} ({$voyage['pays']})\n";
        }
    }
    
    if (!empty($remainingQuizImages)) {
        echo "\nQuiz_images table:\n";
        foreach ($remainingQuizImages as $image) {
            echo "- ID {$image['id']}: Voyage ID {$image['voyage_id']} - {$image['destination']} ({$image['pays']}) - {$image['image_filename']}\n";
        }
    }
    
    echo "\n✅ Cleanup completed!\n";
    echo "Try the quiz now: http://localhost:8000/quiz\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
