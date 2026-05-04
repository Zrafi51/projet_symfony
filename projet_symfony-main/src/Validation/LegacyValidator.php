<?php

namespace App\Validation;

use DateTimeInterface;

final class LegacyValidator
{
    public static function isValidEmail(?string $email): bool
    {
        $email = trim((string) $email);

        return $email !== ''
            && strlen($email) <= 100
            && (bool) preg_match('/^[A-Za-z0-9+_.-]+@(.+)$/', $email);
    }

    public static function isValidPassword(?string $password): bool
    {
        $password = (string) $password;

        return trim($password) !== ''
            && strlen($password) >= 6
            && strlen($password) <= 255;
    }

    public static function isValidPhone(?string $phone): bool
    {
        $phone = trim((string) $phone);

        return $phone !== ''
            && (bool) preg_match('/^\+?[0-9\s]{8,20}$/', $phone);
    }

    public static function isValidPhoneOrBlank(?string $phone): bool
    {
        $phone = trim((string) $phone);

        return $phone === '' || self::isValidPhone($phone);
    }

    public static function isValidName(?string $name): bool
    {
        $name = trim((string) $name);

        return $name !== ''
            && mb_strlen($name) >= 2
            && mb_strlen($name) <= 50
            && (bool) preg_match("/^[\\p{L}][\\p{L}\\s'-]*$/u", $name);
    }

    public static function hasMaxLength(?string $value, int $maxLength): bool
    {
        return mb_strlen((string) $value) <= $maxLength;
    }

    public static function isValidBirthDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!$value instanceof DateTimeInterface) {
            try {
                $value = new \DateTimeImmutable((string) $value);
            } catch (\Throwable) {
                return false;
            }
        }

        $today = new \DateTimeImmutable('today');

        return $value <= $today;
    }
}
