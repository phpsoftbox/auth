<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PHPUnit\Framework\TestCase;

use function password_hash;

use const PASSWORD_DEFAULT;

final class InMemoryUserProviderTest extends TestCase
{
    /**
     * Проверяем, что провайдер находит пользователя через явный credentials matcher.
     */
    public function testRetrieveByCredentials(): void
    {
        $provider = new InMemoryUserProvider(
            users: [
                new AuthTestUser(id: 1, email: 'admin@example.com'),
            ],
            credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof AuthTestUser
                && $user->email === ($credentials['email'] ?? null),
        );

        $user = $provider->retrieveByCredentials(['email' => 'admin@example.com']);

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(1, $user->id());
    }

    /**
     * Проверяем, что валидация пароля проходит через PasswordCredentialsValidator с resolver callback.
     */
    public function testValidateCredentials(): void
    {
        $provider = new InMemoryUserProvider(
            users: [
                new AuthTestUser(
                    id: 1,
                    email: 'admin@example.com',
                    passwordHash: password_hash('secret', PASSWORD_DEFAULT),
                ),
            ],
            credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof AuthTestUser
                && $user->email === ($credentials['email'] ?? null),
            validators: [
                new PasswordCredentialsValidator(
                    passwordHashResolver: static fn (UserInterface $user): ?string => $user instanceof AuthTestUser
                        ? $user->passwordHash
                        : null,
                ),
            ],
        );

        $user = $provider->retrieveByCredentials(['email' => 'admin@example.com']);

        self::assertInstanceOf(UserInterface::class, $user);
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
        $this->assertFalse($provider->validateCredentials($user, ['password' => 'wrong']));
    }
}
