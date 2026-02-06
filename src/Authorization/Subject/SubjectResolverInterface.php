<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

interface SubjectResolverInterface
{
    public function resolve(ServerRequestInterface $request): mixed;
}
