<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\StoryView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoryView>
 */
class StoryViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryView::class);
    }

    /**
     * Idempotent record: insert a view row only if the (story, viewer) pair
     * doesn't exist yet. Returns TRUE when a new row was created (so the
     * caller can decide whether to push a notification).
     */
    public function recordOnce(Story $story, string $viewer): bool
    {
        // Self-views are skipped — the author doesn't need a notification
        // for watching their own story.
        if ($story->getAuteur() === $viewer) {
            return false;
        }
        $existing = $this->findOneBy([
            'story'          => $story,
            'viewerUsername' => $viewer,
        ]);
        if ($existing) {
            return false;
        }
        $view = (new StoryView())
            ->setStory($story)
            ->setViewerUsername($viewer);
        $em = $this->getEntityManager();
        $em->persist($view);
        $em->flush();
        return true;
    }

    /**
     * Recent views across ALL stories authored by $username. Used by the
     * notification-bell aggregator ("X a vu votre story").
     *
     * @return StoryView[]
     */
    public function recentForAuthor(string $username, int $limit = 15): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.story', 's')
            ->where('s.auteur = :u')
            ->andWhere('v.viewerUsername != :u') // safety — self-views shouldn't exist
            ->setParameter('u', $username)
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** Viewer count for a single story (used on the story itself for the owner). */
    public function countForStory(Story $story): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.story = :s')
            ->setParameter('s', $story)
            ->getQuery()->getSingleScalarResult();
    }
}
