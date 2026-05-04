<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class ForumUserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns a map of username => profilePhotoPath for the given list of usernames.
     * Used to display author avatars on posts/comments (since auteur is stored as a
     * plain string, not a User association).
     *
     * @param string[] $usernames
     * @return array<string, string|null>
     */
    public function getPhotoMapByUsernames(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        $rows = $this->createQueryBuilder('u')
            ->select('u.username', 'u.profilePhotoPath')
            ->where('u.username IN (:names)')
            ->setParameter('names', $usernames)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['username']] = $r['profilePhotoPath'] ?? null;
        }
        return $map;
    }

    /** Usernames of all accounts flagged as private — used to exclude them from
     *  the public "Discover" feed. */
    public function getPrivateUsernames(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.username')
            ->where('u.isPrivate = :p')
            ->setParameter('p', true)
            ->getQuery()
            ->getArrayResult();
        return array_column($rows, 'username');
    }

    /** LIKE-search on username (used by the feed's search sidebar). */
    public function searchByUsername(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username LIKE :t')
            ->setParameter('t', '%' . $term . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
