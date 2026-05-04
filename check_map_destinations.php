<?php
// Check the structure of map_destinations table

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n\n";
    
    // Check if map_destinations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'map_destinations'");
    if ($stmt->rowCount() > 0) {
        echo "map_destinations table: EXISTS\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE map_destinations");
        echo "\nTable structure:\n";
        while ($row = $stmt->fetch()) {
            echo "- {$row['Field']} ({$row['Type']})\n";
        }
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM map_destinations LIMIT 5");
        echo "\nSample data:\n";
        while ($row = $stmt->fetch()) {
            echo "Row: ";
            foreach ($row as $key => $value) {
                if ($key !== '0') { // Skip numeric index
                    echo "$key=$value ";
                }
            }
            echo "\n";
        }
        
    } else {
        echo "map_destinations table: MISSING\n";
        
        // List all tables to see alternatives
        $stmt = $pdo->query("SHOW TABLES");
        echo "\nAvailable tables:\n";
        while ($row = $stmt->fetch()) {
            echo "- " . $row[0] . "\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
