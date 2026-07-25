<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Listeners;

use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Support\TokenResolver;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Off by default (config('visits.reset_identity_on_logout')) — resetting on every logout would
 * break attribution continuity on the typical single-user device. Opt in for shared/kiosk
 * devices. Session.user_id is never touched here — it's an immutable historical snapshot.
 */
class ResetVisitorIdentity
{
    public function __construct(
        private readonly Request $request,
        private readonly TokenResolver $tokenResolver,
    ) {
    }

    public function handle(Logout $event): void
    {
        if (! config('visits.enabled', true) || ! config('visits.reset_identity_on_logout', false)) {
            return;
        }

        $token = $this->tokenResolver->resolve($this->request);

        Visitor::withoutGlobalScope(WithoutBotsScope::class)
            ->where('token', $token)
            ->update(['user_type' => null, 'user_id' => null]);
    }
}
