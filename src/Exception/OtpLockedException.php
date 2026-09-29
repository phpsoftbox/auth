<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Exception;

use RuntimeException;

use function sprintf;

/**
 * Новый код нельзя выдать: после исчерпания попыток идентификатор заблокирован до `$lockedUntil`.
 */
final class OtpLockedException extends RuntimeException
{
    public function __construct(
        public readonly int $lockedUntil,
    ) {
        parent::__construct(sprintf('OTP is locked until %d.', $lockedUntil));
    }
}
