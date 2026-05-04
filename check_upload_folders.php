<?php
// Check where user uploaded images are stored

echo "=== Checking Upload Folders ===\n\n";

// Check common upload directories
$uploadDirs = [
    'public/uploads',
    'public/images/uploads', 
    'public/uploads/images',
    'var/uploads',
    'uploads',
    'assets/images',
    'public/assets/images'
];

echo "1. Checking common upload directories:\n";
foreach ($uploadDirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ Found: $dir\n";
        $files = scandir($dir);
        $imageFiles = array_filter($files, function($file) {
            return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($dir . '/' . $file);
        });
        
        if (!empty($imageFiles)) {
            echo "  Images: " . implode(', ', array_slice($imageFiles, 0, 5)) . (count($imageFiles) > 5 ? '...' : '') . "\n";
        }
    } else {
        echo "- Not found: $dir\n";
    }
}

echo "\n2. Checking Symfony configuration:\n";

// Check if there's a config file with upload settings
$configFiles = [
    'config/packages/framework.yaml',
    'config/packages/twig.yaml',
    'config/services.yaml',
    '.env'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        echo "✓ Found config: $file\n";
        $content = file_get_contents($file);
        if (preg_match('/upload/i', $content)) {
            echo "  Contains upload settings\n";
        }
    } else {
        echo "- Not found: $file\n";
    }
}

echo "\n3. Checking public directory structure:\n";
$publicDir = 'public';
if (is_dir($publicDir)) {
    echo "✓ Public directory exists\n";
    
    // Recursively scan public directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $foundUploads = [];
    foreach ($iterator as $file) {
        if ($file->isDir() && preg_match('/upload/i', $file->getFilename())) {
            $foundUploads[] = $file->getPathname();
        }
    }
    
    if (!empty($foundUploads)) {
        foreach ($foundUploads as $dir) {
            echo "✓ Found upload dir: $dir\n";
            $files = scandir($dir);
            $imageFiles = array_filter($files, function($file) use ($dir) {
                return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($dir . '/' . $file);
            });
            
            if (!empty($imageFiles)) {
                echo "  Images: " . implode(', ', array_slice($imageFiles, 0, 3)) . "\n";
            }
        }
    } else {
        echo "- No upload directories found in public/\n";
    }
}

echo "\n4. Checking for User entity or upload handling:\n";

// Check if there's a User entity that might have upload functionality
if (file_exists('src/Entity/User.php')) {
    echo "✓ Found User entity\n";
    $userContent = file_get_contents('src/Entity/User.php');
    if (preg_match('/upload/i', $userContent)) {
        echo "  User entity contains upload-related code\n";
    }
}

// Check controllers for upload handling
$controllerDir = 'src/Controller';
if (is_dir($controllerDir)) {
    echo "✓ Found Controller directory\n";
    
    $files = scandir($controllerDir);
    foreach ($files as $file) {
        if (preg_match('/\.php$/', $file) && $file !== '.' && $file !== '..') {
            $content = file_get_contents($controllerDir . '/' . $file);
            if (preg_match('/upload/i', $content)) {
                echo "✓ Found upload handling in: $file\n";
            }
        }
    }
}

echo "\n5. Current quiz images directory:\n";
$quizImagesDir = 'public/images/destinations';
if (is_dir($quizImagesDir)) {
    echo "✓ Quiz images directory: $quizImagesDir\n";
    $files = scandir($quizImagesDir);
    $imageFiles = array_filter($files, function($file) {
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && !is_dir($quizImagesDir . '/' . $file);
    });
    
    echo "Current quiz images (" . count($imageFiles) . "):\n";
    foreach ($imageFiles as $file) {
        $filePath = $quizImagesDir . '/' . $file;
        $size = filesize($filePath);
        echo "- $file (" . number_format($size) . " bytes)\n";
    }
}

echo "\n=== Summary ===\n";
echo "Quiz images are stored in: public/images/destinations/\n";
echo "If you have user uploads, they might be in:\n";
echo "- public/uploads/\n";
echo "- public/images/uploads/\n";
echo "- var/uploads/\n";

echo "\nTo find user uploaded images, check these directories.\n";
?>
