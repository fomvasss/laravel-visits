<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Visitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an anonymous Visitor is attached to a real user on Login (see
 * MergeVisitorIdentity). Useful for merging pre-signup history (UTM, sessions) into a CRM
 * contact at exactly the moment identity becomes known.
 */
class VisitorIdentified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Visitor $visitor)
    {
    }
}
