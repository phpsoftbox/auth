# RateLimiter

Простой компонент rate limiting на базе PSR-16.

## Пример

```php
use PhpSoftBox\RateLimiter\SimpleCacheRateLimiter;

$limiter = new SimpleCacheRateLimiter($cache);
$result = $limiter->hit('api|127.0.0.1', 60, 60);
```
