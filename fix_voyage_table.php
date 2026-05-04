<?php
// Fix voyage table to match quiz_images data

echo "=== Fixing Voyage Table ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get current quiz_images data
    $stmt = $pdo->query("SELECT voyage_id, destination, pays FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $quizImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current quiz_images data:\n";
    foreach ($quizImages as $image) {
        echo "- Voyage ID {$image['voyage_id']}: {$image['destination']} ({$image['pays']})\n";
    }
    
    echo "\nUpdating voyage table to match quiz_images...\n";
    
    // Update voyage records to match quiz_images
    foreach ($quizImages as $image) {
        $stmt = $pdo->prepare("UPDATE voyage SET destination = :destination, pays = :pays WHERE idVoyage = :voyage_id");
        $result = $stmt->execute([
            ':destination' => $image['destination'],
            ':pays' => $image['pays'],
            ':voyage_id' => $image['voyage_id']
        ]);
        
        if ($result) {
            echo "✓ Updated Voyage ID {$image['voyage_id']}: {$image['destination']}\n";
        } else {
            echo "✗ Failed to update Voyage ID {$image['voyage_id']}\n";
        }
    }
    
    // Set unavailable for voyage IDs that don't have quiz images
    echo "\nSetting unavailable for voyages without quiz images...\n";
    
    $stmt = $pdo->query("SELECT idVoyage FROM voyage WHERE disponible = 1 AND idVoyage NOT IN (SELECT voyage_id FROM quiz_images WHERE is_active = 1)");
    $unmatchedVoyages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($unmatchedVoyages as $voyageId) {
        $stmt = $pdo->prepare("UPDATE voyage SET disponible = 0 WHERE idVoyage = :voyage_id");
        $stmt->execute([':voyage_id' => $voyageId]);
        echo "✓ Set Voyage ID $voyageId as unavailable\n";
    }
    
    echo "\n=== Verification ===\n";
    
    // Show updated voyage table
    $stmt = $pdo->query("SELECT idVoyage, destination, pays, disponible FROM voyage ORDER BY idVoyage");
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Updated voyage table:\n";
    foreach ($voyages as $voyage) {
        $status = $voyage['disponible'] ? '✓' : '✗';
        echo "$status ID {$voyage['idVoyage']}: {$voyage['destination']} ({$voyage['pays']})\n";
    }
    
    echo "\n✅ Voyage table fixed!\n";
    echo "Now quiz will only show destinations that have corresponding images.\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
echo "Try the quiz now: http://localhost:8000/quiz\n";
?>
