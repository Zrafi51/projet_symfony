<?php

namespace App\Security;

use App\Database\PdoConnectionFactory;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users directly from the `users` table via raw PDO. Replaces the
 * Doctrine-backed entity provider so the project can run without ORM.
 */
class PdoUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private readonly PdoConnectionFactory $pdoFactory)
    {
    }

    public function loadUserByIdentifier(string $username): UserInterface
    {
        $pdo = $this->pdoFactory->getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, username, email, password, roles, profile_photo_path, bio, is_private, created_at
             FROM users WHERE username = :u LIMIT 1'
        );
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $username));
        }
        return $this->hydrate($row);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }
        $pdo = $this->pdoFactory->getConnection();
        $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE username = :u');
        $stmt->execute([
            'p' => $newHashedPassword,
            'u' => $user->getUserIdentifier(),
        ]);
        $user->setPassword($newHashedPassword);
    }

    private function hydrate(array $row): User
    {
        $u = new User();
        $u->setId(isset($row['id']) ? (int) $row['id'] : null);
        $u->setUsername((string) $row['username']);
        $u->setEmail($row['email'] ?? null);
        $u->setPassword((string) ($row['password'] ?? ''));

        $roles = [];
        if (!empty($row['roles'])) {
            $decoded = json_decode((string) $row['roles'], true);
            if (is_array($decoded)) {
                $roles = $decoded;
            }
        }
        $u->setRoles($roles);

        $u->setProfilePhotoPath($row['profile_photo_path'] ?? null);
        $u->setBio($row['bio'] ?? null);
        $u->setIsPrivate(!empty($row['is_private']));

        if (!empty($row['created_at'])) {
            try {
                $u->setCreatedAt(new \DateTimeImmutable((string) $row['created_at']));
            } catch (\Exception) {
                // leave default
            }
        }
        return $u;
    }
}
