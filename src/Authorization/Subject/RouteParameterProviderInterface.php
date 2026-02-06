<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

interface RouteParameterProviderInterface
{
    public function get(ServerRequestInterface $request, string $name): mixed;

    /**
     * @return array<string, mixed>
     */
    public function all(ServerRequestInterface $request): array;
}
