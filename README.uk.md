# Laravel Visits

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)

Трекінг відвідувачів/сесій/переглядів сторінок для Laravel: асинхронний за замовчуванням, ідентичність через cookie + client-token, гео/девайс/бот-детекція, атрибуція кампаній (UTM), кастомні конверсійні події, rollup-аналітика та вбудований дашборд — включно з картою активності в реальному часі.

[English documentation](README.md)

## Можливості

- **Трирівнева модель даних** — Visitor (стала, крос-сесійна ідентичність) → Session (одна сесія перегляду) → Event (перегляд сторінки чи кастомна дія), замість однієї пласкої таблиці "visits".
- **Асинхронність за замовчуванням** — middleware/ендпоінт лише резолвить токен відвідувача і ставить job у чергу; вся важка робота (гео-лукап, детекція девайса/бота, записи в БД) відбувається поза циклом request/response.
- **Ідентичність через cookie + client-token** — довгоживуча cookie для звичайного браузерного трафіку, з клієнтським токеном (header чи `localStorage`), що має пріоритет для cross-origin SPA та нативних/мобільних клієнтів, де cookie ненадійні.
- **Гео, девайс та бот-детекція** — через `stevebauman/location` та `matomo/device-detector`.
- **Атрибуція кампаній** — UTM/`ref` параметри (first-touch на Visitor, last-touch на Session), плюс загальний JSON-бакет `extra_params` для click ID рекламних платформ (`gclid`, `fbclid`, `msclkid`, ...).
- **Кастомні конверсійні події** — `Visits::track('order.placed', $order, ['amount' => 100])`, опційно прив'язані до будь-якої Eloquent-моделі через поліморфний зв'язок.
- **Rollup-аналітика** — команда за розкладом попередньо агрегує денну статистику по метриках/вимірах, тож дашборд ніколи не сканує сирі таблиці подій.
- **Вбудований дашборд** — Overview (з картою локацій сесій), Campaigns, Sessions, Visitors, деталі сесії/відвідувача, сторінка Live-активності (polling чи Server-Sent Events) та публічна сторінка Whoami.
- **Публічний ендпоінт Whoami** — JSON-ендпоінт у стилі `ifconfig.me` (детекція IP/гео/девайса/UTM), лише читання, без встановлення cookie — можна використовувати окремо з інших сервісів.
- **Дружній до мультитенантності** — загальна колонка `tenant_id` та хуки перевизначення на рівні моделі, без нав'язування конкретного пакета тенантності.
- **Перевизначення моделей** — кожен зв'язок і шлях запису резолвить моделі через конфіг, тож підклас `Visitor`/`Session`/`Event`/`StatDaily` (додаткові scope, зв'язки, cast'и) підхоплюється всюди автоматично.
- **Хук згоди у стилі GDPR** — опційний resolver-інтерфейс блокує трекінг до підтвердження згоди.

## Вимоги

- PHP ^8.3
- Laravel ^12.0 або ^13.0
- Налаштоване з'єднання черги (трекінг за замовчуванням диспатчиться в чергу — див. ключ `queue` у [Конфігурації](#конфігурація))

## Встановлення

```bash
composer require fomvasss/laravel-visits
php artisan migrate
```

Сервіс-провайдер підключається автоматично (auto-discovery). Middleware трекінгу додається в групу `web` автоматично — вручну реєструвати нічого не треба.

Публікуй конфіг-файл, щоб налаштувати будь-що (назва cookie, rate limits, виключені шляхи, шлях/middleware дашборду, транспорт Live-сторінки, ...):

```bash
php artisan vendor:publish --tag=visits-config
```

