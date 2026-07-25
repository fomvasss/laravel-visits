<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Event as VisitEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired in addition to VisitRecorded when the event is a custom action tied to an
 * eventable business model (Visits::track('order.placed', $order)).
 */
class ConversionRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly VisitEvent $event)
    {
    }
}
