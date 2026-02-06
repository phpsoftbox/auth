<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PHPUnit\Framework\TestCase;

final class ArrayUserProviderTest extends TestCase
{
    /**
     * Проверяем, что провайдер находит пользователя по логин-полю.
     */
    public function testRetrieveByCredentials(): void
    {
        $provider = new ArrayUserProvider([
            ['id' => 1, 'email' => 'admin@example.com'],
        ]);

        $user = $provider->retrieveByCredentials(['email' => 'admin@example.com']);

        $this->assertIsArray($user);
        $this->assertSame(1, $user['id']);
    }

    /**
     * Проверяем, что валидация пароля проходит через PasswordCredentialsValidator.
     */
    public function testValidateCredentials(): void
    {
        $provider = new ArrayUserProvider([
            ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $user = $provider->retrieveByCredentials(['email' => 'admin@example.com']);

        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
    }
}
