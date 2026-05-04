<?php

namespace App\Repository;

use App\Entity\QuizSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method QuizSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method QuizSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method QuizSession[]    findAll()
 * @method QuizSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuizSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSession::class);
    }
}
