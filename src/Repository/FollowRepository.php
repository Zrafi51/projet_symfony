<?php

namespace App\Repository;

use App\Entity\Follow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follow>
 */
class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    /** Find the follow edge from $follower to $following, regardless of status. */
    public function findEdge(string $follower, string $following): ?Follow
    {
        return $this->findOneBy([
            'followerUsername'  => $follower,
            'followingUsername' => $following,
        ]);
    }

    /** Does $follower follow $following with ACCEPTED status? */
    public function isFollowing(string $follower, string $following): bool
    {
        $edge = $this->findEdge($follower, $following);
        return $edge !== null && $edge->isAccepted();
    }

    /** Does a pending request exist from $follower to $following? */
    public function hasPending(string $follower, string $following): bool
    {
        $edge = $this->findEdge($follower, $following);
        return $edge !== null && $edge->isPending();
    }

    /** Usernames that $username is following (accepted only). */
    public function getFollowingUsernames(string $username): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('f.followingUsername')
            ->where('f.followerUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_ACCEPTED)
            ->getQuery()->getArrayResult();
        return array_column($rows, 'followingUsername');
    }

    /** Usernames following $username (accepted only). */
    public function getFollowerUsernames(string $username): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('f.followerUsername')
            ->where('f.followingUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_ACCEPTED)
            ->getQuery()->getArrayResult();
        return array_column($rows, 'followerUsername');
    }

    public function countFollowers(string $username): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.followingUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_ACCEPTED)
            ->getQuery()->getSingleScalarResult();
    }

    public function countFollowing(string $username): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.followerUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_ACCEPTED)
            ->getQuery()->getSingleScalarResult();
    }

    /** Pending follow requests sent TO $username. */
    public function getPendingRequestsFor(string $username): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.followingUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_PENDING)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * Most-recent accepted follows where $username is the one being followed —
     * used by the navbar notifications dropdown to surface "X started following
     * you" events (for public accounts, no approval step).
     */
    public function getRecentFollowersOf(string $username, int $limit = 15): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.followingUsername = :u')
            ->andWhere('f.status = :s')
            ->setParameter('u', $username)
            ->setParameter('s', Follow::STATUS_ACCEPTED)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
