<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

final readonly class OwnershipSubject
{
    public function __construct(
        public string $type,
        public int|string|null $id,
        public int|string|null $ownerId,
        public string $routeParam,
        public mixed $value = null,
    ) {
    }
}
