<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use PhpSoftBox\Auth\Token\CredentialCodec;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

use function array_values;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function trim;

use const JSON_THROW_ON_ERROR;

final class DatabaseEnrollmentCredentialStore implements EnrollmentCredentialStoreInterface
{
    private readonly string $audience;
    private readonly CredentialCodec $codec;

    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        string $audience,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'enrollment_credentials',
        private readonly string $idColumn = 'id',
        private readonly string $subjectIdColumn = 'subject_id',
        private readonly string $audienceColumn = 'audience',
        private readonly string $allowedAudiencesColumn = 'allowed_audiences',
        private readonly string $selectorColumn = 'selector',
        private readonly string $tokenHashColumn = 'token_hash',
        private readonly string $expiresDatetimeColumn = 'expires_datetime',
        private readonly string $revokedDatetimeColumn = 'revoked_datetime',
        private readonly string $usedDatetimeColumn = 'used_datetime',
        private readonly string $createdDatetimeColumn = 'created_datetime',
        private readonly string $createdIpColumn = 'created_ip',
        private readonly string $createdUserAgentColumn = 'created_user_agent',
        private readonly string $usedIpColumn = 'used_ip',
        private readonly string $usedUserAgentColumn = 'used_user_agent',
        private readonly string $metadataColumn = 'metadata',
        ?CredentialCodec $codec = null,
    ) {
        $audience = trim($audience);
        if ($audience === '') {
            throw new InvalidArgumentException('Enrollment audience must not be empty.');
        }

        $this->audience = $audience;
        $this->codec    = $codec ?? new CredentialCodec();
    }

    public function issue(
        int|string $subjectId,
        array $allowedAudiences,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
        ?ServerRequestInterface $request = null,
    ): IssuedEnrollmentCredential {
        $allowedAudiences = $this->normalizeAllowedAudiences($allowedAudiences);
        $credential       = $this->codec->generate();
        $conn             = $this->connections->write($this->connectionName);

        $conn->query()
            ->insert($this->table, [
                $this->subjectIdColumn        => $subjectId,
                $this->audienceColumn         => $this->audience,
                $this->allowedAudiencesColumn => json_encode($allowedAudiences, JSON_THROW_ON_ERROR),
                $this->selectorColumn         => $credential->selector,
                $this->tokenHashColumn        => $credential->secretHash,
                $this->expiresDatetimeColumn  => $this->dateToStorage($expiresAt),
                $this->revokedDatetimeColumn  => null,
                $this->usedDatetimeColumn     => null,
                $this->createdDatetimeColumn  => $this->dateToStorage(Clock::now()),
                $this->createdIpColumn        => $this->requestIp($request),
                $this->createdUserAgentColumn => $this->requestUserAgent($request),
                $this->usedIpColumn           => null,
                $this->usedUserAgentColumn    => null,
                $this->metadataColumn         => $this->metadataToStorage($metadata),
            ])
            ->execute();

        $id = $conn->lastInsertId();

        return new IssuedEnrollmentCredential(
            token: $credential->token,
            selector: $credential->selector,
            subjectId: $subjectId,
            audience: $this->audience,
            allowedAudiences: $allowedAudiences,
            expiresAt: $expiresAt === null ? null : DateTimeImmutable::createFromInterface($expiresAt),
            id: is_numeric($id) ? (int) $id : null,
        );
    }

    public function consume(
        #[SensitiveParameter] string $credential,
        ?ServerRequestInterface $request = null,
    ): EnrollmentGrant {
        return $this->exchange(
            $credential,
            static fn (EnrollmentGrant $grant): EnrollmentGrant => $grant,
            $request,
        );
    }

    public function exchange(
        #[SensitiveParameter] string $credential,
        callable $exchange,
        ?ServerRequestInterface $request = null,
    ): mixed {
        $parsed = $this->codec->parse($credential);
        if ($parsed === null) {
            throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::Invalid);
        }

        return $this->connections->write($this->connectionName)->transaction(
            function (ConnectionInterface $conn) use ($parsed, $exchange, $request): mixed {
                $row = $this->findRowForUpdate($conn, $parsed->selector);
                if ($row === null) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::Invalid);
                }

                $hash = $row[$this->tokenHashColumn] ?? null;
                if (!is_string($hash) || !$this->codec->verify($parsed->secret, $hash)) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::Invalid);
                }

                $grant = $this->grantFromRow($row);
                if ($grant === null || $this->dateFromStorage($row[$this->revokedDatetimeColumn] ?? null) !== null) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::Invalid);
                }

                if ($this->dateFromStorage($row[$this->usedDatetimeColumn] ?? null) !== null) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::AlreadyUsed);
                }

                $now = Clock::now();
                if ($grant->expiresAt !== null && $grant->expiresAt <= $now) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::Expired);
                }

                $updated = $conn->query()
                    ->update($this->table, [
                        $this->usedDatetimeColumn  => $this->dateToStorage($now),
                        $this->usedIpColumn        => $this->requestIp($request),
                        $this->usedUserAgentColumn => $this->requestUserAgent($request),
                    ])
                    ->where($this->idColumn . ' = :id', ['id' => $grant->id])
                    ->where($this->audienceColumn . ' = :audience', ['audience' => $this->audience])
                    ->where($this->usedDatetimeColumn . ' IS NULL')
                    ->where($this->revokedDatetimeColumn . ' IS NULL')
                    ->execute();

                if ($updated !== 1) {
                    throw new EnrollmentCredentialException(EnrollmentFailureReasonEnum::AlreadyUsed);
                }

                return $exchange($grant);
            },
        );
    }

    public function revoke(#[SensitiveParameter] string $credential): int
    {
        $parsed = $this->codec->parse($credential);
        if ($parsed === null) {
            return 0;
        }

        return $this->connections->write($this->connectionName)
            ->query()
            ->update($this->table, [
                $this->revokedDatetimeColumn => $this->dateToStorage(Clock::now()),
            ])
            ->where($this->selectorColumn . ' = :selector', ['selector' => $parsed->selector])
            ->where($this->audienceColumn . ' = :audience', ['audience' => $this->audience])
            ->where($this->revokedDatetimeColumn . ' IS NULL')
            ->execute();
    }

    /** @return array<string, mixed>|null */
    private function findRowForUpdate(ConnectionInterface $conn, string $selector): ?array
    {
        $lock = $conn->driver()->name() === 'sqlite' ? '' : ' FOR UPDATE';
        $sql  = sprintf(
            '
                SELECT *
                FROM %s
                WHERE %s = :selector
                    AND %s = :audience
                LIMIT 1%s
            ',
            $conn->table($this->table),
            $this->selectorColumn,
            $this->audienceColumn,
            $lock,
        );

        return $conn->fetchOne($sql, [
            'selector' => $selector,
            'audience' => $this->audience,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function grantFromRow(array $row): ?EnrollmentGrant
    {
        $id = $row[$this->idColumn] ?? null;
        if (!is_int($id) && !is_string($id)) {
            return null;
        }

        $subjectId = $row[$this->subjectIdColumn] ?? null;
        if (!is_int($subjectId) && !is_string($subjectId)) {
            return null;
        }

        $audience = $row[$this->audienceColumn] ?? null;
        if (!is_string($audience) || $audience !== $this->audience) {
            return null;
        }

        $allowedAudiences = $this->audiencesFromStorage($row[$this->allowedAudiencesColumn] ?? null);
        if ($allowedAudiences === null) {
            return null;
        }

        return new EnrollmentGrant(
            id: $id,
            subjectId: $subjectId,
            audience: $audience,
            allowedAudiences: $allowedAudiences,
            expiresAt: $this->dateFromStorage($row[$this->expiresDatetimeColumn] ?? null),
            metadata: $this->metadataFromStorage($row[$this->metadataColumn] ?? null),
        );
    }

    /**
     * @param list<string> $audiences
     * @return list<string>
     */
    private function normalizeAllowedAudiences(array $audiences): array
    {
        $normalized = [];
        foreach ($audiences as $audience) {
            if (!is_string($audience)) {
                throw new InvalidArgumentException('Allowed enrollment audiences must contain strings only.');
            }

            $audience = trim($audience);
            if ($audience === '') {
                throw new InvalidArgumentException('Allowed enrollment audiences must not contain empty values.');
            }

            $normalized[$audience] = $audience;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('At least one allowed enrollment audience is required.');
        }

        return array_values($normalized);
    }

    /** @return list<string>|null */
    private function audiencesFromStorage(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $audiences = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($audiences)) {
            return null;
        }

        try {
            return $this->normalizeAllowedAudiences($audiences);
        } catch (InvalidArgumentException) {
            return null;
        }
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

    /** @param array<string, mixed> $metadata */
    private function metadataToStorage(array $metadata): ?string
    {
        return $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
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
        $ip = $request?->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && trim($ip) !== '' ? trim($ip) : null;
    }

    private function requestUserAgent(?ServerRequestInterface $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $userAgent = trim($request->getHeaderLine('User-Agent'));

        return $userAgent === '' ? null : $userAgent;
    }
}
