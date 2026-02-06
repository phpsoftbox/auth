# API credentials и enrollment

Этот раздел описывает аутентификацию приложения, в котором один субъект работает
с несколькими API и получает отдельный token для каждого из них.

## Основные понятия

Credential — секрет, который клиент предъявляет серверу. Компонент использует
формат `selector.secret`: selector хранится открыто и нужен для поиска записи,
а secret сохраняется только в виде SHA-256 hash.

Каждый постоянный credential имеет три независимые характеристики:

- `subjectId` — кому он выдан;
- `purpose` — для какого auth flow он предназначен;
- `audience` — в каком API его можно предъявить.

Например, одно устройство может иметь две записи:

```text
subject=device-42, purpose=api_access, audience=catalog
subject=device-42, purpose=api_access, audience=warehouse
```

Raw tokens у этих записей разные. Компрометация или отзыв token одного API не
затрагивает второй API.

## Идентификатор API

В приложении audience должен совпадать со стабильным `ApiDescription::id`:

```php
$catalogApi = ApiDescription::create('catalog')->build();

$catalogTokens = new DatabaseCredentialStore(
    connections: $connections,
    purpose: 'api_access',
    audience: $catalogApi->id,
);
```

URL prefix и версия API не являются audience. Маршруты `/v1` и `/v2` могут
относиться к одному API `catalog`; выбор опубликованной версии выполняется
отдельным version negotiation.

Проект должен создать отдельный audience-aware store или authenticator для каждой
защищённой API route group. Lookup всегда выполняется по точной паре
`purpose + audience`; fallback на credential с `audience = null` запрещён.

## Зачем нужен enrollment credential

Enrollment credential используется только для начального подключения субъекта.
Он не является постоянным Bearer token и не должен приниматься middleware
защищённых API.

У него отдельный lifecycle:

- короткий срок действия;
- audience enrollment endpoint;
- заданный сервером список API, для которых разрешено выпустить credentials;
- атомарное одноразовое погашение через `used_datetime`.

Для него используется специализированный `DatabaseEnrollmentCredentialStore`,
а не обычный `DatabaseCredentialStore`.

## Выдача enrollment credential

Store настраивается audience API, принимающего exchange:

```php
use DateTimeImmutable;
use PhpSoftBox\Auth\Enrollment\DatabaseEnrollmentCredentialStore;

$enrollment = new DatabaseEnrollmentCredentialStore(
    connections: $connections,
    audience: 'device-enrollment',
);

$issued = $enrollment->issue(
    subjectId: 'device-42',
    allowedAudiences: ['catalog', 'warehouse'],
    expiresAt: new DateTimeImmutable('+10 minutes'),
);
```

`allowedAudiences` задаётся доверенной серверной стороной. Exchange endpoint не
должен принимать от клиента произвольный список API и расширять grant.

Raw enrollment token доступен только в `$issued->token`. В БД сохраняются selector
и hash секрета.

## Атомарный exchange

`exchange()` блокирует запись enrollment credential, проверяет secret, audience,
срок действия, отзыв и `used_datetime`, после чего выполняет callback внутри той
же DB-транзакции:

```php
use PhpSoftBox\Auth\Enrollment\EnrollmentGrant;
use LogicException;

$catalogTokens = new DatabaseCredentialStore(
    connections: $connections,
    purpose: 'api_access',
    audience: 'catalog',
);

$warehouseTokens = new DatabaseCredentialStore(
    connections: $connections,
    purpose: 'api_access',
    audience: 'warehouse',
);

$tokenStores = [
    'catalog' => $catalogTokens,
    'warehouse' => $warehouseTokens,
];

$credentials = $enrollment->exchange(
    $rawEnrollmentToken,
    static function (EnrollmentGrant $grant) use ($tokenStores): array {
        $credentials = [];
        foreach ($grant->allowedAudiences as $audience) {
            $store = $tokenStores[$audience] ?? null;
            if ($store === null) {
                throw new LogicException("Credential store is not configured for API: {$audience}");
            }

            $credentials[$audience] = $store->issue($grant->subjectId);
        }

        return $credentials;
    },
    $request,
);
```

Все stores внутри callback должны использовать то же write-подключение. Тогда
ошибка при выпуске любого постоянного credential откатит и созданные записи, и
`used_datetime`. После успешного commit enrollment credential повторно не
принимается.

Выпуск выполняется только для audiences из grant. Нельзя обходить этот список и
выбирать API из request payload.

Если нужно только погасить credential без выпуска других credentials, используйте
`consume()`.

## Причины отказа

`EnrollmentCredentialException::$reason` содержит одно из значений:

- `Invalid` — неверный формат, secret, audience или отозванная запись;
- `Expired` — истёк срок действия;
- `AlreadyUsed` — credential уже был успешно погашен.

Проект сопоставляет эти причины со своими API errors. Raw credential нельзя
добавлять в exception message, логи или profiler.

## Потеря ответа exchange

Постоянные raw tokens показываются только один раз. Если транзакция завершилась,
но клиент не получил HTTP-ответ, повторный exchange вернёт `AlreadyUsed`.

Базовый компонент не хранит постоянные raw tokens и не может безопасно повторить
тот же ответ. Для такого случая проект должен предусмотреть повторное enrollment
либо отдельный idempotency/recovery protocol.

## Авторизация после аутентификации

Audience отвечает только на вопрос, в каком API token можно предъявить. Он не
заменяет роли, permissions или credential scopes. Неверный или чужой audience
возвращает `401`; успешно аутентифицированный subject без права на операцию — `403`.
