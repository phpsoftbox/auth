# Auth

Компонент аутентификации и авторизации для Application.

## Документация

- [Guard](docs/guards.md)
- [Remember и Intended URL](docs/remember.md)
- [Middleware](docs/middleware.md)
- [Роли и пермишены](docs/roles.md)
- [Access Policy](docs/access-policy.md)
- [Защита аккаунта](docs/account-protection.md)
- [API credentials и enrollment](docs/enrollment.md)
- [CLI](docs/cli.md)

## Policy-based permissions

Компонент поддерживает `base/own` сценарии через `RequiresAnyPermission`,
`PermissionCase` и policy registry. Для route-param и ownership subject есть
готовые resolver-ы, включая optional `DatabaseOwnerResolver` для схемы
`id -> owner_id`.

Подробнее: [Access Policy](docs/access-policy.md).

## Миграции

Минимальные примеры таблиц для session storage и lifecycle-token лежат в
[`database/migrations`](database/migrations). Bearer/API tokens и remember-me
tokens хранятся в одной таблице `user_tokens` и различаются purpose в колонке
`token_type` (`user_bearer`, `user_remember` и другие настроенные значения).

Для API credentials дополнительно используется `audience`: стабильный идентификатор
API, в котором credential разрешено предъявлять. `purpose` описывает auth flow,
а `audience` — его получателя. Например, два credentials одного субъекта могут
иметь общий purpose `api_access`, но разные audiences `catalog` и `warehouse`.

Одноразовые enrollment credentials обслуживаются отдельным
`DatabaseEnrollmentCredentialStore`. Он проверяет audience, срок действия и
атомарно выставляет `used_datetime`. Callback метода `exchange()` позволяет в той
же транзакции выпустить отдельный постоянный credential для каждого разрешённого API.
