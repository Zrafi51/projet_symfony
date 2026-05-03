<?php

namespace App\Repository;

use App\Database\PdoConnectionFactory;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository implements PasswordUpgraderInterface
{
    public function __construct(private PdoConnectionFactory $pdoFactory) {}

    private function pdo(): \PDO
    {
        return $this->pdoFactory->getConnection();
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
            } catch (\Exception) {}
        }
        return $u;
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Limited findOneBy: supports criteria with single key 'username' or 'email'.
     */
    public function findOneBy(array $criteria): ?User
    {
        if (isset($criteria['username'])) {
            $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE username = :v LIMIT 1');
            $stmt->execute(['v' => $criteria['username']]);
        } elseif (isset($criteria['email'])) {
            $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = :v LIMIT 1');
            $stmt->execute(['v' => $criteria['email']]);
        } else {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return User[] */
    public function findAll(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM users ORDER BY username ASC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    /** @return User[] */
    public function findAllOrderedByName(): array
    {
        return $this->findAll();
    }

    /**
     * Fetch the User rows whose username is in the given list (used for the
     * follower/following lists).
     *
     * @param string[] $usernames
     * @return User[]
     */
    public function findByUsernames(array $usernames): array
    {
        if (empty($usernames)) return [];
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->pdo()->prepare("SELECT * FROM users WHERE username IN ($placeholders) ORDER BY username ASC");
        $stmt->execute(array_values($usernames));
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param string[] $usernames
     * @return array<string, string|null>
     */
    public function getPhotoMapByUsernames(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->pdo()->prepare("SELECT username, profile_photo_path FROM users WHERE username IN ($placeholders)");
        $stmt->execute(array_values($usernames));
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $map[$r['username']] = $r['profile_photo_path'] ?? null;
        }
        return $map;
    }

    /** @return string[] */
    public function getPrivateUsernames(): array
    {
        $rows = $this->pdo()->query('SELECT username FROM users WHERE is_private = 1')->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'username');
    }

    /** @return User[] */
    public function searchByUsername(string $term, int $limit = 10): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE username LIKE :t ORDER BY username ASC LIMIT ' . (int) $limit);
        $stmt->execute(['t' => '%' . $term . '%']);
        return array_map(fn($r) => $this->hydrate($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(User $u): User
    {
        $pdo = $this->pdo();
        $rolesJson = json_encode(array_values(array_unique(array_diff($u->getRoles(), ['ROLE_USER']))));
        // Fallback if roles array is empty
        if ($rolesJson === '[]') {
            $rolesJson = '[]';
        }
        if ($u->getId() === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, email, password, roles, profile_photo_path, bio, is_private, created_at)
                 VALUES (:username, :email, :password, :roles, :photo, :bio, :priv, :created)'
            );
            $stmt->execute([
                'username' => $u->getUsername(),
                'email' => $u->getEmail(),
                'password' => $u->getPassword(),
                'roles' => $rolesJson,
                'photo' => $u->getProfilePhotoPath(),
                'bio' => $u->getBio(),
                'priv' => $u->isPrivate() ? 1 : 0,
                'created' => ($u->getCreatedAt() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $u->setId((int) $pdo->lastInsertId());
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET username = :username, email = :email, password = :password,
                 roles = :roles, profile_photo_path = :photo, bio = :bio, is_private = :priv
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $u->getId(),
                'username' => $u->getUsername(),
                'email' => $u->getEmail(),
                'password' => $u->getPassword(),
                'roles' => $rolesJson,
                'photo' => $u->getProfilePhotoPath(),
                'bio' => $u->getBio(),
                'priv' => $u->isPrivate() ? 1 : 0,
            ]);
        }
        return $u;
    }

    public function remove(User $u): void
    {
        if ($u->getId() === null) return;
        $stmt = $this->pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $u->getId()]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $stmt = $this->pdo()->prepare('UPDATE users SET password = :p WHERE username = :u');
        $stmt->execute([
            'p' => $newHashedPassword,
            'u' => $user->getUserIdentifier(),
        ]);
        $user->setPassword($newHashedPassword);
    }
}
