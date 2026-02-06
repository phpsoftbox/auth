# Auth

Компонент аутентификации для Application.

## Middleware

```php
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\ArraySessionStore;

$users = new ArrayUserProvider([
    ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
]);

$tokens = new ArrayTokenProvider([
    'token-123' => 1,
]);

$auth = new AuthManager([
    'web' => new SessionGuard(new Session(new ArraySessionStore()), $users),
    'api' => new TokenGuard($tokens, $users),
], defaultGuard: 'web');

$middleware = new AuthMiddleware($auth, guardName: 'web');
```

## Guard

```php
use PhpSoftBox\Auth\Guard\CallbackGuard;

$guard = new CallbackGuard(fn ($request) => $request->getAttribute('user'));
```

## SessionGuard (web)

```php
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\ArraySessionStore;

$users = new ArrayUserProvider([
    ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
], loginFields: ['email']);

$session = new Session(new ArraySessionStore());
$guard = new SessionGuard($session, $users);

$guard->attempt(['email' => 'admin@example.com', 'password' => 'secret']);
```

## TokenGuard (api)

```php
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;

$users = new ArrayUserProvider([
    ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
]);

$tokens = new ArrayTokenProvider([
    'token-123' => 1,
]);

$guard = new TokenGuard($tokens, $users, new BearerTokenExtractor());
```

## Валидация по SMS (OTP)

```php
use PhpSoftBox\Auth\Otp\InMemoryOtpValidator;
use PhpSoftBox\Auth\Credentials\OtpCredentialsValidator;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;

$otp = new InMemoryOtpValidator(maxAttempts: 5);
$otp->setCode('79001234567', '1234');

$users = new ArrayUserProvider(
    users: [
        ['id' => 1, 'phone_number' => '79001234567'],
    ],
    loginFields: ['phone_number'],
    validators: [new OtpCredentialsValidator($otp, credentialKey: 'sms_code', identifierField: 'phone_number')],
);

$user = $users->retrieveByCredentials(['phone_number' => '79001234567', 'sms_code' => '1234']);
```

## Guard для разных URL

```php
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Application\Application;
use PhpSoftBox\Router\RouteCollector;

$app = new Application($router, container: $container);

$app->alias('auth.web', new AuthMiddleware($auth, guardName: 'web'));
$app->alias('auth.api', new AuthMiddleware($auth, guardName: 'api'));

$app->middlewareGroup('web', ['auth.web']);
$app->middlewareGroup('api', ['auth.api']);

$routes = new RouteCollector();
$routes->group('/users', static function (RouteCollector $routes): void {
    $routes->get('/{id}', [UserController::class, 'show']);
}, ['web']);

$routes->group('/api', static function (RouteCollector $routes): void {
    $routes->get('/users/{id}', [UserController::class, 'show']);
}, ['api']);
```

## Провайдеры

- `ArrayUserProvider` / `FileUserProvider` — пользователи из массива/файла.
- `ArrayTokenProvider` / `FileTokenProvider` — токены из массива/файла.
- `DatabaseUserProvider` / `DatabaseTokenProvider` — адаптеры для phpsoftbox/database (опционально, через require-dev).
