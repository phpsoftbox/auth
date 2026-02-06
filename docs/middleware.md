# Middleware

## AuthMiddleware

```php
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Auth\Manager\AuthManager;

$middleware = new AuthMiddleware($auth, guardName: 'web');
```

## AreaAccessMiddleware

`AreaAccessMiddleware` защищает именованную area: `admin`, `account`,
`tenant-admin` и т.п. Middleware не зависит от Inertia, но использует ту же
идею именованных areas, чтобы auth-конфиг можно было держать согласованным с
`InertiaAreaConfig`.

```php
use PhpSoftBox\Auth\Middleware\AreaAccessDeniedMode;
use PhpSoftBox\Auth\Middleware\AreaAccessMiddleware;
use PhpSoftBox\Auth\Middleware\AreaAccessRule;

$middleware = new AreaAccessMiddleware(
    auth: $auth,
    responses: $responseFactory,
    rule: new AreaAccessRule(
        area: 'admin',
        permission: 'admin.access',
        deniedMode: AreaAccessDeniedMode::NotFound,
    ),
);
```

Если пользователь не авторизован или не имеет permission, реакция задаётся
через `AreaAccessDeniedMode`:

- `Forbidden` — вернуть `403`;
- `NotFound` — вернуть `404`, чтобы скрыть area;
- `Redirect` — вернуть `303 Location`, например на `/login`.

```php
new AreaAccessRule(
    area: 'account',
    deniedMode: AreaAccessDeniedMode::Redirect,
    redirectTo: '/login',
)
```

Если `permission` не задан, middleware проверяет только факт авторизации через
guard. При успешном доступе в request добавляются атрибуты:

- `user`;
- `user_id`, если user реализует `UserInterface`;
- `_area` с именем area.

## PermissionMiddleware

```php
use PhpSoftBox\Auth\Middleware\PermissionMiddleware;

$routes->get('/admin/users', [AdminUserController::class, 'index'], [
    new PermissionMiddleware($auth, permission: 'users.base.read'),
]);
```

По умолчанию middleware работает в strict-режиме:
если permission не задан в конструкторе, не передан в `_permission` и не найден в `#[RequiresPermission]`,
будет выброшено исключение конфигурации.

Если нужно сохранить fail-open поведение для отдельных маршрутов:

```php
new PermissionMiddleware($auth, requireResolvedPermission: false)
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

### Централизованная реакция на отказ

По умолчанию middleware выбрасывает `PermissionDeniedException`.  
Если нужно централизованно возвращать redirect/JSON, передайте `PermissionDeniedHandlerInterface`:

```php
use PhpSoftBox\Auth\Middleware\PermissionDeniedHandlerInterface;
use PhpSoftBox\Auth\Middleware\PermissionMiddleware;

$routes->middleware([
    new PermissionMiddleware(
        $auth,
        deniedHandler: $container->get(PermissionDeniedHandlerInterface::class),
    ),
]);
```
