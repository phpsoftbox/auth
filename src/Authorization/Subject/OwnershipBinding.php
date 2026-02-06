<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

final readonly class OwnershipBinding
{
    public function __construct(
        public string $routeParam,
        public ?string $subject,
        public OwnershipResolverInterface $owner,
    ) {
    }
}
