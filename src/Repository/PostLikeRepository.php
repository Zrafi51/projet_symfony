<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostLike>
 */
class PostLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostLike::class);
    }

    public function findByPostAndUser(Post $post, User $user): ?PostLike
    {
        return $this->findOneBy([
            'post' => $post,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    /**
     * Returns the set of post ids the given user has liked.
     *
     * @return int[]
     */
    public function getLikedPostIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('pl')
            ->select('IDENTITY(pl.post) AS post_id')
            ->where('pl.username = :u')
            ->setParameter('u', $user->getUserIdentifier())
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn ($r) => (int) $r['post_id'], $rows);
    }

    public function countForPost(Post $post): int
    {
        return (int) $this->createQueryBuilder('pl')
            ->select('COUNT(pl.id)')
            ->where('pl.post = :p')
            ->setParameter('p', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
