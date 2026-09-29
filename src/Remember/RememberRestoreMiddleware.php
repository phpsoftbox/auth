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
        private ?string $area = null,
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

        // Если задан guard (`area`), токен другого guard не принимается.
        if ($record !== null && $this->area !== null && ($record->metadata['area'] ?? null) !== $this->area) {
            $record = null;
        }

        if ($record === null) {
            $this->cookies->queueForget($request);

            return $handler->handle($this->withUser($request, $sessionUser));
        }

        if ($sessionUser !== null) {
            if (!$this->sameUser($sessionUser, $record->subjectId)) {
                $this->tokens->revoke($rawToken);
                $this->cookies->queueForget($request);
                if ($this->mismatchPolicy === RememberMismatchPolicy::Logout) {
                    $this->guard->logout();
                    $sessionUser = null;
                }
            }

            return $handler->handle($this->withUser($request, $sessionUser));
        }

        $user = $this->users->retrieveById($record->subjectId);
        if ($user === null) {
            $this->tokens->revoke($rawToken);
            $this->cookies->queueForget($request);

            return $handler->handle($request);
        }

        $this->guard->login($user);

        $rotated = $this->tokens->rotate($rawToken, $record, $request);
        $this->cookies->queue($rotated->token, $record->expiresAt, $request);

        return $handler->handle($this->withUser($request, $user));
    }

    private function sameUser(UserInterface $user, int|string $expectedId): bool
    {
        $actualId = $user->id();

        return $actualId !== null && (string) $actualId === (string) $expectedId;
    }

    private function withUser(ServerRequestInterface $request, ?UserInterface $user): ServerRequestInterface
    {
        if ($user === null) {
            return $request;
        }

        $request = $request->withAttribute($this->userAttribute, $user);
        $userId  = $user->id();
        if ($userId !== null) {
            $request = $request->withAttribute('user_id', $userId);
        }

        return $request;
    }
}
