<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class GuardMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
        private string $userAttribute = 'user',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute($this->userAttribute) === null) {
            $user = $this->auth->guard()->user($request);
            if ($user !== null) {
                $request = $request->withAttribute($this->userAttribute, $user);
                if ($user instanceof UserIdentityInterface) {
                    $userId = $user->getId();
                    if ($userId !== null) {
                        $request = $request->withAttribute('user_id', $userId);
                    }
                }
            }
        }

        return $handler->handle($request);
    }
}
