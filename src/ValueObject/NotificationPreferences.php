<?php

namespace App\ValueObject;

final class NotificationPreferences
{
    public function __construct(
        private readonly bool $security,
        private readonly bool $booking,
        private readonly bool $forum,
        private readonly bool $offers,
    ) {
    }

    public static function defaults(): self
    {
        return new self(true, true, true, false);
    }

    public function security(): bool
    {
        return $this->security;
    }

    public function booking(): bool
    {
        return $this->booking;
    }

    public function forum(): bool
    {
        return $this->forum;
    }

    public function offers(): bool
    {
        return $this->offers;
    }
}
