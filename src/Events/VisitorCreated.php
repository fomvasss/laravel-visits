<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Events;

use Fomvasss\Visits\Models\Visitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, the first time a given visitor token is ever seen (a brand new Visitor row).
 * Useful for "new unique visitor" hooks — CRM sync, first-touch attribution capture — that
 * would otherwise have to infer novelty themselves from VisitRecorded.
 *
 * Fires regardless of $visitor->is_bot, same as VisitRecorded/ConversionRecorded — check it
 * yourself if a listener should skip bot traffic.
 */
class VisitorCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Visitor $visitor)
    {
    }
}
