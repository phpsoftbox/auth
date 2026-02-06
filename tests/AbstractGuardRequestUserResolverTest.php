<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Resolver\AbstractGuardRequestUserResolver;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\ArraySessionStore;
use PhpSoftBox\Session\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function password_hash;

use const PASSWORD_DEFAULT;

#[CoversClass(AbstractGuardRequestUserResolver::class)]
final class AbstractGuardRequestUserResolverTest extends TestCase
{
    /**
     * Проверяет, что resolve возвращает пользователя из request-атрибута.
     */
    #[Test]
    public function resolveReturnsRequestAttributeWhenPresent(): void
    {
        $auth     = new AuthManager([], defaultGuard: 'web');
        $authUser = new class () implements UserIdentityInterface {
            public function id(): int|null
            {
                return 12;
            }
        };

        $resolver = $this->resolver(
            auth: $auth,
            request: new ServerRequest('GET', 'https://example.test')->withAttribute('_authUser', $authUser),
        );

        $resolved = $resolver->resolve();

        $this->assertSame($authUser, $resolved);
        $this->assertSame(12, $resolver->getIdOrFail());
    }

    /**
     * Проверяет fallback на guard, когда _authUser отсутствует.
     */
    #[Test]
    public function resolveFallsBackToGuardUser(): void
    {
        $users = new ArrayUserProvider([
            [
                'id'            => 21,
                'email'         => 'resolver@example.test',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ],
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
     */
    #[Test]
    public function getIdOrFailThrowsWhenUserIsMissing(): void
    {
        $users   = new ArrayUserProvider([]);
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

    private function resolver(AuthManager $auth, ServerRequest $request): AbstractGuardRequestUserResolver
    {
        return new readonly class ($auth, $request) extends AbstractGuardRequestUserResolver {
            protected function guardName(): string
            {
                return 'web';
            }
        };
    }
}
