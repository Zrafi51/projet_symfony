<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reaction>
 */
class ReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reaction::class);
    }

    public function findByPostAndUser(Post $post, User $user): ?Reaction
    {
        return $this->findOneBy(['post' => $post, 'username' => $user->getUsername()]);
    }

    public function getReactionCountsForPost(Post $post): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.reactionType, COUNT(r.id) as count')
            ->andWhere('r.post = :post')
            ->setParameter('post', $post)
            ->groupBy('r.reactionType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['reactionType']] = (int) $row['count'];
        }

        return $counts;
    }

    public function getTotalReactionsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.post', 'p')
            ->andWhere('p.auteur = :user')
            ->setParameter('user', $user->getUsername())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getReactionBreakdownForUser(User $user): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.reactionType, COUNT(r.id) as count')
            ->join('r.post', 'p')
            ->andWhere('p.auteur = :user')
            ->setParameter('user', $user->getUsername())
            ->groupBy('r.reactionType')
            ->getQuery()
            ->getResult();

        $breakdown = [];
        foreach ($results as $row) {
            $breakdown[$row['reactionType']] = (int) $row['count'];
        }

        return $breakdown;
    }
}
