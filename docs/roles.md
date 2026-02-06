# Роли и пермишены

## Имена пермишенов

По умолчанию имя строится как `{resource}.{scope}.{action}`:

- `resource` — ресурс модели (например, `user` или `user-profile`).
- `scope` — область действия (`base`, `own` и т.п.).
- `action` — действие (`create`, `read`, `update`, `delete`, `restore`).

Пример: `users.base.read`.

Разделитель можно поменять через конфиг `auth.authorization.separator`.

## PermissionModel

Для CRUD-пермов используется `PermissionModelInterface`:

```php
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\PermissionModelInterface;

final class UserPermission implements PermissionModelInterface
{
    public static function resource(): string
    {
        return 'users';
    }

    public static function scope(): string
    {
        return 'base';
    }

    public static function actions(): array
    {
        return [
            PermissionActionEnum::CREATE,
            PermissionActionEnum::READ,
            PermissionActionEnum::UPDATE,
            PermissionActionEnum::DELETE,
        ];
    }

    public static function labels(): array
    {
        return [];
    }
}
```

## RoleDefinition

```php
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;

RoleDefinition::root()->allowAll();
RoleDefinition::admin()->allowAll();

RoleDefinition::named('manager', 'Менеджер')
    ->allow(UserPermission::class, [PermissionActionEnum::READ, PermissionActionEnum::UPDATE]);
```

Полезные методы:

- `allow()` — добавить одно разрешение.
- `allowMany()` — добавить список разрешений (например, сразу несколько моделей).
- `allowAll()` — разрешить всё (кроме `deny()`).
- `deny()` — запретить конкретное разрешение.

`deny()` применяется последним и переопределяет любые разрешения, включая `allowAll()`.

### Root / admin

`RoleDefinition::root()` и `RoleDefinition::admin()` помечают роль флагами `root` и `adminAccess`.
Роль `root` желательно назначать только через миграции/CLI.

## Где хранить определения

Можно хранить definitions:

1. В конфиге `auth.authorization.roles/models/permissions`.
2. В отдельных PHP-файлах через `auth.authorization.paths`.

Пример провайдера из нескольких файлов:

```php
return [
    'auth' => [
        'authorization' => [
            'paths' => [
                dirname(__DIR__) . '/authorization/*.php',
            ],
        ],
    ],
];
```

Каждый файл может вернуть массив:

```php
return [
    'roles' => [
        RoleDefinition::named('support')->allow('users.base.read'),
    ],
    'models' => [
        UserPermission::class,
    ],
    'permissions' => [
        'admin.access',
    ],
];
```

## Синхронизация с БД

```php
use PhpSoftBox\Auth\Authorization\RoleSynchronizer;

$sync = new RoleSynchronizer($provider, $permissionStore, $roleStore, $rolePermissions);
$sync->sync();
```

CLI:

```
php psb auth:sync
```

## Проверка прав

Есть два варианта проверки:

1. `DatabasePermissionChecker` — читает права из БД.
2. `DefinitionPermissionChecker` — работает по definitions (без БД).

Переключение:

```php
return [
    'auth' => [
        'permissions' => [
            'driver' => 'database', // или 'definition'
        ],
    ],
];
```

## Управление ролями пользователя

Для назначения и чтения ролей есть `UserRoleManager`:

```php
use PhpSoftBox\Auth\Authorization\UserRoleManager;

$role = $roles->role($user);     // ?string
$roles = $roles->roles($user);   // list<string>

$roles->assignRole($user, 'admin');
$roles->assignRoles($user, ['admin', 'manager']);
$roles->replaceRoles($user, ['support']); // заменить все роли
$roles->removeRole($user, 'manager');

$role = $roles->requireRole($user);   // бросит исключение, если ролей нет
$roles = $roles->requireRoles($user); // бросит исключение, если ролей нет
```

`UserRoleManager` работает через `UserRoleStoreInterface`. Для базы данных есть
`DatabaseUserRoleStore` (таблицы `user_roles` и `roles`).
В качестве пользователя можно передать объект, реализующий `UserIdentityInterface`,
или напрямую `int` идентификатор.

### Кеш ролей

`DatabaseUserRoleStore` поддерживает кеширование:

```php
return [
    'auth' => [
        'permissions' => [
            'roles_cache_ttl_seconds' => 300,
            'roles_cache_prefix' => 'auth.user_roles',
        ],
    ],
];
```

### Кастомная логика (свои объекты)

Для условий вроде «только свой объект» используйте политики. В примере ниже
`$user` должен реализовать `UserIdentityInterface`, а объект — `OwnableInterface`:

```php
use PhpSoftBox\Auth\Authorization\PermissionGate;
use PhpSoftBox\Auth\Authorization\PermissionPolicyRegistry;
use PhpSoftBox\Auth\Authorization\Policy\OwnershipPolicy;

$policies = (new PermissionPolicyRegistry())
    ->define('posts.base.update', OwnershipPolicy::byInterfaces());

$checker = new PermissionGate($databaseChecker, $policies);
```

Пример интерфейса предметной модели:

```php
final class Post implements OwnableInterface
{
    public function __construct(
        private int $authorId,
    ) {
    }

    public function getOwnerId(): int|string|null
    {
        return $this->authorId;
    }
}
```

Если интерфейсы не подходят, можно передать свои резолверы:

```php
$policies = (new PermissionPolicyRegistry())
    ->define('posts.base.update', OwnershipPolicy::by(
        static fn ($user) => $user->getId(),
        static fn ($post) => $post->authorId,
    ));
```

### Разделение base/own

Если нужна логика «свои объекты, но не все», разделяйте пермишены:

```php
$policies = (new PermissionPolicyRegistry())
    ->define('posts.own.update', OwnershipPolicy::byInterfaces());

$roles = [
    RoleDefinition::admin()->allow('posts.base.update'),
    RoleDefinition::named('editor')->allow('posts.own.update'),
];
```

Политика применяется как дополнительное условие к пермишену.  
То есть `posts.own.update` сначала проверяет право, затем owner‑policy.

## Роли пользователя при проверке через definitions

`DefinitionPermissionChecker` берёт роли пользователя через `RoleResolverInterface`.
По умолчанию используется `UserRoleResolver`, который работает с
`UserRolesInterface`:

```php
final class User implements UserRolesInterface
{
    public function getRoleNames(): array
    {
        return ['admin'];
    }
}
```

Если нужна другая логика — передайте свой `RoleResolverInterface` в контейнер.
