<?php

namespace App\Repository;

use App\Entity\Story;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Story>
 */
class StoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Story::class);
    }

    /**
     * Active (non-expired) stories authored by one of the given usernames.
     * Used for the home-feed carousel: "me + people I follow".
     *
     * @param string[] $usernames
     * @return Story[]
     */
    public function findActiveByAuthors(array $usernames): array
    {
        if (empty($usernames)) return [];
        return $this->createQueryBuilder('s')
            ->where('s.auteur IN (:authors)')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('authors', $usernames)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * All active stories for a single user, oldest-first (so the viewer shows
     * them in publication order — matches Facebook/Instagram behavior).
     *
     * @return Story[]
     */
    public function findActiveForUser(string $username): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.auteur = :u')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('u', $username)
            ->setParameter('now', new \DateTime())
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Remove every story whose expiry has passed. Called opportunistically on
     * the feed carousel render — cheap enough (indexed on expires_at) and
     * avoids the need for a cron. Returns the number of rows deleted.
     */
    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->delete()
            ->where('s.expiresAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()->execute();
    }

    /**
     * True if the given username has at least one active story — used by the
     * profile page to decide whether to draw the "story ring" around the
     * avatar.
     */
    public function userHasActiveStory(string $username): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.auteur = :u')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('u', $username)
            ->setParameter('now', new \DateTime())
            ->getQuery()->getSingleScalarResult();
        return $count > 0;
    }
}
