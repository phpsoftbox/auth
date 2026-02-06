# Миграции

## Конфигурация

```php
return [
    'connections' => [
        'default' => 'main',
        'main' => [
            'read' => ['dsn' => 'postgres://user:pass@ro-host:5432/app', 'readonly' => true],
            'write' => ['dsn' => 'postgres://user:pass@rw-host:5432/app', 'readonly' => false],
        ],
    ],
    'migrations' => [
        'default' => 'main',
        'main' => [
            'paths' => [
                'database/migrations/main',
            ],
        ],
    ],
];
```

Правила:
- `migrations.default` — имя подключения по умолчанию.
- Если `migrations.default` не задан, используется `connections.default` (строка).
- `connections.default` должен быть строкой, иначе `MigrationsConfig` выбрасывает исключение.
- Если для подключения нет секции в `migrations`, будет ошибка конфигурации.
- Имя подключения `default` зарезервировано под alias.

## CLI-команды

Регистрация провайдера команд:

```php
use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\Database\Cli\DatabaseCommandProvider;
use PhpSoftBox\Database\Migrations\MigrationsConfig;

$registry = new InMemoryCommandRegistry();
$registry->addProvider(DatabaseCommandProvider::class);

// В DI-контейнер также нужно зарегистрировать MigrationsConfig:
// $container->set(MigrationsConfig::class, new MigrationsConfig($config));
```

Команды:
- `db:migrate` — применить миграции
- `db:migrate:rollback` — откатить миграции
- `db:migrate:make` — создать файл миграции

Опции:
- `--connection` (`-c`) — имя подключения/группы (по умолчанию `migrations.default`)
- `--path` (`-p`) — относительный путь внутри базовой директории из конфигурации
- `--steps` (`-s`) — количество шагов для rollback (по умолчанию `1`)

Миграции ищутся рекурсивно по маске `*.php` внутри указанных директорий.

## Примеры

Создать миграцию:

```
php psb db:migrate:make create_users_table
```

Применить миграции:

```
php psb db:migrate --connection=main
```
