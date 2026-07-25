<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Models;

use Illuminate\Database\Eloquent\Model;

class StatDaily extends Model
{
    public const METRIC_SESSIONS = 'sessions';
    public const METRIC_VISITORS = 'visitors';
    public const METRIC_PAGE_VIEWS = 'page_views';
    public const METRIC_CONVERSIONS = 'conversions';

    public $timestamps = false;

    protected $table = 'visit_stats_daily';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
    ];
}
