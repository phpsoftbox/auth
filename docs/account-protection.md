# Защита аккаунта

`AccountProtectionService` ограничивает перебор и abuse в auth-формах:

- считает попытки по IP + scope;
- после порога включает обязательную CAPTCHA;
- хранит состояние в `CacheInterface`;
- проверяет CAPTCHA через `CaptchaVerifierInterface`;
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
    captchaVerifyUrl: 'https://smartcaptcha.cloud.yandex.ru/validate',
    captchaFailOpen: false,
    captchaValidateHost: true,
    captchaAllowEmptyHost: false,
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
- `captchaFailOpen` - если `true`, транспортные ошибки CAPTCHA не блокируют действие. По умолчанию `false`.
- `captchaValidateHost` - если `true`, проверяет `host` из ответа CAPTCHA против host текущего HTTP-запроса.
- `captchaAllowEmptyHost` - если `true`, принимает пустой `host` в ответе CAPTCHA. По умолчанию `false`.
- `scopes` - точечные правила по scope.

## CAPTCHA verifier

`AccountProtectionService` зависит от нейтрального контракта `CaptchaVerifierInterface`.
Это позволяет заменить провайдера CAPTCHA без изменения auth-сценариев.

Дефолтная реализация - `YandexSmartCaptchaVerifier`.
Она отправляет `secret`, `token` и `ip` в SmartCaptcha verify endpoint и считает проверку успешной только при `status = ok`.

Если в Yandex SmartCaptcha отключена проверка домена, приложение должно проверять домен на своей стороне.
Для этого `YandexSmartCaptchaVerifier` по умолчанию сравнивает `host` из ответа SmartCaptcha с host текущего запроса.
Пустой `host` отклоняется, если явно не включен `captchaAllowEmptyHost`.
Поведение соответствует официальной документации SmartCaptcha по валидации пользователя:
https://yandex.cloud/en/docs/smartcaptcha/concepts/validation

Имена разделены намеренно:

- `CaptchaVerifierInterface` - общий контракт для приложения;
- `YandexSmartCaptchaVerifier` - конкретная реализация для Yandex SmartCaptcha;
- для Google/другого провайдера нужна отдельная реализация того же интерфейса, потому что формат ответа и поля диагностики отличаются.

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
