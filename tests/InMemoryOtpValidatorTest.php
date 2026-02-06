<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Otp\InMemoryOtpValidator;
use PHPUnit\Framework\TestCase;

final class InMemoryOtpValidatorTest extends TestCase
{
    /**
     * Проверяем, что валидатор учитывает лимит попыток.
     */
    public function testAttemptsLimit(): void
    {
        $validator = new InMemoryOtpValidator(maxAttempts: 2);

        $validator->setCode('user-1', '1234');

        $this->assertFalse($validator->validate('user-1', '0000'));
        $this->assertFalse($validator->validate('user-1', '1111'));
        $this->assertFalse($validator->validate('user-1', '1234'));
        $this->assertSame(0, $validator->attemptsLeft('user-1'));
    }

    /**
     * Проверяем, что успешная проверка сбрасывает счётчик попыток.
     */
    public function testSuccessResetsAttempts(): void
    {
        $validator = new InMemoryOtpValidator(maxAttempts: 3);

        $validator->setCode('user-2', '9999');

        $this->assertFalse($validator->validate('user-2', '0000'));
        $this->assertTrue($validator->validate('user-2', '9999'));
        $this->assertSame(3, $validator->attemptsLeft('user-2'));
    }
}
