<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support\Resolvers;

use Fomvasss\Visits\Contracts\UserDisplayNameResolverInterface;

/**
 * Ships as the out-of-the-box config('visits.user_display_resolver') default — tries the
 * `name` attribute, falls back to `email`, then null. Swap the config to your own class
 * implementing UserDisplayNameResolverInterface for anything this can't express (a different
 * single attribute, combining fields, calling a fullname()-style accessor, etc).
 */
class DefaultUserDisplayNameResolver implements UserDisplayNameResolverInterface
{
    public function resolve(mixed $user): ?string
    {
        return $user->name ?? $user->email ?? null;
    }
}
