<?php

namespace App\Controller;

use App\Repository\PromptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PromptApiController extends AbstractController
{
    public function __construct(private readonly PromptRepository $promptRepository)
    {
    }

    #[Route('/api/prompts/active', name: 'app_api_prompts_active', methods: ['GET'])]
    public function active(): JsonResponse
    {
        return $this->json($this->promptRepository->getActivePromptPayload());
    }
}
