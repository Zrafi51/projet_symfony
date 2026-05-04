<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\LegacyPasswordHasher;
use App\Service\LegacyRememberMeService;
use App\Service\ProfilePhotoStorageService;
use App\Service\UserNotificationService;
use App\Util\UploadedFileMimeTypeGuesser;
use App\Validation\LegacyValidator;
use App\ValueObject\NotificationPreferences;
use RuntimeException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserNotificationService $notificationService,
        private readonly LegacyRememberMeService $rememberMeService,
        private readonly ProfilePhotoStorageService $profilePhotoStorageService,
        private readonly LegacyPasswordHasher $passwordHasher,
    ) {
    }

    #[Route('/profile', name: 'profile_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Veuillez vous connecter pour acceder a votre profil.');

            return $this->redirectToRoute('app_login');
        }

        $databaseError = null;
        $currentUser = $authUser;
        $latestNotifications = [];
        $unreadNotificationCount = 0;

        try {
            $refreshedUser = $this->loadCurrentUserRecord($request);
            if ($refreshedUser !== null) {
                $currentUser = $refreshedUser;
                $this->syncSessionUser($request, $refreshedUser);
            }

            if ($this->notificationService->isDatabaseAvailable()) {
                $latestNotifications = $this->notificationService->getLatestNotifications((string) ($currentUser['email'] ?? ''), 8);
                $unreadNotificationCount = $this->notificationService->getUnreadCount((string) ($currentUser['email'] ?? ''));
            }
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        $notificationPreferences = $this->loadNotificationPreferences($request, $currentUser);
        $displayName = $this->buildDisplayName(
            (string) ($currentUser['prenom'] ?? ''),
            (string) ($currentUser['nom'] ?? ''),
            (string) ($currentUser['email'] ?? '')
        );

        return $this->render('profile/index.html.twig', [
            'currentUser' => $currentUser,
            'displayName' => $displayName,
            'photoDisplayUrl' => $this->resolvePhotoUrlForView((string) ($currentUser['photo_url'] ?? '')),
            'notificationForm' => $this->buildNotificationForm($notificationPreferences),
            'latestNotifications' => $latestNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
            'databaseError' => $databaseError,
            'modeLabel' => ($databaseError !== null || !$this->userRepository->isDatabaseAvailable())
                ? 'Synchronisation indisponible'
                : 'Base synchronisee',
            'rememberedSession' => $this->rememberMeService->hasRememberedDevice(
                $request,
                (string) ($currentUser['email'] ?? '')
            ),
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ]);
    }

    #[Route('/profile/update', name: 'profile_update', methods: ['POST'])]
    public function update(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Session expiree. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->userRepository->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', 'La mise a jour du profil necessite une base de donnees disponible.');

            return $this->redirectToRoute('profile_index');
        }

        $currentUser = $this->loadCurrentUserRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte client introuvable dans la base de donnees.');

            return $this->redirectToRoute('profile_index');
        }

        $profileForm = [
            'prenom' => trim((string) $request->request->get('prenom', '')),
            'nom' => trim((string) $request->request->get('nom', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'telephone' => trim((string) $request->request->get('telephone', '')),
            'adresse' => trim((string) $request->request->get('adresse', '')),
            'date_naissance' => trim((string) $request->request->get('date_naissance', '')),
            'role' => (string) ($currentUser['role'] ?? 'USER'),
            'photo_url' => (string) ($currentUser['photo_url'] ?? ''),
            'is_active' => (bool) ($currentUser['is_active'] ?? true),
        ];

        $validationError = $this->validateProfileForm($profileForm);
        if ($validationError !== null) {
            $request->getSession()->getFlashBag()->add('error', $validationError);

            return $this->redirectToRoute('profile_index');
        }

        $previousEmail = (string) ($currentUser['email'] ?? '');
        $emailChanged = strcasecmp($previousEmail, $profileForm['email']) !== 0;

        $existingUser = $this->userRepository->getByEmail($profileForm['email']);
        if ($existingUser !== null && (int) ($existingUser['id'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
            $request->getSession()->getFlashBag()->add('error', 'Cet email est deja utilise par un autre compte.');

            return $this->redirectToRoute('profile_index');
        }

        $payload = [
            ...$profileForm,
            'date_naissance' => $profileForm['date_naissance'] !== '' ? $profileForm['date_naissance'] : null,
        ];

        if (!$this->userRepository->updateProfileByEmail($previousEmail, $payload)) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder le profil pour le moment.');

            return $this->redirectToRoute('profile_index');
        }

        if ($emailChanged && $this->notificationService->isDatabaseAvailable()) {
            $this->notificationService->migrateUserEmail(
                $previousEmail,
                $profileForm['email'],
                (string) ($currentUser['role'] ?? 'USER')
            );
        }

        $updatedUser = $this->userRepository->getByEmail($profileForm['email']) ?? [...$currentUser, ...$profileForm];

        $this->syncSessionUser($request, $updatedUser);

        if ($emailChanged) {
            $this->rememberMeService->updateRememberedIdentity($request, $previousEmail, [
                'email' => (string) ($updatedUser['email'] ?? ''),
                'role' => (string) ($updatedUser['role'] ?? 'USER'),
            ]);
        }

        if ($this->notificationService->isDatabaseAvailable()) {
            $this->notificationService->notifyUser(
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                'PROFILE',
                'Profil client mis a jour',
                $this->buildDisplayName(
                    (string) ($updatedUser['prenom'] ?? ''),
                    (string) ($updatedUser['nom'] ?? ''),
                    (string) ($updatedUser['email'] ?? '')
                ).' a mis a jour ses informations de compte.'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'Informations du compte sauvegardees.');

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/profile/avatar', name: 'profile_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Session expiree. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->userRepository->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', "L'avatar necessite une base de donnees disponible.");

            return $this->redirectToRoute('profile_index');
        }

        $currentUser = $this->loadCurrentUserRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte client introuvable dans la base de donnees.');

            return $this->redirectToRoute('profile_index');
        }

        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $request->getSession()->getFlashBag()->add('error', "Impossible de charger l'image selectionnee.");

            return $this->redirectToRoute('profile_index');
        }

        $mimeType = UploadedFileMimeTypeGuesser::detect($file) ?? '';
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $request->getSession()->getFlashBag()->add('error', 'Le fichier selectionne doit etre une image PNG, JPG, WEBP ou GIF.');

            return $this->redirectToRoute('profile_index');
        }

        if ($file->getSize() !== null && $file->getSize() > 5 * 1024 * 1024) {
            $request->getSession()->getFlashBag()->add('error', "L'image est trop lourde. Taille max: 5 Mo.");

            return $this->redirectToRoute('profile_index');
        }

        try {
            $newPhotoPath = $this->profilePhotoStorageService->store($file, (string) ($currentUser['email'] ?? 'client'));
        } catch (Throwable) {
            $request->getSession()->getFlashBag()->add('error', "Impossible d'enregistrer l'image selectionnee.");

            return $this->redirectToRoute('profile_index');
        }

        $previousPhoto = (string) ($currentUser['photo_url'] ?? '');
        $payload = $this->buildProfilePayloadFromSource($currentUser, [
            'photo_url' => $newPhotoPath,
        ]);

        if (!$this->userRepository->updateProfileByEmail((string) ($currentUser['email'] ?? ''), $payload)) {
            $this->profilePhotoStorageService->delete($newPhotoPath);
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder le nouvel avatar.');

            return $this->redirectToRoute('profile_index');
        }

        $updatedUser = $this->userRepository->getByEmail((string) ($currentUser['email'] ?? '')) ?? [...$currentUser, 'photo_url' => $newPhotoPath];

        $this->syncSessionUser($request, $updatedUser);

        if (
            $previousPhoto !== ''
            && $previousPhoto !== $newPhotoPath
            && $this->profilePhotoStorageService->isManagedPhoto($previousPhoto)
        ) {
            $this->profilePhotoStorageService->delete($previousPhoto);
        }

        if ($this->notificationService->isDatabaseAvailable()) {
            $this->notificationService->notifyUser(
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                'PROFILE',
                'Photo de profil mise a jour',
                $this->buildDisplayName(
                    (string) ($updatedUser['prenom'] ?? ''),
                    (string) ($updatedUser['nom'] ?? ''),
                    (string) ($updatedUser['email'] ?? '')
                ).' a change sa photo de profil.'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'Photo de profil mise a jour.');

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/profile/avatar/remove', name: 'profile_avatar_remove', methods: ['POST'])]
    public function removeAvatar(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Session expiree. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->userRepository->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', "La suppression de l'avatar necessite une base de donnees disponible.");

            return $this->redirectToRoute('profile_index');
        }

        $currentUser = $this->loadCurrentUserRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte client introuvable dans la base de donnees.');

            return $this->redirectToRoute('profile_index');
        }

        $previousPhoto = (string) ($currentUser['photo_url'] ?? '');

        $payload = $this->buildProfilePayloadFromSource($currentUser, [
            'photo_url' => null,
        ]);

        if (!$this->userRepository->updateProfileByEmail((string) ($currentUser['email'] ?? ''), $payload)) {
            $request->getSession()->getFlashBag()->add('error', "Impossible de supprimer l'avatar pour le moment.");

            return $this->redirectToRoute('profile_index');
        }

        if ($this->profilePhotoStorageService->isManagedPhoto($previousPhoto)) {
            $this->profilePhotoStorageService->delete($previousPhoto);
        }

        $updatedUser = $this->userRepository->getByEmail((string) ($currentUser['email'] ?? '')) ?? [...$currentUser, 'photo_url' => ''];

        $this->syncSessionUser($request, $updatedUser);

        if ($this->notificationService->isDatabaseAvailable()) {
            $this->notificationService->notifyUser(
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                (string) ($updatedUser['email'] ?? ''),
                (string) ($updatedUser['role'] ?? 'USER'),
                'PROFILE',
                'Avatar supprime',
                $this->buildDisplayName(
                    (string) ($updatedUser['prenom'] ?? ''),
                    (string) ($updatedUser['nom'] ?? ''),
                    (string) ($updatedUser['email'] ?? '')
                ).' a retire sa photo de profil.'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'Avatar supprime.');

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/profile/change-password', name: 'profile_change_password', methods: ['POST'])]
    public function changePassword(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Session expiree. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->userRepository->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', 'Le changement de mot de passe necessite une base de donnees disponible.');

            return $this->redirectToRoute('profile_index');
        }

        $currentUser = $this->loadCurrentUserRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte client introuvable.');

            return $this->redirectToRoute('profile_index');
        }

        $currentPassword = trim((string) $request->request->get('current_password', ''));
        $newPassword = trim((string) $request->request->get('new_password', ''));
        $confirmPassword = trim((string) $request->request->get('confirm_password', ''));

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $request->getSession()->getFlashBag()->add('error', 'Remplissez les trois champs du mot de passe.');

            return $this->redirectToRoute('profile_index');
        }

        $storedPasswordHash = trim((string) ($currentUser['password'] ?? ''));
        $matchesPlainPassword = $this->passwordHasher->checkPassword($currentPassword, $storedPasswordHash);
        $matchesStoredHash = $storedPasswordHash !== '' && hash_equals($storedPasswordHash, $currentPassword);
        $newPasswordMatchesStored = $this->passwordHasher->checkPassword($newPassword, $storedPasswordHash);
        if (!$matchesPlainPassword && !$matchesStoredHash) {
            $request->getSession()->getFlashBag()->add('error', 'Le mot de passe actuel est incorrect.');

            return $this->redirectToRoute('profile_index');
        }

        if (!LegacyValidator::isValidPassword($newPassword)) {
            $request->getSession()->getFlashBag()->add('error', 'Le nouveau mot de passe doit avoir au moins 6 caracteres.');

            return $this->redirectToRoute('profile_index');
        }

        if ($newPassword !== $confirmPassword) {
            $request->getSession()->getFlashBag()->add('error', 'La confirmation ne correspond pas au nouveau mot de passe.');

            return $this->redirectToRoute('profile_index');
        }

        if (
            $newPassword === $currentPassword
            || $newPasswordMatchesStored
            || ($storedPasswordHash !== '' && hash_equals($storedPasswordHash, $newPassword))
        ) {
            $request->getSession()->getFlashBag()->add('error', "Le nouveau mot de passe doit etre different de l'ancien.");

            return $this->redirectToRoute('profile_index');
        }

        if (!$this->userRepository->updatePasswordDirect((string) ($currentUser['email'] ?? ''), $newPassword)) {
            $request->getSession()->getFlashBag()->add('error', 'La mise a jour du mot de passe a echoue.');

            return $this->redirectToRoute('profile_index');
        }

        $this->rememberMeService->updateRememberedPassword($request, (string) ($currentUser['email'] ?? ''), $newPassword);

        if ($this->notificationService->isDatabaseAvailable()) {
            $this->notificationService->notifyUser(
                (string) ($currentUser['email'] ?? ''),
                (string) ($currentUser['role'] ?? 'USER'),
                (string) ($currentUser['email'] ?? ''),
                (string) ($currentUser['role'] ?? 'USER'),
                'PASSWORD',
                'Mot de passe modifie',
                $this->buildDisplayName(
                    (string) ($currentUser['prenom'] ?? ''),
                    (string) ($currentUser['nom'] ?? ''),
                    (string) ($currentUser['email'] ?? '')
                ).' a change le mot de passe de son compte client.'
            );
        }

        $request->getSession()->getFlashBag()->add('success', 'Mot de passe mis a jour.');

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/profile/notifications', name: 'profile_notifications', methods: ['POST'])]
    public function updateNotifications(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Session expiree. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->userRepository->isDatabaseAvailable() || !$this->notificationService->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', 'Les notifications du compte necessitent une base de donnees disponible.');

            return $this->redirectToRoute('profile_index');
        }

        $currentUser = $this->loadCurrentUserRecord($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Compte client introuvable dans la base de donnees.');

            return $this->redirectToRoute('profile_index');
        }

        $preferences = new NotificationPreferences(
            $this->readBooleanRequestValue($request, ['notify_security', 'security_notifications']),
            $this->readBooleanRequestValue($request, ['notify_booking', 'booking_notifications']),
            $this->readBooleanRequestValue($request, ['notify_forum', 'forum_notifications']),
            $this->readBooleanRequestValue($request, ['notify_offers', 'offers_notifications']),
        );

        if (!$this->notificationService->savePreferences(
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'USER'),
            $preferences
        )) {
            $request->getSession()->getFlashBag()->add('error', 'Impossible de sauvegarder les preferences de notifications.');

            return $this->redirectToRoute('profile_index');
        }

        $this->notificationService->notifyUser(
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'USER'),
            (string) ($currentUser['email'] ?? ''),
            (string) ($currentUser['role'] ?? 'USER'),
            'PREFERENCES',
            'Preferences notifications mises a jour',
            $this->buildDisplayName(
                (string) ($currentUser['prenom'] ?? ''),
                (string) ($currentUser['nom'] ?? ''),
                (string) ($currentUser['email'] ?? '')
            ).' a modifie ses preferences de notification.'
        );

        $request->getSession()->getFlashBag()->add('success', 'Preferences de notifications enregistrees.');

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/profile/notifications/read-all', name: 'profile_notifications_read_all', methods: ['POST'])]
    public function markNotificationsAsRead(Request $request): RedirectResponse
    {
        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            return $this->redirectToRoute('app_login');
        }

        $redirectRoute = (string) $request->request->get('redirect_route', 'profile_index');
        if (!in_array($redirectRoute, ['profile_index', 'app_dashboard'], true)) {
            $redirectRoute = 'profile_index';
        }

        if (!$this->notificationService->isDatabaseAvailable()) {
            $request->getSession()->getFlashBag()->add('error', 'Le marquage des notifications necessite une base de donnees disponible.');

            return $this->redirectToRoute($redirectRoute);
        }

        $this->notificationService->markAllAsRead((string) ($authUser['email'] ?? ''));

        return $this->redirectToRoute($redirectRoute);
    }

    private function validateProfileForm(array $profileForm): ?string
    {
        if ($profileForm['prenom'] === '' && $profileForm['nom'] === '') {
            return 'Ajoutez au moins un prenom ou un nom.';
        }

        if (!LegacyValidator::isValidEmail((string) $profileForm['email'])) {
            return 'Veuillez saisir un email valide.';
        }

        if (!LegacyValidator::isValidPhoneOrBlank((string) $profileForm['telephone'])) {
            return 'Veuillez saisir un numero de telephone valide.';
        }

        if (!LegacyValidator::isValidBirthDate((string) $profileForm['date_naissance'])) {
            return 'La date de naissance ne peut pas etre dans le futur.';
        }

        if (!LegacyValidator::hasMaxLength((string) $profileForm['adresse'], 500)) {
            return "L'adresse est trop longue.";
        }

        return null;
    }

    private function buildProfilePayloadFromSource(array $currentUser, array $overrides = []): array
    {
        $payload = [
            'prenom' => (string) ($currentUser['prenom'] ?? ''),
            'nom' => (string) ($currentUser['nom'] ?? ''),
            'email' => (string) ($currentUser['email'] ?? ''),
            'telephone' => (string) ($currentUser['telephone'] ?? ''),
            'adresse' => (string) ($currentUser['adresse'] ?? ''),
            'date_naissance' => $currentUser['date_naissance'] ?? null,
            'role' => (string) ($currentUser['role'] ?? 'USER'),
            'photo_url' => (string) ($currentUser['photo_url'] ?? ''),
            'is_active' => (bool) ($currentUser['is_active'] ?? true),
        ];

        $merged = [...$payload, ...$overrides];
        if (($merged['date_naissance'] ?? '') === '') {
            $merged['date_naissance'] = null;
        }

        return $merged;
    }

    private function loadNotificationPreferences(Request $request, array $currentUser): NotificationPreferences
    {
        if (!$this->notificationService->isDatabaseAvailable()) {
            return new NotificationPreferences(true, true, true, false);
        }

        try {
            return $this->notificationService->getPreferences(
                (string) ($currentUser['email'] ?? ''),
                (string) ($currentUser['role'] ?? 'USER')
            );
        } catch (RuntimeException) {
            return new NotificationPreferences(true, true, true, false);
        }
    }

    private function buildNotificationForm(NotificationPreferences $preferences): array
    {
        return [
            'notify_security' => $preferences->security(),
            'notify_booking' => $preferences->booking(),
            'notify_forum' => $preferences->forum(),
            'notify_offers' => $preferences->offers(),
        ];
    }

    private function readBooleanRequestValue(Request $request, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($request->request->has($key)) {
                return $request->request->getBoolean($key);
            }
        }

        return false;
    }

    private function getAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) && trim((string) ($user['email'] ?? '')) !== '' ? $user : null;
    }

    private function loadCurrentUserRecord(Request $request): ?array
    {
        if (!$this->userRepository->isDatabaseAvailable()) {
            return null;
        }

        $authUser = $this->getAuthenticatedUser($request);
        if ($authUser === null) {
            return null;
        }

        return $this->userRepository->getByEmail((string) ($authUser['email'] ?? ''));
    }

    private function syncSessionUser(Request $request, array $user): void
    {
        $request->getSession()->set('auth_user', [
            'id' => (int) ($user['id'] ?? 0),
            'display_name' => $this->buildDisplayName(
                (string) ($user['prenom'] ?? ''),
                (string) ($user['nom'] ?? ''),
                (string) ($user['email'] ?? '')
            ),
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

    private function buildDisplayName(string $firstName, string $lastName, string $fallbackEmail): string
    {
        $displayName = trim(trim($firstName).' '.trim($lastName));

        return $displayName !== '' ? $displayName : ($fallbackEmail !== '' ? $fallbackEmail : 'Voyageur');
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
