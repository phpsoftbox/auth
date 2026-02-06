# Auth

Компонент аутентификации и авторизации для Application.

## Документация

- [Guard](docs/guards.md)
- [Remember и Intended URL](docs/remember.md)
- [Middleware](docs/middleware.md)
- [Роли и пермишены](docs/roles.md)
- [Access Policy](docs/access-policy.md)
- [Защита аккаунта](docs/account-protection.md)
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
tokens хранятся в одной таблице `user_tokens` и различаются `token_type`.
