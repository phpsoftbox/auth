<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

use function time;

final class OtpState
{
    public function __construct(
        private readonly ?string $code,
        private readonly int $attempts,
        private readonly int $maxAttempts,
        private readonly ?int $expiresAt,
        private readonly ?int $lockedUntil,
    ) {
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function attemptsLeft(): int
    {
        $left = $this->maxAttempts - $this->attempts;

        return $left > 0 ? $left : 0;
    }

    public function expiresAt(): ?int
    {
        return $this->expiresAt;
    }

    public function isExpired(int $now = 0): bool
    {
        $now = $now > 0 ? $now : time();

        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function lockedUntil(): ?int
    {
        return $this->lockedUntil;
    }

    public function isLocked(int $now = 0): bool
    {
        $now = $now > 0 ? $now : time();

        return $this->lockedUntil !== null && $this->lockedUntil > $now;
    }
}
