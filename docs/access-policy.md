# Access Policy

## Зачем

`PermissionCheckerInterface` отвечает на вопрос: есть ли у пользователя permission.

Для бизнес-ограничений уровня предметной области (например, «нельзя редактировать root») нужен отдельный слой policy.

В компоненте `Auth` для этого добавлены:

- `AccessDecision`
- `PermissionDecisionCheckerInterface`
- `PermissionGate::decide()`
- `Authorization\Policy\UserAccessPolicyInterface`
- `PermissionPolicyRegistry`
- `PermissionCase` / `RequiresAnyPermission` / `RequiresAllPermissions`

## Контракт policy

```php
use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\Policy\UserAccessPolicyInterface;

final class UserAccessPolicy implements UserAccessPolicyInterface
{
    public function decide(
        mixed $initiator,
        string $permission,
        mixed $subject = null,
        array $context = [],
    ): AccessDecision {
        // своя логика
        return AccessDecision::allow();
    }
}
```

`AccessDecision` поддерживает:

- `allowed` — итог разрешения;
- `reason` — причина отказа;
- `context` — служебные данные для логирования/диагностики, например `http_status => 404`.

`PermissionCheckerInterface::can()` остается boolean API. Если нужен контекст
отказа, используйте `PermissionDecisionCheckerInterface::decide()` или
`AuthManager::decide()`.

## Интеграция через PermissionPolicyRegistry

`PermissionPolicyRegistry` принимает callable или invokable class-string.
Rule может вернуть:

- `true`
- `false`
- `string` (преобразуется в deny с reason)
- `AccessDecision`

Пример:

```php
$registry->define('admin.users.base.update', static function (
    mixed $user,
    mixed $subject,
    string $permission
) use ($userAccessPolicy): AccessDecision {
    return $userAccessPolicy->decide($user, $permission, $subject);
});
```

Pattern-policy получает тот же набор аргументов:

```php
$registry->definePattern('site.documents.own.*', SiteDocumentOwnPolicy::class);
```

Если registry создан с PSR-11 контейнером, invokable class будет получен из
контейнера. Если контейнер не задан, класс будет создан через `new`.

## PermissionCase

`PermissionCase` можно создать явно или через helper:

```php
enum DocumentPermission: string
{
    case BaseRead = 'site.documents.base.read';
    case OwnRead  = 'site.documents.own.read';
}

PermissionCase::make('site.documents.base.read');
PermissionCase::routeParam(DocumentPermission::OwnRead, 'user');
PermissionCase::ownership('site.documents.own.read', 'document');
PermissionCase::requestAttribute('site.documents.own.read', 'document');
PermissionCase::custom('site.documents.own.read', DocumentSubjectResolver::class);
```

Все публичные API permission name принимают строку или `BackedEnum`; внутри
компонента значение нормализуется в строку.

Для route defaults/cache-friendly конфигов можно использовать массив:

```php
[
    'permission' => 'site.documents.own.read',
    'subject_type' => 'route_param',
    'subject' => 'user',
]
```

## Base / own сценарии

Для одного action можно описать несколько permission cases. Они проверяются
строго по порядку: первый разрешенный case пропускает запрос.

```php
use PhpSoftBox\Auth\Authorization\Attribute\RequiresAnyPermission;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionCaseSubjectTypeEnum;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;

#[RequiresAnyPermission([
    new PermissionCase('site.documents.base.read'),
    new PermissionCase(
        'site.documents.own.read',
        subjectType: PermissionCaseSubjectTypeEnum::RouteParam,
        subject: 'user',
    ),
], deniedMode: PermissionDeniedMode::NotFound)]
final class DocumentIndexAction
{
}
```

Если нужно требовать все permissions, используйте `RequiresAllPermissions`:

```php
use PhpSoftBox\Auth\Authorization\Attribute\RequiresAllPermissions;

#[RequiresAllPermissions([
    new PermissionCase('site.documents.base.read'),
    new PermissionCase('site.documents.audit.read'),
])]
final class DocumentAuditAction
{
}
```

В примере менеджер пройдет по `base.read`. Пользователь с `own.read` пройдет
только если policy разрешит доступ к subject из `{user}`.

Policy для route-param subject:

```php
use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\Subject\RouteParamSubject;

final readonly class SiteDocumentOwnPolicy
{
    public function __invoke(SiteUser $user, RouteParamSubject $subject): AccessDecision
    {
        return (string) $user->id === (string) $subject->value('user')
            ? AccessDecision::allow()
            : AccessDecision::deny(context: ['http_status' => 404]);
    }
}
```

