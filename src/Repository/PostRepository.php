<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[]|null $includeAuthors  Only keep posts by these usernames. null = no filter.
     * @param string[]|null $excludeAuthors  Drop posts by these usernames. null = no filter.
     */
    public function searchPosts(
        ?string $keyword = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        ?int $minLikes = null,
        string $sortBy = 'recent',
        ?array $includeAuthors = null,
        ?array $excludeAuthors = null
    ): array {
        $qb = $this->createQueryBuilder('p');

        if ($keyword) {
            $qb->andWhere('p.description LIKE :keyword OR p.auteur LIKE :keyword')
               ->setParameter('keyword', '%' . $keyword . '%');
        }

        if ($includeAuthors !== null) {
            if (empty($includeAuthors)) {
                // Empty whitelist → no posts at all.
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('p.auteur IN (:includeAuthors)')
                   ->setParameter('includeAuthors', $includeAuthors);
            }
        }

        if (!empty($excludeAuthors)) {
            $qb->andWhere('p.auteur NOT IN (:excludeAuthors)')
               ->setParameter('excludeAuthors', $excludeAuthors);
        }

        if ($dateFrom) {
            $qb->andWhere('p.dateCreation >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            $dateTo = (clone $dateTo)->modify('+1 day');
            $qb->andWhere('p.dateCreation < :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        if ($minLikes !== null && $minLikes > 0) {
            $qb->andWhere('p.likes >= :minLikes')
               ->setParameter('minLikes', $minLikes);
        }

        switch ($sortBy) {
            case 'oldest':
                $qb->orderBy('p.dateCreation', 'ASC');
                break;
            case 'most_liked':
                $qb->orderBy('p.likes', 'DESC');
                break;
            case 'least_liked':
                $qb->orderBy('p.likes', 'ASC');
                break;
            default:
                $qb->orderBy('p.dateCreation', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.auteur = :user')
            ->setParameter('user', $user->getUsername())
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getPostCountByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.auteur = :user')
            ->setParameter('user', $user->getUsername())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalLikesReceived(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.likes), 0)')
            ->andWhere('p.auteur = :user')
            ->setParameter('user', $user->getUsername())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPostsPerMonth(User $user, int $months = 12): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DATE_FORMAT(date_creation, '%Y-%m') as month, COUNT(*) as count
                FROM sf_posts
                WHERE auteur = :username
                GROUP BY month
                ORDER BY month DESC
                LIMIT :months";

        $result = $conn->executeQuery($sql, [
            'username' => $user->getUsername(),
            'months' => $months,
        ], [
            'months' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        $data = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $data[$row['month']] = (int) $row['count'];
        }

        return $data;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
