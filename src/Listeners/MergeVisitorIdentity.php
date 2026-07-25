<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Listeners;

use Fomvasss\Visits\Events\VisitorIdentified;
use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Support\ModelResolver;
use Fomvasss\Visits\Support\TokenResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Replaces the IP-based "last visit from this IP -> this user_id" guess from the legacy
 * projects with an explicit merge: Visitor.user_id is mutable "current known identity"
 * (updated on every login), Session.user_id is an immutable snapshot set once — accurate
 * historical attribution even if the same browser is later used by someone else.
 *
 * Synchronous and idempotent: cheap indexed updates, no queue needed. Prior anonymous
 * history becomes "the user's" automatically through visitor_id, no separate merge step.
 */
class MergeVisitorIdentity
{
    public function __construct(
        private readonly Request $request,
        private readonly TokenResolver $tokenResolver,
    ) {
    }

    public function handle(Login $event): void
    {
        if (! config('visits.enabled', true)) {
            return;
        }

        $token = $this->tokenResolver->resolve($this->request);
        $visitorClass = ModelResolver::visitor();

        $visitor = $visitorClass::withoutGlobalScope(WithoutBotsScope::class)
            ->where('token', $token)
            ->first();

        if (! $visitor) {
            return;
        }

        $visitor->update([
            'user_type' => $event->user::class,
            'user_id' => $event->user->getAuthIdentifier(),
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
                'user_type' => $event->user::class,
                'user_id' => $event->user->getAuthIdentifier(),
            ]);
    }
}
