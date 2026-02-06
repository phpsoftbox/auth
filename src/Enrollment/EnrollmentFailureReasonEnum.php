<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

enum EnrollmentFailureReasonEnum: string
{
    case Invalid     = 'invalid';
    case Expired     = 'expired';
    case AlreadyUsed = 'already_used';
}
