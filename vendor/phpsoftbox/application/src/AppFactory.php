<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use PhpSoftBox\Application\Middleware\KernelRouteMiddlewareResolver;
use PhpSoftBox\Application\Middleware\MiddlewareManager;
use PhpSoftBox\Http\Emitter\EmitterInterface;
use PhpSoftBox\Http\Message\ServerRequestCreator;
use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Exception\RouteCacheException;
use PhpSoftBox\Router\Handler\ContainerHandlerResolver;
use PhpSoftBox\Router\Handler\HandlerResolverInterface;
use PhpSoftBox\Router\Middleware\RouteMiddlewareResolverInterface;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\RouteResolver;
use PhpSoftBox\Router\Router;
use Psr\Container\ContainerInterface;

final class AppFactory
{
    public static function createFromContainer(
        ContainerInterface $container,
        ?RouteCache $routeCache = null,
        ?string $environment = null,
    ): Application {
        $middlewareManager = self::resolveMiddlewareManager($container);
        $handlerResolver = self::resolveHandlerResolver($container);
        $middlewareResolver = self::resolveMiddlewareResolver($container, $middlewareManager);
        $dispatcher = self::resolveDispatcher($container, $handlerResolver, $middlewareResolver);

        $routeCache ??= $container->has(RouteCache::class)
            ? $container->get(RouteCache::class)
            : null;

        [$router, $routesCached] = self::resolveRouter($dispatcher, $routeCache, $environment);

        $requestCreator = $container->has(ServerRequestCreator::class)
            ? $container->get(ServerRequestCreator::class)
            : null;
        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        return new Application(
            $router,
            $middlewareManager,
            $container,
            $requestCreator,
            $emitter,
            $routesCached,
        );
    }

    private static function resolveMiddlewareManager(ContainerInterface $container): MiddlewareManager
    {
        if ($container->has(MiddlewareManager::class)) {
            return $container->get(MiddlewareManager::class);
        }

        return new MiddlewareManager();
    }

    private static function resolveHandlerResolver(ContainerInterface $container): HandlerResolverInterface
    {
        if ($container->has(HandlerResolverInterface::class)) {
            return $container->get(HandlerResolverInterface::class);
        }

        return new ContainerHandlerResolver($container);
    }

    private static function resolveMiddlewareResolver(
        ContainerInterface $container,
        MiddlewareManager $manager,
    ): RouteMiddlewareResolverInterface {
        if ($container->has(RouteMiddlewareResolverInterface::class)) {
            return $container->get(RouteMiddlewareResolverInterface::class);
        }

        return new KernelRouteMiddlewareResolver($manager, $container);
    }

    private static function resolveDispatcher(
        ContainerInterface $container,
        HandlerResolverInterface $handlerResolver,
        RouteMiddlewareResolverInterface $middlewareResolver,
    ): Dispatcher {
        if ($container->has(Dispatcher::class)) {
            return $container->get(Dispatcher::class);
        }

        return new Dispatcher($handlerResolver, $middlewareResolver);
    }

    /**
     * @return array{Router, bool}
     */
    private static function resolveRouter(
        Dispatcher $dispatcher,
        ?RouteCache $routeCache,
        ?string $environment,
    ): array {
        $routesCached = false;
        $collector = null;

        if ($routeCache !== null) {
            try {
                $collector = $routeCache->has($environment) ? $routeCache->load($environment) : null;
                $routesCached = $collector instanceof RouteCollector;
            } catch (RouteCacheException) {
                $collector = null;
                $routesCached = false;
            }
        }

        if (!$collector instanceof RouteCollector) {
            $collector = new RouteCollector();
        }

        $router = new Router(new RouteResolver($collector), $dispatcher, $collector);

        return [$router, $routesCached];
    }
}
