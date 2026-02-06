<?php

declare(strict_types=1);

namespace PhpSoftBox\Router;

use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

use function array_merge;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function strtoupper;

/**
 * Пример использования:
 *
 * Добавление middleware к конкретному маршруту.
 * ```
 * <?php
 * $routeCollector = new RouteCollector();
 *
 * $routeCollector->get('/users', function (ServerRequestInterface $request) {
 *      return new \MyApp\Http\Response(200, [], 'User list');
 * }, [new \PhpSoftBox\Auth\Middleware\AuthMiddleware(), new LoggingMiddleware()]);
 * ```
 *
 * Добавление middleware к группе маршрутов
 * ```
 * <?php
 * $routeCollector = new RouteCollector();
 *
 * $routeCollector->group('/api', function (Router $router) {
 *      $router->get('/users', function (ServerRequestInterface $request) {
 *          return new \MyApp\Http\Response(200, [], 'User list');
 *      }, [new LoggingMiddleware()]); // Локальный middleware
 * }, [new \PhpSoftBox\Auth\Middleware\AuthMiddleware()]); // Глобальный middleware для группы
 * ```
 *
 * Помимо использования основных методов добавления маршрутов, можно создавать resource (CRUD-маршрутов)
 * ```
 * <?php
 * $authMiddleware = [new \PhpSoftBox\Auth\Middleware\AuthMiddleware()];
 * $adminMiddleware = [new \PhpSoftBox\Auth\Middleware\AuthMiddleware(), new AdminMiddleware()];
 *
 * $routeCollector = new RouteCollector();
 *
 * $routeCollector->resource(
 *      '/users',
 *       UserController::class,
 *       [],
 *       [],
 *       [new LoggingMiddleware()],
 *       [
 *           'store' => $authMiddleware,
 *           'update' => $authMiddleware,
 *           'destroy' => $adminMiddleware,
 *       ]
 * );
 * ```
 *
 * Примеры с валидацией:
 * ```
 * <?php
 *
 * // Маршрут с валидацией параметра id (только цифры)
 * $routeCollector->get(
 *      '/users/{id}',
 *      [UserController::class, 'show'],
 *      validators: ['id' => ParamType::INT]
 * );
 *
 * // Маршрут с кастомным валидатором
 * $routeCollector->get(
 *      '/posts/{slug}',
 *      [PostController::class, 'show'],
 *      validators: ['slug' => function ($value) {
 *          return preg_match('/^[a-z0-9-]+$/', $value) === 1;
 *      }]
 * );
 * ```
 */
final class RouteCollector
{
    /**
     * @var Route[] Массив всех зарегистрированных маршрутов.
     */
    private array $routes = [];

    /**
     * @var array<string, Route> Ассоциативный массив именованных маршрутов, где ключ — имя маршрута, значение — путь.
     */
    private array $namedRoutes = [];

    private array $globalMiddlewares     = [];
    private ?RouteGroup $currentGroup    = null;
    private array $controllerMiddlewares = [];

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
        $path = $this->currentGroup ? $this->currentGroup->prefix . $path : $path;

        $middlewares = array_merge(
            $this->globalMiddlewares,
            $this->currentGroup ? $this->currentGroup->middlewares : [],
            $this->resolveControllerMiddlewares($handler),
            $middlewares,
        );

        $host = $host ?? $this->currentGroup?->host;

        $route = new Route(
            method: strtoupper($method),
            path: $path,
            handler: $handler,
            middlewares: $middlewares,
            name: $name,
            host: $host,
            defaults: $defaults,
            validators: $validators,
        );

        $this->routes[] = $route;

        // Сохраняем именованный маршрут
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function group(string $prefix, callable $callback, array $middlewares = [], ?string $host = null): void
    {
        $previousGroup = $this->currentGroup;

        $this->currentGroup = new RouteGroup(
            prefix: $previousGroup ? $previousGroup->prefix . $prefix : $prefix,
            middlewares: array_merge($previousGroup ? $previousGroup->middlewares : [], $middlewares),
            host: $host ?? $previousGroup?->host,
        );

        $callback($this);

        $this->currentGroup = $previousGroup;
    }

