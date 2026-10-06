# Reading data back

The tables are ordinary Eloquent models — query them, join them with your own tables, eager-load them. All four live in `Fomvasss\Visits\Models` (or your overrides, see [Custom models](customization.md)).

## From a business model

Add `HasVisits` to every model you pass to `Visits::track()`:

```php
use Fomvasss\Visits\Concerns\HasVisits;

class Order extends Model
{
    use HasVisits;
}
```

Each `Event` carries its visitor and session, so an order leads straight to how and where it happened:

```php
$event = $order->latestVisitEvent('order.placed')->first();

$event->meta; // ['amount' => 100, 'currency' => 'USD']
$event->visitor; // Visitor — the device, across all its sessions
$event->session; // Session — the visit in which the order was placed
$event->session->utm_source; // last-touch attribution of this order
$event->visitor->utm_source; // first-touch attribution of the customer
$event->session->country_code; // geo when the order was placed
$event->session->device_type; // device used

// every tracked step of this order
$order->visitEvents()->oldest('created_at')->pluck('name');
```

`latestVisitEvent()` and `firstVisitEvent()` return relations (`MorphOne` via `ofMany()`), not models — call `->first()`, or eager-load them:

```php
$orders = Order::with('latestVisitEvent.session')->latest()->limit(50)->get();
```

> [!NOTE]
> Eager-loading `latestVisitEvent` loads the latest event of any name; the `$name` argument applies only when calling the method directly.

## From a user model

On the auth model the same trait links the visitors (devices) that user was identified on:

```php
$user->visitorProfiles; // every Visitor linked to this user
$user->visitorProfiles->count(); // devices/browsers used while known
$user->firstVisitorProfile; // earliest by first_seen_at
$user->latestVisitorProfile; // most recently active, by last_seen_at
$user->visitorProfiles->pluck('utm_source', 'id'); // first-touch channel per device

// all conversions of this user across devices
$user->visitorProfiles->flatMap->events->where('type', \Fomvasss\Visits\Models\Event::TYPE_ACTION);

// a device's first and latest session
$user->latestVisitorProfile?->firstSession;
$user->latestVisitorProfile?->latestSession;
```

`firstVisitorProfile`, `latestVisitorProfile`, `firstSession` and `latestSession` are `ofMany()` relations — one query each, eager-loadable.

A visitor is linked to a user only from the moment it is identified ([Visitor identity](identity.md#linking-a-visitor-to-a-user)). Anonymous browsing before that is on the same visitor and its sessions — reachable once the link exists, since it is the same row.

## Bots

`Visitor`, `Session` and `Event` exclude `is_bot = true` rows through a global scope, also inside relations:

```php
use Fomvasss\Visits\Models\Session;

Session::count(); // humans only
Session::withBots()->count(); // everyone
Session::onlyBots()->count(); // bots only
```

`StatDaily` has no such scope — rollups are built from non-bot rows only.

## Direct queries

```php
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;

// orders placed per campaign this month
Event::query()
    ->where('name', 'order.placed')
    ->where('visit_events.created_at', '>=', now()->startOfMonth())
    ->join('visit_sessions', 'visit_sessions.id', '=', 'visit_events.session_id')
    ->selectRaw('visit_sessions.utm_campaign, count(*) as orders')
    ->groupBy('visit_sessions.utm_campaign')
    ->get();

// sessions currently open
Session::whereNull('ended_at')->where('last_activity_at', '>=', now()->subMinutes(5))->count();
```

When joining, qualify columns: the bot scope adds `visit_events.is_bot = false` (the model's own table) and `created_at` exists on every table.

All relations, scopes and constants are listed in [Models & HasVisits](../reference/models.md).
