<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PHPUnit\Framework\TestCase;

final class ArrayTokenProviderTest extends TestCase
{
    /**
     * Проверяем, что провайдер возвращает идентификатор по токену из map.
     */
    public function testRetrieveFromMap(): void
    {
        $provider = new ArrayTokenProvider([
            'token-1' => 10,
        ]);

        $this->assertSame(10, $provider->retrieveUserIdByToken('token-1'));
    }

    /**
     * Проверяем, что провайдер находит токен в списке записей.
     */
    public function testRetrieveFromList(): void
    {
        $provider = new ArrayTokenProvider([
            ['token' => 'token-2', 'user_id' => 20],
        ]);

        $this->assertSame(20, $provider->retrieveUserIdByToken('token-2'));
    }
}
