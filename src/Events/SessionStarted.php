<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Session;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new Session is opened — not on every event within an already-open one. Useful
 * for "active sessions" counters/webhooks and returning-visitor reactivation hooks.
 */
class SessionStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Session $session)
    {
    }
}
