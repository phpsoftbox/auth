<?php

declare(strict_types=1);

namespace PhpSoftBox\Router\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Router\RouteCollector;

use function count;

final class ListRoutesHandler implements HandlerInterface
{
    public function __construct(
        private readonly RouteCollector $routes,
    ) {
    }

    /**
     * Выводит список зарегистрированных маршрутов.
     */
    public function run(RunnerInterface $runner): int|Response
    {
        $rows = [];
        foreach ($this->routes->getRoutes() as $route) {
            $rows[] = [
                $route->method,
                $route->path,
                $route->name ?? '-',
                $route->host ?? '-',
                (string) count($route->middlewares),
            ];
        }

        if ($rows === []) {
            $runner->io()->writeln('Маршруты не зарегистрированы.', 'comment');

            return Response::SUCCESS;
        }

        $runner->io()->table(['METHOD', 'PATH', 'NAME', 'HOST', 'MW'], $rows);

        return Response::SUCCESS;
    }
}
