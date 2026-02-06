<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;

final readonly class CredentialRecord
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int|string $id,
        public int|string $subjectId,
        public string $selector,
        public string $purpose,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $revokedAt = null,
        public ?DateTimeImmutable $lastUsedAt = null,
        public array $metadata = [],
        public ?string $audience = null,
    ) {
    }
}
