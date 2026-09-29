<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Guard\CallbackGuard;
use PhpSoftBox\Auth\Manager\AuthManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

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

    /**
     * Проверим, что ошибка сборки guard в контейнере не маскируется созданием guard без зависимостей.
     *
     * @see AuthManager::guard()
     */
    #[Test]
    public function propagatesContainerErrorForGuard(): void
    {
        $container = new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('Guard dependency is missing.');
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $manager = new AuthManager(['web' => TestGuard::class], container: $container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Guard dependency is missing.');

        $manager->guard('web');
    }
}
