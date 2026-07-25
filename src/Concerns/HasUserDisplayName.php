<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Concerns;

/**
 * The package only knows user_type/user_id (polymorphic — could be App\Models\User,
 * Admin, Client, anything the host uses), never the host's field naming convention.
 *
 * Two levels of override, cheapest first:
 * - config('visits.user_display_attribute') — a single attribute/accessor name (default
 *   'name'), falls back to `email`, then null.
 * - config('visits.user_display_resolver') — a class implementing
 *   UserDisplayNameResolverInterface, for anything the single-attribute path can't express
 *   (combine fields, call a fullname()-style accessor explicitly, etc).
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

        if ($resolverClass = config('visits.user_display_resolver')) {
            return app($resolverClass)->resolve($user);
        }

        $attribute = config('visits.user_display_attribute', 'name');

        return $user->{$attribute} ?? $user->email ?? null;
    }
}
