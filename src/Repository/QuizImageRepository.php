<?php

namespace App\Repository;

use App\Entity\QuizImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizImage>
 */
class QuizImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizImage::class);
    }

    public function findByVoyageId(int $voyageId): ?QuizImage
    {
        return $this->createQueryBuilder('q')
            ->where('q.voyageId = :voyageId')
            ->andWhere('q.isActive = :active')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDestination(string $destination): ?QuizImage
    {
        return $this->createQueryBuilder('q')
            ->where('q.destination = :destination')
            ->andWhere('q.isActive = :active')
            ->setParameter('destination', $destination)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.voyageId', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
