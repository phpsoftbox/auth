<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Enrollment;

use DateTimeInterface;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

interface EnrollmentCredentialStoreInterface
{
    /**
     * @param list<string> $allowedAudiences
     * @param array<string, mixed> $metadata
     */
    public function issue(
        int|string $subjectId,
        array $allowedAudiences,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
        ?ServerRequestInterface $request = null,
    ): IssuedEnrollmentCredential;

    public function consume(
        #[SensitiveParameter] string $credential,
        ?ServerRequestInterface $request = null,
    ): EnrollmentGrant;

    /**
     * Выполняет callback и погашает enrollment credential в одной транзакции.
     *
     * Все создаваемые callback-ом постоянные credentials должны использовать
     * то же DB-подключение, чтобы их выпуск откатился вместе с погашением.
     *
     * @template T
     * @param callable(EnrollmentGrant): T $exchange
     * @return T
     */
    public function exchange(
        #[SensitiveParameter] string $credential,
        callable $exchange,
        ?ServerRequestInterface $request = null,
    ): mixed;

    public function revoke(#[SensitiveParameter] string $credential): int;
}
