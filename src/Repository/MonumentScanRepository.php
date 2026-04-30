<?php

namespace App\Repository;

use App\Entity\MonumentScan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonumentScan>
 */
class MonumentScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonumentScan::class);
    }

    /**
     * Find successful scans by user
     */
    public function findSuccessfulByUser(User $user): array
    {
        return $this->createQueryBuilder('ms')
            ->where('ms.user = :user')
            ->andWhere('ms.scanStatus = :status')
            ->andWhere('ms.monumentName != :empty')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('empty', '')
            ->orderBy('ms.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find scans by user with pagination
     */
    public function findByUserPaginated(User $user, int $offset = 0, int $limit = 10): array
    {
        return $this->createQueryBuilder('ms')
            ->where('ms.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ms.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count scans by user
     */
    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('ms')
            ->select('COUNT(ms.id)')
            ->where('ms.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find successful scans not added to request
     */
    public function findNotAddedToRequest(User $user): array
    {
        return $this->createQueryBuilder('ms')
            ->where('ms.user = :user')
            ->andWhere('ms.scanStatus = :status')
            ->andWhere('ms.addedToRequest = :notAdded')
            ->andWhere('ms.monumentName != :empty')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('notAdded', false)
            ->setParameter('empty', '')
            ->orderBy('ms.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find scans by monument name (search)
     */
    public function findByMonumentName(string $search, User $user = null): array
    {
        $qb = $this->createQueryBuilder('ms')
            ->where('ms.monumentName LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('ms.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('ms.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Find scans by location
     */
    public function findByLocation(string $location, User $user = null): array
    {
        $qb = $this->createQueryBuilder('ms')
            ->where('ms.city LIKE :location OR ms.country LIKE :location')
            ->setParameter('location', '%' . $location . '%')
            ->orderBy('ms.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('ms.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Get statistics for user
     */
    public function getUserStatistics(User $user): array
    {
        $totalScans = $this->countByUser($user);
        $successfulScans = count($this->findSuccessfulByUser($user));
        $addedToRequest = count($this->findBy(['user' => $user, 'addedToRequest' => true]));

        return [
            'total_scans' => $totalScans,
            'successful_scans' => $successfulScans,
            'added_to_request' => $addedToRequest,
            'success_rate' => $totalScans > 0 ? round(($successfulScans / $totalScans) * 100, 1) : 0
        ];
    }

    /**
     * Find recent successful scans (for dashboard)
     */
    public function findRecentSuccessful(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('ms')
            ->where('ms.user = :user')
            ->andWhere('ms.scanStatus = :status')
            ->andWhere('ms.monumentName != :empty')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('empty', '')
            ->orderBy('ms.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old scans (cleanup)
     */
    public function deleteOldScans(int $days = 30): int
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('ms')
            ->delete()
            ->where('ms.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
