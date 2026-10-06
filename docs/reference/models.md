# Models & HasVisits

All models are in `Fomvasss\Visits\Models` and can be replaced through `visits.models` ([Custom models](../usage/customization.md)). Relations resolve the related class through the same config. Columns: [Database tables](database.md).

## `Visitor`

Table `visit_visitors`. Bots excluded by default.

| Member | Type | Description |
|---|---|---|
| `user()` | `MorphTo` | Linked user (`user_type`/`user_id`) |
| `sessions()` | `HasMany` | Sessions of this visitor |
| `firstSession()` | `HasOne` (`ofMany`) | Session with the earliest `started_at` |
| `latestSession()` | `HasOne` (`ofMany`) | Session with the latest `started_at` |
| `events()` | `HasMany` | Events of this visitor |
| `userDisplayName()` | `?string` | Display name of the linked user through `visits.user_display_resolver` |

Casts: `first_seen_at`, `last_seen_at` (datetime), `extra_params`, `device_meta`, `geo_meta` (array), `lat`, `lng` (`decimal:7`), `is_bot` (bool).

## `Session`

Table `visit_sessions`. Bots excluded by default.

| Member | Type | Description |
|---|---|---|
| `visitor()` | `BelongsTo` | Visitor |
| `user()` | `MorphTo` | User snapshot (`user_type`/`user_id`) |
| `events()` | `HasMany` | Events of this session |
| `isOpen()` | `bool` | `ended_at` is `null` — not yet closed by `visits:close-stale-sessions`; it may still be past the timeout |
| `userDisplayName()` | `?string` | As on `Visitor` |

Casts: `started_at`, `last_activity_at`, `ended_at` (datetime), `extra_params`, `device_meta`, `geo_meta` (array), `lat`, `lng` (`decimal:7`), `page_views_count`, `duration_seconds` (int), `is_bot` (bool).

## `Event`

Table `visit_events`. Bots excluded by default. Has `created_at` only (`UPDATED_AT = null`).

| Member | Type | Description |
|---|---|---|
| `TYPE_PAGE_VIEW` | const | `'page_view'` |
| `TYPE_ACTION` | const | `'action'` |
| `session()` | `BelongsTo` | Session |
| `visitor()` | `BelongsTo` | Visitor |
| `eventable()` | `MorphTo` | Model passed to `Visits::track()` |

Casts: `created_at` (datetime), `meta` (array), `is_bot` (bool).

## `StatDaily`

Table `visit_stats_daily`, no timestamps, no bot scope.

| Constant | Value |
|---|---|
| `METRIC_VISITORS` | `'visitors'` |
| `METRIC_SESSIONS` | `'sessions'` |
| `METRIC_PAGE_VIEWS` | `'page_views'` |
| `METRIC_CONVERSIONS` | `'conversions'` |

Casts: `date` (date), `count` (int).

## Bot scopes

`Visitor`, `Session` and `Event` use `Fomvasss\Visits\Concerns\ExcludesBotsByDefault`, which adds the global scope `Fomvasss\Visits\Models\Scopes\WithoutBotsScope` (`<table>.is_bot = false`).

| Scope | Description |
|---|---|
| `withBots()` | Remove the scope — humans and bots |
| `onlyBots()` | Bots only |
| `withoutGlobalScope(WithoutBotsScope::class)` | Same as `withBots()` |

The scope also applies inside relations: `$session->events` excludes bot events, `$event->visitor` is `null` for a bot visitor.

## `HasVisits` trait

`Fomvasss\Visits\Concerns\HasVisits` — for models passed to `Visits::track()` and for user models.

| Method | Returns | Description |
|---|---|---|
| `visitEvents()` | `MorphMany` | Events whose `eventable` is this model |
| `latestVisitEvent(?string $name = null)` | `MorphOne` | Latest event by `created_at`, optionally only with this name |
| `firstVisitEvent(?string $name = null)` | `MorphOne` | Earliest event by `created_at`, optionally only with this name |
| `visitorProfiles()` | `MorphMany` | Visitors linked to this model as their user |
| `firstVisitorProfile()` | `MorphOne` | Linked visitor with the earliest `first_seen_at` |
| `latestVisitorProfile()` | `MorphOne` | Linked visitor with the latest `last_seen_at` |

The `MorphOne` methods are `ofMany()` relations — call `->first()` or eager-load them. The name filter is applied inside the `ofMany` subquery, so "latest with this name" is correct even when newer events with other names exist. On the user model `visitorProfiles()` also enables the [authenticated-user fallback](../usage/identity.md#logged-in-users-on-bearer-token-apis).

Morph types are written with `getMorphClass()`, so `Relation::morphMap()` aliases match on reads.

## Factories

`Visitor`, `Session` and `Event` have factories (`Fomvasss\Visits\Database\Factories\*`) for your own tests; they need `fakerphp/faker`.

```php
use Fomvasss\Visits\Models\Visitor;

Visitor::factory()->create(); // is_bot = false
Visitor::factory()->bot()->create();
```
