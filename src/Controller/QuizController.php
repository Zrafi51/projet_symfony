<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VoyageRepository;
use App\Repository\QuizImageRepository;
use App\Entity\QuizSession;
use App\Entity\ProctorLog;
use App\Entity\QuizAnswer;
use App\Entity\QuizImage;

class QuizController extends AbstractController
{
    private $entityManager;
    private $voyageRepository;
    private $quizImageRepository;

    public function __construct(EntityManagerInterface $entityManager, VoyageRepository $voyageRepository, QuizImageRepository $quizImageRepository)
    {
        $this->entityManager = $entityManager;
        $this->voyageRepository = $voyageRepository;
        $this->quizImageRepository = $quizImageRepository;
    }

    /**
     * @Route("/quiz", name="quiz_index")
     */
    public function index(): Response
    {
        return $this->render('quiz/index.html.twig');
    }

    /**
     * @Route("/api/quiz/question", name="quiz_question", methods={"GET"})
     */
    public function getQuestion(Request $request): JsonResponse
    {
        $sessionId = $request->query->get('session_id');
        
        if (!$sessionId) {
            return new JsonResponse(['error' => 'Session ID required'], 400);
        }

        // Get already answered voyage IDs for this session
        $answeredVoyages = $this->entityManager
            ->getRepository(QuizAnswer::class)
            ->createQueryBuilder('qa')
            ->select('qa.voyageId')
            ->where('qa.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getScalarResult();

        $answeredIds = array_map('current', $answeredVoyages);

        // Get random available voyage
        $availableVoyages = $this->voyageRepository
            ->createQueryBuilder('v')
            ->where('v.disponible = 1')
            ->andWhere('v.idVoyage NOT IN (:answeredIds)')
            ->setParameter('answeredIds', $answeredIds ?: [0])
            ->getQuery()
            ->getResult();
        
        if (empty($availableVoyages)) {
            return new JsonResponse(['error' => 'No more questions available'], 404);
        }
        
        $voyage = $availableVoyages[array_rand($availableVoyages)];

        if (!$voyage) {
            return new JsonResponse(['error' => 'No more questions available'], 404);
        }

        // Select image directly from upload folder based on voyage
        $imageUrl = null;
        
        // First try to match by voyage ID
        $quizImage = $this->quizImageRepository->findByVoyageId($voyage->getIdVoyage());
        if ($quizImage && $quizImage->getImageFilename()) {
            // Direct path to upload folder
            $imagePath = '/uploads/quiz_img/' . $quizImage->getImageFilename();
            $fullPath = 'public' . $imagePath;
            
            if (file_exists($fullPath)) {
                $imageUrl = $imagePath;
            } else {
                // Default image if not found
                $imageUrl = '/images/destinations/default.jpg';
            }
        } else {
            // Try to match by destination if voyage ID match fails
            $quizImage = $this->quizImageRepository->findByDestination($voyage->getDestination());
            if ($quizImage && $quizImage->getImageFilename()) {
                // Direct path to upload folder
                $imagePath = '/uploads/quiz_img/' . $quizImage->getImageFilename();
                $fullPath = 'public' . $imagePath;
                
                if (file_exists($fullPath)) {
                    $imageUrl = $imagePath;
                } else {
                    // Default image if not found
                    $imageUrl = '/images/destinations/default.jpg';
                }
            } else {
                // Default image if no match found
                $imageUrl = '/images/destinations/default.jpg';
            }
        }

        return new JsonResponse([
            'idVoyage' => $voyage->getIdVoyage(),
            'destination' => $voyage->getDestination(),
            'pays' => $voyage->getPays(),
            'imageUrl' => $imageUrl
        ]);
    }

    /**
     * @Route("/api/quiz/check", name="quiz_check", methods={"POST"})
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $sessionId = $data['session_id'] ?? null;
        $voyageId = $data['voyage_id'] ?? null;
        $userAnswer = $data['user_answer'] ?? null;

        if (!$sessionId || !$voyageId || !$userAnswer) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // Get the correct answer
        $voyage = $this->voyageRepository->find($voyageId);
        if (!$voyage) {
            return new JsonResponse(['error' => 'Voyage not found'], 404);
        }

        $correctAnswer = $voyage->getDestination();
        $isCorrect = strtolower(trim($userAnswer)) === strtolower(trim($correctAnswer));

        // Save the answer
        $quizAnswer = new QuizAnswer();
        $quizAnswer->setSessionId($sessionId);
        $quizAnswer->setVoyageId($voyageId);
        $quizAnswer->setUserAnswer($userAnswer);
        $quizAnswer->setIsCorrect($isCorrect);
        $quizAnswer->setCreatedAt(new \DateTime());

        $this->entityManager->persist($quizAnswer);
        $this->entityManager->flush();

        // Update session score
        $this->updateSessionScore($sessionId);

        return new JsonResponse([
            'correct' => $isCorrect,
            'correct_answer' => $correctAnswer
        ]);
    }

    /**
     * @Route("/api/proctoring/log", name="proctoring_log", methods={"POST"})
     */
    public function logProctoringViolation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $sessionId = $data['session_id'] ?? null;
        $voyageId = $data['voyage_id'] ?? null;
        $violationType = $data['violation_type'] ?? null;

        if (!$sessionId || !$violationType) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        $proctorLog = new ProctorLog();
        $proctorLog->setSessionId($sessionId);
        $proctorLog->setVoyageId($voyageId);
        $proctorLog->setViolationType($violationType);
        $proctorLog->setCreatedAt(new \DateTime());

        $this->entityManager->persist($proctorLog);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/api/quiz/start", name="quiz_start", methods={"POST"})
     */
    public function startQuiz(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $totalQuestions = $data['total_questions'] ?? 5;

        $sessionId = uniqid('quiz_', true);

        $quizSession = new QuizSession();
        $quizSession->setSessionId($sessionId);
        $quizSession->setScore(0);
        $quizSession->setTotalQuestions($totalQuestions);
        $quizSession->setStartedAt(new \DateTime());

        $this->entityManager->persist($quizSession);
        $this->entityManager->flush();

        return new JsonResponse([
            'session_id' => $sessionId,
            'total_questions' => $totalQuestions
        ]);
    }

    /**
     * @Route("/api/quiz/results/{sessionId}", name="quiz_results", methods={"GET"})
     */
    public function getResults(string $sessionId): JsonResponse
    {
        $session = $this->entityManager
            ->getRepository(QuizSession::class)
            ->findOneBy(['sessionId' => $sessionId]);

        if (!$session) {
            return new JsonResponse(['error' => 'Session not found'], 404);
        }

        $violations = $this->entityManager
            ->getRepository(ProctorLog::class)
            ->findBy(['sessionId' => $sessionId]);

        $violationCount = count($violations);

        return new JsonResponse([
            'score' => $session->getScore(),
            'total_questions' => $session->getTotalQuestions(),
            'violations' => $violationCount,
            'started_at' => $session->getStartedAt()->format('Y-m-d H:i:s')
        ]);
    }

    private function updateSessionScore(string $sessionId): void
    {
        $session = $this->entityManager
            ->getRepository(QuizSession::class)
            ->findOneBy(['sessionId' => $sessionId]);

        if ($session) {
            $correctAnswers = $this->entityManager
                ->getRepository(QuizAnswer::class)
                ->count(['sessionId' => $sessionId, 'isCorrect' => true]);

            $session->setScore($correctAnswers);
            $this->entityManager->flush();
        }
    }
}
