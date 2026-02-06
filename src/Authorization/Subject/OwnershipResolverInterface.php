<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

interface OwnershipResolverInterface
{
    public function resolve(
        mixed $routeValue,
        ServerRequestInterface $request,
        OwnershipBinding $binding,
    ): ?OwnershipSubject;
}
