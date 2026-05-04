<?php
// Add sample destinations to map_destinations table and download images

echo "=== Adding Sample Destinations to Database ===\n\n";

// Sample destinations with images
$sampleDestinations = [
    [
        'city' => 'Addis Ababa',
        'country' => 'Éthiopie',
        'continent' => 'Afrique',
        'package_name' => 'Pack Capital Éthiopienne',
        'duration' => '5 jours / 4 nuits',
        'price' => '1590 EUR',
        'original_price' => '1890 EUR',
        'image_path' => 'addis_ababa.jpg',
        'url' => 'https://images.unsplash.com/photo-15572437-0c8b4e8a8b-4b8c5a6a4a6d4?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Shanghai',
        'country' => 'Chine',
        'continent' => 'Asie',
        'package_name' => 'Pack Shanghai Métropole',
        'duration' => '6 jours / 5 nuits',
        'price' => '2190 EUR',
        'original_price' => '2590 EUR',
        'image_path' => 'shanghai.jpg',
        'url' => 'https://images.unsplash.com/photo-1547808036-ce5a416a6239-1a6e5e0c3a9?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Berlin',
        'country' => 'Allemagne',
        'continent' => 'Europe',
        'package_name' => 'Pack Berlin Culture',
        'duration' => '4 jours / 3 nuits',
        'price' => '1290 EUR',
        'original_price' => '1490 EUR',
        'image_path' => 'berlin.jpg',
        'url' => 'https://images.unsplash.com/photo-15202497620-598b2c4a97b-8a465a724b4b6d6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Toronto',
        'country' => 'Canada',
        'continent' => 'Amérique',
        'package_name' => 'Pack Toronto City',
        'duration' => '5 jours / 4 nuits',
        'price' => '1790 EUR',
        'original_price' => '2090 EUR',
        'image_path' => 'toronto.jpg',
        'url' => 'https://images.unsplash.com/photo-1511734619285-79a06e0c6f6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Algiers',
        'country' => 'Algérie',
        'continent' => 'Afrique',
        'package_name' => 'Pack Alger Médina',
        'duration' => '4 jours / 3 nuits',
        'price' => '990 EUR',
        'original_price' => '1190 EUR',
        'image_path' => 'algiers.jpg',
        'url' => 'https://images.unsplash.com/photo-15442956984-9e8590b6b6d-6ab9b064376639?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Rio de Janeiro',
        'country' => 'Brésil',
        'continent' => 'Amérique',
        'package_name' => 'Pack Rio Tropical',
        'duration' => '7 jours / 6 nuits',
        'price' => '2490 EUR',
        'original_price' => '2890 EUR',
        'image_path' => 'rio_de_janeiro.jpg',
        'url' => 'https://images.unsplash.com/photo-1484714784445-920e19d0aeb-4a47b4c6d6a4d6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Casablanca',
        'country' => 'Maroc',
        'continent' => 'Afrique',
        'package_name' => 'Pack Casablanca',
        'duration' => '3 jours / 2 nuits',
        'price' => '790 EUR',
        'original_price' => '990 EUR',
        'image_path' => 'casablanca.jpg',
        'url' => 'https://images.unsplash.com/photo-15134888045314-8d5aa341d671-4b8c5a6a4a6d4?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Marrakech',
        'country' => 'Maroc',
        'continent' => 'Afrique',
        'package_name' => 'Pack Marrakech',
        'duration' => '5 jours / 4 nuits',
        'price' => '1090 EUR',
        'original_price' => '1290 EUR',
        'image_path' => 'marrakech.jpg',
        'url' => 'https://images.unsplash.com/photo-1512836286243-8b7c1e0e2b2b?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Cape Town',
        'country' => 'Afrique du Sud',
        'continent' => 'Afrique',
        'package_name' => 'Pack Le Cap',
        'duration' => '6 jours / 5 nuits',
        'price' => '1990 EUR',
        'original_price' => '2390 EUR',
        'image_path' => 'cape_town.jpg',
        'url' => 'https://images.unsplash.com/photo-1511734619285-79a06e0c6f6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'city' => 'Sydney',
        'country' => 'Australie',
        'continent' => 'Océanie',
        'package_name' => 'Pack Sydney Harbour',
        'duration' => '8 jours / 7 nuits',
        'price' => '3290 EUR',
        'original_price' => '3790 EUR',
        'image_path' => 'sydney.jpg',
        'url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=800&h=600&q=80'
    ]
];

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n\n";
    
    // Clear existing entries to avoid duplicates
    $clearStmt = $pdo->exec("DELETE FROM map_destinations WHERE city IN ('" . implode("','", array_column($sampleDestinations, 'city')) . "')");
    echo "Cleared existing sample destinations\n";
    
    $addedCount = 0;
    $downloadedCount = 0;
    
    foreach ($sampleDestinations as $index => $dest) {
        // Insert into map_destinations table
        $insertStmt = $pdo->prepare("
            INSERT INTO map_destinations (
                city, country, continent, package_name, duration, price, original_price, 
                image_path, description, best_period, includes, highlight_1, highlight_2, highlight_3,
                x_percent, y_percent, ai_score, ai_recommended, is_active, display_order, created_at
            ) VALUES (
                :city, :country, :continent, :package_name, :duration, :price, :original_price,
                :image_path, :description, :best_period, :includes, :highlight_1, :highlight_2, :highlight_3,
                :x_percent, :y_percent, :ai_score, :ai_recommended, :is_active, :display_order, NOW()
            )
        ");
        
        $params = [
            ':city' => $dest['city'],
            ':country' => $dest['country'],
            ':continent' => $dest['continent'],
            ':package_name' => $dest['package_name'],
            ':duration' => $dest['duration'],
            ':price' => $dest['price'],
            ':original_price' => $dest['original_price'],
            ':image_path' => $dest['image_path'],
            ':description' => "Découvrez {$dest['city']}, une destination magnifique avec des paysages spectaculaires et une culture riche.",
            ':best_period' => "Toute l'année",
            ':includes' => "Vol, hôtel, transferts, guide local",
            ':highlight_1' => "Visite des sites emblématiques",
            ':highlight_2' => "Expériences locales authentiques",
            ':highlight_3' => "Hébergement de qualité",
            ':x_percent' => rand(10, 90) / 100,
            ':y_percent' => rand(10, 90) / 100,
            ':ai_score' => rand(85, 98),
            ':ai_recommended' => 1,
            ':is_active' => 1,
            ':display_order' => $index + 1
        ];
        
        if ($insertStmt->execute($params)) {
            echo "✓ Added to database: {$dest['city']}\n";
            $addedCount++;
            
            // Download image
            echo "  Downloading image: {$dest['url']}\n";
            $imageData = @file_get_contents($dest['url']);
            
            if ($imageData !== false) {
                $filePath = "public/images/destinations/" . $dest['image_path'];
                if (file_put_contents($filePath, $imageData)) {
                    echo "  ✓ Image downloaded: {$dest['image_path']}\n";
                    $downloadedCount++;
                } else {
                    echo "  ✗ Failed to save image\n";
                }
            } else {
                echo "  ✗ Failed to download image (URL may be invalid)\n";
            }
        } else {
            echo "✗ Failed to add to database: {$dest['city']}\n";
        }
        
        echo "\n";
    }
    
    echo "=== Summary ===\n";
    echo "- Added $addedCount destinations to map_destinations table\n";
    echo "- Downloaded $downloadedCount images\n";
    echo "- Images stored in: public/images/destinations/\n";
    echo "\n✅ Sample destinations are ready for the quiz!\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
