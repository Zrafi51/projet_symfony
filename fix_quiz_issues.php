<?php
// Fix the remaining quiz issues

echo "=== Fixing Quiz Issues ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Fixing missing destinations in voyage table...\n";
    
    // Add missing destinations to voyage table
    $missingDestinations = [
        ['idVoyage' => 9, 'destination' => 'Alger', 'pays' => 'Algérie'],
        ['idVoyage' => 10, 'destination' => 'Paris', 'pays' => 'France']
    ];
    
    foreach ($missingDestinations as $dest) {
        // Check if it already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voyage WHERE idVoyage = :idVoyage");
        $stmt->execute([':idVoyage' => $dest['idVoyage']]);
        $count = $stmt->fetch()['count'];
        
        if ($count == 0) {
            // Insert the missing destination
            $stmt = $pdo->prepare("INSERT INTO voyage (idVoyage, destination, pays, disponible) VALUES (:idVoyage, :destination, :pays, 1)");
            $result = $stmt->execute([
                ':idVoyage' => $dest['idVoyage'],
                ':destination' => $dest['destination'],
                ':pays' => $dest['pays']
            ]);
            
            if ($result) {
                echo "✓ Added Voyage ID {$dest['idVoyage']}: {$dest['destination']} ({$dest['pays']})\n";
            } else {
                echo "✗ Failed to add Voyage ID {$dest['idVoyage']}\n";
            }
        } else {
            echo "- Voyage ID {$dest['idVoyage']} already exists\n";
        }
    }
    
    echo "\n2. Checking for encoding issues in destinations...\n";
    
    // Check all destinations for encoding issues
    $stmt = $pdo->query("SELECT idVoyage, destination, pays FROM voyage WHERE disponible = 1 ORDER BY idVoyage");
    $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($voyages as $voyage) {
        echo "Voyage ID {$voyage['idVoyage']}: '{$voyage['destination']}' - '{$voyage['pays']}'\n";
        
        // Check for suspicious characters that might cause "Rio de genero"
        if (strpos($voyage['destination'], 'Rio') !== false) {
            echo "  ⚠️  Found Rio destination - checking for issues...\n";
            echo "  Raw: " . bin2hex($voyage['destination']) . "\n";
            echo "  Length: " . strlen($voyage['destination']) . "\n";
        }
    }
    
    echo "\n3. Force refresh Toronto image...\n";
    
    // Delete and re-download Toronto image
    $torontoPath = "public/images/destinations/toronto.jpg";
    if (file_exists($torontoPath)) {
        unlink($torontoPath);
        echo "✓ Deleted old toronto.jpg\n";
    }
    
    // Get the correct Toronto URL from database
    $stmt = $pdo->prepare("SELECT image_url FROM quiz_images WHERE destination LIKE '%Toronto%' AND is_active = 1");
    $stmt->execute();
    $torontoData = $stmt->fetch();
    
    if ($torontoData) {
        echo "Downloading from: {$torontoData['image_url']}\n";
        $imageData = @file_get_contents($torontoData['image_url']);
        
        if ($imageData !== false) {
            if (file_put_contents($torontoPath, $imageData)) {
                echo "✓ Downloaded new toronto.jpg (" . filesize($torontoPath) . " bytes)\n";
            } else {
                echo "✗ Failed to save toronto.jpg\n";
            }
        } else {
            echo "✗ Failed to download from URL\n";
        }
    }
    
    echo "\n4. Clean up unused image files...\n";
    
    // Remove unused image files
    $unusedFiles = ['addis_ababa.jpg', 'alger.jpg', 'algiers.jpg', 'algiers_2.jpg', 'default.jpg'];
    
    foreach ($unusedFiles as $file) {
        $filePath = "public/images/destinations/" . $file;
        if (file_exists($filePath)) {
            unlink($filePath);
            echo "✓ Deleted unused file: $file\n";
        }
    }
    
    echo "\n5. Current status...\n";
    
    // Show current available destinations
    $stmt = $pdo->query("SELECT v.idVoyage, v.destination, v.pays, qi.image_filename 
                          FROM voyage v 
                          LEFT JOIN quiz_images qi ON v.idVoyage = qi.voyage_id 
                          WHERE v.disponible = 1 
                          ORDER BY v.idVoyage");
    $availableDestinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available quiz destinations:\n";
    foreach ($availableDestinations as $dest) {
        $imageStatus = $dest['image_filename'] ? "✓ {$dest['image_filename']}" : "✗ No image";
        echo "  ID {$dest['idVoyage']}: {$dest['destination']} ({$dest['pays']}) - $imageStatus\n";
    }
    
    echo "\n=== Browser Cache Issue ===\n";
    echo "If you still see the wrong Toronto image, try:\n";
    echo "1. Clear browser cache (Ctrl+F5)\n";
    echo "2. Open browser developer tools (F12)\n";
    echo "3. Go to Network tab and check 'Disable cache'\n";
    echo "4. Refresh the page\n";
    
    echo "\n=== Console URLs to Check ===\n";
    echo "Copy these URLs to your browser to verify images:\n\n";
    
    foreach ($availableDestinations as $dest) {
        if ($dest['image_filename']) {
            $url = "http://localhost:8000/images/destinations/" . $dest['image_filename'];
            echo "$url\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ Fixes applied!\n";
echo "Try the quiz now: http://localhost:8000/quiz\n";
?>
