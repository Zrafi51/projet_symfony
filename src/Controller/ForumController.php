<?php

namespace App\Controller;

use App\Repository\ForumRepository;
use App\Repository\NewsletterRepository;
use App\Repository\UserRepository;
use App\Service\BadWordFilterService;
use App\Service\ForumMediaStorageService;
use App\Service\ProfilePhotoStorageService;
use App\Util\UploadedFileMimeTypeGuesser;
use App\Validation\LegacyValidator;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForumController extends AbstractController
{
    private const REACTION_OPTIONS = [
        'LIKE' => [
            'label' => "J'aime",
            'emoji_html' => '&#x1F44D;',
        ],
        'LOVE' => [
            'label' => 'Love',
            'emoji_html' => '&#x2764;&#xFE0F;',
        ],
        'WOW' => [
            'label' => 'Wow',
            'emoji_html' => '&#x1F62E;',
        ],
        'TRAVEL' => [
            'label' => 'Voyage',
            'emoji_html' => '&#x2708;&#xFE0F;',
        ],
        'FIRE' => [
            'label' => 'Top',
            'emoji_html' => '&#x1F525;',
        ],
    ];

    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly ForumRepository $forumRepository,
        private readonly UserRepository $userRepository,
        private readonly NewsletterRepository $newsletterRepository,
        private readonly ForumMediaStorageService $forumMediaStorageService,
        private readonly BadWordFilterService $badWordFilterService,
        private readonly ProfilePhotoStorageService $profilePhotoStorageService,
    ) {
    }

    #[Route('/forum', name: 'app_forum', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST') && $request->request->has('newsletter_email')) {
            return $this->handleNewsletterSubmission($request);
        }

        $currentUser = $this->resolveAuthenticatedUser($request);
        $databaseError = null;
        $feed = [];
        $commentsByPost = [];
        $reactionSummaryByPost = [];
        $userReactionMap = [];
        $stories = [];
        $storyViewCounts = [];
        $stats = [
            'posts' => 0,
            'comments' => 0,
            'stories' => 0,
            'reactions' => 0,
            'authors' => 0,
        ];

        try {
            $feed = $this->forumRepository->getFeed();
            $commentsByPost = $this->forumRepository->getCommentsByPostIds(array_column($feed, 'id'));
            $reactionSummaryByPost = $this->forumRepository->getReactionSummaryByPostIds(array_column($feed, 'id'));
            if ($currentUser !== null) {
                $userReactionMap = $this->forumRepository->getUserReactionMap(
                    (int) ($currentUser['id'] ?? 0),
                    array_column($feed, 'id')
                );
            }
            $stories = $this->forumRepository->getActiveStories();
            $storyViewCounts = $this->forumRepository->getStoryViewCountsByStoryIds(array_column($stories, 'id'));
            $stats = $this->forumRepository->getCommunityStats();
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        $feed = array_map(
            fn (array $post): array => $this->decoratePost(
                $post,
                $currentUser,
                $reactionSummaryByPost[(int) ($post['id'] ?? 0)] ?? null,
                $userReactionMap[(int) ($post['id'] ?? 0)] ?? null
            ),
            $feed
        );
        foreach ($commentsByPost as $postId => $comments) {
            $commentsByPost[$postId] = array_map(
                fn (array $comment): array => $this->decorateComment($comment, $currentUser),
                $comments
            );
        }
        $stories = array_map(
            fn (array $story): array => $this->decorateStory(
                $story,
                $currentUser,
                $storyViewCounts[(int) ($story['id'] ?? 0)] ?? 0
            ),
            $stories
        );

        return new Response($this->renderer->render('forum/index', [
            'title' => 'EasyTravel Social Club - EasyTravel',
            'currentUser' => $currentUser,
            'databaseError' => $databaseError,
            'pageBodyClass' => 'forum-page-body',
            'forumFeed' => $feed,
            'forumCommentsByPost' => $commentsByPost,
            'forumStories' => $stories,
            'forumStats' => $stats,
            'forumReactionOptions' => $this->getReactionOptions(),
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
            'footerNewsletterAction' => '/forum',
            'footerNewsletterStatusMessage' => $this->consumeFlash($request, 'newsletter_success'),
            'footerNewsletterErrorMessage' => $this->consumeFlash($request, 'newsletter_error'),
            'footerCtaLabel' => 'Commencer mon voyage &#8594;',
            'footerContactEmail' => 'contact@easytravel.tn',
            'footerContactPhone' => '+216 71 123 456',
            'footerContactLocation' => 'Tunis, Monastir',
            'footerBrandText' => "Createur d'experiences de voyage uniques avec l'intelligence artificielle depuis 2024.",
        ]));
    }

    #[Route('/forum/posts', name: 'forum_post_create', methods: ['POST'])]
    public function createPost(Request $request): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        if (($validationMessage = $this->validatePostPayload($title, $content)) !== null) {
            $request->getSession()->getFlashBag()->add('error', $validationMessage);

            return $this->redirectToRoute('app_forum');
        }

        $imagePath = $this->handleOptionalForumImageUpload(
            $request->files->get('image'),
            'post-'.(string) ($currentUser['id'] ?? 'user'),
            $request
        );
        if ($imagePath === false) {
            return $this->redirectToRoute('app_forum');
        }

        try {
            $this->forumRepository->createPost((int) ($currentUser['id'] ?? 0), $title, $content, $imagePath);
            $request->getSession()->getFlashBag()->add('success', 'Publication forum ajoutee.');
        } catch (RuntimeException $exception) {
            if (is_string($imagePath) && $imagePath !== '' && $this->forumMediaStorageService->isManagedMedia($imagePath)) {
                $this->forumMediaStorageService->delete($imagePath);
            }
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/posts/{postId}/update', name: 'forum_post_update', methods: ['POST'])]
    public function updatePost(Request $request, int $postId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $post = $this->forumRepository->getPostById($postId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirectToRoute('app_forum');
        }
        if ($post === null || !$this->canManageItem($currentUser, (int) ($post['user_id'] ?? 0))) {
            $request->getSession()->getFlashBag()->add('error', 'Modification du post non autorisee.');

            return $this->redirectToRoute('app_forum');
        }

        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        if (($validationMessage = $this->validatePostPayload($title, $content)) !== null) {
            $request->getSession()->getFlashBag()->add('error', $validationMessage);

            return $this->redirectToRoute('app_forum');
        }

        $removeImage = (string) $request->request->get('remove_image', '') === '1';
        $previousImage = (string) ($post['image_path'] ?? '');
        $nextImage = $removeImage ? null : ($previousImage !== '' ? $previousImage : null);

        $uploadedImage = $this->handleOptionalForumImageUpload(
            $request->files->get('image'),
            'post-edit-'.(string) ($currentUser['id'] ?? 'user'),
            $request
        );
        if ($uploadedImage === false) {
            return $this->redirectToRoute('app_forum');
        }
        if (is_string($uploadedImage) && $uploadedImage !== '') {
            $nextImage = $uploadedImage;
        }

        try {
            if ($this->forumRepository->updatePost($postId, $title, $content, $nextImage)) {
                if (
                    $previousImage !== ''
                    && $previousImage !== (string) $nextImage
                    && $this->forumMediaStorageService->isManagedMedia($previousImage)
                ) {
                    $this->forumMediaStorageService->delete($previousImage);
                }

                $request->getSession()->getFlashBag()->add('success', 'Publication mise a jour.');
            } else {
                if (is_string($uploadedImage) && $uploadedImage !== '' && $this->forumMediaStorageService->isManagedMedia($uploadedImage)) {
                    $this->forumMediaStorageService->delete($uploadedImage);
                }
                $request->getSession()->getFlashBag()->add('error', 'Impossible de mettre a jour cette publication.');
            }
        } catch (RuntimeException $exception) {
            if (is_string($uploadedImage) && $uploadedImage !== '' && $this->forumMediaStorageService->isManagedMedia($uploadedImage)) {
                $this->forumMediaStorageService->delete($uploadedImage);
            }
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/posts/{postId}/delete', name: 'forum_post_delete', methods: ['POST'])]
    public function deletePost(Request $request, int $postId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $post = $this->forumRepository->getPostById($postId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirectToRoute('app_forum');
        }
        if ($post === null || !$this->canManageItem($currentUser, (int) ($post['user_id'] ?? 0))) {
            $request->getSession()->getFlashBag()->add('error', 'Suppression du post non autorisee.');

            return $this->redirectToRoute('app_forum');
        }

        try {
            if ($this->forumRepository->deletePost($postId)) {
                if ($this->forumMediaStorageService->isManagedMedia((string) ($post['image_path'] ?? ''))) {
                    $this->forumMediaStorageService->delete((string) ($post['image_path'] ?? ''));
                }
                $request->getSession()->getFlashBag()->add('success', 'Publication supprimee.');
            } else {
                $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer cette publication.');
            }
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/posts/{postId}/comments', name: 'forum_comment_create', methods: ['POST'])]
    public function createComment(Request $request, int $postId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $post = $this->forumRepository->getPostById($postId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirectToRoute('app_forum');
        }

        if ($post === null) {
            $request->getSession()->getFlashBag()->add('error', 'Publication introuvable.');

            return $this->redirectToRoute('app_forum');
        }

        $content = trim((string) $request->request->get('content', ''));
        if (($validationMessage = $this->validateCommentPayload($content)) !== null) {
            $request->getSession()->getFlashBag()->add('error', $validationMessage);

            return $this->redirectToRoute('app_forum');
        }

        try {
            $this->forumRepository->createComment($postId, (int) ($currentUser['id'] ?? 0), $content);
            $request->getSession()->getFlashBag()->add('success', 'Commentaire ajoute.');
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/comments/{commentId}/delete', name: 'forum_comment_delete', methods: ['POST'])]
    public function deleteComment(Request $request, int $commentId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $comment = $this->forumRepository->getCommentById($commentId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirectToRoute('app_forum');
        }
        if ($comment === null || !$this->canManageItem($currentUser, (int) ($comment['user_id'] ?? 0))) {
            $request->getSession()->getFlashBag()->add('error', 'Suppression du commentaire non autorisee.');

            return $this->redirectToRoute('app_forum');
        }

        try {
            if ($this->forumRepository->deleteComment($commentId)) {
                $request->getSession()->getFlashBag()->add('success', 'Commentaire supprime.');
            } else {
                $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer ce commentaire.');
            }
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/posts/{postId}/reaction', name: 'forum_post_reaction_toggle', methods: ['POST'])]
    public function togglePostReaction(Request $request, int $postId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $post = $this->forumRepository->getPostById($postId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirect('/forum#forum-post-'.$postId);
        }

        if ($post === null) {
            $request->getSession()->getFlashBag()->add('error', 'Publication introuvable.');

            return $this->redirect('/forum#forum-post-'.$postId);
        }

        $reactionCode = $this->normalizeReactionCode((string) $request->request->get('reaction', ''));
        if ($reactionCode === null) {
            $request->getSession()->getFlashBag()->add('error', 'Reaction invalide.');

            return $this->redirect('/forum#forum-post-'.$postId);
        }

        try {
            $this->forumRepository->setPostReaction(
                $postId,
                (int) ($currentUser['id'] ?? 0),
                $reactionCode
            );
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirect('/forum#forum-post-'.$postId);
    }

    #[Route('/forum/stories', name: 'forum_story_create', methods: ['POST'])]
    public function createStory(Request $request): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        $caption = trim((string) $request->request->get('caption', ''));
        if (($validationMessage = $this->validateStoryPayload($caption)) !== null) {
            $request->getSession()->getFlashBag()->add('error', $validationMessage);

            return $this->redirectToRoute('app_forum');
        }

        $storyImage = $request->files->get('story_image');
        if (!$storyImage instanceof UploadedFile || !$storyImage->isValid()) {
            $request->getSession()->getFlashBag()->add('error', 'Une image est obligatoire pour publier une story.');

            return $this->redirectToRoute('app_forum');
        }

        $imagePath = $this->handleOptionalForumImageUpload(
            $storyImage,
            'story-'.(string) ($currentUser['id'] ?? 'user'),
            $request
        );
        if ($imagePath === false || !is_string($imagePath) || $imagePath === '') {
            return $this->redirectToRoute('app_forum');
        }

        $expiresAt = new \DateTimeImmutable('+24 hours');
        try {
            $this->forumRepository->createStory((int) ($currentUser['id'] ?? 0), $caption, $imagePath, $expiresAt);
            $request->getSession()->getFlashBag()->add('success', 'Story publiee pour 24h.');
        } catch (RuntimeException $exception) {
            if ($this->forumMediaStorageService->isManagedMedia($imagePath)) {
                $this->forumMediaStorageService->delete($imagePath);
            }
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/stories/{storyId}/delete', name: 'forum_story_delete', methods: ['POST'])]
    public function deleteStory(Request $request, int $storyId): RedirectResponse
    {
        $currentUser = $this->requireAuthenticatedUser($request);
        if ($currentUser === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $story = $this->forumRepository->getStoryById($storyId);
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

            return $this->redirectToRoute('app_forum');
        }
        if ($story === null || !$this->canManageItem($currentUser, (int) ($story['user_id'] ?? 0))) {
            $request->getSession()->getFlashBag()->add('error', 'Suppression de la story non autorisee.');

            return $this->redirectToRoute('app_forum');
        }

        try {
            if ($this->forumRepository->deleteStory($storyId)) {
                if ($this->forumMediaStorageService->isManagedMedia((string) ($story['image_path'] ?? ''))) {
                    $this->forumMediaStorageService->delete((string) ($story['image_path'] ?? ''));
                }
                $request->getSession()->getFlashBag()->add('success', 'Story supprimee.');
            } else {
                $request->getSession()->getFlashBag()->add('error', 'Impossible de supprimer cette story.');
            }
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    #[Route('/forum/stories/{storyId}/view', name: 'forum_story_view', methods: ['POST'])]
    public function viewStory(Request $request, int $storyId): Response
    {
        try {
            $story = $this->forumRepository->getStoryById($storyId);
            if ($story === null) {
                return $this->json([
                    'success' => false,
                    'message' => 'Story introuvable.',
                ], Response::HTTP_NOT_FOUND);
            }

            $currentUser = $this->resolveAuthenticatedUser($request);
            $views = $this->forumRepository->recordStoryView(
                $storyId,
                (int) ($currentUser['id'] ?? 0),
                $currentUser === null ? $this->resolveStoryViewerKey($request) : null
            );

            return $this->json([
                'success' => true,
                'views' => $views,
            ]);
        } catch (RuntimeException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleNewsletterSubmission(Request $request): RedirectResponse
    {
        $email = trim((string) $request->request->get('newsletter_email', ''));

        if (!LegacyValidator::isValidEmail($email)) {
            $request->getSession()->getFlashBag()->add('newsletter_error', 'Veuillez saisir un email valide pour la newsletter.');

            return $this->redirectToRoute('app_forum');
        }

        try {
            $this->newsletterRepository->subscribe($email);
            $request->getSession()->getFlashBag()->add('newsletter_success', 'Merci ! Vous etes abonne a notre newsletter.');
        } catch (RuntimeException $exception) {
            $request->getSession()->getFlashBag()->add('newsletter_error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_forum');
    }

    private function validatePostPayload(string $title, string $content): ?string
    {
        if ($title === '' || mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            return 'Le titre du post doit contenir entre 3 et 160 caracteres.';
        }

        if ($content === '' || mb_strlen($content) < 10 || mb_strlen($content) > 5000) {
            return 'Le contenu du post doit contenir entre 10 et 5000 caracteres.';
        }

        $blockedWord = $this->badWordFilterService->findFirstBlockedWord($title, $content);
        if ($blockedWord !== null) {
            return 'Votre publication contient un mot interdit: '.$blockedWord.'.';
        }

        return null;
    }

    private function validateCommentPayload(string $content): ?string
    {
        if ($content === '' || mb_strlen($content) < 2 || mb_strlen($content) > 1200) {
            return 'Le commentaire doit contenir entre 2 et 1200 caracteres.';
        }

        $blockedWord = $this->badWordFilterService->findFirstBlockedWord($content);
        if ($blockedWord !== null) {
            return 'Votre commentaire contient un mot interdit: '.$blockedWord.'.';
        }

        return null;
    }

    private function validateStoryPayload(string $caption): ?string
    {
        if (mb_strlen($caption) > 180) {
            return 'La legende de la story ne doit pas depasser 180 caracteres.';
        }

        $blockedWord = $caption !== '' ? $this->badWordFilterService->findFirstBlockedWord($caption) : null;
        if ($blockedWord !== null) {
            return 'Votre story contient un mot interdit: '.$blockedWord.'.';
        }

        return null;
    }

    private function handleOptionalForumImageUpload(mixed $file, string $key, Request $request): string|false|null
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            $request->getSession()->getFlashBag()->add('error', "Impossible de charger l'image selectionnee.");

            return false;
        }

        $mimeType = UploadedFileMimeTypeGuesser::detect($file) ?? '';
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $request->getSession()->getFlashBag()->add('error', 'Le fichier du forum doit etre une image PNG, JPG, WEBP ou GIF.');

            return false;
        }

        if ($file->getSize() !== null && $file->getSize() > 6 * 1024 * 1024) {
            $request->getSession()->getFlashBag()->add('error', "L'image du forum est trop lourde. Taille max: 6 Mo.");

            return false;
        }

        try {
            return $this->forumMediaStorageService->store($file, $key);
        } catch (\Throwable) {
            $request->getSession()->getFlashBag()->add('error', "Impossible d'enregistrer l'image du forum.");

            return false;
        }
    }

    private function requireAuthenticatedUser(Request $request): ?array
    {
        $currentUser = $this->resolveAuthenticatedUser($request);
        if ($currentUser === null) {
            $request->getSession()->getFlashBag()->add('error', 'Connectez-vous pour utiliser le forum.');
        }

        return $currentUser;
    }

    private function resolveAuthenticatedUser(Request $request): ?array
    {
        $sessionUser = $request->getSession()->get('auth_user');
        if (!is_array($sessionUser) || trim((string) ($sessionUser['email'] ?? '')) === '') {
            return null;
        }

        try {
            return $this->userRepository->getByEmail((string) ($sessionUser['email'] ?? '')) ?? $sessionUser;
        } catch (RuntimeException) {
            return $sessionUser;
        }
    }

    private function decoratePost(
        array $post,
        ?array $currentUser,
        ?array $reactionSummary,
        ?string $currentUserReaction
    ): array
    {
        $post['author_photo_display_url'] = $this->resolvePhotoUrlForView((string) ($post['author_photo_url'] ?? ''));
        $post['is_owner'] = $currentUser !== null && (int) ($currentUser['id'] ?? 0) === (int) ($post['user_id'] ?? 0);
        $post['can_manage'] = $currentUser !== null && $this->canManageItem($currentUser, (int) ($post['user_id'] ?? 0));
        $post['reaction_breakdown'] = array_map(
            fn (array $item): array => $this->decorateReactionSummaryItem($item),
            $reactionSummary['items'] ?? []
        );
        $post['reactions_total'] = (int) ($reactionSummary['total'] ?? 0);
        $post['current_user_reaction'] = $this->normalizeReactionCode((string) $currentUserReaction);
        $post['primary_reaction_code'] = $post['current_user_reaction'] ?? 'LIKE';
        $post['primary_reaction_label'] = self::REACTION_OPTIONS[$post['primary_reaction_code']]['label'];
        $post['primary_reaction_emoji_html'] = self::REACTION_OPTIONS[$post['primary_reaction_code']]['emoji_html'];
        $post['primary_reaction_active'] = $post['current_user_reaction'] !== null;

        return $post;
    }

    private function decorateComment(array $comment, ?array $currentUser): array
    {
        $comment['author_photo_display_url'] = $this->resolvePhotoUrlForView((string) ($comment['author_photo_url'] ?? ''));
        $comment['can_manage'] = $currentUser !== null && $this->canManageItem($currentUser, (int) ($comment['user_id'] ?? 0));

        return $comment;
    }

    private function decorateStory(array $story, ?array $currentUser, int $viewCount = 0): array
    {
        $story['author_photo_display_url'] = $this->resolvePhotoUrlForView((string) ($story['author_photo_url'] ?? ''));
        $story['can_manage'] = $currentUser !== null && $this->canManageItem($currentUser, (int) ($story['user_id'] ?? 0));
        $story['view_count'] = max(0, $viewCount);

        return $story;
    }

    private function canManageItem(array $currentUser, int $ownerId): bool
    {
        return (int) ($currentUser['id'] ?? 0) === $ownerId || $this->isAdminRole((string) ($currentUser['role'] ?? 'USER'));
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true);
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

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }

    private function resolveStoryViewerKey(Request $request): string
    {
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $sessionId = trim((string) $session->getId());
        if ($sessionId !== '') {
            return 'session:'.$sessionId;
        }

        $fingerprint = (string) $request->server->get('REMOTE_ADDR', '').'|'.(string) $request->headers->get('User-Agent', '');

        return 'guest:'.substr(hash('sha256', $fingerprint), 0, 48);
    }

    private function getReactionOptions(): array
    {
        $options = [];
        foreach (self::REACTION_OPTIONS as $code => $meta) {
            $options[] = [
                'code' => $code,
                'label' => $meta['label'],
                'emoji_html' => $meta['emoji_html'],
            ];
        }

        return $options;
    }

    private function decorateReactionSummaryItem(array $item): array
    {
        $code = $this->normalizeReactionCode((string) ($item['code'] ?? '')) ?? 'LIKE';
        $meta = self::REACTION_OPTIONS[$code];

        return [
            'code' => $code,
            'label' => $meta['label'],
            'emoji_html' => $meta['emoji_html'],
            'count' => (int) ($item['count'] ?? 0),
        ];
    }

    private function normalizeReactionCode(string $reactionCode): ?string
    {
        $reactionCode = strtoupper(trim($reactionCode));

        return array_key_exists($reactionCode, self::REACTION_OPTIONS) ? $reactionCode : null;
    }
}
