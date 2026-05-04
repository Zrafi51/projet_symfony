<?php

namespace App\Controller;

use App\Entity\Follow;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\FollowRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\StoryLikeRepository;
use App\Repository\StoryViewRepository;
use App\Repository\ForumUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the navbar notification dropdown — invoked from base.html.twig via
 * {{ render(controller('App\\\\Controller\\\\Forum\\\\NotificationController::menu')) }}.
 *
 * Aggregates events the current user cares about:
 *   - Recent likes on their posts
 *   - Recent comments on their posts
 *   - Recent reactions (emoji) on their posts
 *   - Follows: pending requests if the account is private (with accept/reject
 *     link), or "X started following you" for public accounts.
 *
 * Sorted newest-first, capped at RECENT_LIMIT.
 */
class NotificationController extends AbstractController
{
    private const RECENT_LIMIT = 15;

    public function menu(
        PostRepository     $posts,
        PostLikeRepository $likes,
        CommentRepository  $comments,
        ReactionRepository $reactions,
        FollowRepository   $follows,
        ForumUserRepository     $users,
        StoryViewRepository $storyViews,
        StoryLikeRepository $storyLikes
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }
        $me = $user->getUserIdentifier();
        $isPrivate = $user instanceof User ? $user->isPrivate() : false;

        // Collect all ids of posts I own — every event below is filtered against this.
        $myPostIds = array_map(
            fn ($p) => $p->getId(),
            $posts->findByUser($user)
        );

        $events = [];

        // ——— Recent likes on my posts (exclude self-likes) ———
        if (!empty($myPostIds)) {
            $recentLikes = $likes->createQueryBuilder('l')
                ->where('l.post IN (:ids)')
                ->andWhere('l.username != :me')
                ->setParameter('ids', $myPostIds)
                ->setParameter('me', $me)
                ->orderBy('l.createdAt', 'DESC')
                ->setMaxResults(self::RECENT_LIMIT)
                ->getQuery()->getResult();
            foreach ($recentLikes as $l) {
                $events[] = [
                    'type'   => 'like',
                    'actor'  => $l->getUsername(),
                    'postId' => $l->getPost()->getId(),
                    'when'   => $l->getCreatedAt(),
                ];
            }
        }

        // ——— Recent comments on my posts ———
        if (!empty($myPostIds)) {
            $recentComments = $comments->createQueryBuilder('c')
                ->join('c.post', 'p')
                ->where('p.id IN (:ids)')
                ->andWhere('c.auteur != :me')
                ->setParameter('ids', $myPostIds)
                ->setParameter('me', $me)
                ->orderBy('c.dateCommentaire', 'DESC')
                ->setMaxResults(self::RECENT_LIMIT)
                ->getQuery()->getResult();
            foreach ($recentComments as $c) {
                $events[] = [
                    'type'    => 'comment',
                    'actor'   => $c->getAuteur(),
                    'postId'  => $c->getPost()->getId(),
                    'preview' => mb_substr((string) $c->getContenu(), 0, 80),
                    'when'    => $c->getDateCommentaire(),
                ];
            }
        }

        // ——— Recent reactions on my posts (emoji-style) ———
        if (!empty($myPostIds)) {
            $recentReactions = $reactions->createQueryBuilder('r')
                ->join('r.post', 'p')
                ->where('p.id IN (:ids)')
                ->andWhere('r.username != :me')
                ->setParameter('ids', $myPostIds)
                ->setParameter('me', $me)
                ->orderBy('r.createdAt', 'DESC')
                ->setMaxResults(self::RECENT_LIMIT)
                ->getQuery()->getResult();
            foreach ($recentReactions as $r) {
                $events[] = [
                    'type'   => 'reaction',
                    'actor'  => $r->getUsername(),
                    'emoji'  => $r->getReactionType(),
                    'postId' => $r->getPost()->getId(),
                    'when'   => $r->getCreatedAt(),
                ];
            }
        }

        // ——— New posts from people I follow ———
        // Each recent post by a followed account shows up as "X a publié une
        // nouvelle publication" — clickable through to the post page.
        $followingUsernames = $follows->getFollowingUsernames($me);
        if (!empty($followingUsernames)) {
            $recentPosts = $posts->createQueryBuilder('p')
                ->where('p.auteur IN (:authors)')
                ->andWhere('p.auteur != :me')
                ->setParameter('authors', $followingUsernames)
                ->setParameter('me', $me)
                ->orderBy('p.dateCreation', 'DESC')
                ->setMaxResults(self::RECENT_LIMIT)
                ->getQuery()->getResult();
            foreach ($recentPosts as $p) {
                $events[] = [
                    'type'    => 'new_post',
                    'actor'   => $p->getAuteur(),
                    'postId'  => $p->getId(),
                    'preview' => mb_substr((string) $p->getDescription(), 0, 80),
                    'when'    => $p->getDateCreation(),
                ];
            }
        }

        // ——— Recent views on my stories ———
        foreach ($storyViews->recentForAuthor($me, self::RECENT_LIMIT) as $sv) {
            $events[] = [
                'type'    => 'story_view',
                'actor'   => $sv->getViewerUsername(),
                'storyId' => $sv->getStory()?->getId(),
                'when'    => \DateTime::createFromImmutable($sv->getViewedAt()),
            ];
        }

        // ——— Recent likes on my stories ———
        foreach ($storyLikes->recentForAuthor($me, self::RECENT_LIMIT) as $sl) {
            $events[] = [
                'type'    => 'story_like',
                'actor'   => $sl->getLikerUsername(),
                'storyId' => $sl->getStory()?->getId(),
                'when'    => \DateTime::createFromImmutable($sl->getCreatedAt()),
            ];
        }

        // ——— Follows ———
        // Private accounts see pending "X requested to follow you" rows with an
        // accept/reject link. Public accounts have no approval step, so instead
        // surface "X started following you" for recent accepted follows.
        $pendingCount = 0;
        if ($isPrivate) {
            $pendingRequests = $follows->getPendingRequestsFor($me);
            $pendingCount = count($pendingRequests);
            foreach ($pendingRequests as $req) {
                /** @var Follow $req */
                $events[] = [
                    'type'  => 'follow_request',
                    'actor' => $req->getFollowerUsername(),
                    'when'  => $req->getCreatedAt(),
                ];
            }
        } else {
            foreach ($follows->getRecentFollowersOf($me, self::RECENT_LIMIT) as $f) {
                /** @var Follow $f */
                $events[] = [
                    'type'  => 'new_follower',
                    'actor' => $f->getFollowerUsername(),
                    'when'  => $f->getCreatedAt(),
                ];
            }
        }

        // Chronological sort (newest first).
        usort($events, function ($a, $b) {
            $ta = $a['when'] ? $a['when']->getTimestamp() : 0;
            $tb = $b['when'] ? $b['when']->getTimestamp() : 0;
            return $tb <=> $ta;
        });
        $events = array_slice($events, 0, self::RECENT_LIMIT);

        // Fetch author photos for the notification rows.
        $actors = array_unique(array_column($events, 'actor'));
        $photoMap = $users->getPhotoMapByUsernames($actors);

        return $this->render('social/_partials/notifications_menu.html.twig', [
            'events'        => $events,
            'pendingCount'  => $pendingCount,
            'photoMap'      => $photoMap,
        ]);
    }
}
