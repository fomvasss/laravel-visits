<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Controllers;

use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);
        $tenantId = (string) $request->input('tenant', '');
        $statDailyClass = ModelResolver::statDaily();

        $rows = $statDailyClass::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('tenant_id', $tenantId)
            ->get();

        $metrics = [StatDaily::METRIC_VISITORS, StatDaily::METRIC_SESSIONS, StatDaily::METRIC_PAGE_VIEWS, StatDaily::METRIC_CONVERSIONS];

        $totals = [];
        foreach ($metrics as $metric) {
            $totals[$metric] = (int) $rows->where('metric', $metric)->where('dimension', '')->sum('count');
        }

        // per-day series for the stat-tile sparklines — visit_stats_daily already carries this
        // granularity, index() used to throw it away by summing straight into $totals
        $trendDates = collect();
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $trendDates->push($cursor->toDateString());
        }

        $trends = [];
        foreach ($metrics as $metric) {
            $byDate = $rows->where('metric', $metric)->where('dimension', '')
                ->mapWithKeys(fn ($row) => [$row->date->toDateString() => (int) $row->count]);

            $trends[$metric] = $trendDates->map(fn ($d) => $byDate[$d] ?? 0)->values()->all();
        }

        // toggle between "sessions" (traffic volume by source) and "conversions" (which
        // source actually converts) — same 5 dimension panels, different underlying metric.
        // "action" only makes sense once metric=conversions (a session has no single action).
        $breakdownMetric = $request->input('breakdown_metric') === StatDaily::METRIC_CONVERSIONS
            ? StatDaily::METRIC_CONVERSIONS
            : StatDaily::METRIC_SESSIONS;

        $breakdownDimensions = ['utm_source', 'referrer_host', 'country_code', 'device_type', 'client_type'];

        if ($breakdownMetric === StatDaily::METRIC_CONVERSIONS) {
            $breakdownDimensions[] = 'action';
        }

        $breakdowns = [];
        foreach ($breakdownDimensions as $dimension) {
            $breakdowns[$dimension] = $rows->where('metric', $breakdownMetric)
                ->where('dimension', $dimension)
                ->groupBy('dimension_value')
                ->map(fn ($group) => $group->sum('count'))
                ->sortDesc()
                ->take(8);
        }

        $tenants = $statDailyClass::distinct()->orderBy('tenant_id')->pluck('tenant_id');

        // rollups exclude bots entirely, so this reads the raw table directly instead of
        // extending visit_stats_daily with a bot-specific metric just for one summary line.
        // Not tenant-scoped — Session has no tenant_id column of its own (only Visitor does),
        // and joining through visitor for this one supplementary stat isn't worth it.
        $sessionClass = ModelResolver::session();
        $rangeEnd = $to->copy()->endOfDay();
        $botSessions = (int) $sessionClass::onlyBots()->whereBetween('started_at', [$from, $rangeEnd])->count();
        $totalSessionsRaw = $botSessions + (int) $sessionClass::query()->whereBetween('started_at', [$from, $rangeEnd])->count();
        $botPercentage = $totalSessionsRaw > 0 ? round($botSessions / $totalSessionsRaw * 100, 1) : 0.0;

        return view('visits::dashboard.index', compact(
            'totals', 'trends', 'trendDates', 'breakdowns', 'breakdownMetric', 'from', 'to', 'tenantId', 'tenants',
            'botSessions', 'botPercentage'
        ));
    }

    public function sessions(Request $request): View
    {
        $sessionClass = ModelResolver::session();
        $query = $sessionClass::query()->with('visitor');

        if ($request->boolean('with_bots')) {
            $query->withBots();
        }

        if ($from = $request->input('from')) {
            $query->where('started_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->input('to')) {
            $query->where('started_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($visitorId = $request->input('visitor_id')) {
            $query->where('visitor_id', $visitorId);
        }

        foreach (['country_code', 'device_type', 'utm_source', 'ip'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        [$sort, $direction] = $this->resolveSort($request, [
            'started_at', 'page_views_count', 'duration_seconds', 'country_code', 'device_type', 'utm_source', 'referrer_host', 'ip',
        ], 'started_at');
        $query->orderBy($sort, $direction);

        // simplePaginate — no COUNT(*) query, matters once visit_sessions gets large
        $sessions = $query->simplePaginate((int) config('visits.dashboard.per_page', 50))->withQueryString();

        return view('visits::dashboard.sessions', compact('sessions', 'sort', 'direction'));
    }

    public function visitors(Request $request): View
    {
        $visitorClass = ModelResolver::visitor();
        // withCount respects the sessions() relation's own default scope (bots excluded) —
        // one correlated-subquery query, not N+1
        $query = $visitorClass::query()->with('user')->withCount('sessions');

        if ($request->boolean('with_bots')) {
            $query->withBots();
        }

        if ($request->boolean('returning_only')) {
            $query->has('sessions', '>', 1);
        }

        if ($from = $request->input('from')) {
            $query->where('first_seen_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->input('to')) {
            $query->where('first_seen_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($token = $request->input('token')) {
            $query->where('token', 'like', "%{$token}%");
        }

        foreach (['country_code', 'device_type', 'utm_source'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        [$sort, $direction] = $this->resolveSort($request, [
            'first_seen_at', 'last_seen_at', 'sessions_count', 'country_code', 'device_type', 'utm_source',
        ], 'last_seen_at');
        $query->orderBy($sort, $direction);

        // simplePaginate — no COUNT(*) query, matters once visit_visitors gets large
        $visitors = $query->simplePaginate((int) config('visits.dashboard.per_page', 50))->withQueryString();

        return view('visits::dashboard.visitors', compact('visitors', 'sort', 'direction'));
    }

    /**
     * @param  string[]  $sortable  whitelist — column names come straight from the query string
     * @return array{0: string, 1: string}
     */
    private function resolveSort(Request $request, array $sortable, string $default): array
    {
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : $default;
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return [$sort, $direction];
    }

    public function show(int $id): View
    {
        $sessionClass = ModelResolver::session();

        $session = $sessionClass::withoutGlobalScope(WithoutBotsScope::class)
            ->with(['visitor', 'user', 'events' => fn ($q) => $q->withBots()->oldest('created_at')])
            ->findOrFail($id);

        return view('visits::dashboard.show', compact('session'));
    }

    public function showVisitor(int $id): View
    {
        $visitorClass = ModelResolver::visitor();

        $visitor = $visitorClass::withoutGlobalScope(WithoutBotsScope::class)
            ->with(['user', 'sessions' => fn ($q) => $q->withBots()->latest('started_at')])
            ->findOrFail($id);

        return view('visits::dashboard.visitor', compact('visitor'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $to = $request->input('to') ? Carbon::parse($request->input('to')) : now();
        $from = $request->input('from') ? Carbon::parse($request->input('from')) : $to->copy()->subDays(6);

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
