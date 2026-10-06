# Geo, device & bot detection

Both run inside `RecordVisitJob`, never in the request. Device and bot detection come first, so bot traffic never pays for a geo lookup.

## What is stored

| | Columns | JSON |
|---|---|---|
| Geo | `country_code`, `region`, `city`, `timezone`, `lat`, `lng` | `geo_meta`: `country_name`, `currency_code`, `region_code`, `zip_code`, `postal_code`, `metro_code`, `area_code`, `driver` |
| Device | `device_type`, `client_type`, `platform`, `browser`, `is_bot` | `device_meta`: `device_family` (brand), `device_model`, `platform_version`, `browser_version`, `browser_engine` |
| Bot | `is_bot` on visitors, sessions and events; `bot_name`, `bot_category` on events only | — |
| Locale | `locale`, `browser_language` | — |

On `Session` these are a snapshot taken when the session opened. On `Visitor` they are the last known values, overwritten by every recorded request — except geo, which keeps the previous value when a lookup returns nothing. `geo_meta` fields are filled differently by each driver (IpApi sets `zip_code` and `currency_code`, MaxMind `postal_code` and `metro_code`), which is why they are not columns.

## Geo

Lookups go through [`stevebauman/location`](https://github.com/stevebauman/location) with the IP of the request (`$request->ip()`):

- results are cached per IP for `visits.geo.cache_ttl` seconds (one day) under `visits:geo:{ip}` in the default cache store; a failed lookup is cached for five minutes
- a lookup never throws — on failure the geo fields stay empty
- with `visits.geo.store_coordinates = false` no `lat`/`lng` is stored, and the Overview map and the Live page stay empty

Without a published `config/location.php` the package's default driver chain is used: HTTP providers (ip-api.com first), one outbound request per uncached IP, from the queue worker. Check the provider's rate limits and terms before production traffic, or use a local database.

Private and local IPs (`127.0.0.1`, Docker networks) have no location, so local development usually shows no geo. `location.testing` does not change that — the package always passes an explicit IP.

### MaxMind GeoLite2

A local `.mmdb` database — no outbound request per visitor:

1. Get a free license key at [maxmind.com](https://www.maxmind.com/en/geolite2/signup):

   ```env
   MAXMIND_LICENSE_KEY=your-key
   ```

2. Publish `stevebauman/location`'s config and switch the driver, keeping an HTTP provider as fallback:

   ```bash
   php artisan vendor:publish --provider="Stevebauman\Location\LocationServiceProvider"
   ```

   ```php
   // config/location.php
   'driver' => \Stevebauman\Location\Drivers\MaxMind::class,
   'fallbacks' => [
       \Stevebauman\Location\Drivers\IpApi::class,
   ],
   ```

3. Download the database (to `database/maxmind/` by default) and keep it out of git:

   ```bash
   php artisan location:update
   ```

   ```text
   /database/maxmind
   ```

4. GeoLite2 is updated about weekly — schedule the download:

   ```php
   // routes/console.php
   Schedule::command('location:update')->weekly();
   ```

Check that it works:

```bash
php artisan tinker --execute="dump(\Stevebauman\Location\Facades\Location::get('8.8.8.8'))"
```

Or open `/visits/me?ip=8.8.8.8` on the dashboard ([Whoami](whoami.md)).

## Device and bots

[`matomo/device-detector`](https://github.com/matomo-org/device-detector) parses the User-Agent once and returns device, OS, client and bot information.

- `device_type`: `desktop`, `smartphone`, `tablet`, `phablet`, `tv`, ...
- `client_type`: `browser`, `mobile app`, `library`, `feed reader`, `mediaplayer`, `pim` — independent of `is_bot`; HTTP libraries and apps are often not classified as bots
- `bot_name` / `bot_category`: for example `Googlebot` / `Search bot`, stored on the event only

Bots are always recorded — a false positive should not lose a real visit — and excluded from reads by a global scope ([Reading data back](reading-data.md#bots)). Detection is based on the User-Agent and trivial to spoof: a data-quality filter, not a security control.

## Locale

- `locale` — `app()->getLocale()` at the moment the payload is built, i.e. the locale your app had set by then. For `TrackVisit` that depends on whether your locale middleware runs before it (it is appended to the end of the `web` group). For `POST /visits/collect` it is whatever locale that route runs with, usually the default.
- `browser_language` — the first language from `Accept-Language` in Symfony's format (`en_US`, `uk`), skipping `*` and values longer than 10 characters; `null` if none.

## Debugging

`Visits::whoami()` and the [Whoami endpoint](whoami.md) run the same detection for the current request without writing anything — the quickest way to see what the package detects for a browser or an IP.
