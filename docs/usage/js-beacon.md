# JS beacon

`visits.js` is a small script without a build step that posts to `POST /visits/collect`. Use it for what the server cannot see: SPA route changes, clicks and other client-side events, and pages served from a full-page cache.

```bash
php artisan vendor:publish --tag=visits-assets # → public/vendor/visits/visits.js
```

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
    window.VisitsConfig = { autoTrackPageView: false };
</script>
<script src="/vendor/visits/visits.js"></script>
```

```js
Visits.trackPageView(); // first load and every SPA route change
Visits.track('newsletter.subscribed', { plan: 'pro' }); // custom action
```

The whole API is in [JavaScript API](../reference/javascript.md).

## Configuration

`window.VisitsConfig` must be set before the script runs. It is read once.

| Option | Default | Meaning |
|---|---|---|
| `endpoint` | `'/visits/collect'` | URL to post to. Must be absolute when the API is on another origin |
| `autoTrackPageView` | `true` | Send a page view on `window.load` (or immediately if the page has already loaded). Any value other than `false` keeps it on |

> [!WARNING]
> On pages already tracked by `TrackVisit`, leave `autoTrackPageView` at its default and every page load is counted twice — once by the middleware, once by the beacon. Set it to `false` there and use the beacon for route changes and actions only.

## What is sent

```http
POST /visits/collect
Content-Type: application/json
Accept: application/json
X-Visitor-Id: 7d0c3b1e-...
X-CSRF-TOKEN: ...

{"type": "page_view", "url": "https://example.com/pricing"}
{"type": "action", "name": "newsletter.subscribed", "url": "https://example.com/blog", "meta": {"plan": "pro"}}
```

`X-Visitor-Id` is sent once an id is stored in `localStorage`, `X-CSRF-TOKEN` when the page has a `csrf-token` meta tag. The request uses `credentials: 'same-origin'` and `keepalive: true`, so an event sent right before navigation still goes out. The JSON response is `{"visitor_id": "..."}`; the beacon stores it in `localStorage` under `visits_visitor_id` and sends it as `X-Visitor-Id` from then on. The header takes priority over the cookie on the server, which is what makes the beacon work across origins and on cached pages.

Failures are swallowed: a network error, a `419` (CSRF), `403` (`allowed_origins`), `422` (validation) or `429` (throttle) never throws and is not reported anywhere. When tracking "just stops", check the browser's network tab.

## Queueing calls before the script loads

For `async` loading or calls from an inline script earlier in `<head>`, push array-form calls to `window.VisitsQueue`, like GTM's `dataLayer`:

```html
<script>
    window.VisitsQueue = window.VisitsQueue || [];
    window.VisitsQueue.push(['trackPageView']);
    window.VisitsQueue.push(['track', 'newsletter.subscribed', { plan: 'pro' }]);
</script>
<script src="/vendor/visits/visits.js" async></script>
```

Queued calls run when the script loads; after that `VisitsQueue.push()` runs immediately.

## Tracking many elements

One delegated listener instead of one per element, and a short per-element cooldown so a double click is not two conversions:

```html
<section id="pricing">
    <button data-plan="basic">Basic</button>
    <button data-plan="pro">Pro</button>
</section>
```

```js
document.getElementById('pricing').addEventListener('click', function (e) {
    const button = e.target.closest('[data-plan]');
    if (!button) return;

    const now = Date.now();
    if (button.dataset.lastTracked && now - button.dataset.lastTracked < 2000) return;
    button.dataset.lastTracked = now;

    Visits.track('pricing.plan_clicked', { plan: button.dataset.plan });
});
```

## Limitations

The server builds the payload from the `POST /visits/collect` request itself, not from the page that sent it:

- **UTM, `ref` and click IDs are not captured.** They are read from the query string of the collect request, which has none. Pages tracked by `TrackVisit` are not affected — the session is opened by the server-side page view. For beacon-only setups (SPA, cached landing pages) pass the page's query string along:

  ```js
  window.VisitsConfig = { endpoint: '/visits/collect' + window.location.search };
  ```

- **The referrer is the page itself.** The browser sends the current page as `Referer` on the `fetch`, so a session opened by the beacon gets your own host as `referrer_host` (or the SPA's origin, cross-origin), and no search keyword. `document.referrer` is not sent.
- **No route name.** `route_name` stays `null` for collect events — the request's own route is `visits.collect`.
- **No `eventable`.** The endpoint accepts `type`, `name`, `url` and `meta` only; put identifiers into `meta`.
- **Not verified.** Anyone can post any event; see [Security considerations](../guides/security.md).

For an API backend, another origin, a mobile app or calling the endpoint without the script, see [Client integration](../guides/client-integration.md).