    public function addRouteMiddleware(string $method, string $path, MiddlewareInterface|string $middleware): void
    {
        foreach ($this->routes as $index => $route) {
            if ($route->method === strtoupper($method) && $route->path === ($this->currentGroup ? $this->currentGroup->prefix . $path : $path)) {
                // Создаем новый маршрут с добавленным middleware
                $newRoute = new Route(
                    method: $route->method,
                    path: $route->path,
                    handler: $route->handler,
                    middlewares: array_merge($route->middlewares, [$middleware]),
                    name: $route->name,
                    host: $route->host,
                    defaults: $route->defaults,
                    validators: $route->validators,
                );

                // Заменяем старый маршрут на новый
                $this->routes[$index] = $newRoute;

                // Если маршрут именованный — обновляем ссылку в карте именованных маршрутов
                if ($newRoute->name !== null) {
                    $this->namedRoutes[$newRoute->name] = $newRoute;
                }

                return;
            }
        }

        throw new RuntimeException("Route not found: $method $path");
    }

    /**
     * @param array<MiddlewareInterface|string> $middlewares
     * @param string[] $only
     * @param string[] $except
     */
    public function addControllerMiddleware(
        string $controller,
        array $middlewares,
        array $only = [],
        array $except = [],
    ): void {
        $this->controllerMiddlewares[$controller][] = [
            'middlewares' => $middlewares,
            'only'        => $only,
            'except'      => $except,
        ];
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
        $this->addRoute('GET', $path, $handler, $middlewares, $name, $host, $defaults, $validators);
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
        $this->addRoute('POST', $path, $handler, $middlewares, $name, $host, $defaults, $validators);
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
        $this->addRoute('PUT', $path, $handler, $middlewares, $name, $host, $defaults, $validators);
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
        $this->addRoute('DELETE', $path, $handler, $middlewares, $name, $host, $defaults, $validators);
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
        $this->addRoute('ANY', $path, $handler, $middlewares, $name, $host, $defaults, $validators);
    }

    public function resource(
        string $path,
        string $controller,
        array $except = [],
        array $middlewares = [],
        array $routeMiddlewares = [],
        ?string $namePrefix = null,
    ): void {
        $defaultMethods = [
            'index'   => ['GET', $path, 'index'],
            'show'    => ['GET', "$path/{id}", 'show'],
            'store'   => ['POST', $path, 'store'],
            'update'  => ['PUT', "$path/{id}", 'update'],
            'destroy' => ['DELETE', "$path/{id}", 'destroy'],
        ];

        foreach ($defaultMethods as $method => $details) {
            if (!in_array($method, $except, true)) {
                [$httpMethod, $routePath, $action] = $details;
                $routeName                         = $namePrefix ? "{$namePrefix}.{$method}" : $method;

                // Добавляем маршрут
                $this->addRoute(
                    $httpMethod,
                    $routePath,
                    [$controller, $action],
                    $middlewares,
                    $routeName,
                );

                // Добавляем middleware для конкретного маршрута
                if (isset($routeMiddlewares[$method])) {
                    foreach ($routeMiddlewares[$method] as $middleware) {
                        $this->addRouteMiddleware($httpMethod, $routePath, $middleware);
                    }
                }
            }
        }
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return array<string, Route>
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * @return list<MiddlewareInterface|string>
     */
    private function resolveControllerMiddlewares(callable|array|string $handler): array
    {
        [$controller, $action] = $this->resolveControllerAction($handler);
        if ($controller === null || $action === null) {
            return [];
        }

        $configs = $this->controllerMiddlewares[$controller] ?? [];
        if ($configs === []) {
            return [];
        }

        $resolved = [];
        foreach ($configs as $config) {
            $only   = $config['only'] ?? [];
            $except = $config['except'] ?? [];

            if ($only !== [] && !in_array($action, $only, true)) {
                continue;
            }

            if ($except !== [] && in_array($action, $except, true)) {
                continue;
            }

            $resolved = array_merge($resolved, $config['middlewares'] ?? []);
        }

        return $resolved;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function resolveControllerAction(callable|array|string $handler): array
    {
        if (is_string($handler)) {
            return [$handler, '__invoke'];
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controller, $action] = $handler;
            if (is_string($controller)) {
                return [$controller, is_string($action) ? $action : null];
            }

            if (is_object($controller)) {
                return [get_class($controller), is_string($action) ? $action : null];
            }
        }

        return [null, null];
    }
}
