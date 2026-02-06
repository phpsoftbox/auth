# Middleware

## AuthMiddleware

```php
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Auth\Manager\AuthManager;

$middleware = new AuthMiddleware($auth, guardName: 'web');
```

## PermissionMiddleware

```php
use PhpSoftBox\Auth\Middleware\PermissionMiddleware;

$routes->get('/admin/users', [AdminUserController::class, 'index'], [
    new PermissionMiddleware($auth, permission: 'users.base.read'),
]);
```

Если permission не передан в конструктор, middleware читает его из атрибута
`_permission` запроса. Это удобно, если вы прокидываете permission через defaults маршрута.

### Атрибуты

Можно использовать атрибут `#[RequiresPermission]` на контроллере или методе.
При наличии `PermissionMiddleware` оно будет прочитано из `_route_handler`.

```php
use PhpSoftBox\Auth\Authorization\Attribute\RequiresPermission;

final class UserController
{
    #[RequiresPermission('users.base.read')]
    public function index(): ResponseInterface
    {
        // ...
    }
}
```
