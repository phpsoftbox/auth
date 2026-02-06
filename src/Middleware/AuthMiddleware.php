<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Exception\UnauthorizedHttpException;
use PhpSoftBox\Auth\Guard\CallbackGuard;
use PhpSoftBox\Auth\Guard\GuardInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    private readonly GuardInterface $guard;

    /**
     * @param GuardInterface|AuthManager|callable $guard fn(ServerRequestInterface $request): mixed
     */
    public function __construct(
        GuardInterface|AuthManager|callable $guard,
        private readonly bool $required = true,
        private readonly string $attribute = 'user',
        ?string $guardName = null,
    ) {
        if ($guard instanceof AuthManager) {
            $this->guard = $guard->guard($guardName);
        } elseif ($guard instanceof GuardInterface) {
            $this->guard = $guard;
        } else {
            $this->guard = new CallbackGuard($guard);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->guard->user($request);

        if ($this->required && $user === null) {
            throw new UnauthorizedHttpException('Unauthorized');
        }

        $request = $request->withAttribute($this->attribute, $user);

        return $handler->handle($request);
    }
}
