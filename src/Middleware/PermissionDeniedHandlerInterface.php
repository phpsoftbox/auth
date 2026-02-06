<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PermissionDeniedHandlerInterface
{
    public function handle(
        ServerRequestInterface $request,
        string $permission,
        mixed $subject = null,
        mixed $user = null,
    ): ResponseInterface;
}
