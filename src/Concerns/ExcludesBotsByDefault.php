<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Concerns;

use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bots are always recorded (is_bot=true) — detection isn't 100% accurate and a false positive
 * on a real visitor shouldn't lose data. But they're excluded from reads by default, same idea
 * as SoftDeletes: use withBots() to opt back in (SEO crawler monitoring, debugging).
 */
trait ExcludesBotsByDefault
{
    public static function bootExcludesBotsByDefault(): void
    {
        static::addGlobalScope(new WithoutBotsScope());
    }

    public function scopeWithBots(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WithoutBotsScope::class);
    }

    public function scopeOnlyBots(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WithoutBotsScope::class)
            ->where($this->getTable() . '.is_bot', true);
    }
}
