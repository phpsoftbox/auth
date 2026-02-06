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

## Миграции

Минимальные примеры таблиц для session storage и lifecycle-token лежат в
[`database/migrations`](database/migrations). Bearer/API tokens и remember-me
tokens хранятся в одной таблице `user_tokens` и различаются `token_type`.
