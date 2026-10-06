# Custom models & multi-tenancy

## Overriding models

Every relation and write path resolves model classes through `Fomvasss\Visits\Support\ModelResolver`, which reads `visits.models`. An override is picked up everywhere — `RecordVisitJob`, the commands, the dashboard, `HasVisits` and the relations of the other models (`Session::visitor()` returns your visitor class).

```php
// config/visits.php
'models' => [
    'visitor' => \App\Models\Visitor::class,
    'session' => \Fomvasss\Visits\Models\Session::class,
    'event' => \Fomvasss\Visits\Models\Event::class,
    'stat_daily' => \Fomvasss\Visits\Models\StatDaily::class,
],
```

```php
namespace App\Models;

class Visitor extends \Fomvasss\Visits\Models\Visitor
{
    public function scopeFromCampaign($query, string $campaign)
    {
        return $query->where('utm_campaign', $campaign);
    }
}
```

Rules:

- Extend the package model. The job and the events type-hint the base classes.
- Keep the table name. `visits:aggregate`, `visits:seed-demo` and the Live feed query the `visit_*` tables by name.

## Multi-tenancy

`visit_visitors.tenant_id` and `visit_stats_daily.tenant_id` are plain strings defaulting to `''` (not `null`, so equality and unique keys work). The package never sets them — that is up to the app.

What uses `tenant_id`:

- `visits:aggregate` builds rollups separately for every distinct `tenant_id` found in `visit_visitors`; sessions and events count toward their visitor's tenant
- Overview and Campaigns accept `?tenant=...` and show a tenant selector once the rollups contain more than one tenant. Without the parameter they show tenant `''` — if every visitor gets a non-empty tenant and there is only one, the selector is hidden and the pages show zeros until you add `?tenant=...`

What does not: the Sessions and Visitors lists, the session map, Top pages, "online now", the bot summary and the Live page show all tenants. Sessions and events have no `tenant_id` column of their own.

`RecordVisitJob` creates visitors on the queue, where request-bound tenant helpers usually don't work. Derive the tenant from data the row already has — for a multi-domain app, the host of the landing URL:

```php
namespace App\Models;

class Visitor extends \Fomvasss\Visits\Models\Visitor
{
    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor) {
            $visitor->tenant_id = (string) parse_url((string) $visitor->first_landing_url, PHP_URL_HOST);
        });
    }
}
```

The `tenant_resolver` config key is reserved and not read by the package.

## User display name

Sessions and visitors link to any user model (`user_type`/`user_id` is polymorphic), so the dashboard asks a resolver how to display one. The default, `DefaultUserDisplayNameResolver`, returns `name`, then `email`, then `null`.

```php
namespace App\Support;

use Fomvasss\Visits\Contracts\UserDisplayNameResolverInterface;

class UserDisplayName implements UserDisplayNameResolverInterface
{
    public function resolve(mixed $user): ?string
    {
        return trim("{$user->first_name} {$user->last_name}") ?: $user->email;
    }
}
```

```php
// config/visits.php
'user_display_resolver' => \App\Support\UserDisplayName::class,
```

`Visitor::userDisplayName()` and `Session::userDisplayName()` call it; they lazy-load the `user` relation — eager-load `user` when listing many rows.
