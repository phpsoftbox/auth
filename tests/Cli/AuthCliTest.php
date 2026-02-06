<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Cli;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PhpSoftBox\Auth\Cli\AuthCommandProvider;
use PhpSoftBox\Auth\Cli\RolePermissionsHandler;
use PhpSoftBox\Auth\Cli\RoleSyncHandler;
use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\CliApp\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthCommandProvider::class)]
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

        $this->assertSame(RoleSyncHandler::class, $registry->get('auth:sync')?->handler);
        $this->assertSame(RolePermissionsHandler::class, $registry->get('auth:role-permissions')?->handler);
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
}
