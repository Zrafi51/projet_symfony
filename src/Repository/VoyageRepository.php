<?php

namespace App\Repository;

use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Voyage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Voyage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Voyage[]    findAll()
 * @method Voyage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    public function findByClientEmail(string $email): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.destination LIKE :destination')
            ->setParameter('destination', '%' . $email . '%')
            ->orderBy('v.dateDepart', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByClientName(string $name): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.pays LIKE :pays')
            ->setParameter('pays', '%' . $name . '%')
            ->orderBy('v.dateDepart', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
