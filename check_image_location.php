<?php
// Check where map_destinations images are currently stored

echo "Checking image locations...\n\n";

// Check common image directories
$directories = [
    'public/images',
    'public/assets/images', 
    'public/uploads',
    'public/media',
    'public/lo',
    'public/images/destinations',
    'public/images/voyages'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "✓ Directory exists: $dir\n";
        $files = scandir($dir);
        $imageFiles = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
        });
        if (!empty($imageFiles)) {
            echo "  - Contains " . count($imageFiles) . " image(s)\n";
            foreach (array_slice($imageFiles, 0, 3) as $file) {
                echo "    * $file\n";
            }
        }
    } else {
        echo "✗ Directory missing: $dir\n";
    }
}

echo "\nChecking for sample image files from map_destinations:\n";

// Check if we can find any of the image files from the sample data
$sampleImages = [
    '80281906250b49a80467292e998492eb.jpg',
    'da89f34fb5595d60358fcefe64fc6659.jpg', 
    '3fddde5acc7047afabbb1d9dd69301cd.jpg',
    'bac4bce325c9a10f6fb77f30682cc7fa.jpg',
    'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg'
];

foreach ($sampleImages as $image) {
    $found = false;
    foreach ($directories as $dir) {
        if (is_dir($dir) && file_exists($dir . '/' . $image)) {
            echo "✓ Found $image in $dir\n";
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "✗ $image not found\n";
    }
}

echo "\nTest completed.\n";
?>
