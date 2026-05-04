<?php
// Remove image_url column from quiz_images table

echo "=== Removing image_url Column ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Current quiz_images table structure:\n";
    $stmt = $pdo->query("DESCRIBE quiz_images");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n2. Removing image_url column...\n";
    
    // Remove image_url column
    $dropColumnSQL = "ALTER TABLE quiz_images DROP COLUMN image_url";
    $result = $pdo->exec($dropColumnSQL);
    
    if ($result !== false) {
        echo "✓ Removed image_url column from quiz_images table\n";
    } else {
        echo "✗ Failed to remove image_url column\n";
    }
    
    echo "\n3. Updated table structure:\n";
    $stmt = $pdo->query("DESCRIBE quiz_images");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n4. Current data in quiz_images (without image_url):\n";
    $stmt = $pdo->query("SELECT * FROM quiz_images WHERE is_active = 1 ORDER BY voyage_id");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($images as $image) {
        echo "Voyage ID {$image['voyage_id']}: {$image['destination']} ({$image['pays']})\n";
        echo "  Image Filename: {$image['image_filename']}\n";
        echo "  Is Active: " . ($image['is_active'] ? 'Yes' : 'No') . "\n";
        echo "  Created At: {$image['created_at']}\n\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n✅ image_url column removed!\n";
echo "Now quiz will use only local images from folder.\n";
?>
