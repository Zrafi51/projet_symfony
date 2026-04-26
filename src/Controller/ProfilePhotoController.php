<?php

namespace App\Controller;

use App\Service\ProfilePhotoStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilePhotoController extends AbstractController
{
    public function __construct(private readonly ProfilePhotoStorageService $profilePhotoStorageService)
    {
    }

    #[Route('/media/profile-photo/{reference}', name: 'app_profile_photo', methods: ['GET'])]
    public function show(string $reference, Request $request): BinaryFileResponse
    {
        $authUser = $request->getSession()->get('auth_user');
        if (!is_array($authUser) || trim((string) ($authUser['email'] ?? '')) === '') {
            throw $this->createNotFoundException();
        }

        $photoReference = $this->profilePhotoStorageService->decodePhotoReference($reference);
        $absolutePath = $this->profilePhotoStorageService->resolveReadablePath($photoReference);
        if ($absolutePath === null) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($absolutePath));

        return $response;
    }
}
