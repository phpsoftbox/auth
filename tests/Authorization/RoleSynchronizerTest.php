<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\PermissionModel;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PhpSoftBox\Auth\Authorization\Store\PermissionStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\RolePermissionStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function in_array;

#[CoversClass(RoleSynchronizer::class)]
final class RoleSynchronizerTest extends TestCase
{
    /**
     * Проверяет, что синхронизация создаёт права и привязки ролей.
     */
    #[Test]
    public function syncsRolesAndPermissions(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin('admin')->allowAll(),
                RoleDefinition::named('manager')->allow(TestPermission::class, [PermissionActionEnum::READ]),
            ],
            permissionModels: [TestPermission::class],
            permissions: ['admin.access' => 'Админка'],
        );

        $permissions     = new InMemoryPermissionStore();
        $roles           = new InMemoryRoleStore();
        $rolePermissions = new InMemoryRolePermissionStore();

        $sync = new RoleSynchronizer($provider, $permissions, $roles, $rolePermissions);

        $sync->sync();

        self::assertSame(['admin', 'manager'], $roles->names());
        self::assertContains('test.base.read', $permissions->names());
        self::assertContains('admin.access', $permissions->names());

        $adminId   = $roles->findIdByName('admin');
        $managerId = $roles->findIdByName('manager');

        self::assertNotNull($adminId);
        self::assertNotNull($managerId);

        self::assertCount(6, $rolePermissions->listPermissionIds($adminId));
        self::assertCount(1, $rolePermissions->listPermissionIds($managerId));
    }

    /**
     * Проверяет, что синхронизация удаляет лишние роли и пермишены.
     */
    #[Test]
    public function removesObsoleteRolesAndPermissions(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::named('manager')->allow(TestPermission::class, [PermissionActionEnum::READ]),
            ],
            permissionModels: [TestPermission::class],
            permissions: ['admin.access' => 'Админка'],
        );

        $permissions     = new InMemoryPermissionStore();
        $roles           = new InMemoryRoleStore();
        $rolePermissions = new InMemoryRolePermissionStore();

        $legacyPermissionId = $permissions->create('legacy.permission', null);
        $legacyRoleId       = $roles->create('legacy', 'Legacy');
        $rolePermissions->attach($legacyRoleId, $legacyPermissionId);

        $sync = new RoleSynchronizer($provider, $permissions, $roles, $rolePermissions);

        $sync->sync();

        self::assertFalse(in_array('legacy.permission', $permissions->names(), true));
        self::assertFalse(in_array('legacy', $roles->names(), true));
        self::assertSame([], $rolePermissions->listPermissionIds($legacyRoleId));
    }
}

final class TestPermission extends PermissionModel
{
    public static function resource(): string
    {
        return 'test';
    }
}

final class InMemoryPermissionStore implements PermissionStoreInterface
{
    private int $nextId        = 1;
    private array $permissions = [];

    public function findIdByName(string $name): ?int
    {
        return $this->permissions[$name]['id'] ?? null;
    }

    public function create(string $name, ?string $label = null): int
    {
        $id                       = $this->nextId++;
        $this->permissions[$name] = ['id' => $id, 'label' => $label];

        return $id;
    }

    public function updateLabel(int $id, ?string $label = null): void
    {
        foreach ($this->permissions as $name => $data) {
            if ($data['id'] === $id) {
                $this->permissions[$name]['label'] = $label;

                return;
            }
        }
    }

    public function names(): array
    {
        return array_keys($this->permissions);
    }

    public function listIdsByName(): array
    {
        $map = [];
        foreach ($this->permissions as $name => $data) {
            $map[$name] = $data['id'];
        }

        return $map;
    }

    public function deleteByIds(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        foreach ($this->permissions as $name => $data) {
            if (in_array($data['id'], $ids, true)) {
                unset($this->permissions[$name]);
            }
        }
    }
}

final class InMemoryRoleStore implements RoleStoreInterface
{
    private int $nextId  = 1;
    private array $roles = [];

    public function findIdByName(string $name): ?int
    {
        return $this->roles[$name]['id'] ?? null;
    }

    public function create(string $name, ?string $label = null, bool $adminAccess = false): int
    {
        $id                 = $this->nextId++;
        $this->roles[$name] = ['id' => $id, 'label' => $label, 'admin_access' => $adminAccess];

        return $id;
    }

    public function update(string $name, ?string $label = null, bool $adminAccess = false): void
    {
        if (!isset($this->roles[$name])) {
            return;
        }

        $this->roles[$name]['label']        = $label;
        $this->roles[$name]['admin_access'] = $adminAccess;
    }

    public function names(): array
    {
        return array_keys($this->roles);
    }

    public function listIdsByName(): array
    {
        $map = [];
        foreach ($this->roles as $name => $data) {
            $map[$name] = $data['id'];
        }

        return $map;
    }

    public function deleteByIds(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        foreach ($this->roles as $name => $data) {
            if (in_array($data['id'], $ids, true)) {
                unset($this->roles[$name]);
            }
        }
    }
}

final class InMemoryRolePermissionStore implements RolePermissionStoreInterface
{
    private array $map = [];

    public function listPermissionIds(int $roleId): array
    {
        return $this->map[$roleId] ?? [];
    }

    public function attach(int $roleId, int $permissionId): void
    {
        $this->map[$roleId] ??= [];
        if (!in_array($permissionId, $this->map[$roleId], true)) {
            $this->map[$roleId][] = $permissionId;
        }
    }

    public function detach(int $roleId, int $permissionId): void
    {
        if (!isset($this->map[$roleId])) {
            return;
        }

        $this->map[$roleId] = array_values(array_filter(
            $this->map[$roleId],
            static fn (int $id): bool => $id !== $permissionId,
        ));
    }

    public function detachByRoleId(int $roleId): void
    {
        unset($this->map[$roleId]);
    }

    public function detachByPermissionId(int $permissionId): void
    {
        foreach ($this->map as $roleId => $ids) {
            $this->map[$roleId] = array_values(array_filter(
                $ids,
                static fn (int $id): bool => $id !== $permissionId,
            ));
        }
    }
}
