<?php

namespace App\Controller;

use App\Repository\FollowRepository;
use App\Repository\PostRepository;
use App\Repository\StoryRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Public Instagram-style profile page for any user.
 *
 *  - Private profile + not a follower → show name/avatar/stats only, no posts.
 *  - Public profile OR follower → show the full post grid.
 *  - Owner → same view as a follower (sees own posts).
 */
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    // `username` must not contain a slash or be the reserved path
    // `follow-requests` (used by FollowController).
    #[Route('/u/{username}', name: 'app_user_profile', requirements: ['username' => '(?!follow-requests$)[^/]+'])]
    public function show(
        string $username,
        Request $request,
        UserRepository $users,
        PostRepository $posts,
        FollowRepository $follows,
        StoryRepository $stories
    ): Response {
        $user = $users->findOneBy(['username' => $username]);
        if (!$user) { throw $this->createNotFoundException('Utilisateur introuvable.'); }

        $me = $this->getUser()->getUserIdentifier();
        $isSelf = $me === $username;

        // Relationship state for the follow/message buttons.
        $edge = $isSelf ? null : $follows->findEdge($me, $username);
        $followState = 'none';
        if ($edge) {
            $followState = $edge->isAccepted() ? 'following' : 'pending';
        }

        // Posts are visible when: it's your own profile, OR the account is public,
        // OR you're an accepted follower.
        $canSeePosts = $isSelf || !$user->isPrivate() || $followState === 'following';
        $userPosts = $canSeePosts ? $posts->findByUser($user) : [];

        // Stats (always visible, even on private profiles — like Instagram).
        $stats = [
            'posts'     => $posts->getPostCountByUser($user),
            'followers' => $follows->countFollowers($username),
            'following' => $follows->countFollowing($username),
        ];

        // ——— Story ring on the avatar ———
        // Visibility rule (Instagram-style): if the profile is PUBLIC you can
        // see the ring + open the story even without following. Private
        // accounts still gate stories behind accepted followers + the owner.
        $hasActiveStory = $stories->userHasActiveStory($username);
        $canSeeStory    = $hasActiveStory && (
            $isSelf
            || !$user->isPrivate()
            || $followState === 'following'
        );

        return $this->render('user_profile/show.html.twig', [
            'profileUser'    => $user,
            'posts'          => $userPosts,
            'canSeePosts'    => $canSeePosts,
            'isSelf'         => $isSelf,
            'followState'    => $followState,
            'stats'          => $stats,
            'hasActiveStory' => $hasActiveStory,
            'canSeeStory'    => $canSeeStory,
            // Index within $userPosts to deep-link to (clicked from feed avatar).
            'initialPostId'  => $request->query->get('post'),
        ]);
    }
}
