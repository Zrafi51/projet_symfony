<?php

namespace App\Controller;

use App\Repository\NewsletterRepository;
use App\Repository\SupportRepository;
use App\Repository\UserRepository;
use App\Service\UserNotificationService;
use App\Validation\LegacyValidator;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly SupportRepository $supportRepository,
        private readonly UserRepository $userRepository,
        private readonly UserNotificationService $notificationService,
        private readonly NewsletterRepository $newsletterRepository,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->resolveAuthenticatedUser($request);
        $formData = [
            'name' => (string) ($currentUser['display_name'] ?? ''),
            'email' => (string) ($currentUser['email'] ?? ''),
            'subject' => '',
            'phone' => (string) ($currentUser['telephone'] ?? ''),
            'message' => '',
        ];
        $errorMessage = $this->consumeFlash($request, 'error');

        if ($request->isMethod('POST') && $request->request->has('newsletter_email')) {
            $newsletterEmail = trim((string) $request->request->get('newsletter_email', ''));

            if (!LegacyValidator::isValidEmail($newsletterEmail)) {
                $request->getSession()->getFlashBag()->add('newsletter_error', 'Veuillez saisir un email valide pour la newsletter.');
            } else {
                try {
                    $this->newsletterRepository->subscribe($newsletterEmail);
                    $request->getSession()->getFlashBag()->add('newsletter_success', 'Merci ! Vous etes abonne a notre newsletter.');
                } catch (RuntimeException $exception) {
                    $request->getSession()->getFlashBag()->add('newsletter_error', $exception->getMessage());
                }
            }

            return $this->redirectToRoute('app_contact');
        }

        if ($request->isMethod('POST')) {
            $formData = [
                'name' => trim((string) $request->request->get('name', $formData['name'])),
                'email' => trim((string) $request->request->get('email', $formData['email'])),
                'subject' => trim((string) $request->request->get('subject', '')),
                'phone' => trim((string) $request->request->get('phone', $formData['phone'])),
                'message' => trim((string) $request->request->get('message', '')),
            ];

            if ($currentUser !== null) {
                $formData['name'] = (string) ($currentUser['display_name'] ?? $formData['name']);
                $formData['email'] = (string) ($currentUser['email'] ?? $formData['email']);
            }

            $errorMessage = $this->validateFormData($formData, $currentUser !== null);
            if ($errorMessage === null) {
                try {
                    if ($currentUser !== null && (int) ($currentUser['id'] ?? 0) > 0) {
                        $saved = $this->supportRepository->createReclamation(
                            (int) $currentUser['id'],
                            $formData['subject'],
                            $formData['message']
                        );

                        if ($saved) {
                            $this->notificationService->notifyAdmins(
                                (string) ($currentUser['email'] ?? ''),
                                (string) ($currentUser['role'] ?? 'USER'),
                                'ACCOUNT',
                                'Nouvelle reclamation client',
                                (string) ($currentUser['display_name'] ?? 'Client').' a envoye une reclamation : '.$formData['subject']
                            );

                            $request->getSession()->getFlashBag()->add(
                                'success',
                                'Votre reclamation a ete envoyee. Vous pourrez suivre la reponse dans votre dashboard.'
                            );

                            return $this->redirectToRoute('app_contact');
                        }

                        $errorMessage = "Impossible d'envoyer votre reclamation pour le moment.";
                    } else {
                        $saved = $this->supportRepository->submitGuestContact(
                            $formData['name'],
                            $formData['email'],
                            $formData['phone'],
                            $formData['subject'],
                            $formData['message']
                        );

                        if ($saved) {
                            $request->getSession()->getFlashBag()->add(
                                'success',
                                'Merci, votre message a bien ete envoye. Notre equipe reviendra vers vous rapidement.'
                            );

                            return $this->redirectToRoute('app_contact');
                        }

                        $errorMessage = "Impossible d'envoyer votre message pour le moment.";
                    }
                } catch (RuntimeException $exception) {
                    $errorMessage = $exception->getMessage();
                }
            }
        }

        return new Response($this->renderer->render('contact/index', [
            'title' => 'Contact - EasyTravel',
            'currentUser' => $currentUser,
            'formData' => $formData,
            'pageBodyClass' => 'contact-page-body',
            'showImmersiveAlerts' => false,
            'statusMessage' => $this->consumeFlash($request, 'success'),
            'errorMessage' => $errorMessage,
            'footerNewsletterAction' => '/contact',
            'footerNewsletterStatusMessage' => $this->consumeFlash($request, 'newsletter_success'),
            'footerNewsletterErrorMessage' => $this->consumeFlash($request, 'newsletter_error'),
            'footerCtaLabel' => 'Commencer mon voyage &#8594;',
            'footerContactEmail' => 'contact@easytravel.tn',
            'footerContactPhone' => '+216 71 123 456',
            'footerContactLocation' => 'Tunis, Monastir',
            'footerBrandText' => "Createur d'experiences de voyage uniques avec l'intelligence artificielle depuis 2024.",
        ]));
    }

    private function resolveAuthenticatedUser(Request $request): ?array
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!is_array($sessionUser) || trim((string) ($sessionUser['email'] ?? '')) === '') {
            return null;
        }

        $user = $this->userRepository->getByEmail((string) ($sessionUser['email'] ?? ''));

        return $user ?? $sessionUser;
    }

    private function validateFormData(array $formData, bool $authenticated): ?string
    {
        if (!$authenticated && !LegacyValidator::isValidName((string) ($formData['name'] ?? ''))) {
            return 'Veuillez saisir un nom valide.';
        }

        if (!LegacyValidator::isValidEmail((string) ($formData['email'] ?? ''))) {
            return 'Veuillez saisir un email valide.';
        }

        $subject = trim((string) ($formData['subject'] ?? ''));
        if ($subject === '' || mb_strlen($subject) < 3 || mb_strlen($subject) > 255) {
            return 'Le sujet doit contenir entre 3 et 255 caracteres.';
        }

        if (!LegacyValidator::isValidPhoneOrBlank((string) ($formData['phone'] ?? ''))) {
            return 'Le numero de telephone est invalide.';
        }

        $message = trim((string) ($formData['message'] ?? ''));
        if ($message === '' || mb_strlen($message) < 10) {
            return 'Le message doit contenir au moins 10 caracteres.';
        }

        if (!LegacyValidator::hasMaxLength($message, 3000)) {
            return 'Le message est trop long.';
        }

        return null;
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
