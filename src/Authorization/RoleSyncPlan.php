<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class RoleSyncPlan
{
    /**
     * @param list<string> $rolesToCreate
     * @param list<string> $rolesToDelete
     * @param list<string> $permissionsToCreate
     * @param list<string> $permissionsToDelete
     */
    public function __construct(
        public array $rolesToCreate,
        public array $rolesToDelete,
        public array $permissionsToCreate,
        public array $permissionsToDelete,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->rolesToCreate !== []
            || $this->rolesToDelete !== []
            || $this->permissionsToCreate !== []
            || $this->permissionsToDelete !== [];
    }
}
