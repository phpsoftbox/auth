# PhpSoftBox Validator DB

Контракты для интеграции валидатора с базой данных.

## Установка
```bash
composer require phpsoftbox/validator-db
```

## Использование

```php
use PhpSoftBox\Validator\Db\Contracts\DatabaseValidationAdapterInterface;

final class MyAdapter implements DatabaseValidationAdapterInterface
{
    public function exists(string $table, array $criteria, ?string $connection = null): bool
    {
        // ...
    }

    public function unique(
        string $table,
        array $criteria,
        ?string $connection = null,
        ?string $ignoreColumn = null,
        mixed $ignoreValue = null,
    ): bool {
        // ...
    }
}
```

## Лицензия
MIT
