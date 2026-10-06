# Whoami

A read-only snapshot of what the package detects about a request — IP, geo, device, bot classification, locale, referrer and tracking parameters — using the same detection as `RecordVisitJob`. Nothing is written: no visitor, session or event, no cookie.

Three ways to get it:

- `Visits::whoami()` in PHP
- `GET /visits/whoami` — public JSON endpoint, usable by other services
- `/visits/me` — the dashboard page

## In PHP

```php
use Fomvasss\Visits\Facades\Visits;

$info = Visits::whoami(); // the current request
$info = Visits::whoami($request); // another Request instance
$info = Visits::whoami(ip: '8.8.8.8'); // geo for another IP
```

```php
Visits::whoami(?Request $request = null, ?string $ip = null): array
```

`$ip` is passed through as is — validate it if it comes from user input. `whoami()` works even when `visits.enabled` is `false`.

## JSON endpoint

```http
GET /visits/whoami
GET /visits/whoami?ip=8.8.8.8
```

```json
{
  "ip": "8.8.8.8",
  "visitor_id": "7d0c3b1e-4f7e-4a8e-9a43-2a4f5b8c1d20",
  "user_agent": "Mozilla/5.0 ...",
  "bot": { "is_bot": false, "bot_name": null, "bot_category": null },
  "device": {
    "device_type": "desktop",
    "device_family": null,
    "device_model": null,
    "platform": "Windows",
    "platform_version": "10",
    "browser": "Chrome",
    "browser_version": "128.0",
    "browser_engine": "Blink",
    "client_type": "browser"
  },
  "geo": {
    "country_code": "US",
    "region": "California",
    "city": "Mountain View",
    "timezone": "America/Los_Angeles",
    "lat": "37.4056",
    "lng": "-122.0775",
    "country_name": "United States",
    "currency_code": null,
    "region_code": "CA",
    "zip_code": null,
    "postal_code": "94043",
    "metro_code": null,
    "area_code": null,
    "driver": "Stevebauman\\Location\\Drivers\\MaxMind"
  },
  "locale": { "locale": "en", "browser_language": "en_US" },
  "referrer": null,
  "tracking_params": { "utm": { "utm_source": "google" }, "extra": {} }
}
```

- `ip` — the request's IP, or `?ip=` when it is a valid IP address (an invalid value is ignored). Only geo and `ip` change with `?ip=`; device, locale and tracking parameters always describe the real request
- `visitor_id` — the `X-Visitor-Id` header, `visitor_id` input or cookie as sent, not validated; `null` if none. Never generated
- `geo` — `null` when the lookup found nothing; `lat`/`lng` are strings, absent with `geo.store_coordinates = false`. Which fields are filled depends on the driver
- `locale` — an object with `locale` (the app locale) and `browser_language`
- `tracking_params` — `utm` holds the `core` parameters keyed by column, `extra` the `extra_keys` and `extra_pattern` matches; both are `{}` when nothing matched

Configuration: `whoami.enabled`, `whoami.path` (default `visits/whoami`, independent of the dashboard path), `whoami.middleware` (`['web']`) and `rate_limit.whoami` (`60,1`, per IP). The endpoint stays available when `visits.enabled` is `false`.

> [!WARNING]
> The endpoint is public and every uncached `?ip=` triggers a geo lookup on your provider's quota. Keep the throttle, put it behind auth through `whoami.middleware`, or set `whoami.enabled` to `false` if you don't need it.
