<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function array_values;

final class RolePermissionMap
{
    /**
     * @param array<string, RolePermissionSet> $roles
     */
    private function __construct(
        private array $roles,
    ) {
    }

    public static function build(
        RoleDefinitionSet $definitions,
        PermissionResolver $resolver = new PermissionResolver(),
        PermissionCatalog $catalog = new PermissionCatalog(),
    ): self {
        $allPermissions = [];
        foreach ($catalog->build($definitions->permissionModels, $definitions->permissions) as $definition) {
            $allPermissions[$definition->name] = true;
        }

        $roles = [];
        foreach ($definitions->roles as $role) {
            $allowed = [];
            if ($role->allowsAll()) {
                $allowed = $allPermissions;
            } else {
                foreach ($role->grants() as $grant) {
                    foreach ($resolver->resolve($grant) as $name) {
                        $allowed[$name] = true;
                    }
                }
            }

            $denied = [];
            foreach ($role->denied() as $deny) {
                foreach ($resolver->resolve($deny) as $name) {
                    $denied[$name] = true;
                }
            }

            if ($denied !== []) {
                foreach ($denied as $name => $_) {
                    unset($allowed[$name]);
                }
            }

            $roles[$role->name] = new RolePermissionSet(
                name: $role->name,
                allowAll: $role->allowsAll(),
                adminAccess: $role->adminAccess,
                root: $role->root,
                allowed: $allowed,
                denied: $denied,
            );
        }

        return new self($roles);
    }

    public function role(string $name): ?RolePermissionSet
    {
        return $this->roles[$name] ?? null;
    }

    /**
     * @return list<RolePermissionSet>
     */
    public function all(): array
    {
        return array_values($this->roles);
    }
}
