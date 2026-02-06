<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AreaAccessMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
        private ResponseFactoryInterface $responses,
        private AreaAccessRule $rule,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute($this->rule->userAttribute);
        if ($user === null) {
            $user = $this->auth->guard($this->rule->guard)->user($request);
        }

        if ($user === null) {
            return $this->deny();
        }

        $permission = $this->rule->permission ?? '';
        if ($permission !== '' && !$this->auth->can($user, $permission)) {
            return $this->deny();
        }

        $request = $request
            ->withAttribute($this->rule->userAttribute, $user)
            ->withAttribute($this->rule->areaAttribute, $this->rule->area);

        if ($user instanceof UserInterface && $request->getAttribute('user_id') === null) {
            $userId = $user->id();
            if ($userId !== null) {
                $request = $request->withAttribute('user_id', $userId);
            }
        }

        return $handler->handle($request);
    }

    private function deny(): ResponseInterface
    {
        return match ($this->rule->deniedMode) {
            AreaAccessDeniedMode::Redirect => $this->responses
                ->createResponse(303)
                ->withHeader('Location', (string) $this->rule->redirectTo),
            AreaAccessDeniedMode::Forbidden => $this->responses->createResponse(403),
            AreaAccessDeniedMode::NotFound  => $this->responses->createResponse(404),
        };
    }
}
