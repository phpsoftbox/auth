<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

use DateTimeImmutable;

final readonly class EnrollmentGrant
{
    /**
     * @param list<string> $allowedAudiences
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int|string $id,
        public int|string $subjectId,
        public string $audience,
        public array $allowedAudiences,
        public ?DateTimeImmutable $expiresAt = null,
        public array $metadata = [],
    ) {
    }
}
