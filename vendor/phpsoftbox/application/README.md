# Application

Минимальное приложение для работы с PSR-15 пайплайном.

## Быстрый старт

```php
use PhpSoftBox\Application\Application;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\HtmlExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\ContentNegotiationExceptionHandler;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Router\Router;

$router = new Router($resolver, $dispatcher, $collector);
$exceptionHandler = new ContentNegotiationExceptionHandler(
    new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
    new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: true),
);

$app = new Application($router, [
    new ErrorHandlerMiddleware($exceptionHandler),
]);

$response = $app->handle($request);
```

## RouterFactory + RouteCache

```php
use PhpSoftBox\Application\RouterFactory;
use PhpSoftBox\Router\Cache\RouteCache;
use PhpSoftBox\Router\Dispatcher;

$cache = new RouteCache($cacheStorage);
$factory = new RouterFactory(new Dispatcher(), $cache);

$router = $factory->create(function ($routes) {
    $routes->get('/users', [UserController::class, 'index']);
});
```

## AppFactory

```php
use PhpSoftBox\Application\AppFactory;

$app = AppFactory::createFromContainer($container, environment: 'prod');

if (!$app->routesCached()) {
    require __DIR__ . '/routes.php';
}
```

## Регистрация middleware

```php
use PhpSoftBox\Application\Application;
use PhpSoftBox\Application\Middleware\ErrorHandlerMiddleware;
use PhpSoftBox\Application\Middleware\RequestSizeLimitMiddleware;
use PhpSoftBox\Session\SessionMiddleware;

$app = new Application($router, container: $container);

$app->add(new ErrorHandlerMiddleware($exceptionHandler), priority: 100);
$app->add(RequestSizeLimitMiddleware::class);

$app->alias('session', SessionMiddleware::class);
$app->middlewareGroup('web', ['session']);
```

## Группы middleware

```php
$webApp = $app->withMiddlewareGroups(['web']);
$response = $webApp->handle($request);
```

## Регистрация роутов через Application

```php
$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
```

Методы проксируются в `RouteCollector`, если приложение создано с `Router`.

## Middleware для контроллеров

```php
$app->controllerMiddleware(UserController::class, ['auth']);
$app->controllerMiddleware(UserController::class, ['admin'], only: ['store', 'update']);
```

Рекомендуемый путь привязки Middleware — регистрация через Router на маршруты/группы; контроллеры/экшены используйте точечно.

## Группы middleware для маршрутов

```php
use PhpSoftBox\Application\Middleware\KernelRouteMiddlewareResolver;
use PhpSoftBox\Router\Dispatcher;
use PhpSoftBox\Router\Router;

$app->alias('auth', \PhpSoftBox\Auth\Middleware\AuthMiddleware::class);
$app->middlewareGroup('api', ['auth']);

$dispatcher = new Dispatcher(
    handlerResolver: null,
    middlewareResolver: new KernelRouteMiddlewareResolver($app->middlewareManager(), $container),
);

$router = new Router($resolver, $dispatcher, $collector);

$collector->group('/api', function ($routes) {
    $routes->get('/users', [UserController::class, 'index']);
}, ['api']);
```
