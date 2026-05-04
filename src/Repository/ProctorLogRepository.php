<?php

namespace App\Repository;

use App\Entity\ProctorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProctorLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProctorLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProctorLog[]    findAll()
 * @method ProctorLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProctorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProctorLog::class);
    }
}
