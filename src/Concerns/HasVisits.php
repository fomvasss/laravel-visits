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

    public function latestVisitEvent(?string $action = null): MorphOne
    {
        return $this->morphOne(ModelResolver::event(), 'eventable')
            ->latestOfMany()
            ->when($action, fn ($query) => $query->where('action', $action));
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
