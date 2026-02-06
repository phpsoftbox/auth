# Защита аккаунта

`AccountProtectionService` ограничивает перебор и abuse в auth-формах:

- считает попытки по IP + scope;
- после порога включает обязательную CAPTCHA;
- хранит состояние в `CacheInterface`;
- проверяет CAPTCHA через HTTP endpoint провайдера.
- использует `Clock::now()` для расчета времени окна CAPTCHA (удобно для freeze/travel в тестах).

## Конфиг

Через DI передается `AccountProtectionConfig`:

```php
new AccountProtectionConfig(
    maxAttempts: 3,
    captchaSeconds: 900,
    cachePrefix: 'auth.account_protection',
    captchaSiteKey: '...',
    captchaSecretKey: '...',
    captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
    scopes: [
        'admin.login' => new AccountProtectionScopeConfig(maxAttempts: 3, captchaSeconds: 900),
        'cabinet.register' => new AccountProtectionScopeConfig(maxAttempts: 5, captchaSeconds: 900),
    ],
)
```

Поля:

- `maxAttempts` - порог до обязательной CAPTCHA по умолчанию.
- `captchaSeconds` - время обязательной CAPTCHA по умолчанию.
- `cachePrefix` - префикс cache-ключа.
- `captchaSiteKey` - публичный ключ CAPTCHA (ключ клиента).
- `captchaSecretKey` - секретный ключ CAPTCHA (ключ сервера).
- `captchaVerifyUrl` - URL валидации CAPTCHA.
- `scopes` - точечные правила по scope.

## Использование

```php
$scope = 'cabinet.login';

if ($protection->requiresCaptcha($request, $scope)
    && !$protection->validateCaptcha($request, $captchaToken, $scope)) {
    // reject
}

// on failed auth
$protection->registerFailure($request, $scope);

// on success
$protection->reset($request, $scope);
```

Для rate-limit на каждый запрос (не только ошибки) есть `registerAttempt()`.
