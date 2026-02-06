<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\UserRoleManager;
use PhpSoftBox\Auth\Exception\RoleNotAssignedException;
use PhpSoftBox\Auth\Exception\RoleNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserRoleManager::class)]
#[CoversClass(RoleNotAssignedException::class)]
#[CoversClass(RoleNotFoundException::class)]
final class UserRoleManagerTest extends TestCase
{
    /**
     * Проверяет назначение и чтение ролей пользователя.
     */
    #[Test]
    public function assignsAndReadsRoles(): void
    {
        $roles     = new UserRoleManagerRoleStore();
        $userRoles = new UserRoleManagerUserRoleStore();

        $manager = new UserRoleManager($userRoles, $roles);

        $roles->add('admin', 1);
        $roles->add('manager', 2);
        $userRoles->registerRole(1, 'admin');
        $userRoles->registerRole(2, 'manager');

        $user = new UserRoleManagerUserId(10);

        $manager->assignRole($user, 'admin');

        self::assertSame(['admin'], $manager->roles($user));
        self::assertSame('admin', $manager->role($user));

        $manager->assignRole($user, 'manager');
        self::assertSame(['admin', 'manager'], $manager->roles($user));
    }

    /**
     * Проверяет замену ролей и исключение при отсутствии.
     */
    #[Test]
    public function replacesRolesAndThrowsOnMissing(): void
    {
        $roles     = new UserRoleManagerRoleStore();
        $userRoles = new UserRoleManagerUserRoleStore();

        $manager = new UserRoleManager($userRoles, $roles);

        $roles->add('admin', 1);
        $roles->add('support', 2);
        $userRoles->registerRole(1, 'admin');
        $userRoles->registerRole(2, 'support');

        $user = new UserRoleManagerUserId(20);

        $manager->assignRole($user, 'admin');
        $manager->replaceRoles($user, ['support']);

        self::assertSame(['support'], $manager->roles($user));

        $this->expectException(RoleNotAssignedException::class);
        $manager->requireRole(new UserRoleManagerUserId(99));
    }

    /**
     * Проверяет ошибку при назначении несуществующей роли.
     */
    #[Test]
    public function throwsWhenRoleNotFound(): void
    {
        $roles     = new UserRoleManagerRoleStore();
        $userRoles = new UserRoleManagerUserRoleStore();

        $manager = new UserRoleManager($userRoles, $roles);

        $this->expectException(RoleNotFoundException::class);
        $manager->assignRole(new UserRoleManagerUserId(1), 'missing');
    }
}
