<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Event as VisitEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for every recorded event (page_view or action). Host-side integrations (marketing
 * pixels — FB CAPI / GA / PostHog) subscribe to this instead of the package talking to those
 * services directly.
 */
class VisitRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly VisitEvent $event)
    {
    }
}
