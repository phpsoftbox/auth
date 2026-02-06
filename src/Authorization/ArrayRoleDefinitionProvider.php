<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;
use InvalidArgumentException;

use function is_array;
use function is_string;

final class ArrayRoleDefinitionProvider implements RoleDefinitionProviderInterface
{
    /**
     * @param list<RoleDefinition|array<string, mixed>> $roles
     * @param list<class-string<PermissionModelInterface>> $permissionModels
     * @param array<string, string|null>|list<string|BackedEnum> $permissions
     */
    public function __construct(
        private readonly array $roles,
        private readonly array $permissionModels = [],
        private readonly array $permissions = [],
    ) {
    }

    public function load(): RoleDefinitionSet
    {
        $roles = [];
        foreach ($this->roles as $role) {
            if ($role instanceof RoleDefinition) {
                $roles[] = $role;
                continue;
            }

            if (is_array($role)) {
                $roles[] = $this->fromArray($role);
            }
        }

        return new RoleDefinitionSet($roles, $this->permissionModels, $this->permissions);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fromArray(array $data): RoleDefinition
    {
        $name = (string) ($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('RoleDefinition name is required.');
        }

        $definition = new RoleDefinition(
            name: $name,
            label: isset($data['label']) ? (string) $data['label'] : null,
            adminAccess: (bool) ($data['admin_access'] ?? false),
            root: (bool) ($data['root'] ?? false),
        );

        if ((bool) ($data['all'] ?? false)) {
            $definition->allowAll();
        }

        foreach ((array) ($data['permissions'] ?? []) as $permission) {
            if (is_array($permission)) {
                $resource = PermissionName::from($permission['resource'] ?? null);
                if ($resource !== null) {
                    $definition->allow(
                        $resource,
                        $permission['actions'] ?? null,
                        is_string($permission['scope'] ?? null) ? (string) $permission['scope'] : null,
                    );
                }
                continue;
            }

            $definition->allow($permission);
        }

        foreach ((array) ($data['deny'] ?? $data['except'] ?? []) as $permission) {
            if (is_string($permission) || $permission instanceof BackedEnum) {
                $definition->deny($permission);
            }
        }

        return $definition;
    }
}
