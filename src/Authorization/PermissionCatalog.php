<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use InvalidArgumentException;

use function array_values;
use function class_exists;
use function is_array;
use function is_int;
use function is_string;
use function is_subclass_of;
use function trim;

final class PermissionCatalog
{
    public function __construct(
        private readonly PermissionNameFormatterInterface $formatter = new DefaultPermissionNameFormatter(),
    ) {
    }

    /**
     * @param list<class-string<PermissionModelInterface>> $models
     * @param array<string, string|null>|list<string> $extra
     * @return list<PermissionDefinition>
     */
    public function build(array $models, array $extra = []): array
    {
        $definitions = [];

        foreach ($models as $model) {
            foreach ($this->fromModel($model) as $definition) {
                $definitions[] = $definition;
            }
        }

        foreach ($extra as $name => $label) {
            if (is_int($name)) {
                $name  = (string) $label;
                $label = null;
            }
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $definitions[] = new PermissionDefinition($name, is_string($label) ? $label : null);
        }

        return $this->uniqueByName($definitions);
    }

    /**
     * @return list<PermissionDefinition>
     */
    public function fromModel(string $model): array
    {
        if (!class_exists($model) || !is_subclass_of($model, PermissionModelInterface::class)) {
            throw new InvalidArgumentException("Permission model must implement PermissionModelInterface: {$model}");
        }

        $resource    = $model::resource();
        $scope       = $model::scope();
        $labels      = $model::labels();
        $definitions = [];

        foreach ($model::actions() as $action) {
            $actionValue = $action instanceof PermissionActionEnum ? $action->value : (string) $action;
            $name        = $this->formatter->format($resource, $actionValue, $scope);

            $label = null;
            if (is_array($labels)) {
                $label = $labels[$actionValue] ?? $labels[$name] ?? null;
            }

            $definitions[] = new PermissionDefinition($name, is_string($label) ? $label : null);
        }

        return $this->uniqueByName($definitions);
    }

    /**
     * @param list<PermissionDefinition> $definitions
     * @return list<PermissionDefinition>
     */
    private function uniqueByName(array $definitions): array
    {
        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->name] = $definition;
        }

        return array_values($map);
    }
}
