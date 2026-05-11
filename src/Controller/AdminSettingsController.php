<?php

namespace App\Controller;

use App\Repository\AdminProfilePreferenceRepository;
use App\Repository\UserRepository;
use App\Security\LegacyPasswordHasher;
use App\Service\LegacyRememberMeService;
use App\Service\ProfilePhotoStorageService;
use App\Service\UserNotificationService;
use App\Util\UploadedFileMimeTypeGuesser;
use App\Validation\LegacyValidator;
use App\ValueObject\NotificationPreferences;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminSettingsController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly UserRepository $userRepository,
        private readonly AdminProfilePreferenceRepository $adminProfilePreferenceRepository,
        private readonly UserNotificationService $notificationService,
        private readonly LegacyRememberMeService $rememberMeService,
        private readonly ProfilePhotoStorageService $profilePhotoStorageService,
        private readonly LegacyPasswordHasher $passwordHasher,
    ) {
    }

    #[Route('/admin/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        return $this->renderSettingsPage($request);
    }

    #[Route('/admin/settings/profile', name: 'app_admin_settings_profile', methods: ['POST'])]
    public function saveProfile(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $currentUser = $this->loadCurrentAdminRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte admin introuvable.');

            return $this->redirectToRoute('app_admin_dashboard');
        }

        $profileForm = $this->buildProfileFormFromRequest($request, $currentUser);
        $errorMessage = $this->validateProfileForm($profileForm, $currentUser);
        if ($errorMessage !== null) {
            return $this->renderSettingsPage($request, $profileForm, null, $errorMessage);
        }

        $existingUser = $this->userRepository->getByEmail($profileForm['email']);
        if ($existingUser !== null && (int) ($existingUser['id'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
            return $this->renderSettingsPage($request, $profileForm, null, 'Cet email est deja utilise par un autre compte.');
        }

        $previousEmail = (string) ($currentUser['email'] ?? '');
        $emailChanged = strcasecmp($previousEmail, $profileForm['email']) !== 0;
        $payload = [
            'prenom' => $profileForm['prenom'],
            'nom' => $profileForm['nom'],
            'email' => $profileForm['email'],
            'telephone' => $profileForm['telephone'],
            'adresse' => $profileForm['adresse'],
            'date_naissance' => $profileForm['date_naissance'] !== '' ? $profileForm['date_naissance'] : null,
            'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
            'photo_url' => (string) ($currentUser['photo_url'] ?? ''),
            'is_active' => (bool) ($currentUser['is_active'] ?? true),
        ];

        if (!$this->userRepository->updateProfileByEmail($previousEmail, $payload)) {
            return $this->renderSettingsPage($request, $profileForm, null, 'Impossible de sauvegarder le profil admin pour le moment.');
        }

        if ($emailChanged) {
            $this->adminProfilePreferenceRepository->migrateEmail($previousEmail, $profileForm['email']);
            $this->notificationService->migrateUserEmail($previousEmail, $profileForm['email'], (string) ($currentUser['role'] ?? 'ADMIN'));
        }

        if (
            !$this->adminProfilePreferenceRepository->save($profileForm['email'], [
                'job_title' => $profileForm['job_title'],
                'company' => $profileForm['company'],
                'bio' => $profileForm['bio'],
            ])
        ) {
            return $this->renderSettingsPage($request, $profileForm, null, 'Impossible de sauvegarder le profil admin pour le moment.');
        }

        if ($emailChanged) {
            $this->rememberMeService->updateRememberedIdentity($request, $previousEmail, [
                'email' => $profileForm['email'],
                'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
            ]);
        }

        $updatedUser = $this->userRepository->getByEmail($profileForm['email']) ?? $currentUser;
        $this->syncSessionUser($request, $updatedUser);
        $this->notificationService->notifyUser(
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            'PROFILE',
            'Profil admin mis a jour',
            $this->buildDisplayName(
                (string) ($updatedUser['prenom'] ?? ''),
                (string) ($updatedUser['nom'] ?? ''),
                (string) ($updatedUser['email'] ?? '')
            ).' a mis a jour son espace administrateur.'
        );

        $request->getSession()->getFlashBag()->add('success', 'Parametres admin sauvegardes avec succes.');

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/admin/settings/avatar', name: 'app_admin_settings_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $currentUser = $this->loadCurrentAdminRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte admin introuvable.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $file = $request->files->get('avatar');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$file->isValid()) {
            $request->getSession()->getFlashBag()->add('error', "Impossible de charger l'image selectionnee.");

            return $this->redirectToRoute('app_admin_settings');
        }

        $mimeType = UploadedFileMimeTypeGuesser::detect($file) ?? '';
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $request->getSession()->getFlashBag()->add('error', 'Le fichier selectionne doit etre une image.');

            return $this->redirectToRoute('app_admin_settings');
        }

        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            $request->getSession()->getFlashBag()->add('error', "L'image est trop lourde. Taille max: 5 Mo.");

            return $this->redirectToRoute('app_admin_settings');
        }

        try {
            $newPhotoPath = $this->profilePhotoStorageService->store($file, (string) ($currentUser['email'] ?? 'admin'));
        } catch (Throwable) {
            $request->getSession()->getFlashBag()->add('error', "Impossible d'enregistrer l'image selectionnee.");

            return $this->redirectToRoute('app_admin_settings');
        }

        $payload = [
            'prenom' => (string) ($currentUser['prenom'] ?? ''),
            'nom' => (string) ($currentUser['nom'] ?? ''),
            'email' => (string) ($currentUser['email'] ?? ''),
            'telephone' => (string) ($currentUser['telephone'] ?? ''),
            'adresse' => (string) ($currentUser['adresse'] ?? ''),
            'date_naissance' => $currentUser['date_naissance'] ?? null,
            'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
            'photo_url' => $newPhotoPath,
            'is_active' => (bool) ($currentUser['is_active'] ?? true),
        ];

        if (!$this->userRepository->updateProfileByEmail((string) ($currentUser['email'] ?? ''), $payload)) {
            $this->profilePhotoStorageService->delete($newPhotoPath);
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder le nouvel avatar.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $updatedUser = $this->userRepository->getByEmail((string) ($currentUser['email'] ?? '')) ?? [
            ...$currentUser,
            'photo_url' => $newPhotoPath,
        ];
        $this->syncSessionUser($request, $updatedUser);
        $this->notificationService->notifyUser(
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            'PROFILE',
            'Photo de profil admin mise a jour',
            $this->buildDisplayName(
                (string) ($updatedUser['prenom'] ?? ''),
                (string) ($updatedUser['nom'] ?? ''),
                (string) ($updatedUser['email'] ?? '')
            ).' a mis a jour son avatar administrateur.'
        );
        $request->getSession()->getFlashBag()->add('success', 'Avatar admin mis a jour.');

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/admin/settings/avatar/remove', name: 'app_admin_settings_avatar_remove', methods: ['POST'])]
    public function removeAvatar(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $currentUser = $this->loadCurrentAdminRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte admin introuvable.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $previousPhoto = (string) ($currentUser['photo_url'] ?? '');
        $payload = [
            'prenom' => (string) ($currentUser['prenom'] ?? ''),
            'nom' => (string) ($currentUser['nom'] ?? ''),
            'email' => (string) ($currentUser['email'] ?? ''),
            'telephone' => (string) ($currentUser['telephone'] ?? ''),
            'adresse' => (string) ($currentUser['adresse'] ?? ''),
            'date_naissance' => $currentUser['date_naissance'] ?? null,
            'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
            'photo_url' => null,
            'is_active' => (bool) ($currentUser['is_active'] ?? true),
        ];

        if (!$this->userRepository->updateProfileByEmail((string) ($currentUser['email'] ?? ''), $payload)) {
            $request->getSession()->getFlashBag()->add('error', "Impossible de supprimer l'avatar pour le moment.");

            return $this->redirectToRoute('app_admin_settings');
        }

        if ($this->profilePhotoStorageService->isManagedPhoto($previousPhoto)) {
            $this->profilePhotoStorageService->delete($previousPhoto);
        }

        $updatedUser = $this->userRepository->getByEmail((string) ($currentUser['email'] ?? '')) ?? [
            ...$currentUser,
            'photo_url' => '',
        ];
        $this->syncSessionUser($request, $updatedUser);
        $this->notificationService->notifyUser(
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            (string) ($updatedUser['email'] ?? ''),
            (string) ($updatedUser['role'] ?? 'ADMIN'),
            'PROFILE',
            'Avatar admin supprime',
            $this->buildDisplayName(
                (string) ($updatedUser['prenom'] ?? ''),
                (string) ($updatedUser['nom'] ?? ''),
                (string) ($updatedUser['email'] ?? '')
            ).' a retire son avatar administrateur.'
        );
        $request->getSession()->getFlashBag()->add('success', 'Avatar retire. Sauvegarde appliquee.');

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/admin/settings/password', name: 'app_admin_settings_password', methods: ['POST'])]
    public function changePassword(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $currentUser = $this->loadCurrentAdminRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte admin introuvable.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $currentPassword = trim((string) $request->request->get('current_password', ''));
        $newPassword = trim((string) $request->request->get('new_password', ''));
        $confirmPassword = trim((string) $request->request->get('confirm_password', ''));

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $request->getSession()->getFlashBag()->add('error', 'Remplissez les trois champs du mot de passe.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $storedPasswordHash = trim((string) ($currentUser['password'] ?? ''));
        $matchesPlainPassword = $this->passwordHasher->checkPassword($currentPassword, $storedPasswordHash);
        $matchesStoredHash = $storedPasswordHash !== '' && hash_equals($storedPasswordHash, trim($currentPassword));
        $newPasswordMatchesStored = $this->passwordHasher->checkPassword($newPassword, $storedPasswordHash);
        if (!$matchesPlainPassword && !$matchesStoredHash) {
            $request->getSession()->getFlashBag()->add('error', 'Le mot de passe actuel est incorrect.');

            return $this->redirectToRoute('app_admin_settings');
        }

        if (!LegacyValidator::isValidPassword($newPassword)) {
            $request->getSession()->getFlashBag()->add('error', 'Le nouveau mot de passe doit avoir au moins 6 caracteres.');

            return $this->redirectToRoute('app_admin_settings');
        }

        if ($newPassword !== $confirmPassword) {
            $request->getSession()->getFlashBag()->add('error', 'La confirmation ne correspond pas au nouveau mot de passe.');

            return $this->redirectToRoute('app_admin_settings');
        }

        if (
            $newPassword === $currentPassword
            || $newPasswordMatchesStored
            || ($storedPasswordHash !== '' && hash_equals($storedPasswordHash, $newPassword))
        ) {
            $request->getSession()->getFlashBag()->add('error', "Le nouveau mot de passe doit etre different de l'ancien.");

            return $this->redirectToRoute('app_admin_settings');
        }

        if (!$this->userRepository->updatePasswordDirect((string) ($currentUser['email'] ?? ''), $newPassword)) {
            $request->getSession()->getFlashBag()->add('error', 'La mise a jour du mot de passe a echoue.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $this->rememberMeService->updateRememberedPassword($request, (string) ($currentUser['email'] ?? ''), $newPassword);
        $this->notificationService->notifyUser(
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'ADMIN'),
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'ADMIN'),
            'PASSWORD',
            'Mot de passe admin modifie',
            $this->buildDisplayName(
                (string) ($currentUser['prenom'] ?? ''),
                (string) ($currentUser['nom'] ?? ''),
                (string) ($currentUser['email'] ?? '')
            ).' a change son mot de passe admin.'
        );

        $request->getSession()->getFlashBag()->add('success', 'Mot de passe admin mis a jour.');

        return $this->redirectToRoute('app_admin_settings');
    }

    #[Route('/admin/settings/notifications', name: 'app_admin_settings_notifications', methods: ['POST'])]
    public function saveNotifications(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $currentUser = $this->loadCurrentAdminRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte admin introuvable.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $preferences = new NotificationPreferences(
            $request->request->getBoolean('notify_security'),
            $request->request->getBoolean('notify_booking'),
            $request->request->getBoolean('notify_forum'),
            $request->request->getBoolean('notify_offers'),
        );

        if (!$this->notificationService->savePreferences((string) ($currentUser['email'] ?? ''), (string) ($currentUser['role'] ?? 'ADMIN'), $preferences)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder les notifications admin.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $this->notificationService->notifyUser(
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'ADMIN'),
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'ADMIN'),
            'PREFERENCES',
            'Preferences notifications admin mises a jour',
            $this->buildDisplayName(
                (string) ($currentUser['prenom'] ?? ''),
                (string) ($currentUser['nom'] ?? ''),
                (string) ($currentUser['email'] ?? '')
            ).' a modifie ses preferences de notification admin.'
        );

        $request->getSession()->getFlashBag()->add('success', 'Preferences de notifications synchronisees.');

        return $this->redirectToRoute('app_admin_settings');
    }

    private function renderSettingsPage(
        Request $request,
        ?array $profileFormOverride = null,
        ?string $statusMessage = null,
        ?string $errorMessage = null
    ): Response {
        $authUser = $this->getAuthenticatedUser($request);
        $databaseError = null;
        $currentUser = null;
        $profileForm = [
            'prenom' => '',
            'nom' => '',
            'email' => '',
            'telephone' => '',
            'adresse' => '',
            'date_naissance' => '',
            'role' => 'ADMIN',
            'photo_url' => '',
            'job_title' => 'Super Admin',
            'company' => 'EasyTravel',
            'bio' => '',
        ];
        $notificationForm = [
            'notify_security' => true,
            'notify_booking' => true,
            'notify_forum' => true,
            'notify_offers' => false,
        ];

        try {
            $currentUser = $this->loadCurrentAdminRecord($request);
            if ($currentUser !== null) {
                $extraPreferences = $this->adminProfilePreferenceRepository->findByEmail((string) ($currentUser['email'] ?? ''));
                $notificationPreferences = $this->notificationService->getPreferences(
                    (string) ($currentUser['email'] ?? ''),
                    (string) ($currentUser['role'] ?? 'ADMIN')
                );

                $profileForm = [
                    'prenom' => (string) ($currentUser['prenom'] ?? ''),
                    'nom' => (string) ($currentUser['nom'] ?? ''),
                    'email' => (string) ($currentUser['email'] ?? ''),
                    'telephone' => (string) ($currentUser['telephone'] ?? ''),
                    'adresse' => (string) ($currentUser['adresse'] ?? ''),
                    'date_naissance' => (string) ($currentUser['date_naissance'] ?? ''),
                    'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
                    'photo_url' => (string) ($currentUser['photo_url'] ?? ''),
                    'job_title' => (string) ($extraPreferences['job_title'] ?? 'Super Admin'),
                    'company' => (string) ($extraPreferences['company'] ?? 'EasyTravel'),
                    'bio' => (string) ($extraPreferences['bio'] ?? ''),
                ];

                $notificationForm = [
                    'notify_security' => $notificationPreferences->security(),
                    'notify_booking' => $notificationPreferences->booking(),
                    'notify_forum' => $notificationPreferences->forum(),
                    'notify_offers' => $notificationPreferences->offers(),
                ];
            }
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        if ($profileFormOverride !== null) {
            $profileForm = array_merge($profileForm, $profileFormOverride);
        }

        $displayName = $this->buildDisplayName(
            (string) ($profileForm['prenom'] ?? ''),
            (string) ($profileForm['nom'] ?? ''),
            (string) ($profileForm['email'] ?? '')
        );
        $photoDisplayUrl = $this->resolvePhotoUrlForView((string) ($profileForm['photo_url'] ?? ''));
        $roleChipLabel = strtoupper((string) ($profileForm['role'] ?? 'ADMIN'));
        $jobTitle = trim((string) ($profileForm['job_title'] ?? 'Super Admin'));
        $roleSummaryLabel = $roleChipLabel.($jobTitle !== '' ? ' - '.$jobTitle : '');
        $summaryModeLabel = ($databaseError !== null || !$this->userRepository->isDatabaseAvailable())
            ? 'Synchronisation indisponible'
            : 'Base synchronisee';
        $statusMessage ??= $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info');
        $errorMessage ??= $this->consumeFlash($request, 'error');

        return new Response($this->renderer->render('admin/settings', [
            'layout' => 'admin-layout',
            'pageClass' => 'admin-settings-body',
            'title' => 'Admin Settings',
            'databaseError' => $databaseError,
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'currentUser' => $currentUser ?? $authUser,
            'profileForm' => $profileForm,
            'photoDisplayUrl' => $photoDisplayUrl,
            'notificationForm' => $notificationForm,
            'displayName' => $displayName,
            'roleChipLabel' => $roleChipLabel,
            'roleSummaryLabel' => $roleSummaryLabel,
            'summaryModeLabel' => $summaryModeLabel,
            'rememberedSession' => $this->rememberMeService->hasRememberedDevice(
                $request,
                (string) ($profileForm['email'] ?? '')
            ),
        ]));
    }

    private function buildProfileFormFromRequest(Request $request, array $currentUser): array
    {
        return [
            'prenom' => trim((string) $request->request->get('prenom', '')),
            'nom' => trim((string) $request->request->get('nom', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'telephone' => trim((string) $request->request->get('telephone', '')),
            'adresse' => trim((string) $request->request->get('adresse', '')),
            'date_naissance' => trim((string) $request->request->get('date_naissance', '')),
            'role' => (string) ($currentUser['role'] ?? 'ADMIN'),
            'photo_url' => (string) ($currentUser['photo_url'] ?? ''),
            'job_title' => trim((string) $request->request->get('job_title', '')) ?: 'Super Admin',
            'company' => trim((string) $request->request->get('company', '')) ?: 'EasyTravel',
            'bio' => trim((string) $request->request->get('bio', '')),
        ];
    }

    private function validateProfileForm(array $profileForm, array $currentUser): ?string
    {
        if ($profileForm['prenom'] === '' && $profileForm['nom'] === '') {
            return 'Ajoutez au moins un prenom ou un nom pour le profil admin.';
        }

        if (!LegacyValidator::isValidEmail($profileForm['email'])) {
            return 'Veuillez saisir un email admin valide.';
        }

        if (!LegacyValidator::isValidPhoneOrBlank($profileForm['telephone'])) {
            return 'Veuillez saisir un telephone admin valide.';
        }

        if (!LegacyValidator::isValidBirthDate($profileForm['date_naissance'])) {
            return 'La date de naissance ne peut pas etre dans le futur.';
        }

        if (!LegacyValidator::hasMaxLength($profileForm['adresse'], 500) || !LegacyValidator::hasMaxLength($profileForm['bio'], 1000)) {
            return 'Adresse ou bio trop longue.';
        }

        if (!$this->isAdminRole((string) ($currentUser['role'] ?? 'USER'))) {
            return 'Cette page est reservee aux administrateurs.';
        }

        return null;
    }

    private function loadCurrentAdminRecord(Request $request): ?array
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            return null;
        }

        $email = (string) ($authUser['email'] ?? '');
        if ($email === '') {
            return null;
        }

        return $this->userRepository->getByEmail($email);
    }

    private function syncSessionUser(Request $request, array $user): void
    {
        $request->getSession()->set('auth_user', [
            'display_name' => $this->buildDisplayName(
                (string) ($user['prenom'] ?? ''),
                (string) ($user['nom'] ?? ''),
                (string) ($user['email'] ?? '')
            ),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? 'ADMIN'),
            'photo_url' => (string) ($user['photo_url'] ?? ''),
        ]);
    }

    private function resolvePhotoUrlForView(string $photoPath): string
    {
        $photoPath = trim($photoPath);
        if ($photoPath === '') {
            return '';
        }

        if (
            str_starts_with($photoPath, '/')
            || str_starts_with($photoPath, 'http://')
            || str_starts_with($photoPath, 'https://')
            || str_starts_with($photoPath, 'data:')
        ) {
            return $photoPath;
        }

        if ($this->profilePhotoStorageService->resolveReadablePath($photoPath) === null) {
            return '';
        }

        return $this->generateUrl('app_profile_photo', [
            'reference' => $this->profilePhotoStorageService->encodePhotoReference($photoPath),
        ]);
    }

    private function ensureAdminAccess(Request $request): ?RedirectResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            return $this->redirectToRoute('app_dashboard');
        }

        return null;
    }

    private function getAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) && trim((string) ($user['email'] ?? '')) !== '' ? $user : null;
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true);
    }

    private function buildDisplayName(string $firstName, string $lastName, string $fallbackEmail): string
    {
        $displayName = trim(trim($firstName).' '.trim($lastName));

        return $displayName !== '' ? $displayName : ($fallbackEmail !== '' ? $fallbackEmail : 'Admin User');
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
