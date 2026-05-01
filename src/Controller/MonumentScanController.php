<?php

namespace App\Controller;

use App\Entity\MonumentScan;
use App\Service\MonumentRecognizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/monuments')]
class MonumentScanController extends AbstractController
{
    private string $uploadsDir;

    public function __construct(
        private MonumentRecognizer $monumentRecognizer,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
        $this->uploadsDir = __DIR__ . '/../../public/uploads/monuments';
        
        // Ensure uploads directory exists
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }

    #[Route('/scan', name: 'app_monument_scan_new', methods: ['GET', 'POST'])]
    public function scan(Request $request): Response
    {
        // Get authenticated user using the existing system
        $currentUser = $this->getAuthenticatedUser($request);
        
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }
        $result = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('monument_image');
            
            if (!$file) {
                $error = 'Please select a photo to upload.';
            } else {
                try {
                    // Validate file
                    $violations = $this->validator->validate($file, [
                        new \Symfony\Component\Validator\Constraints\Image([
                            'maxSize' => '10M',
                            'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                            'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, or WebP).'
                        ])
                    ]);

                    if (count($violations) > 0) {
                        $error = $violations[0]->getMessage();
                    } else {
                        // Generate unique filename
                        $filename = uniqid('monument_', true) . '.' . $file->guessExtension();
                        
                        // Save file
                        $file->move($this->uploadsDir, $filename);
                        $imagePath = $this->uploadsDir . '/' . $filename;

                        // Recognize monument
                        $result = $this->monumentRecognizer->recognize($imagePath);
                        
                        if ($result['success']) {
                            // Create monument scan record
                            $monumentScan = new MonumentScan($currentUser['id']);
                            $monumentScan->setMonumentName($result['name']);
                            $monumentScan->setCity($result['city']);
                            $monumentScan->setCountry($result['country']);
                            $monumentScan->setDescription($result['description']);
                            $monumentScan->setImageFilename($filename);
                            $monumentScan->setConfidenceScore($result['confidence']);
                            $monumentScan->setApiProvider($result['provider']);
                            $monumentScan->setScanStatus('completed');
                            
                            $this->entityManager->persist($monumentScan);
                            $this->entityManager->flush();

                            // Add image filename to result for display
                            $result['imageFilename'] = $filename;
                        } else {
                            $error = $result['error'] ?? 'Failed to recognize monument. Please try with a clearer photo.';
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->error('Monument scan failed: ' . $e->getMessage());
                    $error = 'An error occurred while processing your image. Please try again.';
                }
            }
        }

        return $this->render('monument/scan.html.twig', [
            'currentUser' => $currentUser,
            'recentScans' => [],
            'result' => $result,
            'error' => $error,
        ]);
    }

    private function generateUniqueFilename($file): string
    {
        return uniqid('monument_', true) . '.' . $file->guessExtension();
    }

    /**
      * Get authenticated user from request
      */
    private function getAuthenticatedUser(Request $request): ?array
    {
        return $request->getSession()->get('auth_user');
    }
}
