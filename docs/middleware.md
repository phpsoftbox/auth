# Middleware

## AuthMiddleware

```php
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Auth\Manager\AuthManager;

$middleware = new AuthMiddleware($auth, guardName: 'web');
```

## AreaAccessMiddleware

`AreaAccessMiddleware` защищает именованную area: `admin`, `account`,
`site-admin` и т.п. Middleware не зависит от Inertia, но использует ту же
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

enum UserPermissionName: string
{
    case Read = 'users.base.read';
}

final class UserController
{
    #[RequiresPermission(UserPermissionName::Read)]
    public function index(): ResponseInterface
    {
        // ...
    }
}
```

`PermissionMiddleware`, `AreaAccessRule`, `RequiresPermission` и
`PermissionCase` принимают как строку, так и `BackedEnum`.

Для `base/own` сценариев используйте `#[RequiresAnyPermission]`. Cases
проверяются в указанном порядке:

```php
use PhpSoftBox\Auth\Authorization\Attribute\RequiresAnyPermission;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionCaseSubjectTypeEnum;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;

final class DocumentController
{
    #[RequiresAnyPermission([
        new PermissionCase('site.documents.base.read'),
        new PermissionCase(
            'site.documents.own.read',
            subjectType: PermissionCaseSubjectTypeEnum::RouteParam,
            subject: 'user',
        ),
    ], deniedMode: PermissionDeniedMode::NotFound)]
    public function index(): ResponseInterface
    {
        // ...
    }
}
```

Если `base.read` запрещен, middleware попробует `own.read` и передаст policy
subject из route-параметра `{user}`. `deniedMode: NotFound` позволяет скрыть
чужой ресурс через `404`.

Если нужно требовать сразу несколько permissions, используйте
`#[RequiresAllPermissions]`.

Cases также можно передавать через route defaults/request attributes:

```php
$routes->get('/users/{user}/documents', [DocumentController::class, 'index'])
    ->defaults([
        '_permission_cases' => [
            ['permission' => 'site.documents.base.read'],
            [
                'permission' => 'site.documents.own.read',
                'subject_type' => 'route_param',
                'subject' => 'user',
            ],
        ],
        '_permission_mode' => 'any',
        '_permission_denied_mode' => 'not_found',
    ]);
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

Если обработчику нужен полный `AccessDecision`, реализуйте
`PermissionDecisionDeniedHandlerInterface`. Он получает `reason` и `context`
из policy, например `http_status => 404`.

Для стандартного HTTP-поведения есть готовый handler:

```php
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;
use PhpSoftBox\Auth\Middleware\PermissionHttpDeniedHandler;

new PermissionMiddleware(
    $auth,
    deniedHandler: new PermissionHttpDeniedHandler(
        responses: $responseFactory,
        defaultMode: PermissionDeniedMode::Redirect,
        redirectTo: '/login',
    ),
);
```
