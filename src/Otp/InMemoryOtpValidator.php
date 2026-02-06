<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

use function hash_equals;
use function max;

final class InMemoryOtpValidator implements OtpValidatorInterface
{
    /** @var array<string, string> */
    private array $codes;

    /** @var array<string, int> */
    private array $attempts = [];

    /**
     * @param array<string, string> $codes
     */
    public function __construct(
        array $codes = [],
        private readonly int $maxAttempts = 3,
    ) {
        $this->codes = $codes;
    }

    public function setCode(string $identifier, string $code): void
    {
        $this->codes[$identifier]    = $code;
        $this->attempts[$identifier] = 0;
    }

    public function validate(string $identifier, string $code): bool
    {
        $attempts = $this->attempts[$identifier] ?? 0;
        if ($attempts >= $this->maxAttempts) {
            return false;
        }

        $expected = $this->codes[$identifier] ?? null;
        if ($expected === null) {
            $this->attempts[$identifier] = $attempts + 1;

            return false;
        }

        if (!hash_equals($expected, $code)) {
            $this->attempts[$identifier] = $attempts + 1;

            return false;
        }

        $this->attempts[$identifier] = 0;

        return true;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function attemptsLeft(string $identifier): int
    {
        $attempts = $this->attempts[$identifier] ?? 0;

        return max(0, $this->maxAttempts - $attempts);
    }

    public function resetAttempts(string $identifier): void
    {
        unset($this->attempts[$identifier]);
    }
}
