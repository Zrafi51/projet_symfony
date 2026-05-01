<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        return $this->json([
            'message' => 'Test endpoint working',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    #[Route('/test/post', name: 'app_test_post', methods: ['POST'])]
    public function testPost(Request $request): JsonResponse
    {
        return $this->json([
            'message' => 'POST test working',
            'files' => $request->files->all(),
            'session_user' => $request->getSession()->get('auth_user')
        ]);
    }
}
