<?php

namespace App\Repository;

use App\Entity\MapDestination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MapDestination>
 */
class MapDestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MapDestination::class);
    }

    public function findByCity(string $city): ?MapDestination
    {
        return $this->createQueryBuilder('m')
            ->where('m.city = :city')
            ->andWhere('m.isActive = :active')
            ->setParameter('city', $city)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCountry(string $country): ?MapDestination
    {
        return $this->createQueryBuilder('m')
            ->where('m.country = :country')
            ->andWhere('m.isActive = :active')
            ->setParameter('country', $country)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
