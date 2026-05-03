<?php

namespace App\Controller;

use App\Entity\Follow;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class FollowController extends AbstractController
{
    #[Route('/u/{username}/follow', name: 'app_follow_toggle', methods: ['POST'])]
    public function toggle(
        string $username,
        UserRepository $users,
        FollowRepository $follows
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
            $follows->remove($edge);
            return new JsonResponse([
                'state'     => 'none',
                'followers' => $follows->countFollowers($username),
            ]);
        }

        $follow = (new Follow())
            ->setFollowerUsername($me)
            ->setFollowingUsername($username)
            ->setStatus($target->isPrivate() ? Follow::STATUS_PENDING : Follow::STATUS_ACCEPTED);
        $follows->save($follow);

        return new JsonResponse([
            'state'     => $follow->getStatus() === Follow::STATUS_ACCEPTED ? 'following' : 'pending',
            'followers' => $follows->countFollowers($username),
        ]);
    }

    #[Route('/u/follow-requests/{id}/accept', name: 'app_follow_accept', methods: ['POST'])]
    public function accept(int $id, FollowRepository $follows): Response
    {
        $edge = $follows->find($id);
        if (!$edge || $edge->getFollowingUsername() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }
        $edge->setStatus(Follow::STATUS_ACCEPTED);
        $follows->save($edge);
        $this->addFlash('success', '@' . $edge->getFollowerUsername() . ' peut maintenant voir votre profil.');
        return $this->redirectToRoute('app_follow_requests');
    }

    #[Route('/u/follow-requests/{id}/reject', name: 'app_follow_reject', methods: ['POST'])]
    public function reject(int $id, FollowRepository $follows): Response
    {
        $edge = $follows->find($id);
        if (!$edge || $edge->getFollowingUsername() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }
        $follows->remove($edge);
        $this->addFlash('info', 'Demande refusée.');
        return $this->redirectToRoute('app_follow_requests');
    }

    #[Route('/u/follow-requests', name: 'app_follow_requests')]
    public function requests(FollowRepository $follows, UserRepository $users): Response
    {
        $me = $this->getUser()->getUserIdentifier();
        $pending = $follows->getPendingRequestsFor($me);
        $photos = $users->getPhotoMapByUsernames(array_map(fn($p) => $p->getFollowerUsername(), $pending));

        return $this->render('follow/requests.html.twig', [
            'pending'  => $pending,
            'photoMap' => $photos,
        ]);
    }

    #[Route('/u/{username}/{kind}', name: 'app_follow_list',
        requirements: ['username' => '(?!follow-requests$)[^/]+', 'kind' => 'followers|following'],
        methods: ['GET'])]
    public function list(
        string $username,
        string $kind,
        UserRepository $users,
        FollowRepository $follows
    ): Response {
        $target = $users->findOneBy(['username' => $username]);
        if (!$target) {
            throw $this->createNotFoundException();
        }
        $me     = $this->getUser()->getUserIdentifier();
        $isSelf = $me === $username;

        $canSee = !$target->isPrivate()
            || $isSelf
            || $follows->isFollowing($me, $username);

        $list = [];
        if ($canSee) {
            $usernames = $kind === 'followers'
                ? $follows->getFollowerUsernames($username)
                : $follows->getFollowingUsernames($username);

            if (!empty($usernames)) {
                $list = $users->findByUsernames($usernames);
            }
        }

        return $this->render('follow/list.html.twig', [
            'target'   => $target,
            'kind'     => $kind,
            'users'    => $list,
            'canSee'   => $canSee,
            'isSelf'   => $isSelf,
            'total'    => $kind === 'followers'
                ? $follows->countFollowers($username)
                : $follows->countFollowing($username),
        ]);
    }

    #[Route('/profile/privacy', name: 'app_profile_privacy_toggle', methods: ['POST'])]
    public function togglePrivacy(
        Request $request,
        UserRepository $users
    ): Response {
        if (!$this->isCsrfTokenValid('privacy', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $users->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);
        if (!$user) { throw $this->createNotFoundException(); }
        $user->setIsPrivate(!$user->isPrivate());
        $users->save($user);

        $this->addFlash('success', $user->isPrivate()
            ? 'Votre profil est maintenant privé. Seuls vos abonnés peuvent voir vos publications.'
            : 'Votre profil est maintenant public.');
        return $this->redirectToRoute('app_profile');
    }
}
