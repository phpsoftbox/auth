<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Cli;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PhpSoftBox\Auth\Authorization\UserRoleManager;
use PhpSoftBox\Auth\Cli\AuthCommandProvider;
use PhpSoftBox\Auth\Cli\RoleAssignHandler;
use PhpSoftBox\Auth\Cli\RoleDischargeHandler;
use PhpSoftBox\Auth\Cli\RolePermissionsHandler;
use PhpSoftBox\Auth\Cli\RoleSyncHandler;
use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\CliApp\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthCommandProvider::class)]
#[CoversClass(RoleAssignHandler::class)]
#[CoversClass(RoleDischargeHandler::class)]
#[CoversClass(RoleSyncHandler::class)]
#[CoversClass(RolePermissionsHandler::class)]
final class AuthCliTest extends TestCase
{
    /**
     * Проверяет регистрацию auth CLI-команд.
     */
    #[Test]
    public function registersAuthCommands(): void
    {
        $registry = new InMemoryCommandRegistry(withDefaultCommands: false);
        $provider = new AuthCommandProvider();

        $provider->register($registry);

        $this->assertSame(RoleSyncHandler::class, $registry->get('auth:role:sync')?->handler);
        $this->assertSame(RolePermissionsHandler::class, $registry->get('auth:role:permissions')?->handler);
        $this->assertSame(RoleAssignHandler::class, $registry->get('auth:role:assign')?->handler);
        $this->assertSame(RoleDischargeHandler::class, $registry->get('auth:role:discharge')?->handler);
        $this->assertNull($registry->get('auth:sync'));
        $this->assertNull($registry->get('auth:role-permissions'));
    }

    /**
     * Проверяет dry-run синхронизации ролей.
     */
    #[Test]
    public function roleSyncDryRunPrintsPlan(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [RoleDefinition::named('manager')->allow('users.base.read')],
            permissions: ['users.base.read'],
        );

        $sync = new RoleSynchronizer(
            $provider,
            new CliInMemoryPermissionStore(),
            new CliInMemoryRoleStore(),
            new CliInMemoryRolePermissionStore(),
        );

        $handler = new RoleSyncHandler($sync);
        $runner  = new CliTestRunner(params: [], options: ['dry-run' => true]);

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertTrue($runner->containsMessage('План синхронизации:'));
        $this->assertTrue($runner->containsMessage('manager'));
    }

    /**
     * Проверяет, что sync без dry-run выполняет синхронизацию.
     */
    #[Test]
    public function roleSyncExecutesSynchronization(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [RoleDefinition::named('manager')->allow('users.base.read')],
            permissions: ['users.base.read'],
        );

        $permissions     = new CliInMemoryPermissionStore();
        $roles           = new CliInMemoryRoleStore();
        $rolePermissions = new CliInMemoryRolePermissionStore();

        $sync = new RoleSynchronizer(
            $provider,
            $permissions,
            $roles,
            $rolePermissions,
        );

        $handler = new RoleSyncHandler($sync);
        $runner  = new CliTestRunner(params: [], options: ['dry-run' => false]);

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertNotNull($roles->findIdByName('manager'));
        $this->assertTrue($runner->containsMessage('Роли и права синхронизированы.'));
    }

    /**
     * Проверяет вывод прав роли.
     */
    #[Test]
    public function rolePermissionsHandlerPrintsRolePermissions(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::named('admin')
                    ->allow('users.base.read')
                    ->deny('users.base.delete'),
            ],
        );

        $handler = new RolePermissionsHandler($provider);
        $runner  = new CliTestRunner(params: ['role' => 'admin']);

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertTrue($runner->containsMessage('Роль: admin'));
        $this->assertTrue($runner->containsMessage('users.base.read'));
        $this->assertTrue($runner->containsMessage('users.base.delete'));
    }

    /**
     * Проверяет назначение роли пользователю через CLI.
     */
    #[Test]
    public function roleAssignHandlerAssignsRole(): void
    {
        $roles     = new CliInMemoryRoleStore();
        $userRoles = new CliInMemoryUserRoleStore();

        $roleId = $roles->create('manager');
        $userRoles->registerRole($roleId, 'manager');

        $handler = new RoleAssignHandler(new UserRoleManager($userRoles, $roles));
        $runner  = new CliTestRunner(params: ['user' => '42', 'role' => 'manager']);

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertSame([$roleId], $userRoles->listRoleIdsByUserId(42));
        $this->assertTrue($runner->containsMessage('Роль manager назначена пользователю 42.'));
    }

    /**
     * Проверяет снятие роли с пользователя через CLI.
     */
    #[Test]
    public function roleDischargeHandlerRemovesRole(): void
    {
        $roles     = new CliInMemoryRoleStore();
        $userRoles = new CliInMemoryUserRoleStore();

        $roleId = $roles->create('manager');
        $userRoles->registerRole($roleId, 'manager');
        $userRoles->attach(42, $roleId);

        $handler = new RoleDischargeHandler(new UserRoleManager($userRoles, $roles));
        $runner  = new CliTestRunner(params: ['user' => '42', 'role' => 'manager']);

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertSame([], $userRoles->listRoleIdsByUserId(42));
        $this->assertTrue($runner->containsMessage('Роль manager снята с пользователя 42.'));
    }
}
