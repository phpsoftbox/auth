<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use function array_key_exists;

final readonly class RouteParamSubject
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        private array $params,
        public ?string $primary = null,
    ) {
    }

    public function value(?string $name = null, mixed $default = null): mixed
    {
        $name ??= $this->primary;
        if ($name === null) {
            return $default;
        }

        return $this->params[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->params);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->params;
    }
}
