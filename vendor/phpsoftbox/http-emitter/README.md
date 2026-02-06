# Http Emitter

Выводит PSR-7 ответ в окружение SAPI.

## Пример

```php
use PhpSoftBox\Http\Emitter\SapiEmitter;

$emitter = new SapiEmitter();
$emitter->emit($response);
```
