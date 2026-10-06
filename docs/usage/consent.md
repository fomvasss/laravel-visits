# Consent

Automatic page views can wait for consent. Implement the resolver:

```php
namespace App\Support;

use Fomvasss\Visits\Contracts\ConsentResolverInterface;
use Illuminate\Http\Request;

class CookieConsentResolver implements ConsentResolverInterface
{
    public function hasConsent(Request $request): bool
    {
        return $request->cookie('cookie_consent') === 'accepted';
    }
}
```

```php
// config/visits.php
'consent' => [
    'require_consent' => true,
    'resolver' => \App\Support\CookieConsentResolver::class,
],
```

The resolver is resolved from the container on every request `TrackVisit` would otherwise track. Without consent the request is passed through untouched: no job, no cookie.

> [!WARNING]
> Consent gates only `TrackVisit`. `POST /visits/collect`, `Visits::track()`, `Visits::identify()` and the `Login` listener are not checked — decide before calling them, and don't load the [JS beacon](js-beacon.md) until consent is given. With `require_consent` on and no `resolver`, `TrackVisit` tracks nothing.

A cookie read in `hasConsent()` must be readable by Laravel: a cookie set by a JavaScript consent banner is not encrypted, so add its name to the `EncryptCookies` exceptions:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['cookie_consent']);
})
```
