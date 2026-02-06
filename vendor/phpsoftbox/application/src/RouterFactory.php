<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Exception\RouteCacheException;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\RouteResolver;
use PhpSoftBox\Router\Router;

final class RouterFactory
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ?RouteCache $cache = null,
        private readonly ?string $environment = null,
    ) {
    }

    /**
     * @param callable(RouteCollector):void $routes
     */
    public function create(callable $routes, ?string $environment = null): Router
    {
        $env = $environment ?? $this->environment;
        $collector = null;

        if ($this->cache !== null) {
            try {
                $collector = $this->cache->has($env) ? $this->cache->load($env) : null;
            } catch (RouteCacheException) {
                $collector = null;
            }
        }

        if (!$collector instanceof RouteCollector) {
            $collector = new RouteCollector();
            $routes($collector);

            if ($this->cache !== null) {
                $this->cache->dump($collector, $env);
            }
        }

        return new Router(new RouteResolver($collector), $this->dispatcher, $collector);
    }
}
