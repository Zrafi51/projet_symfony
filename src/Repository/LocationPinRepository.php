<?php

namespace App\Repository;

use App\Entity\LocationPin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocationPin>
 */
class LocationPinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationPin::class);
    }

    /** The current user's active pin (not expired), or null. */
    public function findActiveFor(string $username): ?LocationPin
    {
        $pin = $this->findOneBy(['username' => $username]);
        if ($pin && $pin->isExpired()) {
            return null;
        }
        return $pin;
    }

    /**
     * Insert-or-update: a user has at most one pin row. Re-pinning replaces the
     * old lat/lng/expiry instead of spawning duplicates.
     */
    public function upsertFor(string $username, float $lat, float $lng, \DateTimeInterface $expiresAt, ?string $label = null): LocationPin
    {
        $pin = $this->findOneBy(['username' => $username]);
        if (!$pin) {
            $pin = (new LocationPin())->setUsername($username);
        }
        $pin->setLatitude($lat)
            ->setLongitude($lng)
            ->setExpiresAt($expiresAt)
            ->setLabel($label);
        $em = $this->getEntityManager();
        $em->persist($pin);
        $em->flush();
        return $pin;
    }

    public function removeFor(string $username): void
    {
        $pin = $this->findOneBy(['username' => $username]);
        if ($pin) {
            $em = $this->getEntityManager();
            $em->remove($pin);
            $em->flush();
        }
    }

    /**
     * Pins of the people I follow that haven't expired yet. Used to render
     * the shared map. Includes self via $includeSelf so the viewer also sees
     * their own pin on the map.
     *
     * @param string[] $followingUsernames
     * @return LocationPin[]
     */
    public function findActiveForUsers(array $followingUsernames): array
    {
        if (empty($followingUsernames)) {
            return [];
        }
        return $this->createQueryBuilder('p')
            ->where('p.username IN (:users)')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('users', $followingUsernames)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** Housekeeping — delete expired pins. Safe to call opportunistically. */
    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.expiresAt <= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()->execute();
    }
}
