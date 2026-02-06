<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

    /**
     * Проверяет, что sync() использует транзакционный wrapper store.
     */
    #[Test]
    public function syncRunsInsideTransactionWhenStoreSupportsIt(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::named('manager')->allow(TestPermission::class, [PermissionActionEnum::READ]),
            ],
            permissionModels: [TestPermission::class],
        );

        $permissions     = new TransactionalInMemoryPermissionStore();
        $roles           = new InMemoryRoleStore();
        $rolePermissions = new InMemoryRolePermissionStore();

        $sync = new RoleSynchronizer($provider, $permissions, $roles, $rolePermissions);

        $sync->sync();

        self::assertSame(1, $permissions->transactions);
    }
}
