<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Authorization\Store\PermissionStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\RolePermissionStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;

use function array_diff;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function sort;

use const SORT_STRING;

final class RoleSynchronizer
{
    public function __construct(
        private readonly RoleDefinitionProviderInterface $definitions,
        private readonly PermissionStoreInterface $permissions,
        private readonly RoleStoreInterface $roles,
        private readonly RolePermissionStoreInterface $rolePermissions,
        private readonly PermissionNameFormatterInterface $formatter = new DefaultPermissionNameFormatter(),
    ) {
    }

    public function sync(): void
    {
        $set = $this->definitions->load();

        $catalog  = new PermissionCatalog($this->formatter);
        $resolver = new PermissionResolver($this->formatter);

        $permissionDefinitions = $catalog->build($set->permissionModels, $set->permissions);

        $existingPermissions = $this->permissions->listIdsByName();
        $permissionIds       = $existingPermissions;
        $expectedPermissions = $this->buildExpectedPermissions($set, $permissionDefinitions, $resolver);

        foreach ($permissionDefinitions as $definition) {
            $id = $this->permissions->findIdByName($definition->name);
            if ($id === null) {
                $id = $this->permissions->create($definition->name, $definition->label);
            } elseif ($definition->label !== null) {
                $this->permissions->updateLabel($id, $definition->label);
            }
            $permissionIds[$definition->name] = $id;
        }

        foreach (array_keys($expectedPermissions) as $permissionName) {
            if (!isset($permissionIds[$permissionName])) {
                $id                             = $this->permissions->create($permissionName, null);
                $permissionIds[$permissionName] = $id;
            }
        }

        $obsoletePermissionIds = [];
        foreach ($existingPermissions as $name => $id) {
            if (!isset($expectedPermissions[$name])) {
                $obsoletePermissionIds[] = $id;
                unset($permissionIds[$name]);
            }
        }

        foreach ($obsoletePermissionIds as $permissionId) {
            $this->rolePermissions->detachByPermissionId((int) $permissionId);
        }
        $this->permissions->deleteByIds(array_values(array_unique($obsoletePermissionIds)));

        $existingRoles   = $this->roles->listIdsByName();
        $expectedRoles   = $this->buildExpectedRoles($set);
        $obsoleteRoleIds = [];
        foreach ($existingRoles as $name => $id) {
            if (!isset($expectedRoles[$name])) {
                $obsoleteRoleIds[] = $id;
            }
        }
        foreach ($obsoleteRoleIds as $roleId) {
            $this->rolePermissions->detachByRoleId((int) $roleId);
        }
        $this->roles->deleteByIds(array_values(array_unique($obsoleteRoleIds)));

        foreach ($set->roles as $role) {
            $roleId = $this->roles->findIdByName($role->name);
            if ($roleId === null) {
                $roleId = $this->roles->create($role->name, $role->label, $role->adminAccess);
            } else {
                $this->roles->update($role->name, $role->label, $role->adminAccess);
            }

            $allowed = [];
            if ($role->allowsAll()) {
                $allowed = array_keys($permissionIds);
            } else {
                foreach ($role->grants() as $grant) {
                    $allowed = array_merge($allowed, $resolver->resolve($grant));
                }
            }

            if ($role->denied() !== []) {
                $allowed = array_diff($allowed, $role->denied());
            }

            $allowed = array_values(array_unique($allowed));

            $targetIds = [];
            foreach ($allowed as $permissionName) {
                if (!isset($permissionIds[$permissionName])) {
                    $newId                          = $this->permissions->create($permissionName, null);
                    $permissionIds[$permissionName] = $newId;
                }
                $targetIds[] = $permissionIds[$permissionName];
            }

            $current = $this->rolePermissions->listPermissionIds($roleId);

            $toAttach = array_diff($targetIds, $current);
            foreach ($toAttach as $permissionId) {
                $this->rolePermissions->attach($roleId, (int) $permissionId);
            }

            $toDetach = array_diff($current, $targetIds);
            foreach ($toDetach as $permissionId) {
                $this->rolePermissions->detach($roleId, (int) $permissionId);
            }
        }
    }

    public function plan(): RoleSyncPlan
    {
        $set = $this->definitions->load();

        $catalog  = new PermissionCatalog($this->formatter);
        $resolver = new PermissionResolver($this->formatter);

        $permissionDefinitions = $catalog->build($set->permissionModels, $set->permissions);
        $expectedPermissions   = $this->buildExpectedPermissions($set, $permissionDefinitions, $resolver);
        $existingPermissions   = $this->permissions->listIdsByName();

        $permissionsToCreate = [];
        foreach (array_keys($expectedPermissions) as $name) {
            if (!isset($existingPermissions[$name])) {
                $permissionsToCreate[] = $name;
            }
        }
        $permissionsToDelete = [];
        foreach ($existingPermissions as $name => $id) {
            if (!isset($expectedPermissions[$name])) {
                $permissionsToDelete[] = $name;
            }
        }

        $expectedRoles = $this->buildExpectedRoles($set);
        $existingRoles = $this->roles->listIdsByName();

        $rolesToCreate = [];
        foreach (array_keys($expectedRoles) as $name) {
            if (!isset($existingRoles[$name])) {
                $rolesToCreate[] = $name;
            }
        }
        $rolesToDelete = [];
        foreach ($existingRoles as $name => $id) {
            if (!isset($expectedRoles[$name])) {
                $rolesToDelete[] = $name;
            }
        }

        sort($permissionsToCreate, SORT_STRING);
        sort($permissionsToDelete, SORT_STRING);
        sort($rolesToCreate, SORT_STRING);
        sort($rolesToDelete, SORT_STRING);

        return new RoleSyncPlan(
            rolesToCreate: $rolesToCreate,
            rolesToDelete: $rolesToDelete,
            permissionsToCreate: $permissionsToCreate,
            permissionsToDelete: $permissionsToDelete,
        );
    }

    /**
     * @param list<PermissionDefinition> $permissionDefinitions
     * @return array<string, true>
     */
    private function buildExpectedPermissions(RoleDefinitionSet $set, array $permissionDefinitions, PermissionResolver $resolver): array
    {
        $expectedPermissions = [];

        foreach ($permissionDefinitions as $definition) {
            $expectedPermissions[$definition->name] = true;
        }

        foreach ($set->roles as $role) {
            foreach ($role->grants() as $grant) {
                foreach ($resolver->resolve($grant) as $permissionName) {
                    $expectedPermissions[$permissionName] = true;
                }
            }
            foreach ($role->denied() as $permissionName) {
                foreach ($resolver->resolve($permissionName) as $resolved) {
                    $expectedPermissions[$resolved] = true;
                }
            }
        }

        return $expectedPermissions;
    }

    /**
     * @return array<string, true>
     */
    private function buildExpectedRoles(RoleDefinitionSet $set): array
    {
        $expected = [];
        foreach ($set->roles as $role) {
            $expected[$role->name] = true;
        }

        return $expected;
    }
}
