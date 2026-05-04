<?php
// Database connection parameters
$host = '127.0.0.1';
$username = 'root';
$password = ''; // Update with your MySQL password
$database = 'voyage';

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Create quiz_session table
    $sql1 = "CREATE TABLE IF NOT EXISTS `quiz_session` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `session_id` varchar(255) NOT NULL,
      `score` int(11) NOT NULL,
      `total_questions` int(11) NOT NULL,
      `started_at` datetime NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `UNIQ_quiz_session_session_id` (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql1);
    echo "Table quiz_session created successfully\n";
    
    // Create proctor_log table
    $sql2 = "CREATE TABLE IF NOT EXISTS `proctor_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `session_id` varchar(255) NOT NULL,
      `voyage_id` int(11) DEFAULT NULL,
      `violation_type` varchar(100) NOT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql2);
    echo "Table proctor_log created successfully\n";
    
    // Create quiz_answer table
    $sql3 = "CREATE TABLE IF NOT EXISTS `quiz_answer` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `session_id` varchar(255) NOT NULL,
      `voyage_id` int(11) NOT NULL,
      `user_answer` varchar(255) NOT NULL,
      `is_correct` tinyint(1) NOT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql3);
    echo "Table quiz_answer created successfully\n";
    
    echo "All quiz tables created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
