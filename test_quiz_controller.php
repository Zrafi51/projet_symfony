<?php
// Test the QuizController directly to identify the issue

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VoyageRepository;
use App\Entity\QuizSession;
use App\Entity\ProctorLog;
use App\Entity\QuizAnswer;

echo "Testing QuizController components...\n";

// Test 1: Check if we can create a simple QuizSession
try {
    $quizSession = new QuizSession();
    $quizSession->setSessionId('test_session_' . time());
    $quizSession->setScore(0);
    $quizSession->setTotalQuestions(5);
    $quizSession->setStartedAt(new \DateTime());
    
    echo "✓ QuizSession creation: SUCCESS\n";
    echo "  - Session ID: " . $quizSession->getSessionId() . "\n";
    echo "  - Score: " . $quizSession->getScore() . "\n";
    echo "  - Total Questions: " . $quizSession->getTotalQuestions() . "\n";
    
} catch (Exception $e) {
    echo "✗ QuizSession creation: FAILED - " . $e->getMessage() . "\n";
}

// Test 2: Check if we can create a ProctorLog
try {
    $proctorLog = new ProctorLog();
    $proctorLog->setSessionId('test_session');
    $proctorLog->setVoyageId(1);
    $proctorLog->setViolationType('TEST_VIOLATION');
    $proctorLog->setCreatedAt(new \DateTime());
    
    echo "✓ ProctorLog creation: SUCCESS\n";
    
} catch (Exception $e) {
    echo "✗ ProctorLog creation: FAILED - " . $e->getMessage() . "\n";
}

// Test 3: Check if we can create a QuizAnswer
try {
    $quizAnswer = new QuizAnswer();
    $quizAnswer->setSessionId('test_session');
    $quizAnswer->setVoyageId(1);
    $quizAnswer->setUserAnswer('Test Answer');
    $quizAnswer->setIsCorrect(true);
    $quizAnswer->setCreatedAt(new \DateTime());
    
    echo "✓ QuizAnswer creation: SUCCESS\n";
    
} catch (Exception $e) {
    echo "✗ QuizAnswer creation: FAILED - " . $e->getMessage() . "\n";
}

// Test 4: Check Voyage entity
try {
    require_once 'src/Entity/Voyage.php';
    $voyage = new \App\Entity\Voyage();
    $voyage->setIdVoyage(1);
    $voyage->setDestination('Test Destination');
    $voyage->setPays('Test Country');
    $voyage->setDisponible(true);
    
    echo "✓ Voyage entity: SUCCESS\n";
    echo "  - Destination: " . $voyage->getDestination() . "\n";
    echo "  - Pays: " . $voyage->getPays() . "\n";
    echo "  - Disponible: " . ($voyage->getDisponible() ? 'true' : 'false') . "\n";
    
} catch (Exception $e) {
    echo "✗ Voyage entity: FAILED - " . $e->getMessage() . "\n";
}

// Test 5: Simulate the startQuiz method logic
try {
    $sessionId = uniqid('quiz_', true);
    $quizSession = new QuizSession();
    $quizSession->setSessionId($sessionId);
    $quizSession->setScore(0);
    $quizSession->setTotalQuestions(5);
    $quizSession->setStartedAt(new \DateTime());
    
    $response = [
        'session_id' => $sessionId,
        'total_questions' => 5
    ];
    
    echo "✓ startQuiz logic simulation: SUCCESS\n";
    echo "  - Response: " . json_encode($response) . "\n";
    
} catch (Exception $e) {
    echo "✗ startQuiz logic simulation: FAILED - " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
