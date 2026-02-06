# Http Message

Собственная реализация PSR-7 и PSR-17 для PhpSoftBox.

## Пример

```php
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;

$request = new ServerRequest('GET', new Uri('https://example.com/ping'));
```

## Создание запроса из globals

```php
use PhpSoftBox\Http\Message\ServerRequestCreator;

$creator = new ServerRequestCreator();
$request = $creator->fromGlobals();
```
