<?php

namespace App\Security;

final class LegacyPasswordHasher
{
    public function hashPassword(string $password): string
    {
        return base64_encode(hash('sha256', $password, true));
    }

    public function checkPassword(?string $plainPassword, ?string $hashedPassword): bool
    {
        $plainPassword = (string) $plainPassword;
        $hashedPassword = trim((string) $hashedPassword);

        if ($plainPassword === '' || $hashedPassword === '') {
            return false;
        }

        return hash_equals($hashedPassword, $this->hashPassword($plainPassword));
    }
}
