<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use BackedEnum;
use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\PermissionCheckerInterface;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;
use PhpSoftBox\Auth\Authorization\PermissionGate;
use PhpSoftBox\Auth\Authorization\PermissionPolicyRegistry;
use PhpSoftBox\Auth\Authorization\PermissionRequirementMode;
use PhpSoftBox\Auth\Authorization\Subject\OwnershipRegistry;
use PhpSoftBox\Auth\Authorization\Subject\OwnershipSubject;
use PhpSoftBox\Auth\Authorization\Subject\PermissionCaseSubjectResolver;
use PhpSoftBox\Auth\Authorization\Subject\RequestAttributeRouteParameterProvider;
use PhpSoftBox\Auth\Authorization\Subject\RouteParameterProviderInterface;
use PhpSoftBox\Auth\Authorization\Subject\RouteParamSubject;
use PhpSoftBox\Auth\Exception\PermissionDeniedException;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Middleware\PermissionDecisionDeniedHandlerInterface;
use PhpSoftBox\Auth\Middleware\PermissionDeniedHandlerInterface;
use PhpSoftBox\Auth\Middleware\PermissionHttpDeniedHandler;
use PhpSoftBox\Auth\Middleware\PermissionMiddleware;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class PermissionMiddlewareTest extends TestCase
{
    public function testResolvesPermissionFromHandlerAttribute(): void
    {
        $guard   = new TestGuard(new AuthTestUser(id: 10));
        $checker = new class () implements PermissionCheckerInterface {
            public ?string $permission = null;
            public mixed $subject      = null;

            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
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
        $guard   = new TestGuard(new AuthTestUser(id: 10));
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
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
        $guard   = new TestGuard(new AuthTestUser(id: 10));
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
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
        $guard   = new TestGuard(new AuthTestUser(id: 10));
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
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
        $guard   = new TestGuard(new AuthTestUser(id: 10));
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
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

    public function testRequiresAnyPermissionStopsOnFirstAllowedCase(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            /** @var list<string> */
            public array $calls = [];

            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                $this->calls[] = $permission;

                return $permission === 'tenant.companies.base.read';
            }
        };

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker));

        $middleware = new PermissionMiddleware($auth);
        $request    = new ServerRequest('GET', 'https://example.com/users/10/companies')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'userCompanies'])
            ->withAttribute('_route_params', ['user' => 10]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['tenant.companies.base.read'], $checker->calls);
    }

    public function testRequiresAnyPermissionAllowsOwnCaseByRouteParamSubject(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            /** @var list<string> */
            public array $calls = [];

            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                $this->calls[] = $permission;

                return $permission === 'tenant.companies.own.read';
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->definePattern('tenant.companies.own.*', static function (AuthTestUser $user, RouteParamSubject $subject): bool {
                return (string) $user->id() === (string) $subject->value('user');
            });

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker, $policies));

        $middleware = new PermissionMiddleware($auth);
        $request    = new ServerRequest('GET', 'https://example.com/users/10/companies')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'userCompanies'])
            ->withAttribute('_route_params', ['user' => 10]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['tenant.companies.base.read', 'tenant.companies.own.read'], $checker->calls);
    }

    public function testRequiresAnyPermissionReadsRouteParamsThroughProvider(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return $permission === 'tenant.companies.own.read';
            }
        };

        $routes = new class () implements RouteParameterProviderInterface {
            public bool $called = false;

            public function get(ServerRequestInterface $request, string $name): mixed
            {
                return $this->all($request)[$name] ?? null;
            }

            public function all(ServerRequestInterface $request): array
            {
                $this->called = true;

                return ['user' => 10];
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->definePattern('tenant.companies.own.*', static function (AuthTestUser $user, RouteParamSubject $subject): bool {
                return (string) $user->id() === (string) $subject->value('user');
            });

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker, $policies));

        $middleware = new PermissionMiddleware(
            $auth,
            subjectResolver: new PermissionCaseSubjectResolver(routes: $routes),
        );
        $request = new ServerRequest('GET', 'https://example.com/users/10/companies')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'userCompanies']);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($routes->called);
    }

    public function testRequiresAnyPermissionReturnsNotFoundWhenOwnPolicyDenies(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return $permission === 'tenant.companies.own.read';
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->definePattern('tenant.companies.own.*', static function (AuthTestUser $user, RouteParamSubject $subject): AccessDecision {
                return (string) $user->id() === (string) $subject->value('user')
                    ? AccessDecision::allow()
                    : AccessDecision::deny('Resource is hidden.');
            });

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker, $policies));

        $middleware = new PermissionMiddleware($auth, responses: new ResponseFactory());
        $request    = new ServerRequest('GET', 'https://example.com/users/20/companies')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'userCompanies'])
            ->withAttribute('_route_params', ['user' => 20]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRequiresAnyPermissionAllowsOwnCaseByOwnershipSubject(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return $permission === 'tenant.companies.own.read';
            }
        };

        $ownership = new OwnershipRegistry()
            ->define('company', 'company', static fn (int $companyId): OwnershipSubject => new OwnershipSubject(
                type: 'company',
                id: $companyId,
                ownerId: 10,
                routeParam: 'company',
                value: $companyId,
            ));

        $policies = new PermissionPolicyRegistry()
            ->definePattern('tenant.companies.own.*', static function (AuthTestUser $user, OwnershipSubject $subject): bool {
                return (string) $user->id() === (string) $subject->ownerId;
            });

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker, $policies));

        $middleware = new PermissionMiddleware(
            $auth,
            subjectResolver: new PermissionCaseSubjectResolver(
                routes: new RequestAttributeRouteParameterProvider(),
                ownership: $ownership,
            ),
        );
        $request = new ServerRequest('GET', 'https://example.com/companies/77')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'company'])
            ->withAttribute('_route_params', ['company' => 77]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequiresAllPermissionsDeniesWhenAnyCaseIsDenied(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            /** @var list<string> */
            public array $calls = [];

            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                $this->calls[] = $permission;

                return $permission === 'tenant.companies.base.read';
            }
        };

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker));

        $middleware = new PermissionMiddleware($auth, responses: new ResponseFactory());
        $request    = new ServerRequest('GET', 'https://example.com/companies/audit')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'audit']);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['tenant.companies.base.read', 'tenant.companies.audit.read'], $checker->calls);
    }

    public function testPermissionCasesCanBeProvidedByRouteDefaultsAsArrays(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return $permission === 'tenant.companies.own.read';
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->definePattern('tenant.companies.own.*', static function (AuthTestUser $user, RouteParamSubject $subject): bool {
                return (string) $user->id() === (string) $subject->value('user');
            });

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker, $policies));

        $middleware = new PermissionMiddleware($auth);
        $request    = new ServerRequest('GET', 'https://example.com/users/10/companies')
            ->withAttribute('_route_params', ['user' => 10])
            ->withAttribute('_permission_cases', [
                ['permission' => 'tenant.companies.base.read'],
                [
                    'permission'   => 'tenant.companies.own.read',
                    'subject_type' => 'route_param',
                    'subject'      => 'user',
                ],
            ])
            ->withAttribute('_permission_mode', PermissionRequirementMode::Any->value)
            ->withAttribute('_permission_denied_mode', PermissionDeniedMode::NotFound->value);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMissingOwnershipBindingDeniesRequest(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return $permission === 'tenant.companies.own.read';
            }
        };

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker));

        $middleware = new PermissionMiddleware(
            $auth,
            responses: new ResponseFactory(),
            subjectResolver: new PermissionCaseSubjectResolver(
                routes: new RequestAttributeRouteParameterProvider(),
                ownership: new OwnershipRegistry(),
            ),
        );
        $request = new ServerRequest('GET', 'https://example.com/companies/77')
            ->withAttribute('_route_handler', [PermissionPolicyController::class, 'company'])
            ->withAttribute('_route_params', ['company' => 77]);

        $response = $middleware->process($request, new TestHandler());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDecisionDeniedHandlerReceivesAccessDecision(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
            {
                return false;
            }
        };

        $deniedHandler = new class () implements PermissionDecisionDeniedHandlerInterface {
            public ?AccessDecision $decision = null;

            public function handleDecision(
                ServerRequestInterface $request,
                AccessDecision $decision,
                string $permission,
                mixed $subject = null,
                mixed $user = null,
            ): ResponseInterface {
                $this->decision = $decision;

                return new Response(404);
            }

            public function handle(
                ServerRequestInterface $request,
                string $permission,
                mixed $subject = null,
                mixed $user = null,
            ): ResponseInterface {
                return new Response(403);
            }
        };

        $auth = new AuthManager(['web' => new TestGuard(new AuthTestUser(id: 10))], permissions: new PermissionGate($checker));

        $middleware = new PermissionMiddleware(
            $auth,
            permission: 'tenant.companies.base.read',
            deniedHandler: $deniedHandler,
        );

        $response = $middleware->process(new ServerRequest('GET', 'https://example.com/companies'), new TestHandler());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertInstanceOf(AccessDecision::class, $deniedHandler->decision);
    }

    public function testHttpDeniedHandlerRedirectsWhenDecisionModeIsRedirect(): void
    {
        $handler = new PermissionHttpDeniedHandler(
            responses: new ResponseFactory(),
            defaultMode: PermissionDeniedMode::Redirect,
            redirectTo: '/login',
        );

        $response = $handler->handleDecision(
            new ServerRequest('GET', 'https://example.com/private'),
            AccessDecision::deny('Login required.'),
            'private.access',
        );

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }
}
