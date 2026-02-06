<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

final readonly class RoleDefinitionSet
{
    /**
     * @param list<RoleDefinition> $roles
     * @param list<class-string<PermissionModelInterface>> $permissionModels
     * @param array<string, string|null>|list<string|BackedEnum> $permissions
     */
    public function __construct(
        public array $roles,
        public array $permissionModels = [],
        public array $permissions = [],
    ) {
    }
}
