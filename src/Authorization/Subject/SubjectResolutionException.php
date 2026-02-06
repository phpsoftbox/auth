<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use RuntimeException;
use Throwable;

final class SubjectResolutionException extends RuntimeException
{
    public function __construct(
        public readonly AccessDecision $decision,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : (string) $decision->reason, 0, $previous);
    }
}
