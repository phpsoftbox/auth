<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\UserProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_int;
use function is_string;

final readonly class RememberRestoreMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionGuard $guard,
        private UserProviderInterface $users,
        private DatabaseRememberTokenStore $tokens,
        private RememberTokenExtractor $extractor,
        private RememberCookieManager $cookies,
        private RememberMismatchPolicy $mismatchPolicy = RememberMismatchPolicy::RevokeToken,
        private string $userAttribute = 'user',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionUser = $this->guard->user($request);
        $rawToken    = $this->extractor->extract($request);
        if ($rawToken === null) {
            return $handler->handle($this->withUser($request, $sessionUser));
        }

        $record = $this->tokens->findValid($rawToken, $request);
        if ($record === null) {
            $this->cookies->queueForget($request);

            return $handler->handle($this->withUser($request, $sessionUser));
        }

        if ($sessionUser !== null) {
            if (!$this->sameUser($sessionUser, $record->userId)) {
                $this->tokens->revoke($rawToken);
                $this->cookies->queueForget($request);
                if ($this->mismatchPolicy === RememberMismatchPolicy::Logout) {
                    $this->guard->logout();
                    $sessionUser = null;
                }
            }

            return $handler->handle($this->withUser($request, $sessionUser));
        }

        $user = $this->users->retrieveById($record->userId);
        if ($user === null) {
            $this->tokens->revoke($rawToken);
            $this->cookies->queueForget($request);

            return $handler->handle($request);
        }

        $this->guard->login($user);

        return $handler->handle($this->withUser($request, $user));
    }

    private function sameUser(mixed $user, int|string $expectedId): bool
    {
        $actualId = $this->userId($user);

        return $actualId !== null && (string) $actualId === (string) $expectedId;
    }

    private function userId(mixed $user): int|string|null
    {
        if ($user instanceof UserInterface) {
            return $user->id();
        }

        return is_int($user) || is_string($user) ? $user : null;
    }

    private function withUser(ServerRequestInterface $request, mixed $user): ServerRequestInterface
    {
        if ($user === null) {
            return $request;
        }

        $request = $request->withAttribute($this->userAttribute, $user);
        $userId  = $this->userId($user);
        if ($userId !== null) {
            $request = $request->withAttribute('user_id', $userId);
        }

        return $request;
    }
}
