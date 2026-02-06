# Использование

```php
use PhpSoftBox\Pagination\Paginator;

$paginator = new Paginator(perPage: 20);

$result = $paginator->make(
    items: $items,
    total: 120,
    page: 2,
);
```

По умолчанию резолвер не читает `perPage` из query-параметров. Чтобы разрешить это,
передайте `perPageParam` (и, при необходимости, `perPageMax`).

## Настройка без DI

```php
use PhpSoftBox\Pagination\Paginator;
use PhpSoftBox\Pagination\RequestPaginationContextResolver;

$resolver = new RequestPaginationContextResolver($request, perPageParam: 'per_page', perPageMax: 100);

$paginator = new Paginator(perPage: 20, resolver: $resolver);
```

## Настройка через DI

```php
use DI\ContainerBuilder;
use function DI\autowire;
use function DI\get;

use PhpSoftBox\Pagination\Paginator;
use PhpSoftBox\Pagination\RequestPaginationContextResolver;
use Psr\Http\Message\ServerRequestInterface;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    RequestPaginationContextResolver::class => function () {
        return new RequestPaginationContextResolver(
            get(ServerRequestInterface::class),
            perPageParam: 'per_page',
            perPageMax: 100,
        );
    },
    Paginator::class => autowire()
        ->constructor(perPage: 20, resolver: get(RequestPaginationContextResolver::class)),
]);

$container = $builder->build();
```

Результат:

```json
{
  "data": [],
  "links": {
    "first": "/users?page=1",
    "last": "/users?page=6",
    "prev": "/users?page=1",
    "next": "/users?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 21,
    "last_page": 6,
    "links": [],
    "path": "/users",
    "per_page": 20,
    "to": 40,
    "total": 120
  }
}
```
