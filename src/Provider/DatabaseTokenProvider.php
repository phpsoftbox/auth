<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use DateTimeInterface;
use PhpSoftBox\Auth\Token\DatabaseTokenStore;
use PhpSoftBox\Auth\Token\IssuedToken;
use PhpSoftBox\Auth\Token\TokenRecord;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DatabaseTokenProvider implements RequestAwareTokenProviderInterface
{
    public function __construct(
        private DatabaseTokenStore $tokens,
        private UserProviderInterface $users,
    ) {
    }

    public function retrieveUserByToken(string $token): mixed
    {
        return $this->resolveUser($this->tokens->findValid($token));
    }

    public function retrieveUserByTokenForRequest(string $token, ServerRequestInterface $request): mixed
    {
        return $this->resolveUser($this->tokens->findValid($token, $request));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function issue(
        int|string $userId,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
        ?ServerRequestInterface $request = null,
    ): IssuedToken {
        return $this->tokens->issue($userId, $expiresAt, $metadata, $request);
    }

    public function revoke(string $token): int
    {
        return $this->tokens->revoke($token);
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return $this->tokens->revokeAllForUser($userId);
    }

    private function resolveUser(?TokenRecord $record): mixed
    {
        if ($record === null) {
            return null;
        }

        return $this->users->retrieveById($record->userId);
    }
}
