<?php

declare(strict_types=1);

namespace PhpSoftBox\Router\Tests;

use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\Tests\Utils\DummyController;
use PhpSoftBox\Router\Tests\Utils\HeaderAppendMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_shift;

final class RouteCollectorTest extends TestCase
{
    public function testNamedRoutesAndBasicAdd(): void
    {
        $rc = new RouteCollector();

        $rc->get('/users/{id}', function (ServerRequestInterface $r) {
            return new Response(200);
        }, name: 'user.show');

        $routes = $rc->getRoutes();
        $this->assertCount(1, $routes);

        $named = $rc->getNamedRoutes();
        $this->assertArrayHasKey('user.show', $named);
        $this->assertSame('/users/{id}', $named['user.show']->path);
    }

    public function testMiddlewareMergingOrderGlobalGroupLocal(): void
    {
        $rc = new RouteCollector();

        // Глобальный
        $rc->addMiddleware(new HeaderAppendMiddleware('global', 'X-Order'));

        $rc->group('/api', function (RouteCollector $r) {
            // Локальный для маршрута
            $r->get('/users', function (ServerRequestInterface $req) {
                return new Response(200, ['X-Order' => 'H']);
            }, [new HeaderAppendMiddleware('route', 'X-Order')]);
        }, [
            // Групповой
            new HeaderAppendMiddleware('group', 'X-Order'),
        ]);

        $routes = $rc->getRoutes();
        $this->assertCount(1, $routes);

        // Прогоним стек middleware вручную через handler, чтобы проверить порядок
        $route   = $routes[0];
        $handler = new class ($route->handler, $route->middlewares) implements RequestHandlerInterface {
            private $handler;
            private array $mw;
            public function __construct(
                $h,
                array $mw,
            ) {
                $this->handler = $h;
                $this->mw      = $mw;
            }
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                if (empty($this->mw)) {
                    $h = $this->handler;

                    return $h($request);
                }
                /** @var MiddlewareInterface $m */
                $m = array_shift($this->mw);

                return $m->process($request, $this);
            }
        };

        $resp = $handler->handle(new ServerRequest('GET', 'https://example.com/api/users'));

        $this->assertSame('H-route-group-global', $resp->getHeaderLine('X-Order'));
    }

    public function testResourceRoutesAndPerActionMiddleware(): void
    {
        $rc = new RouteCollector();

        $rc->resource(
            '/users',
            DummyController::class,
            except: [],
            middlewares: [],
            routeMiddlewares: [
                'store'   => [new HeaderAppendMiddleware('store')],
                'update'  => [new HeaderAppendMiddleware('update')],
                'destroy' => [new HeaderAppendMiddleware('destroy')],
            ],
            namePrefix: 'users',
        );

        $named = $rc->getNamedRoutes();
        $this->assertArrayHasKey('users.index', $named);
        $this->assertArrayHasKey('users.show', $named);
        $this->assertArrayHasKey('users.store', $named);
        $this->assertArrayHasKey('users.update', $named);
        $this->assertArrayHasKey('users.destroy', $named);

        // Проверим, что у destroy есть наш middleware
        $destroy = $named['users.destroy'];
        $this->assertNotEmpty($destroy->middlewares);
    }

    public function testResourceRoutesExepts(): void
    {
        $rc = new RouteCollector();

        $rc->resource(
            '/users',
            DummyController::class,
            except: ['show'],
            namePrefix: 'users',
        );

        $named = $rc->getNamedRoutes();
        $this->assertArrayHasKey('users.index', $named);
        $this->assertArrayNotHasKey('users.show', $named);
        $this->assertArrayHasKey('users.store', $named);
        $this->assertArrayHasKey('users.update', $named);
        $this->assertArrayHasKey('users.destroy', $named);
    }
}
