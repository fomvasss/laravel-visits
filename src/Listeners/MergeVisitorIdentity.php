<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Listeners;

use Fomvasss\Visits\Support\TokenResolver;
use Fomvasss\Visits\Support\VisitorIdentityMerger;
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
 *
 * Thin wrapper around VisitorIdentityMerger — see that class for the actual merge, and for
 * VisitsManager::identify(), the non-Login-event equivalent for identity established without
 * an actual login (a guest checkout matching/creating a User by email or phone, for example).
 */
class MergeVisitorIdentity
{
    public function __construct(
        private readonly Request $request,
        private readonly TokenResolver $tokenResolver,
        private readonly VisitorIdentityMerger $merger,
    ) {
    }

    public function handle(Login $event): void
    {
        $this->merger->merge($event->user, $this->tokenResolver->resolve($this->request));
    }
}
