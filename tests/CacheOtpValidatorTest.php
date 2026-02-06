<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Otp\CacheOtpValidator;
use PhpSoftBox\Auth\Otp\OtpCodeGenerator;
use PhpSoftBox\Auth\Otp\OtpState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheOtpValidator::class)]
final class CacheOtpValidatorTest extends TestCase
{
    #[Test]
    public function issuesAndValidatesCode(): void
    {
        $cache = new ArrayCache();

        $validator = new CacheOtpValidator($cache, new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 3, lockSeconds: 900);

        $state = $validator->issue('user.1', 4);

        self::assertInstanceOf(OtpState::class, $state);
        self::assertNotNull($state->code());
        self::assertSame(3, $state->attemptsLeft());

        $isValid = $validator->validate('user.1', (string) $state->code());
        self::assertTrue($isValid);
        self::assertNull($validator->state('user.1'));
    }

    #[Test]
    public function locksAfterMaxAttempts(): void
    {
        $cache = new ArrayCache();

        $validator = new CacheOtpValidator($cache, new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 2, lockSeconds: 60);

        $state = $validator->issue('user.2', 4);

        self::assertFalse($validator->validate('user.2', '0000'));
        self::assertFalse($validator->validate('user.2', '0000'));

        $lockedState = $validator->state('user.2');
        self::assertNotNull($lockedState);
        self::assertTrue($lockedState->isLocked());
        self::assertSame(0, $lockedState->attemptsLeft());
    }
}
