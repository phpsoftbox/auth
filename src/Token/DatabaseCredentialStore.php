<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function trim;

use const JSON_THROW_ON_ERROR;

final class DatabaseCredentialStore implements CredentialStoreInterface
{
    public const string PURPOSE_USER_BEARER   = 'user_bearer';
    public const string PURPOSE_USER_REMEMBER = 'user_remember';

    private readonly CredentialCodec $codec;
    private readonly string $purpose;
    private readonly ?string $audience;

    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'user_tokens',
        private readonly string $idColumn = 'id',
        private readonly string $subjectIdColumn = 'user_id',
        private readonly string $selectorColumn = 'selector',
        private readonly string $tokenHashColumn = 'token_hash',
        private readonly string $purposeColumn = 'token_type',
        string $purpose = self::PURPOSE_USER_BEARER,
        private readonly string $audienceColumn = 'audience',
        ?string $audience = null,
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
        ?CredentialCodec $codec = null,
    ) {
        $purpose = trim($purpose);
        if ($purpose === '') {
            throw new InvalidArgumentException('Credential purpose must not be empty.');
        }

        $audience = $audience === null ? null : trim($audience);
        if ($audience === '') {
            throw new InvalidArgumentException('Credential audience must be null or a non-empty string.');
        }

        $this->purpose  = $purpose;
        $this->audience = $audience;
        $this->codec    = $codec ?? new CredentialCodec();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function issue(
        int|string $subjectId,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
        ?ServerRequestInterface $request = null,
    ): IssuedCredential {
        $credential = $this->codec->generate();

        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->insert($this->table, [
                $this->subjectIdColumn         => $subjectId,
                $this->selectorColumn          => $credential->selector,
                $this->tokenHashColumn         => $credential->secretHash,
                $this->purposeColumn           => $this->purpose,
                $this->audienceColumn          => $this->audience,
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

        return new IssuedCredential(
            token: $credential->token,
            selector: $credential->selector,
            subjectId: $subjectId,
            purpose: $this->purpose,
            audience: $this->audience,
            expiresAt: $expiresAt === null ? null : DateTimeImmutable::createFromInterface($expiresAt),
            id: is_numeric($id) ? (int) $id : null,
        );
    }

    public function findValid(string $credential, ?ServerRequestInterface $request = null): ?CredentialRecord
    {
        $parsed = $this->codec->parse($credential);
        if ($parsed === null) {
            return null;
        }

        $row = $this->findRowBySelector($parsed->selector);
        if ($row === null) {
            return null;
        }

        $hash = $row[$this->tokenHashColumn] ?? null;
        if (!is_string($hash) || !$this->codec->verify($parsed->secret, $hash)) {
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

    public function revoke(string $credential): int
    {
        $parsed = $this->codec->parse($credential);
        if ($parsed === null) {
            return 0;
        }

        return $this->revokeSelector($parsed->selector);
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
            ->where($this->purposeColumn . ' = :purpose', ['purpose' => $this->purpose])
            ->where($this->audienceCondition(), $this->audienceBindings())
            ->where($this->revokedDatetimeColumn . ' IS NULL')
            ->execute();
    }

    /**
     * Сокращает срок действия учётных данных до `$expiresAt` (если он был позже): старый remember-токен после ротации
     * ещё немного действует для параллельных запросов.
     */
    public function expireAt(string $credential, DateTimeInterface $expiresAt): int
    {
        $parsed = $this->codec->parse($credential);
        if ($parsed === null) {
            return 0;
        }

        $at = $this->dateToStorage($expiresAt);

        return $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [$this->expiresDatetimeColumn => $at])
            ->where($this->selectorColumn . ' = :selector', ['selector' => $parsed->selector])
            ->where($this->purposeColumn . ' = :purpose', ['purpose' => $this->purpose])
            ->where($this->audienceCondition(), $this->audienceBindings())
            ->whereRaw('(' . $this->expiresDatetimeColumn . ' IS NULL OR ' . $this->expiresDatetimeColumn . ' > :expire_at)', ['expire_at' => $at])
            ->execute();
    }

    public function revokeAllForSubject(int|string $subjectId): int
    {
        return $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [
                $this->revokedDatetimeColumn => $this->dateToStorage(Clock::now()),
            ])
            ->where($this->subjectIdColumn . ' = :subject_id', ['subject_id' => $subjectId])
            ->where($this->purposeColumn . ' = :purpose', ['purpose' => $this->purpose])
            ->where($this->audienceCondition(), $this->audienceBindings())
            ->where($this->revokedDatetimeColumn . ' IS NULL')
            ->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRowBySelector(string $selector): ?array
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT * FROM %s WHERE %s = :selector AND %s = :purpose AND %s LIMIT 1',
            $conn->table($this->table),
            $this->selectorColumn,
            $this->purposeColumn,
            $this->audienceCondition(),
        );

        return $conn->fetchOne($sql, [
            'selector' => $selector,
            'purpose'  => $this->purpose,
        ] + $this->audienceBindings());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordFromRow(array $row): ?CredentialRecord
    {
        $id = $row[$this->idColumn] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $subjectId = $row[$this->subjectIdColumn] ?? null;
        if (!is_int($subjectId) && !is_string($subjectId)) {
            return null;
        }

        $selector = $row[$this->selectorColumn] ?? null;
        if (!is_string($selector) || trim($selector) === '') {
            return null;
        }

        $purpose = $row[$this->purposeColumn] ?? null;
        if (!is_string($purpose) || trim($purpose) === '') {
            return null;
        }

        $audience = $row[$this->audienceColumn] ?? null;
        if ($audience !== null && (!is_string($audience) || trim($audience) === '')) {
            return null;
        }

        return new CredentialRecord(
            id: $id,
            subjectId: $subjectId,
            selector: $selector,
            purpose: $purpose,
            audience: $audience,
            expiresAt: $this->dateFromStorage($row[$this->expiresDatetimeColumn] ?? null),
            revokedAt: $this->dateFromStorage($row[$this->revokedDatetimeColumn] ?? null),
            lastUsedAt: $this->dateFromStorage($row[$this->lastUsedDatetimeColumn] ?? null),
            metadata: $this->metadataFromStorage($row[$this->metadataColumn] ?? null),
        );
    }

    private function touch(CredentialRecord $record, ?ServerRequestInterface $request, DateTimeImmutable $now): void
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

    private function audienceCondition(): string
    {
        return $this->audience === null
            ? $this->audienceColumn . ' IS NULL'
            : $this->audienceColumn . ' = :audience';
    }

    /** @return array<string, string> */
    private function audienceBindings(): array
    {
        return $this->audience === null ? [] : ['audience' => $this->audience];
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

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
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
