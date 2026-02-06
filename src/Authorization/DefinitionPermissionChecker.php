<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function is_string;
use function trim;

final class DefinitionPermissionChecker implements PermissionCheckerInterface
{
    private ?RolePermissionMap $map = null;

    public function __construct(
        private readonly RoleDefinitionProviderInterface $definitions,
        private readonly RoleResolverInterface $roles,
        private readonly PermissionResolver $resolver = new PermissionResolver(),
        private readonly PermissionCatalog $catalog = new PermissionCatalog(),
        private readonly string $adminPermission = 'admin.access',
    ) {
    }

    public function can(mixed $user, string $permission, mixed $subject = null): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $roleNames = $this->roles->resolve($user);
        if ($roleNames === []) {
            return false;
        }

        $map = $this->getMap();

        foreach ($roleNames as $roleName) {
            if (!is_string($roleName)) {
                continue;
            }

            $role = $map->role($roleName);
            if ($role === null) {
                continue;
            }

            if ($permission === $this->adminPermission && $role->adminAccess) {
                return true;
            }

            if ($role->allows($permission)) {
                return true;
            }
        }

        return false;
    }

    private function getMap(): RolePermissionMap
    {
        if ($this->map === null) {
            $this->map = RolePermissionMap::build(
                definitions: $this->definitions->load(),
                resolver: $this->resolver,
                catalog: $this->catalog,
            );
        }

        return $this->map;
    }
}