## Ownership subject

Если владелец не указан в URL, например `/documents/{document}`, можно
подготовить subject через ownership binding:

```php
use PhpSoftBox\Auth\Authorization\Subject\OwnershipRegistry;
use PhpSoftBox\Auth\Authorization\Subject\OwnershipSubject;

$ownership = (new OwnershipRegistry())
    ->define('document', Document::class, static function (int $documentId): ?OwnershipSubject {
        $row = findDocumentRow($documentId);
        if ($row === null) {
            return null;
        }

        return new OwnershipSubject(
            type: Document::class,
            id: $documentId,
            ownerId: $row['user_id'],
            routeParam: 'document',
        );
    });
```

Для стандартного случая `id -> owner_id` есть готовый resolver. Он находится в
`Auth`, но использует optional-компонент `phpsoftbox/database`; для этого
достаточно установить зависимость в приложении.

```php
use PhpSoftBox\Auth\Authorization\Subject\DatabaseOwnerResolver;

$ownership->define(
    routeParam: 'document',
    subject: Document::class,
    owner: new DatabaseOwnerResolver(
        connections: $connections,
        table: 'documents',
        idColumn: 'id',
        ownerColumn: 'user_id',
        connection: 'default',
    ),
);
```

Action:

```php
#[RequiresAnyPermission([
    new PermissionCase('site.documents.base.read'),
    new PermissionCase(
        'site.documents.own.read',
        subjectType: PermissionCaseSubjectTypeEnum::Ownership,
        subject: 'document',
    ),
], deniedMode: PermissionDeniedMode::NotFound)]
final class DocumentShowAction
{
}
```

`OwnershipRegistry` живет в `Auth`, потому что это subject для policy, а не
route-model binding. Router только кладет route params в request.

## Subject resolvers

Системные типы `PermissionCaseSubjectTypeEnum`:

- `RouteParam` — берет route parameter через `RouteParameterProviderInterface`;
- `Ownership` — берет route parameter и превращает его в `OwnershipSubject`;
- `RequestAttribute` — читает обычный request attribute;
- `Custom` — использует переданный `SubjectResolverInterface`.

По умолчанию `PermissionMiddleware` использует
`RequestAttributeRouteParameterProvider`, который читает `_route_params`.
Если приложение использует другой Router, зарегистрируйте свой
`RouteParameterProviderInterface`.

```php
use PhpSoftBox\Auth\Authorization\Subject\PermissionCaseSubjectResolver;
use PhpSoftBox\Auth\Authorization\Subject\RequestAttributeRouteParameterProvider;

new PermissionMiddleware(
    $auth,
    subjectResolver: new PermissionCaseSubjectResolver(
        routes: new RequestAttributeRouteParameterProvider(),
        ownership: $ownership,
    ),
);
```

## Route defaults

Cases можно задавать не только атрибутом на action, но и route defaults/request
attributes. Это удобно для роутов, которые описываются декларативно:

```php
$routes->get('/users/{user}/documents', DocumentIndexAction::class)
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

Для AND-сценария используйте `'_permission_mode' => 'all'`.

### Пример DI (PHP-DI)

```php
use PhpSoftBox\Auth\Authorization\Policy\UserAccessPolicyInterface;
use App\Security\UserAccessPolicy;

return [
    UserAccessPolicyInterface::class => DI\autowire(UserAccessPolicy::class),
];
```

## Metadata в PermissionGrant

`PermissionGrant` поддерживает поле `meta`:

```php
new PermissionGrant(
    resource: 'admin.users',
    actions: [PermissionActionEnum::UPDATE],
    scope: 'base',
    meta: [
        'max_manage_rank' => 30,
        'allow_self_role_change' => false,
    ],
);
```

`meta` не навязывает формат.  
Рекомендуется хранить только декларативные ограничения (ранг, флаги, scope-ограничения), а вычисления выполнять в policy.

## Рекомендуемый формат metadata

```php
[
    'max_manage_rank' => 30,          // до какого ранга можно управлять
    'max_assign_rank' => 30,          // до какого ранга можно назначать роли
    'allow_self' => false,            // можно ли применять действие к себе
    'target_scope' => 'same_area',    // ограничение контекста (пример)
]
```

Этот формат является рекомендованным соглашением, а не жесткой схемой компонента.
