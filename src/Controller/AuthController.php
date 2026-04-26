<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\LegacyPasswordHasher;
use App\Service\LegacyRememberMeService;
use App\Service\UserNotificationService;
use App\Validation\LegacyValidator;
use App\View\PhpTemplateRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly UserRepository $userRepository,
        private readonly LegacyPasswordHasher $passwordHasher,
        private readonly LegacyRememberMeService $rememberMeService,
        private readonly UserNotificationService $notificationService,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has('auth_user')) {
            $currentUser = $session->get('auth_user', []);

            return $this->redirectToRoute($this->resolveDashboardRoute((string) ($currentUser['role'] ?? 'USER')));
        }

        $form = [
            'email' => '',
            'password' => '',
            'remember_me' => false,
        ];
        $errorMessage = null;
        $fieldErrors = [];
        $rememberMeAvailable = $this->rememberMeService->isDatabaseAvailable();
        $statusMessage = $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info');

        if ($request->isMethod('GET') && $rememberMeAvailable) {
            $remembered = $this->rememberMeService->loadRememberedCredentials($request);
            if ($remembered !== null) {
                $form['email'] = (string) ($remembered['email'] ?? '');
                $form['password'] = (string) ($remembered['password'] ?? '');
                $form['remember_me'] = true;
            }
        }

        if ($request->isMethod('POST')) {
            $form = [
                'email' => trim((string) $request->request->get('email', '')),
                'password' => (string) $request->request->get('password', ''),
                'remember_me' => $request->request->getBoolean('remember_me'),
            ];

            $validationError = $this->validateLoginForm($form);
            if ($validationError !== null) {
                $errorMessage = $validationError['message'];
                $fieldErrors = $validationError['fields'];
            }

            if ($errorMessage === null) {
                if (!$this->userRepository->isDatabaseAvailable()) {
                    $errorMessage = 'Base de donnees indisponible';
                } else {
                    $user = $this->userRepository->getByEmail($form['email']);
                    if ($user === null || !$this->passwordHasher->checkPassword($form['password'], (string) ($user['password'] ?? ''))) {
                        $errorMessage = 'Email ou mot de passe incorrect';
                    } elseif (($user['is_active'] ?? false) !== true) {
                        $errorMessage = "Ce compte est suspendu ou desactive par l'administration.";
                    } elseif (($user['is_pending_validation'] ?? false) === true) {
                        $errorMessage = 'Votre compte est en attente de validation par un administrateur.';
                    } else {
                        $session->set('auth_user', [
                            'id' => (int) ($user['id'] ?? 0),
                            'display_name' => (string) ($user['display_name'] ?? ''),
                            'prenom' => (string) ($user['prenom'] ?? ''),
                            'nom' => (string) ($user['nom'] ?? ''),
                            'email' => (string) ($user['email'] ?? ''),
                            'telephone' => (string) ($user['telephone'] ?? ''),
                            'adresse' => (string) ($user['adresse'] ?? ''),
                            'date_naissance' => $user['date_naissance'] ?? null,
                            'role' => (string) ($user['role'] ?? 'USER'),
                            'photo_url' => (string) ($user['photo_url'] ?? ''),
                            'is_active' => (bool) ($user['is_active'] ?? true),
                        ]);

                        $response = $this->redirectToRoute($this->resolveDashboardRoute((string) ($user['role'] ?? 'USER')));
                        if ($rememberMeAvailable && $form['remember_me']) {
                            $this->rememberMeService->remember($request, $response, $user, $form['password']);
                        } elseif ($rememberMeAvailable) {
                            $this->rememberMeService->clear($request, $response);
                        }

                        return $response;
                    }
                }
            }
        }

        return new Response($this->renderer->render('auth/login', [
            'layout' => 'auth-layout',
            'title' => 'Connexion',
            'form' => $form,
            'errorMessage' => $errorMessage,
            'fieldErrors' => $fieldErrors,
            'rememberMeAvailable' => $rememberMeAvailable,
            'statusMessage' => $statusMessage,
        ]));
    }

    #[Route('/sign-up', name: 'app_sign_up', methods: ['GET', 'POST'])]
    public function signUp(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has('auth_user')) {
            $currentUser = $session->get('auth_user', []);

            return $this->redirectToRoute($this->resolveDashboardRoute((string) ($currentUser['role'] ?? 'USER')));
        }

        $form = [
            'prenom' => '',
            'nom' => '',
            'email' => '',
            'password' => '',
            'confirm_password' => '',
        ];
        $errorMessage = null;
        $fieldErrors = [];

        if ($request->isMethod('POST')) {
            $form = [
                'prenom' => trim((string) $request->request->get('prenom', '')),
                'nom' => trim((string) $request->request->get('nom', '')),
                'email' => trim((string) $request->request->get('email', '')),
                'password' => (string) $request->request->get('password', ''),
                'confirm_password' => (string) $request->request->get('confirm_password', ''),
            ];

            $validationError = $this->validateSignUpForm($form);
            if ($validationError !== null) {
                $errorMessage = $validationError['message'];
                $fieldErrors = $validationError['fields'];
            }

            if ($errorMessage === null) {
                if (!$this->userRepository->isDatabaseAvailable()) {
                    $errorMessage = 'Base de donnees indisponible';
                } elseif ($this->userRepository->emailExists($form['email'])) {
                    $errorMessage = 'Cet email est deja utilise';
                } else {
                    $registered = $this->userRepository->register([
                        'prenom' => $form['prenom'],
                        'nom' => $form['nom'],
                        'email' => $form['email'],
                        'password' => $form['password'],
                        'role' => 'USER',
                        'is_active' => true,
                        'is_validated' => false,
                        'date_inscription' => date('Y-m-d'),
                    ]);

                    if (!$registered) {
                        $errorMessage = "Erreur lors de l'inscription";
                    } else {
                        if ($this->notificationService->isDatabaseAvailable()) {
                            $this->notificationService->notifyUser(
                                $form['email'],
                                'USER',
                                'system@easytravel.local',
                                'SYSTEM',
                                'ACCOUNT',
                                'Compte cree en attente de validation',
                                'Bienvenue '.$form['prenom'].", votre compte EasyTravel a ete cree et attend maintenant la validation d'un administrateur."
                            );
                            $this->notificationService->notifyAdmins(
                                $form['email'],
                                'USER',
                                'ACCOUNT',
                                'Nouveau compte client a valider',
                                $form['prenom'].' '.$form['nom']." a cree un nouveau compte client et attend une validation admin."
                            );
                        }

                        $request->getSession()->getFlashBag()->add(
                            'success',
                            "Inscription envoyee. Un administrateur doit valider votre compte avant la connexion."
                        );

                        return $this->redirectToRoute('app_login');
                    }
                }
            }
        }

        return new Response($this->renderer->render('auth/signup', [
            'layout' => 'auth-layout',
            'title' => 'Inscription',
            'form' => $form,
            'errorMessage' => $errorMessage,
            'fieldErrors' => $fieldErrors,
        ]));
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET'])]
    public function forgotPassword(Request $request): RedirectResponse
    {
        $request->getSession()->getFlashBag()->add('info', 'Fonctionnalite a venir...');

        return $this->redirectToRoute('app_login');
    }

    private function validateLoginForm(array $form): ?array
    {
        if ($form['email'] === '' || $form['password'] === '') {
            return $this->buildValidationError('Veuillez remplir tous les champs', ['email', 'password']);
        }

        if (!LegacyValidator::isValidEmail($form['email'])) {
            return $this->buildValidationError('Veuillez saisir un email valide', ['email']);
        }

        return null;
    }

    private function validateSignUpForm(array $form): ?array
    {
        if (
            $form['prenom'] === ''
            || $form['nom'] === ''
            || $form['email'] === ''
            || $form['password'] === ''
            || $form['confirm_password'] === ''
        ) {
            return $this->buildValidationError(
                'Veuillez remplir tous les champs',
                ['prenom', 'nom', 'email', 'password', 'confirm_password']
            );
        }

        if (!LegacyValidator::isValidName($form['prenom'])) {
            return $this->buildValidationError("Le prenom n'est pas valide", ['prenom']);
        }

        if (!LegacyValidator::isValidName($form['nom'])) {
            return $this->buildValidationError("Le nom n'est pas valide", ['nom']);
        }

        if (!LegacyValidator::isValidEmail($form['email'])) {
            return $this->buildValidationError('Email invalide', ['email']);
        }

        if (!LegacyValidator::isValidPassword($form['password'])) {
            return $this->buildValidationError('Le mot de passe doit contenir au moins 6 caracteres', ['password']);
        }

        if ($form['password'] !== $form['confirm_password']) {
            return $this->buildValidationError('Les mots de passe ne correspondent pas', ['confirm_password']);
        }

        return null;
    }

    private function resolveDashboardRoute(string $role): string
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true)
            ? 'app_admin_dashboard'
            : 'app_dashboard';
    }

    private function buildValidationError(string $message, array $fields): array
    {
        return [
            'message' => $message,
            'fields' => $fields,
        ];
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
