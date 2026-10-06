# Custom events & conversions

`Visits::track()` records an event of type `action` from PHP — a controller, a listener, a job, a webhook handler.

```php
use Fomvasss\Visits\Facades\Visits;

// a plain action
Visits::track('newsletter.subscribed');

// tied to a model, with metadata
Visits::track('order.placed', $order, ['amount' => $order->total, 'currency' => 'USD']);
```

```php
Visits::track(string $name, ?Model $eventable = null, ?array $meta = null, ?string $inheritFrom = null): void
```

| Argument | Stored as |
|---|---|
| `$name` | `visit_events.name` — any string; a dotted `noun.verb` convention keeps the Conversion panels readable |
| `$eventable` | `eventable_type` (the model's morph class, so `Relation::morphMap()` aliases are respected) and `eventable_id` (string column, UUID/ULID keys work) |
| `$meta` | `meta` JSON |
| `$inheritFrom` | not stored — see [Events without a visitor](#events-without-a-visitor) |

What happens:

1. The visitor id is resolved from the current request ([Visitor identity](identity.md)); a new one is generated if there is none
2. The visitor cookie is queued on the response — a first interaction that happens to be a `POST` (which `TrackVisit` skips) still gets a cookie
3. `RecordVisitJob` is dispatched with type `action`, the current request's URL and route name, IP, User-Agent, referrer and query parameters

The job attaches the event to the visitor's open session or opens a new one, then fires `VisitRecorded`, plus `ConversionRecorded` when an `$eventable` model was passed. Use those [events](../reference/events.md) to forward conversions to an ad platform's conversion API.

`Visits::track()` returns nothing and never throws on tracking problems — they happen later, in the job. With `visits.enabled = false` it does nothing.

> [!NOTE]
> Every call creates a new event. There is no idempotency key: a client retry or a webhook delivered twice records the conversion twice. Guard the call yourself if a conversion must never be counted twice.

## Reading conversions back

Add `HasVisits` to every model you pass as `$eventable`:

```php
use Fomvasss\Visits\Concerns\HasVisits;

class Order extends Model
{
    use HasVisits;
}
```

```php
$order->visitEvents; // all events tied to this order
$order->latestVisitEvent('order.placed')->first(); // latest event with that name, or null
$order->firstVisitEvent()->first(); // earliest event of any name
```

More in [Reading data back](reading-data.md).

## Events without a visitor

A payment provider's webhook carries no cookie and no visitor header — `Visits::track()` there would create a new anonymous visitor with the provider's server IP. `inheritFrom` takes the visitor from an earlier event on the same model instead:

```php
// 1. Product pages — recorded by TrackVisit, nothing to write.

// 2. Checkout submitted from the browser — the request identifies the visitor.
Visits::track('order.placed', $order, ['amount' => $order->total]);

// 3. Payment webhook, minutes or days later — no identity on the request.
Visits::track('order.paid', $order, ['amount' => $order->total], inheritFrom: 'order.placed');
```

`inheritFrom` is used only when the request has no visitor id of its own (no valid header, input or cookie). It looks up `$eventable->latestVisitEvent($inheritFrom)->first()?->visitor`, so `$eventable` must use `HasVisits` and already have an event with that name. If there is none (the order was placed before tracking was added), resolution continues with the [authenticated-user fallback](identity.md#logged-in-users-on-bearer-token-apis) and then generates a new id — no error.

> [!WARNING]
> The webhook request is still a request: its IP, User-Agent and geo are those of the payment provider. If the inherited visitor's session has timed out, the job opens a new session with the provider's IP and device data, and overwrites the visitor's last-known geo and device with them. The `order.paid` event itself is attributed correctly; treat session/visitor geo after such calls with care. Inherited events from bots are not found — `latestVisitEvent()` excludes bot events.

The same applies to `Visits::track()` from a queued job or an Artisan command: the "request" is the console request Laravel creates (`APP_URL`, IP `127.0.0.1`), and without `inheritFrom` every call creates a new visitor.

### Browser variant

If the payment provider redirects the customer back to a "thank you" page, that page is a real visit with the customer's own cookie or `localStorage` id — the [JS beacon](js-beacon.md) can record the step without `inheritFrom`:

```blade
<script>
    Visits.track('order.paid', { order_id: {{ $order->id }}, amount: {{ $order->total }} });
</script>
```

The beacon cannot set `eventable` — pass the order identifier in `meta`. And it fires only if the browser actually reaches the page; keep the webhook as the reliable signal.
