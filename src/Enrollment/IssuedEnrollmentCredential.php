<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

use DateTimeImmutable;

final readonly class IssuedEnrollmentCredential
{
    /** @param list<string> $allowedAudiences */
    public function __construct(
        public string $token,
        public string $selector,
        public int|string $subjectId,
        public string $audience,
        public array $allowedAudiences,
        public ?DateTimeImmutable $expiresAt = null,
        public int|string|null $id = null,
    ) {
    }
}
