<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Concerns;

use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Apply to any host model tracked via Visits::track('some.action', $model) — Order, Lead,
 * Comment, User, etc. Method name latestVisit()-style kept familiar from the dropshop/greespi
 * HasVisits trait this replaces, just pointed at the new three-tier structure instead of a
 * flat Visit row.
 */
trait HasVisits
{
    public function visitEvents(): MorphMany
    {
        return $this->morphMany(ModelResolver::event(), 'eventable');
    }

    /**
     * A plain ->latestOfMany()->where('name', ...) would filter the *outer* query only — the
     * ofMany subquery (which picks "the latest row per parent") is built independently and
     * knows nothing about that where, so it would keep resolving to the globally-latest event
     * and then discard it if that one doesn't match $name, even when an older matching event
     * exists. Passing the constraint into ofMany's own closure form applies it to the subquery
     * itself, so "latest" is computed among matching rows, not globally.
     */
    public function latestVisitEvent(?string $name = null): MorphOne
    {
        return $this->morphOne(ModelResolver::event(), 'eventable')
            ->ofMany(['created_at' => 'max'], function ($query) use ($name) {
                if ($name) {
                    $query->where('name', $name);
                }
            });
    }

    /**
     * Only meaningful on the host's User model(s) — every Visitor row ever linked to this
     * person via Visitor.user_id/user_type (set on Login, see MergeVisitorIdentity), across
     * all their devices/browsers. Full cross-device history, not a single conversion snapshot.
     */
    public function visitorProfiles(): MorphMany
    {
        return $this->morphMany(ModelResolver::visitor(), 'user');
    }
}
