<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

use function bin2hex;
use function explode;
use function hash;
use function hash_equals;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sprintf;
use function str_contains;
use function trim;

use const JSON_THROW_ON_ERROR;

final class DatabaseTokenStore
{
    public const string TYPE_BEARER   = 'bearer';
    public const string TYPE_REMEMBER = 'remember';

    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'user_tokens',
        private readonly string $idColumn = 'id',
        private readonly string $userIdColumn = 'user_id',
        private readonly string $selectorColumn = 'selector',
        private readonly string $tokenHashColumn = 'token_hash',
        private readonly string $tokenTypeColumn = 'token_type',
        private readonly string $tokenType = self::TYPE_BEARER,
        private readonly string $expiresDatetimeColumn = 'expires_datetime',
        private readonly string $revokedDatetimeColumn = 'revoked_datetime',
        private readonly string $lastUsedDatetimeColumn = 'last_used_datetime',
        private readonly string $createdDatetimeColumn = 'created_datetime',
        private readonly string $createdIpColumn = 'created_ip',
        private readonly string $createdUserAgentColumn = 'created_user_agent',
        private readonly string $lastUsedIpColumn = 'last_used_ip',
        private readonly string $lastUsedUserAgentColumn = 'last_used_user_agent',
        private readonly string $metadataColumn = 'metadata',
        private readonly int $touchThrottleSeconds = 300,
    ) {
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
        $selector = bin2hex(random_bytes(16));
        $secret   = bin2hex(random_bytes(32));
        $token    = $selector . '.' . $secret;

        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->insert($this->table, [
                $this->userIdColumn            => $userId,
                $this->selectorColumn          => $selector,
                $this->tokenHashColumn         => $this->hashSecret($secret),
                $this->tokenTypeColumn         => $this->tokenType,
                $this->expiresDatetimeColumn   => $this->dateToStorage($expiresAt),
                $this->revokedDatetimeColumn   => null,
                $this->lastUsedDatetimeColumn  => null,
                $this->createdDatetimeColumn   => $this->dateToStorage(Clock::now()),
                $this->createdIpColumn         => $this->requestIp($request),
                $this->createdUserAgentColumn  => $this->requestUserAgent($request),
                $this->lastUsedIpColumn        => null,
                $this->lastUsedUserAgentColumn => null,
                $this->metadataColumn          => $this->metadataToStorage($metadata),
            ])
            ->execute();

        $id = $conn->lastInsertId();

        return new IssuedToken(
            token: $token,
            selector: $selector,
            userId: $userId,
            expiresAt: $expiresAt === null ? null : DateTimeImmutable::createFromInterface($expiresAt),
            id: is_numeric($id) ? (int) $id : null,
        );
    }

    public function findValid(string $token, ?ServerRequestInterface $request = null): ?TokenRecord
    {
        $parts = $this->parseToken($token);
        if ($parts === null) {
            return null;
        }

        $row = $this->findRowBySelector($parts['selector']);
        if ($row === null) {
            return null;
        }

        $hash = $row[$this->tokenHashColumn] ?? null;
        if (!is_string($hash) || !hash_equals($hash, $this->hashSecret($parts['secret']))) {
            return null;
        }

        $record = $this->recordFromRow($row);
        if ($record === null) {
            return null;
        }

        if ($record->revokedAt !== null) {
            return null;
        }

        $now = Clock::now();
        if ($record->expiresAt !== null && $record->expiresAt <= $now) {
            return null;
        }

        $this->touch($record, $request, $now);

        return $record;
    }

    public function revoke(string $token): int
    {
        $parts = $this->parseToken($token);
        if ($parts === null) {
            return 0;
        }

        return $this->revokeSelector($parts['selector']);
    }

    public function revokeSelector(string $selector): int
    {
        $selector = trim($selector);
        if ($selector === '') {
            return 0;
        }

        return $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [
                $this->revokedDatetimeColumn => $this->dateToStorage(Clock::now()),
            ])
            ->where($this->selectorColumn . ' = :selector', ['selector' => $selector])
            ->where($this->tokenTypeColumn . ' = :token_type', ['token_type' => $this->tokenType])
            ->where($this->revokedDatetimeColumn . ' IS NULL')
            ->execute();
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [
                $this->revokedDatetimeColumn => $this->dateToStorage(Clock::now()),
            ])
            ->where($this->userIdColumn . ' = :user_id', ['user_id' => $userId])
            ->where($this->tokenTypeColumn . ' = :token_type', ['token_type' => $this->tokenType])
            ->where($this->revokedDatetimeColumn . ' IS NULL')
            ->execute();
    }

    /**
     * @return array{selector: string, secret: string}|null
     */
    private function parseToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }

        [$selector, $secret] = explode('.', $token, 2);
        $selector            = trim($selector);
        $secret              = trim($secret);
        if ($selector === '' || $secret === '') {
            return null;
        }

        return [
            'selector' => $selector,
            'secret'   => $secret,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRowBySelector(string $selector): ?array
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT * FROM %s WHERE %s = :selector AND %s = :token_type LIMIT 1',
            $conn->table($this->table),
            $this->selectorColumn,
            $this->tokenTypeColumn,
        );

        return $conn->fetchOne($sql, [
            'selector'   => $selector,
            'token_type' => $this->tokenType,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordFromRow(array $row): ?TokenRecord
    {
        $id = $row[$this->idColumn] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $userId = $row[$this->userIdColumn] ?? null;
        if (!is_int($userId) && !is_string($userId)) {
            return null;
        }

        $selector = $row[$this->selectorColumn] ?? null;
        if (!is_string($selector) || trim($selector) === '') {
            return null;
        }

        return new TokenRecord(
            id: $id,
            userId: $userId,
            selector: $selector,
            expiresAt: $this->dateFromStorage($row[$this->expiresDatetimeColumn] ?? null),
            revokedAt: $this->dateFromStorage($row[$this->revokedDatetimeColumn] ?? null),
            lastUsedAt: $this->dateFromStorage($row[$this->lastUsedDatetimeColumn] ?? null),
            metadata: $this->metadataFromStorage($row[$this->metadataColumn] ?? null),
        );
    }

    private function touch(TokenRecord $record, ?ServerRequestInterface $request, DateTimeImmutable $now): void
    {
        if ($record->lastUsedAt !== null && $this->touchThrottleSeconds > 0) {
            $elapsed = $now->getTimestamp() - $record->lastUsedAt->getTimestamp();
            if ($elapsed < $this->touchThrottleSeconds) {
                return;
            }
        }

        $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [
                $this->lastUsedDatetimeColumn  => $this->dateToStorage($now),
                $this->lastUsedIpColumn        => $this->requestIp($request),
                $this->lastUsedUserAgentColumn => $this->requestUserAgent($request),
            ])
            ->where($this->idColumn . ' = :id', ['id' => $record->id])
            ->execute();
    }

    private function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function dateToStorage(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function dateFromStorage(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataToStorage(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }

        return json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFromStorage(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $metadata = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function requestIp(?ServerRequestInterface $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $server = $request->getServerParams();
        $value  = $server['REMOTE_ADDR'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requestUserAgent(?ServerRequestInterface $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $value = $request->getHeaderLine('User-Agent');

        return trim($value) !== '' ? trim($value) : null;
    }
}
