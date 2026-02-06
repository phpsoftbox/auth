<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

use function is_int;
use function is_object;
use function is_string;

final readonly class CallbackOwnershipResolver implements OwnershipResolverInterface
{
    public function __construct(
        private mixed $resolver,
    ) {
    }

    public function resolve(
        mixed $routeValue,
        ServerRequestInterface $request,
        OwnershipBinding $binding,
    ): ?OwnershipSubject {
        $result = ($this->resolver)($routeValue, $request, $binding);
        if ($result instanceof OwnershipSubject) {
            return $result;
        }

        if ($result === null) {
            return null;
        }

        if (is_int($result) || is_string($result)) {
            return new OwnershipSubject(
                type: $binding->subject ?? $binding->routeParam,
                id: $this->resolveId($routeValue),
                ownerId: $result,
                routeParam: $binding->routeParam,
                value: $routeValue,
            );
        }

        return null;
    }

    private function resolveId(mixed $value): int|string|null
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_object($value) && isset($value->id) && (is_int($value->id) || is_string($value->id))) {
            return $value->id;
        }

        return null;
    }
}
