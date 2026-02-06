<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Cli;

use PhpSoftBox\Auth\Authorization\PermissionCatalog;
use PhpSoftBox\Auth\Authorization\PermissionResolver;
use PhpSoftBox\Auth\Authorization\RoleDefinitionProviderInterface;
use PhpSoftBox\Auth\Authorization\RolePermissionMap;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

use function is_string;
use function ksort;
use function min;
use function sort;
use function strpos;
use function substr;
use function trim;

final class RolePermissionsHandler implements HandlerInterface
{
    public function __construct(
        private readonly RoleDefinitionProviderInterface $definitions,
        private readonly PermissionResolver $resolver = new PermissionResolver(),
        private readonly PermissionCatalog $catalog = new PermissionCatalog(),
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $role = $runner->request()->param('role', '');
        $role = is_string($role) ? trim($role) : '';

        if ($role === '') {
            $runner->io()->writeln('Укажите имя роли.', 'error');

            return Response::FAILURE;
        }

        $map = RolePermissionMap::build(
            definitions: $this->definitions->load(),
            resolver: $this->resolver,
            catalog: $this->catalog,
        );

        $definition = $map->role($role);
        if ($definition === null) {
            $runner->io()->writeln('Роль не найдена: ' . $role, 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Роль: ' . $definition->name);
        if ($definition->root) {
            $runner->io()->writeln('Тип: root');
        } elseif ($definition->adminAccess) {
            $runner->io()->writeln('Тип: admin');
        }

        $allowed = $definition->allowed();
        if ($definition->allowAll) {
            $runner->io()->writeln('Разрешено: все');
        } else {
            $runner->io()->writeln('Разрешено:');
        }
        $this->printGrouped($runner, $allowed);

        $denied = $definition->denied();
        if ($denied === []) {
            $runner->io()->writeln('Запрещено: нет');
        } else {
            $runner->io()->writeln('Запрещено:');
            $this->printGrouped($runner, $denied);
        }

        return Response::SUCCESS;
    }

    /**
     * @param list<string> $permissions
     */
    private function printGrouped(RunnerInterface $runner, array $permissions): void
    {
        $groups = $this->groupByResource($permissions);
        if ($groups === []) {
            $runner->io()->writeln('  - (нет)');

            return;
        }

        foreach ($groups as $resource => $items) {
            $runner->io()->writeln('  ' . $resource . ':');
            foreach ($items as $permission) {
                $runner->io()->writeln('    - ' . $permission);
            }
        }
    }

    /**
     * @param list<string> $permissions
     * @return array<string, list<string>>
     */
    private function groupByResource(array $permissions): array
    {
        $groups = [];
        foreach ($permissions as $permission) {
            $resource            = $this->extractResource($permission);
            $groups[$resource][] = $permission;
        }

        ksort($groups);
        foreach ($groups as $resource => $items) {
            sort($items);
            $groups[$resource] = $items;
        }

        return $groups;
    }

    private function extractResource(string $permission): string
    {
        $posDot  = strpos($permission, '.');
        $posDash = strpos($permission, '-');

        if ($posDot === false && $posDash === false) {
            return $permission;
        }

        if ($posDot === false) {
            $pos = $posDash;
        } elseif ($posDash === false) {
            $pos = $posDot;
        } else {
            $pos = min($posDot, $posDash);
        }

        return $pos > 0 ? substr($permission, 0, $pos) : $permission;
    }
}
