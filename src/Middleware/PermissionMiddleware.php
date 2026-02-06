<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Authorization\PermissionAttributeResolver;
use PhpSoftBox\Auth\Exception\PermissionDeniedException;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly ?string $permission = null,
        private readonly ?string $guardName = null,
        private readonly string $permissionAttribute = '_permission',
        private readonly string $subjectAttribute = '_permission_subject',
        private readonly PermissionAttributeResolver $resolver = new PermissionAttributeResolver(),
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permission = $this->permission ?? (string) $request->getAttribute($this->permissionAttribute, '');
        $subject    = $request->getAttribute($this->subjectAttribute, null);

        if ($permission === '') {
            $handler = $request->getAttribute('_route_handler');
            if ($handler !== null) {
                $requirement = $this->resolver->resolve($handler);
                if ($requirement !== null) {
                    $permission = $requirement->permission;
                    if ($subject === null && $requirement->subjectAttribute !== null) {
                        $subject = $request->getAttribute($requirement->subjectAttribute, null);
                    }
                }
            }
        }

        if ($permission === '') {
            return $handler->handle($request);
        }

        $guard = $this->auth->guard($this->guardName);
        $user  = $guard->user($request);

        if ($user === null || !$this->auth->can($user, $permission, $subject)) {
            throw new PermissionDeniedException('Permission denied: ' . $permission);
        }

        return $handler->handle($request);
    }
}
