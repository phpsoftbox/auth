# PhpSoftBox Pagination

Компонент для формирования пагинации и ссылок.

## Установка

```bash
composer require phpsoftbox/pagination
```

## Быстрый старт

```php
<?php

use PhpSoftBox\Pagination\Paginator;
use PhpSoftBox\Pagination\RequestPaginationContextResolver;

$resolver = new RequestPaginationContextResolver($request);

$paginator = new Paginator(perPage: 20, resolver: $resolver);
$paginator = $paginator->appends(['status' => 'active']);

$result = $paginator->make(
    items: $items,
    total: 120,
    page: 2,
);

$payload = $result->toArray();
```

## Документация

- [docs/01-usage.md](docs/01-usage.md) — базовое использование
- [docs/02-links.md](docs/02-links.md) — ссылки и параметры
