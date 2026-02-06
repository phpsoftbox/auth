<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;

final readonly class TokenRecord
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int|string $id,
        public int|string $userId,
        public string $selector,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $revokedAt = null,
        public ?DateTimeImmutable $lastUsedAt = null,
        public array $metadata = [],
    ) {
    }
}
