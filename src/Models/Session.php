<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Models;

use Fomvasss\Visits\Concerns\ExcludesBotsByDefault;
use Fomvasss\Visits\Database\Factories\SessionFactory;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Session extends Model
{
    use ExcludesBotsByDefault;
    use HasFactory;

    protected $table = 'visit_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'extra_params' => 'array',
        'device_meta' => 'array',
        'geo_meta' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'page_views_count' => 'integer',
        'duration_seconds' => 'integer',
        'is_bot' => 'boolean',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::visitor(), 'visitor_id');
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ModelResolver::event(), 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    protected static function newFactory(): SessionFactory
    {
        return SessionFactory::new();
    }
}
