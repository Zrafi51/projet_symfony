<?php

namespace App\Repository;

use App\Entity\Story;
use App\Entity\StoryLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoryLike>
 */
class StoryLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryLike::class);
    }

    public function findLike(Story $story, string $liker): ?StoryLike
    {
        return $this->findOneBy(['story' => $story, 'likerUsername' => $liker]);
    }

    public function countForStory(Story $story): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.story = :s')
            ->setParameter('s', $story)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns a map { storyId => bool } telling whether $liker has liked each
     * of the given stories. Saves a roundtrip when rendering a batch viewer.
     *
     * @param Story[] $stories
     * @return array<int, bool>
     */
    public function likedMapFor(array $stories, string $liker): array
    {
        if (empty($stories)) return [];
        $ids = array_map(fn (Story $s) => $s->getId(), $stories);
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.story) AS sid')
            ->where('l.story IN (:ids)')
            ->andWhere('l.likerUsername = :u')
            ->setParameter('ids', $ids)
            ->setParameter('u', $liker)
            ->getQuery()->getArrayResult();
        $map = array_fill_keys($ids, false);
        foreach ($rows as $r) { $map[(int) $r['sid']] = true; }
        return $map;
    }

    /**
     * Recent likes on any story authored by $username (notifications feed).
     *
     * @return StoryLike[]
     */
    public function recentForAuthor(string $username, int $limit = 15): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.story', 's')
            ->where('s.auteur = :u')
            ->andWhere('l.likerUsername != :u')
            ->setParameter('u', $username)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
