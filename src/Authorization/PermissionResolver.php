<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use InvalidArgumentException;

use function class_exists;
use function is_string;
use function is_subclass_of;
use function trim;

final class PermissionResolver
{
    public function __construct(
        private readonly PermissionNameFormatterInterface $formatter = new DefaultPermissionNameFormatter(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(PermissionGrant|string $grant): array
    {
        if (is_string($grant)) {
            if (class_exists($grant) && is_subclass_of($grant, PermissionModelInterface::class)) {
                return $this->resolveModel($grant);
            }

            $name = trim($grant);

            return $name === '' ? [] : [$name];
        }

        return $this->resolveGrant($grant);
    }

    /**
     * @return list<string>
     */
    private function resolveGrant(PermissionGrant $grant): array
    {
        $resource = $grant->resource;

        if (class_exists($resource) && is_subclass_of($resource, PermissionModelInterface::class)) {
            $model   = $resource;
            $scope   = $grant->scope ?? $model::scope();
            $actions = $grant->actions ?? $model::actions();

            return $this->resolveResource($model::resource(), $actions, $scope);
        }

        if ($grant->actions === null) {
            $name = trim($resource);

            return $name === '' ? [] : [$name];
        }

        return $this->resolveResource($resource, $grant->actions, $grant->scope ?? 'base');
    }

    /**
     * @param list<PermissionActionEnum|string> $actions
     * @return list<string>
     */
    private function resolveResource(string $resource, array $actions, string $scope): array
    {
        $names = [];
        foreach ($actions as $action) {
            $actionValue = $action instanceof PermissionActionEnum ? $action->value : (string) $action;
            $names[]     = $this->formatter->format($resource, $actionValue, $scope);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function resolveModel(string $model): array
    {
        if (!is_subclass_of($model, PermissionModelInterface::class)) {
            throw new InvalidArgumentException("Permission model must implement PermissionModelInterface: {$model}");
        }

        return $this->resolveResource($model::resource(), $model::actions(), $model::scope());
    }
}
