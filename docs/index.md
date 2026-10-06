# Laravel Visits

Self-hosted, first-party analytics for Laravel: visitor, session and page-view tracking, geo/device/bot detection, campaign attribution, conversions tied to your own Eloquent models, daily rollups and a built-in dashboard — all stored in your own database.

The package records three levels of data instead of one flat "visits" table:

```
Visitor   one row per browser/device — durable identity (cookie or client-supplied id)
  └─ Session   one browsing session, closed after inactivity
       └─ Event   a page view or a custom action ("order.placed")
```

- **Async-first** — the request only resolves the visitor id and dispatches `RecordVisitJob`; geo lookup, device/bot detection and all database writes run on the queue
- **Three entry points** — automatic page views (`TrackVisit` middleware), a JSON endpoint for the JS beacon and mobile apps (`POST /visits/collect`), and `Visits::track()` from PHP
- **Identity** — a long-lived cookie, a client-supplied `X-Visitor-Id` for SPAs and mobile apps, automatic reconnection of logged-in users on Bearer-token APIs, merge on `Login` or `Visits::identify()`
- **Attribution** — UTM/`ref` first-touch on the visitor and last-touch on the session, ad click IDs (`gclid`, `fbclid`, ...), organic search keywords from the referrer
- **Conversions on your models** — `Visits::track('order.placed', $order)` writes a polymorphic `eventable` relation you can read back from the order
- **Geo, device, bot detection** — `stevebauman/location` (HTTP providers or a local MaxMind database) and `matomo/device-detector`; bots are recorded but hidden from reads by default
- **Dashboard** — Overview with a session map, Campaigns, Sessions, Visitors, per-session/visitor detail, Live activity map (polling or SSE), Whoami
- **Hooks** — events for every recorded visit, conversion, new visitor, new session and identified visitor

![Dashboard](images/dashboard.gif)

## Quick example

```bash
composer require fomvasss/laravel-visits
php artisan migrate
```

Every `GET` request through the `web` middleware group is now tracked (a queue worker must be running), and the dashboard is at `/visits`.

Track a conversion from anywhere in your code:

```php
use Fomvasss\Visits\Facades\Visits;

Visits::track('order.placed', $order, ['amount' => $order->total]);
```

Read it back from the order (the model uses the `HasVisits` trait):

```php
$event = $order->latestVisitEvent('order.placed')->first();

$event->session->utm_source; // campaign that brought this order
$event->session->country_code; // where it was placed from
```

## Contents

Getting started

1. [Installation](installation.md)
2. [Configuration](configuration.md)

Usage

3. [How tracking works](usage/how-it-works.md)
4. [Page views](usage/page-views.md)
5. [Custom events & conversions](usage/custom-events.md)
6. [JS beacon](usage/js-beacon.md)
7. [Visitor identity](usage/identity.md)
8. [Reading data back](usage/reading-data.md)
9. [Campaign attribution](usage/attribution.md)
10. [Geo, device & bot detection](usage/geo-device.md)
11. [Consent](usage/consent.md)
12. [Custom models & multi-tenancy](usage/customization.md)
13. [Dashboard](usage/dashboard.md)
14. [Whoami](usage/whoami.md)
15. [Rollups, sessions & retention](usage/maintenance.md)

Guides

16. [Production checklist](guides/production.md)
17. [Client integration scenarios](guides/client-integration.md)
18. [Security considerations](guides/security.md)
19. [When to use this package](guides/comparison.md)
20. [Architecture](guides/architecture.md)

Reference

21. [Visits facade](reference/facade.md)
22. [Models & HasVisits](reference/models.md)
23. [Database tables](reference/database.md)
24. [Routes & middleware](reference/routes.md)
25. [JavaScript API](reference/javascript.md)
26. [Events](reference/events.md)
27. [Artisan commands](reference/commands.md)
28. [Contracts](reference/contracts.md)

[Upgrading](upgrading.md)
