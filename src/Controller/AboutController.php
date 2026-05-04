<?php

namespace App\Controller;

use App\Repository\NewsletterRepository;
use App\Validation\LegacyValidator;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly NewsletterRepository $newsletterRepository,
    ) {
    }

    #[Route('/about', name: 'app_about', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('newsletter_email', ''));

            if (!LegacyValidator::isValidEmail($email)) {
                $request->getSession()->getFlashBag()->add('error', 'Veuillez saisir un email valide pour la newsletter.');
            } else {
                try {
                    $this->newsletterRepository->subscribe($email);
                    $request->getSession()->getFlashBag()->add('success', 'Merci, votre email a bien ete enregistre pour la newsletter EasyTravel.');
                } catch (RuntimeException $exception) {
                    $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
                }
            }

            return $this->redirectToRoute('app_about');
        }

        return new Response($this->renderer->render('about/index', [
            'title' => 'A propos - EasyTravel',
            'statusMessage' => $this->consumeFlash($request, 'success'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ]));
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
