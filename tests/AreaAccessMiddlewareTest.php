<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use BackedEnum;
use PhpSoftBox\Auth\Authorization\PermissionCheckerInterface;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Middleware\AreaAccessDeniedMode;
use PhpSoftBox\Auth\Middleware\AreaAccessMiddleware;
use PhpSoftBox\Auth\Middleware\AreaAccessRule;
use PhpSoftBox\Auth\Tests\Authorization\TestPermissionEnum;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AreaAccessDeniedMode::class)]
#[CoversClass(AreaAccessMiddleware::class)]
#[CoversClass(AreaAccessRule::class)]
final class AreaAccessMiddlewareTest extends TestCase
{
    /**
     * Проверяет режим скрытия protected-area через 404.
     */
    #[Test]
    public function returnsNotFoundWhenUnauthenticatedAndModeIsNotFound(): void
    {
        $auth = new AuthManager(['web' => new TestGuard(null)]);

        $middleware = new AreaAccessMiddleware(
            $auth,
            new ResponseFactory(),
            new AreaAccessRule('admin', deniedMode: AreaAccessDeniedMode::NotFound),
        );

        $response = $middleware->process(new ServerRequest('GET', 'https://admin.example.test'), new TestHandler());

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * Проверяет redirect-реакцию для area access.
     */
    #[Test]
    public function redirectsWhenDeniedModeIsRedirect(): void
    {
        $auth = new AuthManager(['web' => new TestGuard(null)]);

        $middleware = new AreaAccessMiddleware(
            $auth,
            new ResponseFactory(),
            new AreaAccessRule('account', deniedMode: AreaAccessDeniedMode::Redirect, redirectTo: '/login'),
        );

        $response = $middleware->process(new ServerRequest('GET', 'https://example.test/account'), new TestHandler());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    /**
     * Проверяет permission-based доступ и прокидывание area/user в request.
     */
    #[Test]
    public function allowsUserWithAreaPermission(): void
    {
        $user = new class () implements UserInterface {
            public function id(): int|string|null
            {
                return 10;
            }
        };

        $checker = new class () implements PermissionCheckerInterface {
            public ?string $permission = null;

            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                $this->permission = $permission;

                return $permission === 'admin.access';
            }
        };

        $auth = new AuthManager(['web' => new TestGuard($user)], permissions: $checker);

        $handler = new TestHandler();

        $middleware = new AreaAccessMiddleware(
            $auth,
            new ResponseFactory(),
            new AreaAccessRule('admin', permission: TestPermissionEnum::AdminAccess),
        );

        $response = $middleware->process(new ServerRequest('GET', 'https://admin.example.test'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('admin.access', $checker->permission);
        self::assertSame('admin', $handler->request?->getAttribute('_area'));
        self::assertSame(10, $handler->request?->getAttribute('user_id'));
    }

    /**
     * Проверяет отказ, если authenticated пользователь не имеет permission для area.
     */
    #[Test]
    public function deniesUserWithoutAreaPermission(): void
    {
        $auth = new AuthManager(
            ['web' => new TestGuard(new AuthTestUser(id: 10))],
            permissions: new class () implements PermissionCheckerInterface {
                public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
                {
                    return false;
                }
            },
        );

        $middleware = new AreaAccessMiddleware(
            $auth,
            new ResponseFactory(),
            new AreaAccessRule('admin', permission: 'admin.access'),
        );

        $response = $middleware->process(new ServerRequest('GET', 'https://admin.example.test'), new TestHandler());

        self::assertSame(403, $response->getStatusCode());
    }
}
