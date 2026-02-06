<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use DateTimeInterface;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Token\CredentialRecord;
use PhpSoftBox\Auth\Token\CredentialStoreInterface;
use PhpSoftBox\Auth\Token\IssuedCredential;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DatabaseTokenProvider implements RequestAwareTokenProviderInterface
{
    public function __construct(
        private CredentialStoreInterface $tokens,
        private UserProviderInterface $users,
    ) {
    }

    public function retrieveUserByToken(string $token): ?UserInterface
    {
        return $this->resolveUser($this->tokens->findValid($token));
    }

    public function retrieveUserByTokenForRequest(string $token, ServerRequestInterface $request): ?UserInterface
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
    ): IssuedCredential {
        return $this->tokens->issue($userId, $expiresAt, $metadata, $request);
    }

    public function revoke(string $token): int
    {
        return $this->tokens->revoke($token);
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return $this->tokens->revokeAllForSubject($userId);
    }

    private function resolveUser(?CredentialRecord $record): ?UserInterface
    {
        if ($record === null) {
            return null;
        }

        return $this->users->retrieveById($record->subjectId);
    }
}
