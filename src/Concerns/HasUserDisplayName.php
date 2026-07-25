<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Concerns;

/**
 * The package only knows user_type/user_id (polymorphic — could be App\Models\User,
 * Admin, Client, anything the host uses), never the host's field naming convention.
 * Actual name resolution always goes through config('visits.user_display_resolver') — a
 * class implementing UserDisplayNameResolverInterface, defaulting to the package's own
 * DefaultUserDisplayNameResolver (name -> email -> null). Swap the config for anything else
 * (a different attribute, combining fields, calling a fullname()-style accessor, etc).
 */
trait HasUserDisplayName
{
    public function userDisplayName(): ?string
    {
        if (! $this->user_type) {
            return null;
        }

        // $this->user triggers a lazy-load if the relation wasn't eager-loaded — fine for a
        // single detail page, callers listing many rows should eager-load 'user' themselves
        $user = $this->user;

        if (! $user) {
            return null;
        }

        return app(config('visits.user_display_resolver'))->resolve($user);
    }
}
