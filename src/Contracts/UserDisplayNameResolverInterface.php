<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Contracts;

/**
 * Full control over how an identified user is displayed on the dashboard — combine fields
 * ("Name <email>"), call a fullname()-style accessor, whatever the host's User model needs.
 * Registered as a class name in config('visits.user_display_resolver') — a closure would work
 * at runtime too, but can't survive `php artisan config:cache` (Closures aren't serializable),
 * same reason tenant_resolver/consent.resolver in this package are class-strings, not callables.
 */
interface UserDisplayNameResolverInterface
{
    public function resolve(mixed $user): ?string;
}
