# Contracts

Extension points configured by class name in `config/visits.php`.

## `ConsentResolverInterface`

`Fomvasss\Visits\Contracts\ConsentResolverInterface` — `consent.resolver`.

```php
public function hasConsent(Illuminate\Http\Request $request): bool;
```

Asked by `TrackVisit` on every request it would track, when `consent.require_consent` is `true`. Resolved from the container each time. See [Consent](../usage/consent.md).

## `UserDisplayNameResolverInterface`

`Fomvasss\Visits\Contracts\UserDisplayNameResolverInterface` — `user_display_resolver`.

```php
public function resolve(mixed $user): ?string;
```

Receives the user model linked to a visitor or session; the dashboard shows the result. Default: `Fomvasss\Visits\Support\Resolvers\DefaultUserDisplayNameResolver` (`$user->name ?? $user->email ?? null`). See [Custom models](../usage/customization.md#user-display-name).

## `TokenResolver`

`Fomvasss\Visits\Support\TokenResolver` — a class, not an interface; replace it with a subclass through `token_resolver`. Bound in the container, so every consumer (`TrackVisit`, `CollectController`, `VisitsManager`, the auth listeners) gets the subclass.

| Member | Description |
|---|---|
| `HEADER` | `'X-Visitor-Id'` |
| `INPUT_KEY` | `'visitor_id'` |
| `resolve(Request $request, ?callable $fallback = null): string` | Header/input → cookie → `$fallback()` → authenticated user's latest visitor → `generate()`; each candidate must pass `isValidFormat()` |
| `hasRequestIdentity(Request $request): bool` | Whether the header/input or cookie carries a valid id |
| `generate(): string` | New id — `Str::uuid()` |
| `isValidFormat(string $token): bool` | Matches `visits.visitor_id.format_regex` |

See [Visitor identity](../usage/identity.md#custom-token-resolver).

## `VisitPayload`

`Fomvasss\Visits\DTO\VisitPayload` — readonly DTO passed to `RecordVisitJob`: `token`, `type`, `name`, `url`, `routeName`, `ip`, `userAgent`, `referrer`, `searchTerm`, `utm`, `extraParams`, `locale`, `browserLanguage`, `authUserType`, `authUserId`, `eventableType`, `eventableId`, `meta`, `recordEvent`.

Dispatching the job yourself with a hand-made payload runs the full pipeline — useful to test geo locally with a public IP:

```php
use Fomvasss\Visits\DTO\VisitPayload;
use Fomvasss\Visits\Jobs\RecordVisitJob;
use Fomvasss\Visits\Models\Event;
use Illuminate\Support\Str;

RecordVisitJob::dispatchSync(new VisitPayload(
    token: (string) Str::uuid(),
    type: Event::TYPE_PAGE_VIEW,
    name: null,
    url: 'https://example.test/',
    routeName: null,
    ip: '8.8.8.8',
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
    referrer: null,
    searchTerm: null,
    utm: [],
    extraParams: [],
    locale: 'en',
    browserLanguage: 'en',
    authUserType: null,
    authUserId: null,
    eventableType: null,
    eventableId: null,
    meta: null,
));
```

The DTO is internal: its constructor may change between minor versions, and queued jobs serialized with an older shape can fail after an upgrade.
