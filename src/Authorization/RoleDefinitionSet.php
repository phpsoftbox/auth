<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class RoleDefinitionSet
{
    /**
     * @param list<RoleDefinition> $roles
     * @param list<class-string<PermissionModelInterface>> $permissionModels
     * @param array<string, string|null>|list<string> $permissions
     */
    public function __construct(
        public array $roles,
        public array $permissionModels = [],
        public array $permissions = [],
    ) {
    }
}
