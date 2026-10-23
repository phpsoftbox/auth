<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\GuardInterface;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Resolver\AbstractGuardRequestUserResolver;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

#[CoversClass(AbstractGuardRequestUserResolver::class)]
#[CoversMethod(AbstractGuardRequestUserResolver::class, 'resolve')]
#[CoversMethod(AbstractGuardRequestUserResolver::class, 'getId')]
#[CoversMethod(AbstractGuardRequestUserResolver::class, 'getIdOrFail')]
final class AbstractGuardRequestUserResolverTest extends TestCase
{
    /**
     * Проверяет, что resolve возвращает пользователя из request-атрибута.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     */
    #[Test]
    public function resolveReturnsRequestAttributeWhenPresent(): void
    {
        $auth     = new AuthManager([], defaultGuard: 'web');
        $authUser = new class () implements UserInterface {
            public function id(): int|string|null
            {
                return 12;
            }
        };

        $resolver = $this->resolver(
            auth: $auth,
            request: new ServerRequest('GET', 'https://example.test')->withAttribute('user', $authUser),
        );

        $resolved = $resolver->resolve();

        $this->assertSame($authUser, $resolved);
        $this->assertSame(12, $resolver->getIdOrFail());
    }

    /**
     * Проверяет fallback на guard, когда request-атрибут user отсутствует.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     */
    #[Test]
    public function resolveFallsBackToGuardUser(): void
    {
        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 21, email: 'resolver@example.test'),
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 21);

        $guard = new SessionGuard($session, $users);

        $auth = new AuthManager(['web' => $guard], defaultGuard: 'web');

        $resolver = $this->resolver(
            auth: $auth,
            request: new ServerRequest('GET', 'https://example.test'),
        );

        $this->assertSame(21, $resolver->getIdOrFail());
    }

    /**
     * Проверяет RuntimeException при отсутствии пользователя.
     *
     * @see AbstractGuardRequestUserResolver::getIdOrFail()
     */
    #[Test]
    public function getIdOrFailThrowsWhenUserIsMissing(): void
    {
        $users   = new InMemoryUserProvider([]);
        $session = new Session(new ArraySessionStore());

        $session->start();

        $guard = new SessionGuard($session, $users);

        $auth = new AuthManager(['web' => $guard], defaultGuard: 'web');

        $resolver = $this->resolver(
            auth: $auth,
            request: new ServerRequest('GET', 'https://example.test'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found.');

        $resolver->getIdOrFail();
    }

    /**
     * Проверяет, что невалидный request-атрибут user игнорируется.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     */
    #[Test]
    public function ignoresInvalidRequestUserAttribute(): void
    {
        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 'user-42', email: 'resolver@example.test'),
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 'user-42');

        $guard = new SessionGuard($session, $users);

        $auth = new AuthManager(['web' => $guard], defaultGuard: 'web');

        $resolver = $this->resolver(
            auth: $auth,
            request: new ServerRequest('GET', 'https://example.test')
                ->withAttribute('user', ['id' => 'ignored']),
        );

        $this->assertSame('user-42', $resolver->getIdOrFail());
    }

    /**
     * Проверяет, что один resolver берёт пользователя из текущего запроса при каждом вызове.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     * @see AbstractGuardRequestUserResolver::getIdOrFail()
     */
    #[Test]
    public function requestProviderUsesCurrentRequestAcrossSequentialRequests(): void
    {
        $firstUser  = new AuthTestUser(id: 31, email: 'first@example.test');
        $secondUser = new AuthTestUser(id: 32, email: 'second@example.test');
        $request    = new ServerRequest('GET', 'https://example.test')->withAttribute('user', $firstUser);

        $resolver = $this->resolver(
            auth: new AuthManager([], defaultGuard: 'web'),
            requestProvider: static function () use (&$request): ServerRequestInterface {
                return $request;
            },
        );

        $this->assertSame($firstUser, $resolver->resolve());

        $request = new ServerRequest('GET', 'https://example.test')->withAttribute('user', $secondUser);

        $this->assertSame($secondUser, $resolver->resolve());
        $this->assertSame(32, $resolver->getIdOrFail());
    }

    /**
     * Проверяет, что guard получает новый запрос, когда пользователь не задан request-атрибутом.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     */
    #[Test]
    public function requestProviderPassesCurrentRequestToGuard(): void
    {
        $firstUser  = new AuthTestUser(id: 41, email: 'first@example.test');
        $secondUser = new AuthTestUser(id: 42, email: 'second@example.test');
        $request    = new ServerRequest('GET', 'https://example.test')->withAttribute('identity', $firstUser);

        $guard = $this->createMock(GuardInterface::class);
        $guard->expects($this->exactly(2))
            ->method('user')
            ->willReturnCallback(static fn (ServerRequestInterface $current): ?UserInterface => $current->getAttribute('identity'));

        $resolver = $this->resolver(
            auth: new AuthManager(['web' => $guard]),
            requestProvider: static function () use (&$request): ServerRequestInterface {
                return $request;
            },
        );

        $this->assertSame($firstUser, $resolver->resolve());

        $request = new ServerRequest('GET', 'https://example.test')->withAttribute('identity', $secondUser);

        $this->assertSame($secondUser, $resolver->resolve());
    }

    /**
     * Проверяет, что ошибка провайдера не возвращает пользователя из сохранённого старого запроса.
     *
     * @see AbstractGuardRequestUserResolver::resolve()
     */
    #[Test]
    public function invalidRequestProviderDoesNotFallBackToStoredRequest(): void
    {
        $user = new AuthTestUser(id: 33, email: 'previous@example.test');

        $resolver = $this->resolver(
            auth: new AuthManager([], defaultGuard: 'web'),
            request: new ServerRequest('GET', 'https://example.test')->withAttribute('user', $user),
            requestProvider: static fn (): null => null,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Request provider must return a server request.');

        $resolver->resolve();
    }

    private function resolver(
        AuthManager $auth,
        ?ServerRequestInterface $request = null,
        ?callable $requestProvider = null,
    ): AbstractGuardRequestUserResolver {
        return new readonly class ($auth, $request, requestProvider: $requestProvider) extends AbstractGuardRequestUserResolver {
            protected function guardName(): string
            {
                return 'web';
            }
        };
    }
}
