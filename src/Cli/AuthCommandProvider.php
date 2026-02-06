<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Cli;

use PhpSoftBox\CliApp\Command\ArgumentDefinition;
use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class AuthCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'auth:role:sync',
            description: 'Синхронизирует роли и разрешения',
            signature: [
                new OptionDefinition('dry-run', 'd', 'Показать изменения без записи', flag: true),
            ],
            handler: RoleSyncHandler::class,
        ));

        $registry->register(Command::define(
            name: 'auth:role:permissions',
            description: 'Показывает разрешённые и запрещённые пермишены роли',
            signature: [
                new ArgumentDefinition('role', 'Имя роли', required: true),
            ],
            handler: RolePermissionsHandler::class,
        ));

        $registry->register(Command::define(
            name: 'auth:role:assign',
            description: 'Назначает роль пользователю',
            signature: [
                new ArgumentDefinition('user', 'ID пользователя', required: true),
                new ArgumentDefinition('role', 'Имя роли', required: true),
            ],
            handler: RoleAssignHandler::class,
        ));

        $registry->register(Command::define(
            name: 'auth:role:discharge',
            description: 'Снимает роль с пользователя',
            signature: [
                new ArgumentDefinition('user', 'ID пользователя', required: true),
                new ArgumentDefinition('role', 'Имя роли', required: true),
            ],
            handler: RoleDischargeHandler::class,
        ));
    }
}
