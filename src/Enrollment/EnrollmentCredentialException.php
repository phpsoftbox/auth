<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

use RuntimeException;

final class EnrollmentCredentialException extends RuntimeException
{
    public function __construct(
        public readonly EnrollmentFailureReasonEnum $reason,
    ) {
        parent::__construct(match ($reason) {
            EnrollmentFailureReasonEnum::Invalid     => 'Invalid enrollment credential.',
            EnrollmentFailureReasonEnum::Expired     => 'Enrollment credential has expired.',
            EnrollmentFailureReasonEnum::AlreadyUsed => 'Enrollment credential has already been used.',
        });
    }
}
