<?php

namespace App\Controller;

use App\Entity\MonumentScan;
use App\Entity\User;
use App\Service\MonumentRecognizer;
use App\Repository\MonumentScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/monument')]
class MonumentController extends AbstractController
{
    private MonumentRecognizer $monumentRecognizer;
    private EntityManagerInterface $entityManager;
    private MonumentScanRepository $monumentScanRepository;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private string $uploadsDir;

    public function __construct(
        MonumentRecognizer $monumentRecognizer,
        EntityManagerInterface $entityManager,
        MonumentScanRepository $monumentScanRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ) {
        $this->monumentRecognizer = $monumentRecognizer;
        $this->entityManager = $entityManager;
        $this->monumentScanRepository = $monumentScanRepository;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->uploadsDir = dirname(__DIR__, 2) . '/public/uploads/monuments';
    }

    /**
     * Show monument scan page
     */
    #[Route('/scan', name: 'app_monument_scan', methods: ['GET'])]
    public function scan(Request $request): Response
    {
        $currentUser = $this->getAuthenticatedUser($request);
        
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        // For now, return empty recent scans since we're not using the database relationship
        $recentScans = [];

        return $this->render('monument/scan.html.twig', [
            'currentUser' => $currentUser,
            'recentScans' => $recentScans,
        ]);
    }

