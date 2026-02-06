<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

interface OtpValidatorInterface
{
    public function validate(string $identifier, string $code): bool;

    public function maxAttempts(): int;

    public function attemptsLeft(string $identifier): int;

    public function resetAttempts(string $identifier): void;
}
