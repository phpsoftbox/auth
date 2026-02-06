<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

final class OwnershipRegistry
{
    /**
     * @var array<string, OwnershipBinding>
     */
    private array $bindings = [];

    public function define(
        string $routeParam,
        ?string $subject,
        OwnershipResolverInterface|callable $owner,
    ): self {
        if (!$owner instanceof OwnershipResolverInterface) {
            $owner = new CallbackOwnershipResolver($owner);
        }

        $this->bindings[$routeParam] = new OwnershipBinding($routeParam, $subject, $owner);

        return $this;
    }

    public function find(string $routeParam): ?OwnershipBinding
    {
        return $this->bindings[$routeParam] ?? null;
    }
}
