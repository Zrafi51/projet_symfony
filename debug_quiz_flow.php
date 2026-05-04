<?php
// Debug quiz flow issue

echo "=== Debugging Quiz Flow Issue ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=voyage", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Checking quiz session flow...\n";
    
    // Simulate a quiz session
    $sessionId = 'debug_' . time();
    
    echo "Session ID: $sessionId\n\n";
    
    // Start quiz session
    $stmt = $pdo->prepare("INSERT INTO quiz_session (session_id, score, total_questions, started_at) VALUES (:session_id, 0, 5, NOW())");
    $stmt->execute([':session_id' => $sessionId]);
    echo "✓ Started quiz session\n";
    
    // Get first question
    $stmt = $pdo->query("SELECT idVoyage, destination, pays FROM voyage WHERE disponible = 1 ORDER BY RAND() LIMIT 1");
    $question = $stmt->fetch();
    
    if ($question) {
        echo "✓ Got question: {$question['destination']} (ID: {$question['idVoyage']})\n";
        
        // Simulate incorrect answer
        $wrongAnswer = "Wrong Answer";
        $stmt = $pdo->prepare("INSERT INTO quiz_answer (session_id, voyage_id, user_answer, is_correct, created_at) VALUES (:session_id, :voyage_id, :user_answer, 0, NOW())");
        $stmt->execute([
            ':session_id' => $sessionId,
            ':voyage_id' => $question['idVoyage'],
            ':user_answer' => $wrongAnswer
        ]);
        echo "✓ Submitted incorrect answer: '$wrongAnswer'\n";
        
        // Check if next question is available
        $answeredStmt = $pdo->prepare("SELECT voyage_id FROM quiz_answer WHERE session_id = :session_id");
        $answeredStmt->execute([':session_id' => $sessionId]);
        $answeredIds = $answeredStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voyage WHERE disponible = 1 AND idVoyage NOT IN (:answeredIds)");
        $stmt->execute([':answeredIds' => $answeredIds ?: [0]]);
        $availableCount = $stmt->fetch()['count'];
        
        echo "✓ Available questions remaining: $availableCount\n";
        
        if ($availableCount > 0) {
            echo "✓ Next question should be available\n";
            
            // Get next question to verify
            $stmt = $pdo->prepare("SELECT idVoyage, destination FROM voyage WHERE disponible = 1 AND idVoyage NOT IN (:answeredIds) ORDER BY RAND() LIMIT 1");
            $stmt->execute([':answeredIds' => $answeredIds ?: [0]]);
            $nextQuestion = $stmt->fetch();
            
            if ($nextQuestion) {
                echo "✓ Next question: {$nextQuestion['destination']} (ID: {$nextQuestion['idVoyage']})\n";
            } else {
                echo "✗ Failed to get next question\n";
            }
        } else {
            echo "✗ No more questions available\n";
        }
        
    } else {
        echo "✗ No questions available\n";
    }
    
    echo "\n2. Checking JavaScript flow issue...\n";
    echo "The issue is likely in the submitAnswer() method:\n";
    echo "- After incorrect answer, submit button is disabled\n";
    echo "- Submit button is hidden (display: none)\n";
    echo "- Next button is shown (display: inline-block)\n";
    echo "- But the next button click should call loadNextQuestion()\n";
    echo "- loadNextQuestion() should reset the submit button state\n\n";
    
    echo "3. Checking loadNextQuestion() logic...\n";
    echo "In loadNextQuestion():\n";
    echo "- Line 231: answer-input.disabled = false ✓\n";
    echo "- Line 232: submit-btn.style.display = 'inline-block' ✓\n";
    echo "- Line 233: next-btn.style.display = 'none' ✓\n";
    echo "- This should reset the UI properly\n\n";
    
    echo "4. Possible issues:\n";
    echo "- submitAnswer() line 325: submit-btn.disabled = true (but not disabled in loadNextQuestion)\n";
    echo "- submitAnswer() line 356: submit-btn.style.display = 'none' (correctly reset in loadNextQuestion)\n";
    echo "- The disabled state might not be reset\n\n";
    
    echo "=== Fix Needed ===\n";
    echo "In loadNextQuestion(), need to reset: submit-btn.disabled = false\n";
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nProcess completed.\n";
?>
