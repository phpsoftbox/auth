<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Cli;

use PhpSoftBox\Auth\Authorization\RoleSynchronizer;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;

final class RoleSyncHandler implements HandlerInterface
{
    public function __construct(
        private readonly RoleSynchronizer $sync,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $dryRun = (bool) $runner->request()->option('dry-run', false);

        if ($dryRun) {
            $plan = $this->sync->plan();

            $runner->io()->writeln('План синхронизации:');

            $this->printList($runner, 'Роли: добавить', $plan->rolesToCreate);
            $this->printList($runner, 'Роли: удалить', $plan->rolesToDelete);
            $this->printList($runner, 'Пермишены: добавить', $plan->permissionsToCreate);
            $this->printList($runner, 'Пермишены: удалить', $plan->permissionsToDelete);

            if (!$plan->hasChanges()) {
                $runner->io()->writeln('Изменений нет.');
            }

            return Response::SUCCESS;
        }

        $this->sync->sync();
        $runner->io()->writeln('Роли и права синхронизированы.', 'success');

        return Response::SUCCESS;
    }

    /**
     * @param list<string> $items
     */
    private function printList(RunnerInterface $runner, string $label, array $items): void
    {
        $runner->io()->writeln($label . ':');
        if ($items === []) {
            $runner->io()->writeln('  - (нет)');

            return;
        }

        foreach ($items as $item) {
            $runner->io()->writeln('  - ' . $item);
        }
    }
}
