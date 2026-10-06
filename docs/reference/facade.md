# Visits facade

`Fomvasss\Visits\Facades\Visits` proxies to `Fomvasss\Visits\VisitsManager`. The alias `Visits` is registered by package discovery. `VisitsManager` can also be injected; it reads the current `Request` from the container.

| Method | Returns | Description |
|---|---|---|
| `track(string $name, ?Model $eventable = null, ?array $meta = null, ?string $inheritFrom = null)` | `void` | Record an `action` event for the current request's visitor. Queues the visitor cookie and dispatches `RecordVisitJob`. No-op when `visits.enabled` is `false` |
| `identify(Authenticatable $user)` | `void` | Link the current request's visitor to `$user` and fire `VisitorIdentified` — the `Login` merge without a login. No-op when disabled or when no visitor row exists yet for the id |
| `whoami(?Request $request = null, ?string $ip = null)` | `array` | Detection snapshot (IP, visitor id, User-Agent, bot, device, geo, locale, referrer, tracking params) for `$request` or the current request; `$ip` overrides the IP used for geo. Writes nothing. Works when disabled |

## `track()`

| Parameter | Description |
|---|---|
| `$name` | Event name, stored in `visit_events.name` (up to 255 characters) |
| `$eventable` | Model the event belongs to — `eventable_type` = `getMorphClass()`, `eventable_id` = `getKey()` |
| `$meta` | Array stored as JSON in `meta` |
| `$inheritFrom` | Event name to take the visitor from when the request carries no visitor id (no valid header, input or cookie): `$eventable->latestVisitEvent($inheritFrom)->first()?->visitor`. Requires `HasVisits` on `$eventable` |

Fires later, in the job: `VisitorCreated` / `SessionStarted` if new, `VisitRecorded`, and `ConversionRecorded` when `$eventable` is given.

```php
Visits::track('lead.created', $lead, ['form' => 'contact']);
Visits::track('order.paid', $order, ['amount' => $order->total], inheritFrom: 'order.placed');
```

See [Custom events & conversions](../usage/custom-events.md).

## `identify()`

```php
Visits::identify($user);
```

Resolves the current visitor id like every entry point, then updates `visit_visitors.user_type`/`user_id` and, if the visitor's open session (within `session_timeout_minutes`) has no user yet, the session too. See [Visitor identity](../usage/identity.md#identifying-without-a-login).

## `whoami()`

```php
$info = Visits::whoami();
$info['geo']['country_code'] ?? null;
$info['device']['browser'];
```

Keys: `ip`, `visitor_id`, `user_agent`, `bot` (`is_bot`, `bot_name`, `bot_category`), `device` (`device_type`, `device_family`, `device_model`, `platform`, `platform_version`, `browser`, `browser_version`, `browser_engine`, `client_type`), `geo` (array or `null`), `locale` (`locale`, `browser_language`), `referrer`, `tracking_params` (`utm`, `extra`). See [Whoami](../usage/whoami.md).
