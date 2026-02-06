<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Guard\CallbackGuard;
use PhpSoftBox\Auth\Manager\AuthManager;
use PHPUnit\Framework\TestCase;

final class AuthManagerTest extends TestCase
{
    /**
     * Проверяем, что AuthManager возвращает guard по умолчанию.
     */
    public function testReturnsDefaultGuard(): void
    {
        $manager = new AuthManager([
            'web' => fn () => new CallbackGuard(fn () => ['id' => 1]),
        ], defaultGuard: 'web');

        $guard = $manager->guard();

        $this->assertInstanceOf(CallbackGuard::class, $guard);
    }
}
