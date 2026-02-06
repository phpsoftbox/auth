<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;

final readonly class IssuedCredential
{
    public function __construct(
        public string $token,
        public string $selector,
        public int|string $subjectId,
        public string $purpose,
        public ?DateTimeImmutable $expiresAt = null,
        public ?int $id = null,
        public ?string $audience = null,
    ) {
    }
}
