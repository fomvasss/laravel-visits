# Security considerations

Most of these are inherent to any client-side analytics beacon (the same holds for GA4's collect endpoint). They are listed so they are an informed choice, not a surprise.

## The visitor id is a bearer value, not a credential

`X-Visitor-Id` and `visitor_id` are checked for format only (`TokenResolver::isValidFormat()`), never for authenticity, and they win over the cookie. Anyone who learns another visitor's id — through XSS, logs, a shared link with `?visitor_id=` — can record events under that identity.

The impact is limited to the anonymous tracking identity: the id never authenticates anyone in your app. It can, however, affect which user a visitor row points to — a logged-in attacker sending a victim's id makes `RecordVisitJob` link that visitor row to the attacker's account. Treat the user link on visitors as analytics, not as an audit trail.

## Cookie and localStorage

The cookie is encrypted and `httpOnly` like every Laravel cookie. `visits.js` deliberately keeps the same id in `localStorage` — readable by any script on the page — because that is what makes cross-origin and cached-page tracking work. An XSS on the page can read it either way.

## Client data is not verified

`POST /visits/collect` accepts whatever `type`, `name`, `url` and `meta` a caller sends; nothing confirms that a reported page view or conversion happened. `rate_limit.endpoint` and `rate_limit.visitor_budget` bound the volume, not the authenticity. `collect.allowed_origins` filters by `Origin`/`Referer`, which non-browser clients forge freely — a filter against casual misuse, not authentication.

Record conversions that matter (orders, payments) server-side with `Visits::track()`, and treat beacon events as signals.

## No idempotency

A client retry or a webhook delivered twice records the event twice. Guard calls yourself where a conversion must not be counted twice.

## Bot detection is a filter

`matomo/device-detector` classifies by User-Agent, which any client sets freely. It keeps crawler noise out of the dashboard; it does not stop anyone.

## Dashboard and whoami

- The dashboard has no authentication by default — `dashboard.middleware` is `['web']`. It shows IPs, visitor ids, linked users and full URLs, including query strings. Protect it before deploying ([Dashboard](../usage/dashboard.md#securing-the-dashboard)).
- `/visits/whoami` is public and unauthenticated, throttled per IP by `rate_limit.whoami` (`60,1`). Every uncached `?ip=` costs a geo lookup. Tune the throttle, protect it, or disable it with `whoami.enabled = false`.

## Personal data

Visitor and session rows contain IP addresses, User-Agents, approximate location and full URLs. Under GDPR and similar laws these are personal data: gate tracking behind [consent](../usage/consent.md) where required, add routes whose URLs carry secrets (password reset links, signed URLs) to `exclude_paths`, set `geo.store_coordinates = false` if you don't need coordinates, and schedule `visits:prune` for a retention period ([Rollups, sessions & retention](../usage/maintenance.md#retention--visitsprune)).
