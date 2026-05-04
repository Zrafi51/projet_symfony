<?php
// Simple test to debug the quiz API issue

// Test database connection first
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection: OK\n";
    
    // Test if quiz_session table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz_session'");
    if ($stmt->rowCount() > 0) {
        echo "quiz_session table: EXISTS\n";
    } else {
        echo "quiz_session table: MISSING\n";
    }
    
    // Test if proctor_log table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'proctor_log'");
    if ($stmt->rowCount() > 0) {
        echo "proctor_log table: EXISTS\n";
    } else {
        echo "proctor_log table: MISSING\n";
    }
    
    // Test if quiz_answer table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'quiz_answer'");
    if ($stmt->rowCount() > 0) {
        echo "quiz_answer table: EXISTS\n";
    } else {
        echo "quiz_answer table: MISSING\n";
    }
    
    // Test if voyage table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM voyage WHERE disponible = 1");
    $result = $stmt->fetch();
    echo "Available voyages: " . $result['count'] . "\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

// Test if we can create a simple QuizSession object
try {
    require_once 'src/Entity/QuizSession.php';
    echo "QuizSession class: LOADED\n";
    
    $quizSession = new \App\Entity\QuizSession();
    echo "QuizSession object: CREATED\n";
    
    $quizSession->setSessionId('test_session_123');
    $quizSession->setScore(0);
    $quizSession->setTotalQuestions(5);
    $quizSession->setStartedAt(new \DateTime());
    
    echo "QuizSession setters: WORKING\n";
    
} catch(Exception $e) {
    echo "QuizSession error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
