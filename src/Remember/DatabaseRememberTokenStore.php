<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use DateTimeInterface;
use PhpSoftBox\Auth\Token\DatabaseTokenStore;
use PhpSoftBox\Auth\Token\IssuedToken;
use PhpSoftBox\Auth\Token\TokenRecord;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DatabaseRememberTokenStore
{
    private DatabaseTokenStore $tokens;

    public function __construct(
        ConnectionManagerInterface $connections,
        string $connectionName = 'default',
        string $table = 'user_tokens',
        string $idColumn = 'id',
        string $userIdColumn = 'user_id',
        string $selectorColumn = 'selector',
        string $tokenHashColumn = 'token_hash',
        string $tokenTypeColumn = 'token_type',
        string $tokenType = DatabaseTokenStore::TYPE_REMEMBER,
        string $expiresDatetimeColumn = 'expires_datetime',
        string $revokedDatetimeColumn = 'revoked_datetime',
        string $lastUsedDatetimeColumn = 'last_used_datetime',
        string $createdDatetimeColumn = 'created_datetime',
        string $createdIpColumn = 'created_ip',
        string $createdUserAgentColumn = 'created_user_agent',
        string $lastUsedIpColumn = 'last_used_ip',
        string $lastUsedUserAgentColumn = 'last_used_user_agent',
        string $metadataColumn = 'metadata',
        int $touchThrottleSeconds = 300,
    ) {
        $this->tokens = new DatabaseTokenStore(
            connections: $connections,
            connectionName: $connectionName,
            table: $table,
            idColumn: $idColumn,
            userIdColumn: $userIdColumn,
            selectorColumn: $selectorColumn,
            tokenHashColumn: $tokenHashColumn,
            tokenTypeColumn: $tokenTypeColumn,
            tokenType: $tokenType,
            expiresDatetimeColumn: $expiresDatetimeColumn,
            revokedDatetimeColumn: $revokedDatetimeColumn,
            lastUsedDatetimeColumn: $lastUsedDatetimeColumn,
            createdDatetimeColumn: $createdDatetimeColumn,
            createdIpColumn: $createdIpColumn,
            createdUserAgentColumn: $createdUserAgentColumn,
            lastUsedIpColumn: $lastUsedIpColumn,
            lastUsedUserAgentColumn: $lastUsedUserAgentColumn,
            metadataColumn: $metadataColumn,
            touchThrottleSeconds: $touchThrottleSeconds,
        );
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

    public function findValid(string $token, ?ServerRequestInterface $request = null): ?TokenRecord
    {
        return $this->tokens->findValid($token, $request);
    }

    public function revoke(string $token): int
    {
        return $this->tokens->revoke($token);
    }

    public function revokeSelector(string $selector): int
    {
        return $this->tokens->revokeSelector($selector);
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return $this->tokens->revokeAllForUser($userId);
    }
}