    /**
     * Process monument scan upload
     */
    #[Route('/scan', name: 'app_monument_scan_process', methods: ['POST'])]
    public function processScan(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Monument scan process started');
            $currentUser = $this->getAuthenticatedUser($request);
            
            if (!$currentUser) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            /** @var UploadedFile $file */
            $file = $request->files->get('monument_image');
            
            if (!$file) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No file uploaded'
                ], 400);
            }

            // Validate file
            $violations = $this->validator->validate($file, new Image([
                'maxSize' => '10M',
                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or WebP)'
            ]));

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                
                return new JsonResponse([
                    'success' => false,
                    'error' => implode(', ', $errors)
                ], 400);
            }

            $this->logger->info('Starting monument scan processing', [
                'user_id' => $currentUser['id'],
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            
            // Ensure uploads directory exists and is writable
            if (!is_dir($this->uploadsDir)) {
                if (!mkdir($this->uploadsDir, 0755, true)) {
                    throw new \Exception('Cannot create uploads directory: ' . $this->uploadsDir);
                }
            }
            
            if (!is_writable($this->uploadsDir)) {
                throw new \Exception('Uploads directory is not writable: ' . $this->uploadsDir);
            }
            
            // Save file with error handling
            try {
                $file->move($this->uploadsDir, $filename);
                $imagePath = $this->uploadsDir . '/' . $filename;
                
                $this->logger->info('File saved successfully', [
                    'filename' => $filename,
                    'image_path' => $imagePath,
                    'uploads_dir' => $this->uploadsDir
                ]);
            } catch (\Exception $e) {
                $this->logger->error('File upload failed: ' . $e->getMessage(), [
                    'filename' => $filename,
                    'uploads_dir' => $this->uploadsDir,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Failed to save uploaded file: ' . $e->getMessage());
            }

            $this->logger->info('File saved successfully', [
                'filename' => $filename,
                'image_path' => $imagePath
            ]);

            // Create monument scan record
            $monumentScan = new MonumentScan($currentUser['id']);
            $monumentScan->setImageFilename($filename);
            $monumentScan->setScanStatus('pending');
            
            $this->entityManager->persist($monumentScan);
            $this->entityManager->flush();

            $this->logger->info('Monument scan record created', [
                'scan_id' => $monumentScan->getId()
            ]);

            // Recognize monument
            $result = $this->monumentRecognizer->recognize($imagePath);
            
            $this->logger->info('Monument recognition result', [
                'result' => $result,
                'scan_id' => $monumentScan->getId()
            ]);
            
            if ($result['success']) {
                $monumentScan->setMonumentName($result['name']);
                $monumentScan->setCity($result['city']);
                $monumentScan->setCountry($result['country']);
                $monumentScan->setDescription($result['description']);
                $monumentScan->setConfidenceScore($result['confidence']);
                $monumentScan->setApiProvider($result['provider'] ?? 'unknown');
                $monumentScan->setScanStatus('completed');
                
                $this->entityManager->flush();
                
                $this->logger->info('Monument scan completed successfully', [
                    'scan_id' => $monumentScan->getId(),
                    'monument_name' => $monumentScan->getMonumentName()
                ]);
                
                return new JsonResponse([
                    'success' => true,
                    'monumentScan' => [
                        'id' => $monumentScan->getId(),
                        'monumentName' => $monumentScan->getMonumentName(),
                        'city' => $monumentScan->getCity(),
                        'country' => $monumentScan->getCountry(),
                        'description' => $monumentScan->getDescription(),
                        'confidence' => $monumentScan->getConfidenceScore(),
                        'provider' => $monumentScan->getApiProvider(),
                        'imagePath' => $monumentScan->getImagePath(),
                        'createdAt' => $monumentScan->getCreatedAt()->format('Y-m-d H:i:s'),
                        'addedToRequest' => $monumentScan->isAddedToRequest()
                    ]
                ], 200, [], JsonResponse::DEFAULT_ENCODING_OPTIONS);
            } else {
                $monumentScan->setScanStatus('failed');
                $this->entityManager->flush();
                
                $this->logger->warning('Monument recognition failed', [
                    'scan_id' => $monumentScan->getId(),
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                
                return new JsonResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Unable to recognize monument'
                ], 400);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Monument scan failed: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $currentUser['id'] ?? 'unknown'
            ]);
            
            return new JsonResponse([
                'success' => false,
                'error' => 'An error occurred while processing your image. Please try again.'
            ], 500, [], JsonResponse::DEFAULT_ENCODING_OPTIONS);
        }
    }

    /**
     * Add monument scan to travel request
     */
    #[Route('/add-to-request/{id}', name: 'app_monument_add_to_request', methods: ['POST'])]
    public function addToRequest(int $id, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        
        if (!$currentUser) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        $monumentScan = $this->monumentScanRepository->find($id);
        
        if (!$monumentScan) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Monument scan not found'
            ], 404);
        }

        if ($monumentScan->getUserId() !== $currentUser['id']) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Access denied'
            ], 403);
        }

        if (!$monumentScan->isSuccessful()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Cannot add failed scan to request'
            ], 400);
        }

        try {
            $monumentScan->setAddedToRequest(true);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Monument added to your travel request',
                'redirectUrl' => '/billing?destination=' . urlencode($monumentScan->getCity() . ', ' . $monumentScan->getCountry())
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to add monument to request: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to add monument to request'
            ], 500);
        }
    }

    /**
     * Get user's monument scans
     */
    #[Route('/my-scans', name: 'app_monument_my_scans', methods: ['GET'])]
    public function myScans(Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        
        if (!$currentUser) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        $scans = $this->monumentScanRepository->findBy(
            ['userId' => $currentUser['id']],
            ['createdAt' => 'DESC']
        );

        $scansData = [];
        foreach ($scans as $scan) {
            $scansData[] = [
                'id' => $scan->getId(),
                'monumentName' => $scan->getMonumentName(),
                'city' => $scan->getCity(),
                'country' => $scan->getCountry(),
                'description' => $scan->getDescription(),
                'confidence' => $scan->getConfidenceScore(),
                'provider' => $scan->getApiProvider(),
                'imagePath' => $scan->getImagePath(),
                'createdAt' => $scan->getCreatedAt()->format('Y-m-d H:i:s'),
                'addedToRequest' => $scan->isAddedToRequest(),
                'scanStatus' => $scan->getScanStatus()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'scans' => $scansData
        ]);
    }

    /**
     * Delete monument scan
     */
    #[Route('/delete/{id}', name: 'app_monument_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $currentUser = $this->getAuthenticatedUser($request);
        
        if (!$currentUser) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        $monumentScan = $this->monumentScanRepository->find($id);
        
        if (!$monumentScan) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Monument scan not found'
            ], 404);
        }

        if ($monumentScan->getUserId() !== $currentUser['id']) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Access denied'
            ], 403);
        }

        try {
            // Delete image file
            $imagePath = $monumentScan->getImageAbsolutePath();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            // Delete database record
            $this->entityManager->remove($monumentScan);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Monument scan deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete monument scan: ' . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to delete monument scan'
            ], 500);
        }
    }

    /**
     * Get authenticated user from session
     */
    private function getAuthenticatedUser(Request $request): ?array
    {
        try {
            $session = $request->getSession();
            if (!$session) {
                return null;
            }
            
            $user = $session->get('auth_user');
            return is_array($user) ? $user : null;
        } catch (\Exception $e) {
            $this->logger->error('Session access error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate unique filename for uploaded image
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        return uniqid('monument_', true) . '.' . $file->guessExtension();
    }

    /**
     * Ensure uploads directory exists
     */
    private function ensureUploadsDirectory(): void
    {
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }
}
