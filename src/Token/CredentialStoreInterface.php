<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use DateTimeInterface;
use Psr\Http\Message\ServerRequestInterface;

interface CredentialStoreInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function issue(
        int|string $subjectId,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
        ?ServerRequestInterface $request = null,
    ): IssuedCredential;

    public function findValid(
        string $credential,
        ?ServerRequestInterface $request = null,
    ): ?CredentialRecord;

    public function revoke(string $credential): int;

    public function revokeSelector(string $selector): int;

    public function revokeAllForSubject(int|string $subjectId): int;
}
