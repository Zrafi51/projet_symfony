<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();
        $session->remove('auth_user');

        $response = $this->redirectToRoute('app_login');

        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
