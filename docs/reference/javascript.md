# JavaScript API

`resources/js/visits.js`, published to `public/vendor/visits/visits.js` with `--tag=visits-assets`. Plain ES5, no dependencies, uses `fetch`.

## `window.VisitsConfig`

Read once when the script runs.

| Option | Type | Default | Description |
|---|---|---|---|
| `endpoint` | string | `'/visits/collect'` | Collect URL; absolute for another origin |
| `autoTrackPageView` | boolean | `true` | Send `trackPageView()` on `window.load`, or immediately if already loaded. Only `false` turns it off |

## `window.Visits`

| Method | Sends | Returns |
|---|---|---|
| `trackPageView(url?)` | `{"type": "page_view", "url": url ?? location.href}` | `Promise` of the response data, or `null`/`undefined` on failure |
| `track(name, meta?)` | `{"type": "action", "name": name, "url": location.href, "meta": meta ?? null}` | same |

Every call sends `Content-Type: application/json`, `Accept: application/json`, `X-Visitor-Id` (when one is stored) and `X-CSRF-TOKEN` (when `<meta name="csrf-token">` exists), with `credentials: 'same-origin'` and `keepalive: true`. The returned promise never rejects.

## `window.VisitsQueue`

An array of calls, `[method, ...args]`. Calls pushed before the script loads run when it loads; after that `push()` runs the call immediately. Unknown method names are ignored.

```js
window.VisitsQueue = window.VisitsQueue || [];
window.VisitsQueue.push(['trackPageView', '/checkout/step-2']);
window.VisitsQueue.push(['track', 'checkout.started', { items: 3 }]);
```

## Storage

| Key | Where | Value |
|---|---|---|
| `visits_visitor_id` | `localStorage` | `visitor_id` from the last successful response |

When `localStorage` is unavailable (blocked storage, some private modes) the beacon sends no `X-Visitor-Id`, and identity falls back to the cookie on same-origin requests.

See [JS beacon](../usage/js-beacon.md) for setup and limitations.
