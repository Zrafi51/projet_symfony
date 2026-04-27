<?php

namespace App\Controller;

use App\Entity\Follow;
use App\Repository\FollowRepository;
use App\Repository\ForumUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FollowController extends AbstractController
{
    /**
     * Follow or unfollow a user. If the target is private, the first call
     * creates a PENDING request (requires approval); otherwise it's ACCEPTED
     * immediately. A second call on an existing accepted edge unfollows.
     */
    #[Route('/social/u/{username}/follow', name: 'forum_follow_toggle', methods: ['POST'])]
    public function toggle(
        string $username,
        ForumUserRepository $users,
        FollowRepository $follows,
        EntityManagerInterface $em
    ): JsonResponse {
        $me = $this->getUser()->getUserIdentifier();
        if ($me === $username) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas vous suivre vous-même.'], 400);
        }

        $target = $users->findOneBy(['username' => $username]);
        if (!$target) {
            return new JsonResponse(['error' => 'Utilisateur introuvable.'], 404);
        }

        $edge = $follows->findEdge($me, $username);
        if ($edge) {
            $em->remove($edge);
            $em->flush();
            return new JsonResponse([
                'state'     => 'none',
                'followers' => $follows->countFollowers($username),
            ]);
        }

        $follow = (new Follow())
            ->setFollowerUsername($me)
            ->setFollowingUsername($username)
            ->setStatus($target->isPrivate() ? Follow::STATUS_PENDING : Follow::STATUS_ACCEPTED);
        $em->persist($follow);
        $em->flush();

        return new JsonResponse([
            'state'     => $follow->getStatus() === Follow::STATUS_ACCEPTED ? 'following' : 'pending',
            'followers' => $follows->countFollowers($username),
        ]);
    }

    /** Accept an incoming follow request (private-account owner only). */
    #[Route('/social/u/follow-requests/{id}/accept', name: 'forum_follow_accept', methods: ['POST'])]
    public function accept(int $id, FollowRepository $follows, EntityManagerInterface $em): Response
    {
        $edge = $follows->find($id);
        if (!$edge || $edge->getFollowingUsername() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }
        $edge->setStatus(Follow::STATUS_ACCEPTED);
        $em->flush();
        $this->addFlash('success', '@' . $edge->getFollowerUsername() . ' peut maintenant voir votre profil.');
        return $this->redirectToRoute('forum_follow_requests');
    }

    /** Reject (delete) an incoming follow request. */
    #[Route('/social/u/follow-requests/{id}/reject', name: 'forum_follow_reject', methods: ['POST'])]
    public function reject(int $id, FollowRepository $follows, EntityManagerInterface $em): Response
    {
        $edge = $follows->find($id);
        if (!$edge || $edge->getFollowingUsername() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($edge);
        $em->flush();
        $this->addFlash('info', 'Demande refusée.');
        return $this->redirectToRoute('forum_follow_requests');
    }

    /** Inbox of pending follow requests for the current user. */
    #[Route('/social/u/follow-requests', name: 'forum_follow_requests')]
    public function requests(FollowRepository $follows, ForumUserRepository $users): Response
    {
        $me = $this->getUser()->getUserIdentifier();
        $pending = $follows->getPendingRequestsFor($me);
        $photos = $users->getPhotoMapByUsernames(array_map(fn($p) => $p->getFollowerUsername(), $pending));

        return $this->render('social/follow/requests.html.twig', [
            'pending'  => $pending,
            'photoMap' => $photos,
        ]);
    }

    /**
     * List view of someone's followers OR following.
     *
     *   /u/{username}/followers  → people who follow this user
     *   /u/{username}/following  → people this user is following
     *
     * Privacy: always visible for public accounts; on a PRIVATE account, only
     * the owner and accepted followers can see the lists. Otherwise we show
     * the page with a "compte privé" notice but no list.
     */
    #[Route('/social/u/{username}/{kind}', name: 'forum_follow_list',
        requirements: ['username' => '(?!follow-requests$)[^/]+', 'kind' => 'followers|following'],
        methods: ['GET'])]
    public function list(
        string $username,
        string $kind,
        ForumUserRepository $users,
        FollowRepository $follows
    ): Response {
        $target = $users->findOneBy(['username' => $username]);
        if (!$target) {
            throw $this->createNotFoundException();
        }
        $me     = $this->getUser()->getUserIdentifier();
        $isSelf = $me === $username;

        // Same visibility rule as posts: public OR owner OR accepted follower.
        $canSee = !$target->isPrivate()
            || $isSelf
            || $follows->isFollowing($me, $username);

        $list = [];
        if ($canSee) {
            $usernames = $kind === 'followers'
                ? $follows->getFollowerUsernames($username)
                : $follows->getFollowingUsernames($username);

            if (!empty($usernames)) {
                // Fetch the full User rows so the template can render avatar +
                // private-lock icon per row.
                $list = $users->createQueryBuilder('u')
                    ->where('u.username IN (:names)')
                    ->setParameter('names', $usernames)
                    ->orderBy('u.username', 'ASC')
                    ->getQuery()->getResult();
            }
        }

        return $this->render('social/follow/list.html.twig', [
            'target'   => $target,
            'kind'     => $kind,  // 'followers' or 'following'
            'users'    => $list,
            'canSee'   => $canSee,
            'isSelf'   => $isSelf,
            'total'    => $kind === 'followers'
                ? $follows->countFollowers($username)
                : $follows->countFollowing($username),
        ]);
    }

    /** Toggle the current user's own privacy. */
    #[Route('/social/profile/privacy', name: 'forum_profile_privacy_toggle', methods: ['POST'])]
    public function togglePrivacy(
        Request $request,
        EntityManagerInterface $em,
        ForumUserRepository $users
    ): Response {
        if (!$this->isCsrfTokenValid('privacy', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $users->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);
        if (!$user) { throw $this->createNotFoundException(); }
        $user->setIsPrivate(!$user->isPrivate());
        $em->flush();

        $this->addFlash('success', $user->isPrivate()
            ? 'Votre profil est maintenant privé. Seuls vos abonnés peuvent voir vos publications.'
            : 'Votre profil est maintenant public.');
        return $this->redirectToRoute('forum_profile');
    }
}
