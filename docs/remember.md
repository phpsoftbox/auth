# Remember и Intended URL

Документ описывает готовые building blocks для browser remember-me и возврата
пользователя на исходный URL после авторизации.

Низкоуровневые классы `DatabaseRememberTokenStore`, `RememberCookieManager`,
`RememberTokenExtractor` и `RememberRestoreMiddleware` описаны в
[`guards.md`](guards.md#remember-me). Их можно использовать напрямую, если в
приложении один web guard и не нужен общий сервис для нескольких зон.

## MultiGuardRememberService

`MultiGuardRememberService` закрывает типовой multi-guard сценарий: один
application-level сервис управляет remember-token для разных browser guard,
например `web` и `tenant`.

```php
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Remember\MultiGuardRememberService;
use PhpSoftBox\Auth\Remember\RememberCookieConfig;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Auth\Remember\RememberGuardConfig;
use PhpSoftBox\Auth\Remember\RememberTokenExtractor;

$service = new MultiGuardRememberService(
    enabled: true,
    ttlDays: 30,
    guards: [
        'web' => new RememberGuardConfig(
            store: new DatabaseRememberTokenStore($connections),
            cookies: new RememberCookieManager($cookieQueue, new RememberCookieConfig(
                name: 'remember_web',
                path: '/',
            )),
            extractor: new RememberTokenExtractor('remember_web'),
            users: $webUsers,
            guard: $webGuard,
            metadata: ['scope' => 'central'],
        ),
        'tenant' => new RememberGuardConfig(
            store: new DatabaseRememberTokenStore($connections),
            cookies: new RememberCookieManager($cookieQueue, new RememberCookieConfig(
                name: 'remember_tenant',
                path: '/',
            )),
            extractor: new RememberTokenExtractor('remember_tenant'),
            users: $tenantUsers,
            guard: $tenantGuard,
            metadata: ['scope' => 'tenant'],
        ),
    ],
);
```

Основные операции:

```php
// Восстановить session по remember-cookie, если текущий guard еще не авторизован.
$service->restore('tenant', $request);

// Выдать remember-token после успешного login.
$service->issue('tenant', $user->id(), $request);

// Отозвать текущий remember-token и удалить cookie.
$service->forget('tenant', $request);
```

Если сервис выключен через `enabled: false`, методы становятся no-op. Если
guard не сконфигурирован, сервис бросает `InvalidArgumentException`: это
помогает ловить ошибки wiring-а на старте, а не молча пропускать remember-flow.

`issue()` добавляет в metadata техническое поле `area` с именем guard, а затем
добавляет metadata из `RememberGuardConfig`. Поле `area` нельзя переопределить
через config metadata. Raw-token в базе не хранится:
`DatabaseRememberTokenStore` использует безопасный формат `selector.secret`,
хранит hash секрета и пишет `token_type = remember`.

## IntendedUrlStore

`IntendedUrlStore` хранит в session первый GET URL, на который пользователь
попал до авторизации, и после login возвращает его через `pull()`.

```php
use PhpSoftBox\Auth\Redirect\IntendedUrlStore;

$intended = new IntendedUrlStore(
    session: $session,
    key: 'tenant.auth.intended',
    excludePaths: ['/tenant/login'],
    excludePrefixes: ['/tenant/logout', '/tenant/password'],
);

$intended->remember($request);

// После успешной авторизации:
$redirectTo = $intended->pull('/tenant');
```

Правила сохранения:

- сохраняются только GET-запросы;
- если URL уже сохранен, следующий `remember()` его не перезаписывает;
- `excludePaths` сравнивается с нормализованным path целиком;
- `excludePrefixes` исключает группы URL, например login/logout/password flow;
- `pull($fallback)` удаляет сохраненный URL из session и возвращает fallback,
  если URL отсутствует или пустой.

Для разных зон приложения используйте разные session keys, например
`auth.intended` для центральной зоны и `tenant.auth.intended` для tenant-зоны.
