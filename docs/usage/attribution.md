# Campaign attribution

Query parameters of a tracked request are split three ways (`visits.tracking_params`):

| Group | Default parameters | Stored in |
|---|---|---|
| `core` | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `ref` | Columns of the same name on `visit_visitors` and `visit_sessions` |
| `extra_keys` | `gclid`, `fbclid`, `msclkid`, `ttclid`, `yclid`, `twclid`, `li_fat_id` | `extra_params` JSON |
| `extra_pattern` | `null` | `extra_params` JSON — any other parameter whose name matches the regex |

Empty values are ignored. Only the current request's query string is read — not a form body, and for `POST /visits/collect` not the page that sent the beacon ([JS beacon limitations](js-beacon.md#limitations)).

## First touch and last touch

- **Visitor — first touch.** Written once, when the visitor row is created by its first recorded request. Never overwritten.
- **Session — last touch.** Written when a session is opened: the request's own parameters if it has any, otherwise copied from the visitor's first touch. UTM and `extra_params` are taken as a group: a request with only `utm_source` does not mix with the visitor's `utm_campaign`. Parameters on later requests of an already open session are not stored.

So `$visitor->utm_source` answers "how did this customer first find us" and `$session->utm_source` answers "what brought this visit".

## Click IDs

Ad click IDs are high-cardinality and platform-specific — not worth a column each, but worth keeping to send back to the platform's conversion API:

```php
use Fomvasss\Visits\Events\ConversionRecorded;
use Illuminate\Support\Facades\Event;

Event::listen(ConversionRecorded::class, function (ConversionRecorded $e) {
    $gclid = $e->event->session?->extra_params['gclid'] ?? null;
    // send the conversion to Google Ads with $gclid
});
```

Add your own parameters:

```php
'tracking_params' => [
    // ...
    'extra_keys' => ['gclid', 'fbclid', 'msclkid', 'ttclid', 'yclid', 'twclid', 'li_fat_id', 'epik'],
    'extra_pattern' => '/^aff_/', // aff_id, aff_sub, ...
],
```

The `core` map is `column => query parameter`. You can change which query parameter feeds a column (`'ref' => 'partner'`), but not add columns — there are exactly these six.

## Referrer

`Referer` is stored as `referrer_url` / `referrer_host` on the session and `first_referrer_url` / `first_referrer_host` on the visitor, cut to the column length (2048 and 255 characters). On the dashboard the referrer host is a breakdown dimension.

## Search keywords

When the referrer's host contains a key of `visits.search_engines`, the keyword is read from that query parameter of the referrer URL into `search_term` — first touch on the visitor, last touch on the session (a new session without a keyword inherits the visitor's).

```php
'search_engines' => [
    'google.' => 'q',
    'bing.com' => 'q',
    'duckduckgo.com' => 'q',
    'search.yahoo.com' => 'p',
    'yandex.' => 'text',
],
```

Most search engines send only their origin as the referrer today ("keyword not provided"), so this catches the remaining cases, not all organic traffic.
