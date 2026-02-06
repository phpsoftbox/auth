# Access Policy

## Зачем

`PermissionCheckerInterface` отвечает на вопрос: есть ли у пользователя permission.

Для бизнес-ограничений уровня предметной области (например, «нельзя редактировать root») нужен отдельный слой policy.

В компоненте `Auth` для этого добавлены:

- `AccessDecision`
- `Authorization\Policy\UserAccessPolicyInterface`

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
- `context` — служебные данные для логирования/диагностики.

## Интеграция через PermissionPolicyRegistry

`PermissionPolicyRegistry` принимает rule, которая может вернуть:

- `true`
- `false`
- `string` (преобразуется в deny с reason)
- `AccessDecision`

Пример:

```php
$registry->define('dispatcher.users.base.update', static function (
    mixed $user,
    mixed $subject,
    string $permission
) use ($userAccessPolicy): AccessDecision {
    return $userAccessPolicy->decide($user, $permission, $subject);
});
```

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
    resource: 'dispatcher.users',
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
