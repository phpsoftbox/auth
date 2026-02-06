<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PHPUnit\Framework\TestCase;

use function password_hash;

use const PASSWORD_DEFAULT;

final class ArrayTokenProviderTest extends TestCase
{
    /**
     * Проверяем, что провайдер резолвит пользователя по токену из map.
     */
    public function testRetrieveFromMap(): void
    {
        $users = new ArrayUserProvider([
            ['id' => 10, 'email' => 'u10@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $provider = new ArrayTokenProvider([
            'token-1' => 10,
        ], $users);

        $user = $provider->retrieveUserByToken('token-1');

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(10, $user->id());
    }

    /**
     * Проверяем, что провайдер находит токен в списке записей и резолвит пользователя.
     */
    public function testRetrieveFromList(): void
    {
        $users = new ArrayUserProvider([
            ['id' => 20, 'email' => 'u20@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $provider = new ArrayTokenProvider([
            ['token' => 'token-2', 'user_id' => 20],
        ], $users);

        $user = $provider->retrieveUserByToken('token-2');

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(20, $user->id());
    }

    /**
     * Проверяем, что провайдер может вернуть пользователя напрямую без UserProvider.
     */
    public function testRetrieveDirectUser(): void
    {
        $provider = new ArrayTokenProvider([
            'token-3' => ['id' => 30, 'email' => 'u30@example.com'],
        ]);

        $this->assertSame(['id' => 30, 'email' => 'u30@example.com'], $provider->retrieveUserByToken('token-3'));
    }

    /**
     * Проверяем, что скалярный subject без UserProvider не проходит как пользователь.
     */
    public function testScalarSubjectWithoutUserProviderReturnsNull(): void
    {
        $provider = new ArrayTokenProvider([
            'token-4' => 40,
        ]);

        $this->assertNull($provider->retrieveUserByToken('token-4'));
    }
}
