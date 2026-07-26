<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Fomvasss\Visits\Events\VisitorIdentified;
use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by MergeVisitorIdentity (Laravel's own Login event — a real authentication just
 * happened) and VisitsManager::identify() (called directly, for cases that establish who
 * someone is without an actual login: a guest checkout matching/creating a User by email or
 * phone, for example). Deliberately not itself tied to the Login event — dispatching Login for
 * something that isn't one would be misleading to any other Login listener added later (a
 * security/fraud-detection hook, "new login" notifications, ...), which is exactly the
 * confusion this class exists to avoid.
 */
class VisitorIdentityMerger
{
    public function merge(Authenticatable $user, string $token): void
    {
        if (! config('visits.enabled', true)) {
            return;
        }

        $visitorClass = ModelResolver::visitor();

        $visitor = $visitorClass::withoutGlobalScope(WithoutBotsScope::class)
            ->where('token', $token)
            ->first();

        if (! $visitor) {
            return;
        }

        // getMorphClass() (not ::class) — respects the host's Relation::morphMap() alias when
        // registered, so HasVisits::visitorProfiles() (morphMany, filters by its own
        // getMorphClass()) actually matches what gets written here.
        $userType = $user instanceof Model ? $user->getMorphClass() : $user::class;

        $visitor->update([
            'user_type' => $userType,
            'user_id' => $user->getAuthIdentifier(),
        ]);

        VisitorIdentified::dispatch($visitor);

        $timeoutMinutes = (int) config('visits.session_timeout_minutes', 30);
        $sessionClass = ModelResolver::session();

        $sessionClass::withoutGlobalScope(WithoutBotsScope::class)
            ->where('visitor_id', $visitor->id)
            ->whereNull('ended_at')
            ->whereNull('user_id')
            ->where('last_activity_at', '>=', now()->subMinutes($timeoutMinutes))
            ->latest('last_activity_at')
            ->first()
            ?->update([
                'user_type' => $userType,
                'user_id' => $user->getAuthIdentifier(),
            ]);
    }
}
