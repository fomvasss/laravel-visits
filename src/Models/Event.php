<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Models;

use Fomvasss\Visits\Concerns\ExcludesBotsByDefault;
use Fomvasss\Visits\Database\Factories\EventFactory;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Event extends Model
{
    use ExcludesBotsByDefault;
    use HasFactory;

    public const TYPE_PAGE_VIEW = 'page_view';
    public const TYPE_ACTION = 'action';

    public const UPDATED_AT = null;

    protected $table = 'visit_events';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'meta' => 'array',
        'is_bot' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::session(), 'session_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::visitor(), 'visitor_id');
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }
}
