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
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the navbar notification dropdown.
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
        UserRepository     $users,
        StoryViewRepository $storyViews,
        StoryLikeRepository $storyLikes
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }
        $me = $user->getUserIdentifier();
        $isPrivate = $user instanceof User ? $user->isPrivate() : false;

        $myPostIds = array_map(
            fn ($p) => $p->getId(),
            $posts->findByUser($user)
        );

        $events = [];

        if (!empty($myPostIds)) {
            foreach ($likes->getRecentForPostIds($myPostIds, $me, self::RECENT_LIMIT) as $l) {
                $events[] = [
                    'type'   => 'like',
                    'actor'  => $l->getUsername(),
                    'postId' => $l->getPostId(),
                    'when'   => $l->getCreatedAt(),
                ];
            }

            foreach ($comments->getRecentForPostIds($myPostIds, $me, self::RECENT_LIMIT) as $c) {
                $events[] = [
                    'type'    => 'comment',
                    'actor'   => $c->getAuteur(),
                    'postId'  => $c->getPostId(),
                    'preview' => mb_substr((string) $c->getContenu(), 0, 80),
                    'when'    => $c->getDateCommentaire(),
                ];
            }

            foreach ($reactions->getRecentForPostIds($myPostIds, $me, self::RECENT_LIMIT) as $r) {
                $events[] = [
                    'type'   => 'reaction',
                    'actor'  => $r->getUsername(),
                    'emoji'  => $r->getReactionType(),
                    'postId' => $r->getPostId(),
                    'when'   => $r->getCreatedAt(),
                ];
            }
        }

        $followingUsernames = $follows->getFollowingUsernames($me);
        if (!empty($followingUsernames)) {
            foreach ($posts->getRecentPostsByAuthors($followingUsernames, $me, self::RECENT_LIMIT) as $p) {
                $events[] = [
                    'type'    => 'new_post',
                    'actor'   => $p->getAuteur(),
                    'postId'  => $p->getId(),
                    'preview' => mb_substr((string) $p->getDescription(), 0, 80),
                    'when'    => $p->getDateCreation(),
                ];
            }
        }

        foreach ($storyViews->recentForAuthor($me, self::RECENT_LIMIT) as $sv) {
            $events[] = [
                'type'    => 'story_view',
                'actor'   => $sv->getViewerUsername(),
                'storyId' => $sv->getStory()?->getId() ?? $sv->getStoryId(),
                'when'    => \DateTime::createFromImmutable($sv->getViewedAt()),
            ];
        }

        foreach ($storyLikes->recentForAuthor($me, self::RECENT_LIMIT) as $sl) {
            $events[] = [
                'type'    => 'story_like',
                'actor'   => $sl->getLikerUsername(),
                'storyId' => $sl->getStory()?->getId() ?? $sl->getStoryId(),
                'when'    => \DateTime::createFromImmutable($sl->getCreatedAt()),
            ];
        }

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

        usort($events, function ($a, $b) {
            $ta = $a['when'] ? $a['when']->getTimestamp() : 0;
            $tb = $b['when'] ? $b['when']->getTimestamp() : 0;
            return $tb <=> $ta;
        });
        $events = array_slice($events, 0, self::RECENT_LIMIT);

        $actors = array_unique(array_column($events, 'actor'));
        $photoMap = $users->getPhotoMapByUsernames($actors);

        return $this->render('_partials/notifications_menu.html.twig', [
            'events'        => $events,
            'pendingCount'  => $pendingCount,
            'photoMap'      => $photoMap,
        ]);
    }
}
