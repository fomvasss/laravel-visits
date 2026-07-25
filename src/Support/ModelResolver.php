<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Models\Visitor;

/**
 * Central place every relation/write-path resolves model classes through, so overriding a
 * model in config('visits.models') is picked up consistently everywhere — including relations
 * defined on the *other* models (e.g. Session::visitor() returns whatever the host configured
 * for 'visitor', not always the base Visitor class).
 */
class ModelResolver
{
    /**
     * @return class-string<Visitor>
     */
    public static function visitor(): string
    {
        return config('visits.models.visitor', Visitor::class);
    }

    /**
     * @return class-string<Session>
     */
    public static function session(): string
    {
        return config('visits.models.session', Session::class);
    }

    /**
     * @return class-string<Event>
     */
    public static function event(): string
    {
        return config('visits.models.event', Event::class);
    }

    /**
     * @return class-string<StatDaily>
     */
    public static function statDaily(): string
    {
        return config('visits.models.stat_daily', StatDaily::class);
    }
}
