# Events

All events are in `Fomvasss\Visits\Events`, use `Dispatchable` and `SerializesModels`, and carry one model in a public readonly property. They are dispatched synchronously from where they happen — four of them inside `RecordVisitJob`, so their listeners run on the queue worker, not in the web request.

| Event | Property | Fired when |
|---|---|---|
| `VisitRecorded` | `$event` (`Event`) | An event row was written — every page view and action |
| `ConversionRecorded` | `$event` (`Event`) | Additionally, for an `action` with an `eventable` model (`Visits::track('order.placed', $order)`) |
| `VisitorCreated` | `$visitor` (`Visitor`) | A visitor id was seen for the first time and its row was created |
| `SessionStarted` | `$session` (`Session`) | A new session was opened (not for events joining an open session) |
| `VisitorIdentified` | `$visitor` (`Visitor`) | A visitor was linked to a user by the `Login` listener or `Visits::identify()` (fires in the request), or for the first time by `RecordVisitJob` (fires in the queue) |

Notes:

- All of them fire for bots too — check `is_bot` on the carried model when a listener should skip bot traffic.
- `VisitRecorded` and `ConversionRecorded` are not fired for page views skipped by `page_views = first_only`.
- `RecordVisitJob` fires `VisitorIdentified` (in the queue) when an authenticated tracked request links a visitor that had no user — including after a `Login` merge that found no visitor row yet. A visitor already linked doesn't fire it again ([Visitor identity](../usage/identity.md#linking-a-visitor-to-a-user)).
- The package does not forward data anywhere itself. Use these events to send conversions to Meta CAPI, GA4, PostHog or a CRM.

```php
use Fomvasss\Visits\Events\ConversionRecorded;
use Illuminate\Support\Facades\Event;

Event::listen(function (ConversionRecorded $e) {
    if ($e->event->is_bot || $e->event->name !== 'order.paid') {
        return;
    }

    $session = $e->event->session;

    SendConversionToMeta::dispatch(
        orderId: $e->event->eventable_id,
        fbclid: $session?->extra_params['fbclid'] ?? null,
        ip: $session?->ip,
        userAgent: $session?->user_agent,
    );
});
```

`SendConversionToMeta` stands for your own job — keep slow HTTP calls out of the listener, since it runs inside `RecordVisitJob`. A listener that throws fails the job after the event row was already written; a retry would write it again.
