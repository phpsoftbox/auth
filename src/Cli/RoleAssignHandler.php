<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Cli;

use PhpSoftBox\Auth\Authorization\UserRoleManager;
use PhpSoftBox\Auth\Exception\RoleNotAssignedException;
use PhpSoftBox\Auth\Exception\RoleNotFoundException;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

use function is_int;
use function is_string;
use function trim;

final class RoleAssignHandler implements HandlerInterface
{
    public function __construct(
        private readonly UserRoleManager $roles,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $user = $this->user($runner);
        $role = $this->role($runner);

        if ($user === null) {
            $runner->io()->writeln('Укажите ID пользователя.', 'error');

            return Response::FAILURE;
        }

        if ($role === '') {
            $runner->io()->writeln('Укажите имя роли.', 'error');

            return Response::FAILURE;
        }

        try {
            $this->roles->assignRole($user, $role);
        } catch (RoleNotAssignedException | RoleNotFoundException $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln("Роль {$role} назначена пользователю {$user}.", 'success');

        return Response::SUCCESS;
    }

    private function user(RunnerInterface $runner): int|string|null
    {
        $user = $runner->request()->param('user', '');
        if (is_int($user)) {
            return $user > 0 ? $user : null;
        }

        if (!is_string($user)) {
            return null;
        }

        $user = trim($user);

        return $user !== '' ? $user : null;
    }

    private function role(RunnerInterface $runner): string
    {
        $role = $runner->request()->param('role', '');
        $role = is_string($role) ? trim($role) : '';

        return $role;
    }
}