Опційно опублікуй JS-беакон (потрібен лише для трекінгу SPA-роутів чи клієнтських кастомних подій — див. [JS-беакон](#js-беакон)):

```bash
php artisan vendor:publish --tag=visits-assets
```

## Швидкий старт

Це все, що треба для автоматичного трекінгу переглядів сторінок — кожен `GET`-запит через middleware-групу `web` уже трекається. Відкрий `/visits`, щоб побачити дашборд.

Щоб затрекати кастомну конверсію із серверного коду:

```php
use Fomvasss\Visits\Facades\Visits;

Visits::track('order.placed', $order, ['amount' => $order->total]);
```

Щоб перевірити, що пакет зараз бачить про запит, нічого не трекаючи:

```php
Visits::whoami(); // ['ip' => ..., 'geo' => ..., 'device' => ..., 'tracking_params' => ...]
```

або звернутись до публічного JSON-ендпоінту `GET /visits/whoami`.

## Як це працює

```
Visitor  (один рядок на браузер/пристрій, назавжди — стала ідентичність)
  └─ Session  (один рядок на сесію перегляду, закривається після неактивності)
       └─ Event  (один рядок на перегляд сторінки чи кастомну дію)
```

Запит проходить через middleware `TrackVisit` (або `POST /visits/collect`, або `Visits::track()`). Синхронно резолвиться лише токен відвідувача, і cookie ставиться в чергу на відправку — все інше (детекція бота/гео/девайса, пошук-або-створення `Visitor`, пошук-або-відкриття `Session`, запис `Event`) відбувається в `RecordVisitJob`, що диспатчиться в чергу, налаштовану через `visits.queue`. Застарілу (старшу за `session_timeout_minutes`) сесію закриває команда `visits:close-stale-sessions`, а не той запит, який інакше почав би нову.

## Трекінг

Який механізм використовувати — залежить від типу застосунку на іншому кінці:

- **Blade/серверно-рендерений сайт** — використовуй middleware `TrackVisit` ([нижче](#автоматичний-трекінг-переглядів)). Це відбувається автоматично: кожен `GET` — повне завантаження сторінки, тож є реальний серверний запит, на який можна повісити трекінг, без жодного додаткового коду.
- **API-бекенд за SPA чи мобільним застосунком** — middleware тут не застосовний. `GET` до API-ендпоінту (`GET /api/products`) — це фетч даних, а не перегляд сторінки, і зазвичай живе в групі `api`, якої `TrackVisit` взагалі не торкається. Трекай перегляди явно: [JS-беакон](#js-беакон) (`Visits.trackPageView()`) при зміні роута для SPA, або прямий виклик `POST /visits/collect` для мобільного застосунку (див. [`docs/client-integration.md`](docs/client-integration.md)).
- **Кастомні дії/конверсії, для будь-якого з вищезгаданих** — завжди [`Visits::track()`](#кастомні-дії-серверні), викликаний саме там, де бізнес-подія реально відбувається на сервері (контролер, job, обробник вебхука) — незалежно від того, чи запит, що її спричинив, був блейд-формою, API-викликом, чи queued job без запиту взагалі.

### Автоматичний трекінг переглядів

Будь-який `GET`-запит через middleware-групу `web` трекається автоматично, окрім шляхів, що збігаються з `visits.exclude_paths` (шляхи admin/debugbar/horizon/health-check виключені за замовчуванням) — і власних шляхів дашборду/whoami пакета, які завжди виключені незалежно від `exclude_paths` (інакше перегляд `/visits` сам генерував би рядки page-view про перегляд дашборду).

### Кастомні дії (серверні)

```php
use Fomvasss\Visits\Facades\Visits;

// проста дія, без пов'язаної моделі
Visits::track('newsletter.subscribed');

// прив'язана до Eloquent-моделі (записує eventable_type/eventable_id), з додатковими метаданими
Visits::track('order.placed', $order, ['amount' => $order->total, 'currency' => 'USD']);
```

Це проходить через той самий асинхронний пайплайн, що й перегляди сторінок, прикріплюється до тієї `Session`, що зараз відкрита для резолвленого токена відвідувача, і генерує [`VisitRecorded`](#події) (і [`ConversionRecorded`](#події), коли передано `$eventable`).

### JS-беакон

Для зміни роутів SPA та клієнтських кастомних подій, які серверний middleware не бачить. Без білд-кроку — підключай напряму, або через `vendor:publish --tag=visits-assets`:

```html
<script>
  window.VisitsConfig = { endpoint: '/visits/collect', autoTrackPageView: true };
</script>
<script src="/vendor/visits/visits.js"></script>
```

```js
Visits.trackPageView(); // виклич вручну при зміні роута SPA, якщо autoTrackPageView вимкнено
Visits.track('newsletter.subscribed', { plan: 'pro' });
```

Беакон зберігає `visitor_token` у `localStorage` (мовчки не спрацьовує, якщо недоступний) і шле його як `X-Visitor-Token`, що має пріоритет над cookie на сервері — саме це дозволяє йому працювати між origin'ами, де cookie ненадійні.

Якщо хочеш чергувати виклики так, як працює `dataLayer` у GTM (наприклад, завантажуючи `visits.js` з `async`, чи викликаючи події з inline `<script>` раніше в `<head>`, ще до того, як беакон встигне запуститись), пуш масив-виклики в `window.VisitsQueue` замість цього — безпечно як до, так і після завантаження скрипта:

```html
<script>
  window.VisitsQueue = window.VisitsQueue || [];
  window.VisitsQueue.push(['trackPageView']);
  window.VisitsQueue.push(['track', 'newsletter.subscribed', { plan: 'pro' }]);
</script>
<script src="/vendor/visits/visits.js" async></script>
```

### За межами same-origin Blade-застосунку

Беакон опційний — `POST /visits/collect` це звичайний JSON-ендпоінт, який можна викликати напряму будь-яким HTTP-клієнтом. Для API-only бекенду, окремого SPA/мобільного застосунку, бекенду, що обслуговує і веб-застосунок, і API одночасно, чи фронтенду на іншому домені, ніж API — див. [`docs/client-integration.md`](docs/client-integration.md), що саме треба відтворити самому, та специфіку конфігу/CORS/CSRF для кожного з цих випадків.

### Резолюція ідентичності

Пріоритет при резолюції токена відвідувача на кожному запиті: клієнтський токен (header `X-Visitor-Token` чи input `visitor_token`) → наявна cookie → щойно згенерований. Cookie (`visits.cookie.name`, TTL 2 роки за замовчуванням) (пере)ставиться в чергу на кожному затрекованому запиті незалежно від того, який шлях резолвив токен.

### Прив'язка visits до власних моделей

Додай трейт `HasVisits` до будь-якої моделі, яку передаєш у `Visits::track($name, $model)` (`Order`, `Lead`, `User`, ...):

```php
use Fomvasss\Visits\Concerns\HasVisits;

class Order extends Model
{
    use HasVisits;
}

$order->visitEvents; // кожен Event, прив'язаний до цієї моделі через eventable
$order->latestVisitEvent('order.shipped'); // останній Event з цією назвою, або null
```

На моделі `User` (чи якою б не була твоя auth-модель) той самий трейт додатково дає:

```php
$user->visitorProfiles; // кожен Visitor, коли-небудь пов'язаний з цим юзером, на всіх його пристроях/браузерах
```

## Tracking Params

Query-параметри розділені трьома способами (`config('visits.tracking_params')`):

- **core** — `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref`. Реальні, індексовані колонки. First-touch (записується один раз) на `Visitor`, last-touch (перезаписується, якщо присутній, інакше успадковується) на `Session`.
- **extra_keys** — завжди захоплюються в JSON-бакет `extra_params`: click ID рекламних платформ (`gclid`, `fbclid`, `msclkid`, `ttclid`, `yclid`, `twclid`, `li_fat_id` за замовчуванням) — висока кардинальність, не варто окремої колонки на кожен, але варто зберігати, щоб мати змогу відправити ID назад у conversion API тієї платформи.
- **extra_pattern** — опційний regex; будь-який інший query-параметр, чиє *ім'я* збігається з ним, теж захоплюється в `extra_params`. За замовчуванням `null`. Приклад: `'/^aff_/'` захопить кожен параметр `aff_*` з власної афіліат-мережі.

## Гео та детекція девайса

Гео-лукапи йдуть через `stevebauman/location` (налаштуй його драйвер у своєму `config/location.php`); результати кешуються по IP на `visits.geo.cache_ttl` секунд. Встанови `visits.geo.store_coordinates` у `false`, щоб не зберігати `lat`/`lng` для privacy-чутливих деплойментів.

Детекція девайса/браузера/платформи та класифікація ботів йде через `matomo/device-detector`, що компілює свій набір правил при першому використанні і кешує його в `visits.device_detection.cache_dir`. Бот-трафік ніколи не платить за гео-лукап (перевіряється першим), і запити дашборду виключають ботів за замовчуванням (див. `ExcludesBotsByDefault` — використай `withBots()`/`onlyBots()`, щоб повернути їх у будь-якому запиті).

### Використання MaxMind-драйвера

Драйвери `stevebauman/location` за замовчуванням роблять зовнішній HTTP-запит на кожен лукап. Для self-hosted альтернативи без зовнішніх запитів — перемкнись на локальний MaxMind (GeoLite2) драйвер:

1. Отримай безкоштовний license key на [MaxMind](https://www.maxmind.com/en/geolite2/signup) і додай у `.env`:
   ```
   MAXMIND_LICENSE_KEY=your-key-here
   ```
2. У `config/location.php` (публікується самим `stevebauman/location`, не цим пакетом — `php artisan vendor:publish --provider="Stevebauman\Location\LocationServiceProvider"`) встанови MaxMind драйвером, а HTTP-драйвер лиши як fallback:
   ```php
   'driver' => \Stevebauman\Location\Drivers\MaxMind::class,
   'fallbacks' => [
       \Stevebauman\Location\Drivers\IpApi::class,
   ],
   ```
3. Завантаж `.mmdb` базу:
   ```bash
   php artisan location:update
   ```
4. Додай директорію завантаженої бази (`database/maxmind` за замовчуванням) у `.gitignore` свого застосунку — це бінарний файл, який перезавантажується, а не комітиться.
5. GeoLite2-бази MaxMind оновлює приблизно щотижня. Плануй `location:update` періодично (наприклад, щотижня), щоб дані лишались актуальними:
   ```php
   // routes/console.php
   Schedule::command('location:update')->weekly();
   ```

## Згода (GDPR)

```php
'consent' => [
    'require_consent' => true,
    'resolver' => \App\Support\CookieConsentResolver::class,
],
```

```php
use Fomvasss\Visits\Contracts\ConsentResolverInterface;

class CookieConsentResolver implements ConsentResolverInterface
{
    public function hasConsent(Request $request): bool
    {
        return $request->cookie('cookie_consent') === 'accepted';
    }
}
```

Коли `require_consent` дорівнює `true`, middleware `TrackVisit` повністю пропускає трекінг, поки resolver не поверне `true`. Це блокує лише автоматичний middleware перегляду сторінок — `Visits::track()` і `POST /visits/collect` не блокуються, оскільки згоду зазвичай треба перевіряти ще до того, як ти вирішиш їх викликати.

## Мультитенантність

`Visitor` (і `visit_stats_daily`) мають загальну строкову колонку `tenant_id`, за замовчуванням `''` (не `null` — щоб unique/агрегаційні запити з порівнянням `tenant_id = ''` працювали надійно). Пакет ніколи не встановлює й не скоупить її сам; якщо в тебе мультитенантний застосунок, встанови її сам — наприклад, у хуку `booted()` на перевизначеній моделі `Visitor`, чи власному listener'і — і передавай `?tenant=...` на роутах дашборду, щоб фільтрувати по ній.

## Перевизначення моделей

```php
'models' => [
    'visitor' => \App\Models\Visitor::class,
    'session' => \Fomvasss\Visits\Models\Session::class,
    'event' => \Fomvasss\Visits\Models\Event::class,
    'stat_daily' => \Fomvasss\Visits\Models\StatDaily::class,
],
```

```php
class Visitor extends \Fomvasss\Visits\Models\Visitor
{
    protected static function booted(): void
    {
        static::creating(fn ($visitor) => $visitor->tenant_id = tenant()->id);
    }
}
```

Кожен внутрішній зв'язок і шлях запису резолвить моделі через `Fomvasss\Visits\Support\ModelResolver`, тож перевизначення підхоплюється послідовно всюди — включно зі зв'язками, визначеними на *інших* моделях (`Session::visitor()` повертає те, що ти налаштував для `'visitor'`, а не завжди базовий клас).

## Події

- **`VisitRecorded`** — генерується для кожного записаного `Event` (перегляд сторінки чи дія). Підпишись на неї для інтеграцій на боці хоста (пересилка конверсій у Meta CAPI, GA4, PostHog, ...) замість того, щоб пакет напряму спілкувався з цими сервісами. Несе `Event` як `$event`.
- **`ConversionRecorded`** — генерується додатково до `VisitRecorded`, коли подія — це кастомна дія, прив'язана до eventable-моделі (`Visits::track('order.placed', $order)`). Несе `Event` як `$event`.
- **`VisitorCreated`** — генерується один раз, коли токен відвідувача бачиться вперше (щойно створений рядок `Visitor`). Корисно для хуків "новий унікальний відвідувач" — синхронізація з CRM, захоплення first-touch атрибуції. Несе `Visitor` як `$visitor`.
- **`SessionStarted`** — генерується, коли відкривається нова `Session`, а не на кожній події в межах уже відкритої. Корисно для лічильників/вебхуків "активні сесії". Несе `Session` як `$session`.
- **`VisitorIdentified`** — генерується, коли анонімний `Visitor` прив'язується до реального юзера на `Login` (див. [Резолюцію ідентичності](#резолюція-ідентичності)). Корисно для мержу доreєстраційної історії в CRM-контакт саме в момент, коли ідентичність стає відомою. Несе `Visitor` як `$visitor`.

`VisitorCreated`/`SessionStarted` генеруються незалежно від бот-статусу, так само як `VisitRecorded`/`ConversionRecorded` — перевіряй `is_bot` на переданій моделі сам, якщо listener має пропускати бот-трафік.

## Ендпоінт Whoami

`GET /visits/whoami` (власний шлях/middleware, конфіг `visits.whoami.*`) повертає read-only JSON-знімок того, що пакет детектує про поточний запит — IP, гео, класифікація девайса/бота, локаль, referrer і tracking params. Нічого не записується: жодного рядка `Visitor`/`Session`/`Event`, жодної cookie. Корисно для іншого проєкту/сервісу, який хоче цю детекцію без прийняття всього трекінг-пайплайну, чи для дебагу, чому конкретний візит був/не був атрибутований так, як очікувалось.

```json
{
  "ip": "203.0.113.4",
  "visitor_token": "…",
  "user_agent": "…",
  "bot": { "is_bot": false, "bot_name": null, "bot_category": null },
  "device": { "device_type": "desktop", "platform": "Windows", "browser": "Chrome", "client_type": "browser", "...": "..." },
  "geo": { "country_code": "US", "city": "Mountain View", "lat": 37.751, "lng": -97.822, "...": "..." },
  "locale": "en",
  "referrer": null,
  "tracking_params": { "utm": { "utm_source": "google" }, "extra": {} }
}
```

`geo` дорівнює `null` (не `{}`), коли лукап зазнав невдачі — "невідомо" і "відомо, але порожньо" це різні стани. `tracking_params.utm`/`.extra` лишаються `{}`, коли відповідних query-параметрів не було, оскільки це саме по собі нормальний, змістовний стан.

Опційно передай `?ip=1.2.3.4`, щоб перевірити гео для іншого IP (девайс/локаль/tracking params все одно відображають реальний запит — немає сенсу симулювати їх для чужого девайса).

## Дашборд

![Dashboard](art/dashboard.gif)

Вбудований веб-інтерфейс, увімкнений за замовчуванням на `/visits` (конфіг `visits.dashboard.*` для шляху/middleware/пагінації). **Автентифікація за замовчуванням не застосовується** — додай власну (`auth`, `can:...`) через `visits.dashboard.middleware` перед деплоєм будь-де, крім local.

- **Overview** (`/visits`) — тотали + спарклайни трендів для відвідувачів/сесій/переглядів/конверсій за проміжок дат, панелі розбивки (UTM source, referrer host, країна, девайс, тип клієнта — чи за назвою конверсії), зведення по бот-трафіку, і карта локацій сесій (Leaflet, кластеризація маркерів, перемикач fullscreen).
- **Campaigns** (`/visits/campaigns`) — той самий механізм проміжку дат/розбивки, але всі UTM/`ref` виміри одразу, для заглиблення саме в атрибуцію кампаній.
- **Sessions** (`/visits/sessions`) — сортований, фільтрований (проміжок дат, країна, девайс, UTM source, IP) пагінований список; веде на деталі сесії (`/visits/sessions/{id}`) з повним таймлайном подій.
- **Visitors** (`/visits/visitors`) — та сама ідея, один рядок на `Visitor`, з фільтром "тільки повторні" й кількістю сесій; веде на деталі відвідувача (`/visits/visitors/{id}`).
- **Live** (`/visits/live`) — недавні події як згасаючі маркери-спалахи на мапі світу, плюс таблиця-лог, що скролиться, під нею (з посиланням назад на сторінку деталей кожної сесії). Не справжній реал-тайм — події проходять через чергу, перш ніж потрапити сюди, тож спалах відображає "нещодавно оброблено", а не точну мить, коли це сталося. Дивись [Сторінку Live-активності](#сторінка-live-активності) нижче щодо вибору polling vs. SSE.
- **Whoami** (`/visits/me`) — власне представлення дашбордом даних [Whoami](#ендпоінт-whoami), з формою для перевірки іншого IP.

### "Breakdown by: Sessions vs Conversions"

Перемикач на Overview/Campaigns (`?breakdown_metric=`) змінює саме те, що́ рахують панелі розбивки, а не лише групування:

- **Sessions** рахує візити — обсяг трафіку по джерелу (UTM, referrer, країна, ...).
- **Conversions** рахує самі conversion-*події* (`Visits::track()` / `Event::TYPE_ACTION`, не `page_view`) — сесія з 2 конверсіями рахує як 2, не як "1 сесія з подіями".

Це різні одиниці виміру, тож цифри відрізняються між режимами — це очікувано, не баг. Перемикання на Conversions на Overview ще й додає панель "Conversion event" — розбивку по самій `name` дії.

### Сторінка Live-активності

`visits.live.transport` обирає, як сторінка отримує оновлення:

- **`poll`** (за замовчуванням) — браузер фетчить `/visits/live/feed` кожні `poll_interval_ms`. Працює будь-де, коштує один короткий запит на інтервал на кожну відкриту вкладку.
- **`sse`** — браузер відкриває одне довгоживуче з'єднання до `/visits/live/stream` (Server-Sent Events), і сервер пушить оновлення по мірі їх появи. Нижча затримка і жодних марних "нічого нового" запитів, але з'єднання тримає один PHP-FPM воркер на кожну відкриту вкладку впродовж `sse_max_duration` секунд (`EventSource` браузера після цього перепідключається автоматично). Вмикай, лише якщо твій хостинг може дозволити собі відкриті з'єднання (щедрий FPM-пул, чи Octane).

## Консольні команди

### `visits:aggregate`

```bash
php artisan visits:aggregate                          # сьогодні
php artisan visits:aggregate --date=yesterday
php artisan visits:aggregate --from=2026-01-01 --to=2026-01-31
```

Перераховує `visit_stats_daily` за задану дату(и): видаляє наявні рядки для цієї пари `(date, tenant_id)`, потім вставляє свіжо обчислені (ідемпотентно). Сторінки Overview/Campaigns дашборду читають лише з цієї rollup-таблиці, ніколи не сканують сирі події напряму.

### `visits:close-stale-sessions`

```bash
php artisan visits:close-stale-sessions
```

Закриває будь-яку сесію, чий `last_activity_at` старший за `visits.session_timeout_minutes`, встановлюючи `ended_at`/`duration_seconds`/`exit_url`.

### `visits:prune`

```bash
php artisan visits:prune                 # використовує visits.retention_days
php artisan visits:prune --days=180
php artisan visits:prune --force         # пропустити підтвердження
```

Видаляє сирі рядки `visit_events`/`visit_sessions`/`visit_visitors`, старші за вікно зберігання. Ніколи не запускається автоматично — підключай у власний scheduler свідомо.

### `visits:seed-demo`

```bash
php artisan visits:seed-demo --visitors=150 --days=30 --fresh --force
```

Лише для розробки/тестування (реєструється тільки в оточеннях `local`/`testing`) — генерує реалістичні ланцюжки Visitor → Session → Event з узгодженими гео/девайс/UTM даними, потім запускає `visits:aggregate` по засіяному проміжку, щоб дашборд не був порожнім. `--fresh` спершу очищає наявні таблиці `visit_*` (питає підтвердження, якщо не передано ще й `--force`).

### Розклад (Scheduling)

`visits.schedule.enabled` за замовчуванням `true` — сервіс-провайдер сам реєструє `visits:close-stale-sessions` і `visits:aggregate` на фіксованих частотах нижче — жодних правок `routes/console.php` не потрібно для свіжого інсталу. Вимкни (`VISITS_SCHEDULE_ENABLED=false`), якщо хочеш інші частоти, або вже сам запланував ці команди (лишивши в такому разі увімкнено — вони запустяться двічі).

`visits:prune` свідомо ніколи не планується автоматично, навіть з увімкненим флагом — видалення рядків завжди має бути окремим, явним рішенням:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('visits:prune')->daily()->when(fn () => config('visits.retention_days') > 0);
```

Щоб налаштувати інші частоти замість авторегестрованих, встанови `visits.schedule.enabled` у `false` і додай їх сам:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('visits:close-stale-sessions')->everyFiveMinutes();
Schedule::command('visits:aggregate --date=today')->everyFiveMinutes();
Schedule::command('visits:aggregate --date=yesterday')->dailyAt('00:10');
```

## Конфігурація

Повний анотований конфіг-файл — у [`config/visits.php`](config/visits.php) — опублікуй його через `vendor:publish --tag=visits-config`, щоб налаштувати. Основні групи:

| Ключ | Призначення |
|---|---|
| `enabled` | Головний перемикач для всього пакета. |
| `models` | Перевизначити `Visitor`/`Session`/`Event`/`StatDaily` власними підкласами. |
| `queue` | Назва з'єднання/черги, в яку диспатчиться `RecordVisitJob`. |
| `cookie` | Назва/TTL cookie ідентичності відвідувача. |
| `reset_identity_on_logout` | Скидати `Visitor.user_id` на логауті (спільні/кіоскові пристрої). |
| `session_timeout_minutes` | Вікно неактивності, перш ніж `visits:close-stale-sessions` закриє сесію. |
| `exclude_paths` | Шляхи, які middleware трекінгу ніколи не трекає. |
| `tracking_params` | Core-колонки UTM/`ref`, `extra_keys` для click ID, опційний regex `extra_pattern`. |
| `geo` | TTL кешу гео-лукапу, чи зберігати координати. |
| `device_detection` | Директорія кешу правил `matomo/device-detector`. |
| `rate_limit` | Throttle для `/visits/collect` (`endpoint`), бюджет подій на відвідувача (`visitor_budget`), і для `/visits/whoami` (`whoami`). |
| `collect` | Middleware для `POST /visits/collect` (див. [`docs/client-integration.md`](docs/client-integration.md)), плюс опційний серверний allowlist `allowed_origins`. |
| `schedule.enabled` | Автореєстрація `visits:close-stale-sessions`/`visits:aggregate` за фіксованим розкладом (див. [Розклад](#розклад-scheduling)); увімкнено за замовчуванням. |
| `retention_days` | Вік, при якому сирі рядки стають доступні для `visits:prune`. |
| `aggregate.dimensions` | По яких вимірах `visits:aggregate` розбиває rollups. |
| `consent` | Заблокувати трекінг за реалізацією `ConsentResolverInterface`. |
| `dashboard` | Шлях/middleware/пагінація, проміжок дат за замовчуванням, URL тайлів мапи/ліміт маркерів. |
| `live` | Live-сторінка увімк./вимк., транспорт `poll`/`sse`, інтервали, ліміт фіда. |
| `whoami` | Шлях/middleware для публічного whoami-ендпоінту. |
| `tenant_resolver` | Зарезервовано для використання хостом — пакет сам ніколи не читає й не скоупить по ньому. |
| `user_display_resolver` | Клас, що реалізує `UserDisplayNameResolverInterface`, резолвить display-ім'я для поліморфного зв'язку `user` на дашборді. |

## Питання безпеки

Дещо з цього притаманне будь-якому клієнтському аналітичному беакону (те саме стосується і власного collect-ендпоінту GA4), не є унікальним для цього пакета — перелічено тут, щоб це був свідомий, поінформований вибір, а не сюрприз.

- **`visitor_token` — це bearer-токен, не підписаний credential.** `X-Visitor-Token`/`visitor_token` перевіряється лише на формат (`TokenResolver::isValidFormat()`), ніколи на автентичність. Будь-хто, хто отримає чужий токен (XSS, витік у referrer/логах), може записувати події від імені цієї ідентичності. Вплив обмежений підміною *анонімної трекінг*-ідентичності — `Visitor.user_id` встановлюється з власної події `Login` Laravel, не з цього токена, тож це не можна використати для імітації автентифікованого акаунту.
- **Cookie — `httpOnly`; копія в `localStorage` — ні.** `Cookie::queue()` у Laravel за замовчуванням `httpOnly`, тож сама cookie стійка до випадкових XSS-читань — але `visits.js` навмисно зберігає той самий токен у `localStorage` (читається будь-яким JS на сторінці), оскільки саме це й уможливлює cross-origin/SPA використання взагалі. XSS будь-де на сторінці може прочитати його в обох випадках.
- **Клієнтські дані не верифікуються.** `POST /visits/collect` (і все, що доходить до `Visits::track()` з клієнтського вводу) приймає будь-який `type`/`name`/`meta`/`url`, що надішле викликач — нічого не підтверджує, що заявлений перегляд сторінки чи конверсія реально сталися. `rate_limit.endpoint`/`visitor_budget` обмежують обсяг, не автентичність. `collect.allowed_origins` (див. [`docs/client-integration.md`](docs/client-integration.md)) фільтрує запити за `Origin`/`Referer`, але обидва контролюються атакуючим — сприймай це як фільтр для випадкових зловживань, не автентифікацію.
- **Немає ключа ідемпотентності на кастомних діях.** Клієнтський retry (наприклад, власна логіка повторів мобільного застосунку) може задвоїти той самий запис конверсії. Додай власну перевірку ідемпотентності в `meta`, якщо конкретна дія ніколи не повинна рахуватись двічі.
- **Бот-детекція (`matomo/device-detector`) — це фільтр якості даних, не засіб безпеки.** Вона базується на User-Agent і тривіально підміняється — добре для того, щоб прибрати очевидний краулерний шум з дашборду, не для блокування чогось чутливого.
- **Дашборд без автентифікації за замовчуванням.** `visits.dashboard.middleware` з коробки — `['web']` — додай власну (`auth`, `can:...`, чи свій gate) перед деплоєм будь-де, крім local.
- **`/visits/whoami` публічний, неавтентифікований, і прив'язаний лише до IP.** Тепер має власний throttle (`rate_limit.whoami`, за замовчуванням `60,1`), окремий від `rate_limit.endpoint` — зменш його (чи встанови `whoami.enabled` у `false`), якщо не хочеш, щоб він був доступний у продакшені взагалі.

## Тестування

```bash
composer test
```

## Підтримка

Якщо цей пакет корисний для тебе, розглянь можливість підтримати його розробку:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> USDT TRC20: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`

## Ліцензія

MIT — див. [LICENSE](LICENSE.md).
