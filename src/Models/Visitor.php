<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Models;

use Fomvasss\Visits\Concerns\ExcludesBotsByDefault;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Visitor extends Model
{
    use ExcludesBotsByDefault;
    use HasFactory;

    protected $table = 'visit_visitors';

    protected $guarded = ['id'];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'extra_params' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_bot' => 'boolean',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'visitor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'visitor_id');
    }
}
