<?php

declare(strict_types=1);

namespace PhpSoftBox\Application;

use BadMethodCallException;
use InvalidArgumentException;
use PhpSoftBox\Http\Emitter\EmitterInterface;
use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\ServerRequestCreator;
use PhpSoftBox\Application\Middleware\MiddlewareManager;
use PhpSoftBox\Router\RouteCollector;
use PhpSoftBox\Router\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function array_reverse;
use function get_debug_type;
use function is_string;

final class Application implements RequestHandlerInterface
{
    private MiddlewareManager $middlewareManager;
    private ?ContainerInterface $container;
    private array $middlewareGroups = [];
    private ?RouteCollector $routes = null;
    private ?ServerRequestCreator $requestCreator;
    private ?EmitterInterface $emitter;
    private bool $routesCached;

    public function __construct(
        private RequestHandlerInterface $handler,
        array|MiddlewareManager $middlewares = [],
        ?ContainerInterface $container = null,
        ?ServerRequestCreator $requestCreator = null,
        ?EmitterInterface $emitter = null,
        bool $routesCached = false,
    ) {
        $this->middlewareManager = $middlewares instanceof MiddlewareManager
            ? $middlewares
            : new MiddlewareManager();

        if (is_array($middlewares)) {
            foreach ($middlewares as $middleware) {
                $this->middlewareManager->add($middleware);
            }
        }

        $this->container = $container;
        $this->routes = $handler instanceof Router ? $handler->routes() : null;
        $this->requestCreator = $requestCreator;
        $this->emitter = $emitter;
        $this->routesCached = $routesCached;
    }

    /**
     * @param MiddlewareInterface|string $middleware
     */
    public function add(MiddlewareInterface|string $middleware, int $priority = 0): self
    {
        $this->middlewareManager->add($middleware, $priority);

        return $this;
    }

    /**
     * @param MiddlewareInterface|string $middleware
     */
    public function addMiddlewareToGroup(string $group, MiddlewareInterface|string $middleware, int $priority = 0): self
    {
        $this->middlewareManager->addToGroup($group, $middleware, $priority);

        return $this;
    }

    /**
     * @param array<MiddlewareInterface|string> $middlewares
     */
    public function middlewareGroup(string $name, array $middlewares, int $priority = 0): self
    {
        $this->middlewareManager->addGroup($name, $middlewares, $priority);

        return $this;
    }

    /**
     * @param MiddlewareInterface|string $middleware
     */
    public function alias(string $alias, MiddlewareInterface|string $middleware): self
    {
        $this->middlewareManager->alias($alias, $middleware);

        return $this;
    }

    /**
     * @param string[] $groups
     */
    public function withMiddlewareGroups(array $groups): self
    {
        $clone = clone $this;
        $clone->middlewareGroups = $groups;

        return $clone;
    }

    public function routes(): RouteCollector
    {
        if ($this->routes === null) {
            throw new BadMethodCallException('Router is not attached to the application.');
        }

        return $this->routes;
    }

    public function routesCached(): bool
    {
        return $this->routesCached;
    }

    public function middlewareManager(): MiddlewareManager
    {
        return $this->middlewareManager;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stack = $this->resolveMiddlewareStack();

        if ($stack === []) {
            return $this->handler->handle($request);
        }

        $handler = $this->handler;

        foreach (array_reverse($stack) as $middleware) {
            $handler = new class ($middleware, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $handler,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->handler);
                }
            };
        }

        return $handler->handle($request);
    }

    public function run(?ServerRequestInterface $request = null, ?EmitterInterface $emitter = null): ResponseInterface
    {
        $request ??= ($this->requestCreator?->fromGlobals() ?? (new ServerRequestCreator())->fromGlobals());

        $response = $this->handle($request);

        $emitter ??= $this->emitter ?? new SapiEmitter();
        $emitter->emit($response);

        return $response;
    }

    /**
     * @return list<MiddlewareInterface>
     */
    private function resolveMiddlewareStack(): array
    {
        $stack = $this->middlewareManager->stack($this->middlewareGroups);

        if ($stack === []) {
            return [];
        }

        $resolved = [];

        foreach ($stack as $middleware) {
            $resolved[] = $this->resolveMiddleware($middleware);
        }

        return $resolved;
    }

    /**
     * @param MiddlewareInterface|string $middleware
     */
    private function resolveMiddleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        $middleware = $this->middlewareManager->resolveAlias($middleware);

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            if ($this->container !== null) {
                $instance = $this->container->get($middleware);
                if (!$instance instanceof MiddlewareInterface) {
                    throw new InvalidArgumentException("Resolved middleware must implement MiddlewareInterface: {$middleware}");
                }

                return $instance;
            }

            $instance = new $middleware();
            if (!$instance instanceof MiddlewareInterface) {
                throw new InvalidArgumentException("Resolved middleware must implement MiddlewareInterface: {$middleware}");
            }

            return $instance;
        }

        $type = get_debug_type($middleware);
        throw new InvalidArgumentException("Unsupported middleware definition: {$type}");
    }

    private function assertRoutesNotCached(): void
    {
        if ($this->routesCached) {
            throw new RuntimeException('Routes are cached. Clear route cache before registering new routes.');
        }
    }

    public function addRoute(
        string $method,
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->addRoute($method, $path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function addRouteMiddleware(string $method, string $path, MiddlewareInterface|string $middleware): void
    {
        $this->assertRoutesNotCached();
        $this->routes()->addRouteMiddleware($method, $path, $middleware);
    }

    public function controllerMiddleware(string $controller, array $middlewares, array $only = [], array $except = []): void
    {
        $this->assertRoutesNotCached();
        $this->routes()->addControllerMiddleware($controller, $middlewares, $only, $except);
    }

    public function group(string $prefix, callable $callback, array $middlewares = [], ?string $host = null): void
    {
        $this->assertRoutesNotCached();
        $this->routes()->group($prefix, $callback, $middlewares, $host);
    }

    public function resource(
        string $path,
        string $controller,
        array $except = [],
        array $middlewares = [],
        array $routeMiddlewares = [],
        ?string $namePrefix = null,
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->resource($path, $controller, $except, $middlewares, $routeMiddlewares, $namePrefix);
    }

    public function get(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->get($path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function post(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->post($path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function put(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->put($path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function delete(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->delete($path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function any(
        string $path,
        callable|array|string $handler,
        array $middlewares = [],
        ?string $name = null,
        ?string $host = null,
        array $defaults = [],
        array $validators = [],
    ): void {
        $this->assertRoutesNotCached();
        $this->routes()->any($path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }
}
