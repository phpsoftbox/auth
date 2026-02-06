# Ссылки и параметры

## Базовый путь

```php
$paginator = (new Paginator(perPage: 20))->path('/users');
```

## Дополнительные query-параметры

```php
$paginator = (new Paginator(perPage: 20))
    ->path('/users')
    ->appends(['status' => 'active']);
```

## Фрагмент

```php
$paginator = (new Paginator(perPage: 20))
    ->path('/users')
    ->fragment('list');
```

## Окно ссылок

```php
$paginator = (new Paginator(perPage: 20))->window(2);
```

`window` — это количество страниц вокруг текущей, которые будут показаны в `meta.links`.

## Параметр страницы

```php
$paginator = (new Paginator(perPage: 20))->pageParam('p');
```

## PSR Request

Если путь не задан вручную, можно получить его из PSR Request через резолвер:

```php
use PhpSoftBox\Pagination\RequestPaginationContextResolver;
use Psr\Http\Message\ServerRequestInterface;

$resolver = new RequestPaginationContextResolver($request);

$paginator = (new Paginator(perPage: 20))
    ->resolver($resolver);
```

Можно передать резолвер сразу в конструктор:

```php
$paginator = new Paginator(perPage: 20, resolver: $resolver);
```

Query-параметры из резолвера автоматически попадают в ссылки.
`appends()` можно использовать для дополнения или перезаписи параметров.

## perPage из query

По умолчанию отключено. Включается через резолвер:

```php
$resolver = new RequestPaginationContextResolver(
    $request,
    perPageParam: 'per_page',
    perPageMax: 100,
);

$paginator = (new Paginator(perPage: 20))->resolver($resolver);
```
