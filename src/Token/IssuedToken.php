<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;

final readonly class IssuedToken
{
    public function __construct(
        public string $token,
        public string $selector,
        public int|string $userId,
        public ?DateTimeImmutable $expiresAt = null,
        public ?int $id = null,
    ) {
    }
}
