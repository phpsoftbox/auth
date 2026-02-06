<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Authorization\PermissionCheckerInterface;
use PhpSoftBox\Auth\Exception\PermissionDeniedException;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Middleware\PermissionDeniedHandlerInterface;
use PhpSoftBox\Auth\Middleware\PermissionMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class PermissionMiddlewareTest extends TestCase
{
    public function testResolvesPermissionFromHandlerAttribute(): void
    {
        $guard   = new TestGuard(['id' => 10]);
        $checker = new class () implements PermissionCheckerInterface {
            public ?string $permission = null;
            public mixed $subject      = null;

            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                $this->permission = $permission;
                $this->subject    = $subject;

                return true;
            }
        };

        $auth = new AuthManager(['web' => $guard], permissions: $checker);

        $middleware = new PermissionMiddleware($auth);

        $request = new ServerRequest('GET', 'https://example.com/users/5')
            ->withAttribute('_route_handler', [PermissionController::class, 'update'])
            ->withAttribute('user', ['id' => 5]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('users.base.update', $checker->permission);
        $this->assertSame(['id' => 5], $checker->subject);
    }

    public function testDeniedThrowsByDefault(): void
    {
        $guard   = new TestGuard(['id' => 10]);
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return false;
            }
        };

        $auth = new AuthManager(['web' => $guard], permissions: $checker);

        $middleware = new PermissionMiddleware($auth, permission: 'users.base.read');

        $request = new ServerRequest('GET', 'https://example.com/users');

        $this->expectException(PermissionDeniedException::class);

        $middleware->process($request, new TestHandler());
    }

    public function testDeniedUsesCustomHandlerWhenProvided(): void
    {
        $guard   = new TestGuard(['id' => 10]);
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return false;
            }
        };

        $deniedHandler = new class () implements PermissionDeniedHandlerInterface {
            public ?string $permission = null;

            public function handle(
                ServerRequestInterface $request,
                string $permission,
                mixed $subject = null,
                mixed $user = null,
            ): ResponseInterface {
                $this->permission = $permission;

                return new Response(303, ['Location' => '/tasks']);
            }
        };

        $auth = new AuthManager(['web' => $guard], permissions: $checker);

        $middleware = new PermissionMiddleware(
            $auth,
            permission: 'tenant.fulfillment.base.read',
            deniedHandler: $deniedHandler,
        );

        $request = new ServerRequest('GET', 'https://example.com/my-companies');

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/tasks', $response->getHeaderLine('Location'));
        $this->assertSame('tenant.fulfillment.base.read', $deniedHandler->permission);
    }

    public function testThrowsWhenPermissionIsNotResolvedInStrictMode(): void
    {
        $guard   = new TestGuard(['id' => 10]);
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return true;
            }
        };

        $auth = new AuthManager(['web' => $guard], permissions: $checker);

        $middleware = new PermissionMiddleware($auth);

        $request = new ServerRequest('GET', 'https://example.com/no-permission');

        $this->expectException(RuntimeException::class);

        $middleware->process($request, new TestHandler());
    }

    public function testAllowsRequestWhenPermissionIsNotResolvedInNonStrictMode(): void
    {
        $guard   = new TestGuard(['id' => 10]);
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return true;
            }
        };

        $auth = new AuthManager(['web' => $guard], permissions: $checker);

        $middleware = new PermissionMiddleware($auth, requireResolvedPermission: false);

        $request = new ServerRequest('GET', 'https://example.com/no-permission');

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
