<?php
// Create sample travel destination images for quiz

echo "Creating sample travel destination images...\n\n";

// Sample image URLs (using free stock photos or placeholders)
$sampleImages = [
    // Southeast Asia
    [
        'destination' => 'Bali',
        'pays' => 'Indonésie',
        'filename' => 'bali.jpg',
        'url' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Jakarta',
        'pays' => 'Indonésie',
        'filename' => 'jakarta.jpg',
        'url' => 'https://images.unsplash.com/photo-1555333145-4acf190da336?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Yogyakarta',
        'pays' => 'Indonésie',
        'filename' => 'yogyakarta.jpg',
        'url' => 'https://images.unsplash.com/photo-1542640247-32f7689c2dad?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // Europe
    [
        'destination' => 'Paris',
        'pays' => 'France',
        'filename' => 'paris.jpg',
        'url' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Nice',
        'pays' => 'France',
        'filename' => 'nice.jpg',
        'url' => 'https://images.unsplash.com/photo-1531321103020-09633f7f65c6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Bordeaux',
        'pays' => 'France',
        'filename' => 'bordeaux.jpg',
        'url' => 'https://images.unsplash.com/photo-1523205771623-e0eaa4d2816d?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Lyon',
        'pays' => 'France',
        'filename' => 'lyon.jpg',
        'url' => 'https://images.unsplash.com/photo-1495107334309-fcf20504d2b6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // East Asia
    [
        'destination' => 'Tokyo',
        'pays' => 'Japon',
        'filename' => 'tokyo.jpg',
        'url' => 'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Kyoto',
        'pays' => 'Japon',
        'filename' => 'kyoto.jpg',
        'url' => 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Osaka',
        'pays' => 'Japon',
        'filename' => 'osaka.jpg',
        'url' => 'https://images.unsplash.com/photo-1590559899731-a382839e5544?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Hiroshima',
        'pays' => 'Japon',
        'filename' => 'hiroshima.jpg',
        'url' => 'https://images.unsplash.com/photo-1577017441638-6d0e8a8b0b9d?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // Korea
    [
        'destination' => 'Seoul',
        'pays' => 'Corée du Sud',
        'filename' => 'seoul.jpg',
        'url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Busan',
        'pays' => 'Corée du Sud',
        'filename' => 'busan.jpg',
        'url' => 'https://images.unsplash.com/photo-1582279127644-c6a7a165c6b7?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Jeju',
        'pays' => 'Corée du Sud',
        'filename' => 'jeju.jpg',
        'url' => 'https://images.unsplash.com/photo-1501159599894-155982264a55?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // More European destinations
    [
        'destination' => 'Rome',
        'pays' => 'Italie',
        'filename' => 'rome.jpg',
        'url' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Venice',
        'pays' => 'Italie',
        'filename' => 'venice.jpg',
        'url' => 'https://images.unsplash.com/photo-1514890547357-a9ee288728e0?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Florence',
        'pays' => 'Italie',
        'filename' => 'florence.jpg',
        'url' => 'https://images.unsplash.com/photo-1534593078512-4c63c9cb9b7c?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Barcelona',
        'pays' => 'Espagne',
        'filename' => 'barcelona.jpg',
        'url' => 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Madrid',
        'pays' => 'Espagne',
        'filename' => 'madrid.jpg',
        'url' => 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'London',
        'pays' => 'Royaume-Uni',
        'filename' => 'london.jpg',
        'url' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Amsterdam',
        'pays' => 'Pays-Bas',
        'filename' => 'amsterdam.jpg',
        'url' => 'https://images.unsplash.com/photo-1534351590666-13e3e96b5017?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Vienna',
        'pays' => 'Autriche',
        'filename' => 'vienna.jpg',
        'url' => 'https://images.unsplash.com/photo-1517927087757-3cbb0f6f5c7c?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Prague',
        'pays' => 'République Tchèque',
        'filename' => 'prague.jpg',
        'url' => 'https://images.unsplash.com/photo-1519677100203-a0e668c924e3?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // Americas
    [
        'destination' => 'New York',
        'pays' => 'États-Unis',
        'filename' => 'new_york.jpg',
        'url' => 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'San Francisco',
        'pays' => 'États-Unis',
        'filename' => 'san_francisco.jpg',
        'url' => 'https://images.unsplash.com/photo-1501594907352-04cda38ebc29?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Mexico City',
        'pays' => 'Mexique',
        'filename' => 'mexico_city.jpg',
        'url' => 'https://images.unsplash.com/photo-1512813195386-6cf811ad4b1d?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // Africa
    [
        'destination' => 'Cairo',
        'pays' => 'Égypte',
        'filename' => 'cairo.jpg',
        'url' => 'https://images.unsplash.com/photo-1539766246-5dd422ffb0e8?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Marrakech',
        'pays' => 'Maroc',
        'filename' => 'marrakech.jpg',
        'url' => 'https://images.unsplash.com/photo-1512836286243-8b7c1e0e2b2b?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Cape Town',
        'pays' => 'Afrique du Sud',
        'filename' => 'cape_town.jpg',
        'url' => 'https://images.unsplash.com/photo-1511734619285-79a06e0c6f6?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    
    // Oceania
    [
        'destination' => 'Sydney',
        'pays' => 'Australie',
        'filename' => 'sydney.jpg',
        'url' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d2?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Melbourne',
        'pays' => 'Australie',
        'filename' => 'melbourne.jpg',
        'url' => 'https://images.unsplash.com/photo-1545044846-351f5c0c0b5c?auto=format&fit=crop&w=800&h=600&q=80'
    ],
    [
        'destination' => 'Auckland',
        'pays' => 'Nouvelle-Zélande',
        'filename' => 'auckland.jpg',
        'url' => 'https://images.unsplash.com/photo-1507699622108-4be3abd695ad?auto=format&fit=crop&w=800&h=600&q=80'
    ]
];

$destinationDir = 'public/images/destinations/';
$createdCount = 0;

foreach ($sampleImages as $image) {
    $filePath = $destinationDir . $image['filename'];
    
    // Download image
    $imageData = file_get_contents($image['url']);
    
    if ($imageData !== false) {
        if (file_put_contents($filePath, $imageData)) {
            echo "✓ Created: {$image['filename']} ({$image['destination']}, {$image['pays']})\n";
            $createdCount++;
        } else {
            echo "✗ Failed to save: {$image['filename']}\n";
        }
    } else {
        echo "✗ Failed to download: {$image['url']}\n";
    }
}

echo "\nSummary:\n";
echo "- Created $createdCount sample images in $destinationDir\n";
echo "- These images will be used for the quiz questions\n";

// Update map_destinations table with these image paths
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "\nUpdating map_destinations table with image paths...\n";
    
    foreach ($sampleImages as $image) {
        // Find matching destination in map_destinations
        $stmt = $pdo->prepare("UPDATE map_destinations SET image_path = :image_path WHERE city = :city LIMIT 1");
        $stmt->execute([
            ':image_path' => $image['filename'],
            ':city' => $image['destination']
        ]);
        
        echo "✓ Updated: {$image['destination']} -> {$image['filename']}\n";
    }
    
    echo "\n✅ Database updated with image paths!\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
