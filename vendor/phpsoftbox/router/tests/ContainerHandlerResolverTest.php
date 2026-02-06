<?php

declare(strict_types=1);

namespace PhpSoftBox\Router\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Handler\ContainerHandlerResolver;
use PhpSoftBox\Router\Route;
use PhpSoftBox\Router\Tests\Fixtures\RouteParamController;
use PhpSoftBox\Router\Tests\Utils\ContainerCallStub;
use PhpSoftBox\Router\Tests\Utils\ContainerStub;
use PhpSoftBox\Router\Tests\Utils\DummyController;
use PHPUnit\Framework\TestCase;

final class ContainerHandlerResolverTest extends TestCase
{
    /**
     * Проверяем, что ContainerHandlerResolver берёт контроллер из контейнера.
     */
    public function testResolvesControllerFromContainer(): void
    {
        $controller = new DummyController();

        $container = new ContainerStub([DummyController::class => $controller]);

        $resolver = new ContainerHandlerResolver($container);

        $dispatcher = new Dispatcher($resolver);

        $route = new Route('GET', '/hi', [DummyController::class, 'hello']);

        $response = $dispatcher->dispatch($route, new ServerRequest('GET', 'https://example.com/hi'));

        $this->assertSame(201, $response->getStatusCode());
    }

    /**
     * Проверяем, что при наличии container->call используется инъекция параметров.
     */
    public function testContainerCallInjectsParams(): void
    {
        $container = new ContainerCallStub([RouteParamController::class => new RouteParamController()]);

        $resolver = new ContainerHandlerResolver($container);

        $dispatcher = new Dispatcher($resolver);

        $route = new Route('GET', '/users/{id}', [RouteParamController::class, 'show']);

        $request = new ServerRequest('GET', 'https://example.com/users/42')
            ->withAttribute('id', '42');

        $response = $dispatcher->dispatch($route, $request);

        $this->assertTrue($container->called);
        $this->assertSame('42', $response->getHeaderLine('X-Id'));
    }
}
