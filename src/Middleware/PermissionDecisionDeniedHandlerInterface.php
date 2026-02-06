<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PermissionDecisionDeniedHandlerInterface extends PermissionDeniedHandlerInterface
{
    public function handleDecision(
        ServerRequestInterface $request,
        AccessDecision $decision,
        string $permission,
        mixed $subject = null,
        mixed $user = null,
    ): ResponseInterface;
}
