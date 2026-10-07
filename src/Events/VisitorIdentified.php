<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Visitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an anonymous Visitor is attached to a real user: on Login and identify() (see
 * VisitorIdentityMerger), and by RecordVisitJob when an authenticated request links a visitor
 * that had no user yet — e.g. the login happened before the visitor row existed. Useful for
 * merging pre-signup history (UTM, sessions) into a CRM contact once identity becomes known.
 */
class VisitorIdentified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Visitor $visitor)
    {
    }
}
