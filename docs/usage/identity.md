# Visitor identity

A visitor is identified by a visitor id — a UUID stored in `visit_visitors.token`. There is no fingerprinting and no IP guessing: the id travels in a cookie, a header or a request field.

## Resolution order

On every tracked request (`TrackVisit`, `POST /visits/collect`, `Visits::track()`, and the `Login`/`Logout` listeners and `Visits::identify()`), `TokenResolver::resolve()` takes the first valid id from:

1. the `X-Visitor-Id` header, or else the `visitor_id` input (query string, form field or JSON body)
2. the visitor cookie (`visits.cookie.name`, `visits_visitor_id` by default)
3. `inheritFrom` — only in `Visits::track()`, see [Custom events](custom-events.md#events-without-a-visitor)
4. the authenticated user's most recently active visitor — see [below](#logged-in-users-on-bearer-token-apis)
5. a newly generated UUID

"Valid" means matching `visits.visitor_id.format_regex` (a UUID by default). An id in another format is ignored without error and the next source is tried.

The cookie is queued on the response for every tracked request, whichever source the id came from, with a lifetime of `cookie.ttl_minutes` (two years). It is a normal Laravel cookie: encrypted by `EncryptCookies` and `httpOnly`. Cookies are only written to responses that pass through the `web` group (`AddQueuedCookiesToResponse`) — on `api` routes the client has to keep the id itself.

`POST /visits/collect` also returns the id in its JSON body (`{"visitor_id": "..."}`) for clients without a cookie jar.

> [!NOTE]
> The header and input win over the cookie. A link like `https://example.com/?visitor_id=<uuid>` sets the identity for that request — this is how an id is handed over from another system ([Client integration](../guides/client-integration.md#handing-off-an-identifier-from-another-system)), and also how anyone can attach their requests to an id they know. See [Security considerations](../guides/security.md).

## Linking a visitor to a user

`visit_visitors.user_type`/`user_id` hold the user currently known on that device. Three things set them:

- **Laravel's `Login` event** — `MergeVisitorIdentity` resolves the current visitor id and links the visitor to the user, then fires `VisitorIdentified`. It also writes the user onto the visitor's open session if that session has no user yet.
- **`Visits::identify($user)`** — the same merge without a login, see below.
- **Any tracked request of an authenticated user** — `RecordVisitJob` copies `$request->user()` onto the visitor if it has no user yet. A visitor linked to another user is left alone; only `Login` and `identify()` move it. This does not fire `VisitorIdentified`.

`visit_sessions.user_type`/`user_id` is a snapshot: set when the session is opened by an authenticated request, or by the merge above if still empty, and never changed afterwards — even if another account logs in on the same device later.

`user_type` is the user model's morph class, so a `Relation::morphMap()` alias is respected. `user_id` is a string column — UUID and ULID keys work.

> [!WARNING]
> The merge only updates an existing visitor row. On a visitor's very first requests the row may not exist yet — `RecordVisitJob` is still in the queue — and then the merge does nothing and `VisitorIdentified` is not fired. The link is still made by the job on the next authenticated tracked request, without the event. Don't rely on `VisitorIdentified` firing for every login.

## Identifying without a login

A guest checkout matches or creates a `User` by email or phone — no password, no `Auth::login()`, no `Login` event. Link the visitor explicitly:

```php
use Fomvasss\Visits\Facades\Visits;

$user = User::firstOrCreate(['email' => $request->email]);

Visits::identify($user);
```

Don't dispatch a fake `Login` event for this. Other `Login` listeners (sign-in notifications, fraud checks, counters) would treat a form submission as an authentication. The reverse holds too: when a real authentication happens without `Auth::login()` — a Sanctum token issued after checking a password or an OTP — dispatching `Login` yourself is accurate and links the visitor:

```php
event(new \Illuminate\Auth\Events\Login('sanctum', $user, false));
```

## Logged-in users on Bearer-token APIs

A cookie only comes back when the browser sends it: always on the same origin, cross-origin only with `credentials: 'include'` and CORS `supports_credentials`. A typical token API (`Authorization: Bearer ...`) never gets the visitor cookie back, so every `Visits::track()` for a logged-in user would create a new anonymous visitor.

To prevent that, when the request has no id of its own, the resolver uses the authenticated user's most recently active visitor:

```php
$request->user()->visitorProfiles()->latest('last_seen_at')->value('token');
```

This needs the `HasVisits` trait on the user model — without it the step is skipped silently:

```php
use Fomvasss\Visits\Concerns\HasVisits;

class User extends Authenticatable
{
    use HasVisits;
}
```

Only the first anonymous visit before any visitor is linked to the user cannot be reconnected this way. `$request->user()` uses the default guard — call `Auth::shouldUse('sanctum')` (or authenticate through the matching middleware) before `Visits::track()` if your API uses another guard.

## Logout

By default logout changes nothing: the device stays linked to the last user, which keeps attribution continuous on a personal device. For shared or kiosk devices:

```php
// config/visits.php
'reset_identity_on_logout' => true,
```

On `Logout` the visitor's `user_type`/`user_id` are cleared. Session snapshots are not touched. A tracked request of that user that is still waiting in the queue will link the visitor again when it runs.

## Custom token resolver

Ids come from `Fomvasss\Visits\Support\TokenResolver`, bound in the container through `visits.token_resolver`. Subclass it to change how ids are generated or validated — for example to accept identifiers from an external system:

```php
namespace App\Support;

use Fomvasss\Visits\Support\TokenResolver;

class VisitorIdResolver extends TokenResolver
{
    public function generate(): string
    {
        return (string) \Illuminate\Support\Str::ulid();
    }

    public function isValidFormat(string $token): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $token);
    }
}
```

```php
// config/visits.php
'token_resolver' => \App\Support\VisitorIdResolver::class,
```

Every class that type-hints `TokenResolver` receives the override. Generated ids must fit the 64-character `token` column, and `generate()` must produce ids that `isValidFormat()` accepts, or every request gets a new visitor. Public methods: [Contracts](../reference/contracts.md#tokenresolver).
