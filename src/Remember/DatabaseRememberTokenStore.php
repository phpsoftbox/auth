<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use DateTimeInterface;
use PhpSoftBox\Auth\Token\CredentialRecord;
use PhpSoftBox\Auth\Token\DatabaseCredentialStore;
use PhpSoftBox\Auth\Token\IssuedCredential;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

/**
 * Remember-токены поверх {@see DatabaseCredentialStore}.
 *
 * `audience` отделяет токены разных guard в одной таблице: токен одного guard в store другого не находится.
 */
final readonly class DatabaseRememberTokenStore
{
    /**
     * Сколько секунд старый токен действует после ротации: параллельные запросы той же вкладки не должны разлогинить.
     */
    public const int ROTATION_GRACE_SECONDS = 60;

    private DatabaseCredentialStore $tokens;

    public function __construct(
        ConnectionManagerInterface $connections,
        string $connectionName = 'default',
        string $table = 'user_tokens',
        string $idColumn = 'id',
        string $subjectIdColumn = 'user_id',
        string $selectorColumn = 'selector',
        string $tokenHashColumn = 'token_hash',
        string $purposeColumn = 'token_type',
        string $purpose = DatabaseCredentialStore::PURPOSE_USER_REMEMBER,
        ?string $audience = null,
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
        $this->tokens = new DatabaseCredentialStore(
            connections: $connections,
            connectionName: $connectionName,
            table: $table,
            idColumn: $idColumn,
            subjectIdColumn: $subjectIdColumn,
            selectorColumn: $selectorColumn,
            tokenHashColumn: $tokenHashColumn,
            purposeColumn: $purposeColumn,
            purpose: $purpose,
            audience: $audience,
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
    ): IssuedCredential {
        return $this->tokens->issue($userId, $expiresAt, $metadata, $request);
    }

    public function findValid(string $token, ?ServerRequestInterface $request = null): ?CredentialRecord
    {
        return $this->tokens->findValid($token, $request);
    }

    /**
     * Выдаёт новый токен вместо использованного: тот же пользователь, срок и метаданные. Использованный токен
     * действует ещё {@see self::ROTATION_GRACE_SECONDS} секунд.
     */
    public function rotate(string $token, CredentialRecord $record, ?ServerRequestInterface $request = null): IssuedCredential
    {
        $issued = $this->tokens->issue($record->subjectId, $record->expiresAt, $record->metadata, $request);
        $this->tokens->expireAt($token, Clock::now()->modify(sprintf('+%d seconds', self::ROTATION_GRACE_SECONDS)));

        return $issued;
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
        return $this->tokens->revokeAllForSubject($userId);
    }
}
