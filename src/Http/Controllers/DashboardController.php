<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Controllers;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\StatDaily;
use Fomvasss\Visits\Support\ModelResolver;
use Fomvasss\Visits\VisitsManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function live(): View
    {
        return view('visits::dashboard.live');
    }

    /**
     * Polled by the /visits/live page (transport=poll) — recently processed events with a known
     * location, newest first. `since` is an echo of the newest event's timestamp (or the
     * request's own `since` if nothing new came in), the client feeds it back as the next poll's
     * cursor.
     */
    public function liveFeed(Request $request): JsonResponse
    {
        $since = $this->resolveSince($request->input('since'));
        $events = $this->liveEventsSince($since);

        return response()->json([
            'events' => $events,
            'since' => $events->first()['created_at'] ?? $since->toIso8601String(),
        ]);
    }

    /**
     * Server-Sent Events alternative to liveFeed (transport=sse) — one held-open connection,
     * server pushes a new `data:` frame whenever it finds events instead of the client
     * re-requesting on a timer. Bounded to sse_max_duration seconds so a single connection
     * doesn't tie up a PHP-FPM worker indefinitely; the browser's EventSource reconnects
     * automatically once the server closes it.
     */
    public function liveStream(Request $request): StreamedResponse
    {
        $since = $this->resolveSince($request->input('since'));

        return response()->stream(function () use ($since) {
            $cursor = $since;
            $deadline = now()->addSeconds((int) config('visits.live.sse_max_duration', 300));
            $checkInterval = max(1, (int) config('visits.live.sse_check_interval', 2));

            while (now()->lt($deadline)) {
                if (connection_aborted()) {
                    return;
                }

                $events = $this->liveEventsSince($cursor);

                if ($events->isNotEmpty()) {
                    $cursor = Carbon::parse($events->first()['created_at']);
                    echo 'data: ' . json_encode(['events' => $events, 'since' => $cursor->toIso8601String()]) . "\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                sleep($checkInterval);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            // nginx buffers proxied responses by default, which would hold every frame until
            // the connection closes — defeating the whole point of a push. This header
            // (nginx-specific, harmless elsewhere) tells it not to.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function resolveSince(mixed $rawSince): Carbon
    {
        // an unparseable `since` (e.g. a client sending a malformed value) degrades to the
        // default lookback rather than a 500 — this is called automatically every few seconds,
        // it should never break the page.
        if ($rawSince) {
            try {
                return Carbon::parse($rawSince);
            } catch (\Exception) {
                // fall through to the default below
            }
        }

        return now()->subSeconds(30);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function liveEventsSince(Carbon $since): Collection
    {
        $eventClass = ModelResolver::event();

        return $eventClass::query()
            ->join('visit_sessions as s', 's.id', '=', 'visit_events.session_id')
            ->whereNotNull('s.lat')
            ->whereNotNull('s.lng')
            ->where('visit_events.created_at', '>', $since)
            ->orderByDesc('visit_events.created_at')
            ->limit((int) config('visits.live.feed_limit', 50))
            ->get(['visit_events.session_id', 'visit_events.type', 'visit_events.name', 'visit_events.created_at', 's.lat', 's.lng', 's.city', 's.country_code'])
            ->map(fn ($row) => [
                'session_id' => $row->session_id,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'city' => $row->city,
                'country_code' => $row->country_code,
                'type' => $row->type,
                'name' => $row->name,
                'created_at' => $row->created_at->toIso8601String(),
            ]);
    }

    public function whoami(Request $request, VisitsManager $visits): View
    {
        $requestedIp = $request->input('ip');
        $ipError = null;
        $ip = null;

        if (is_string($requestedIp) && $requestedIp !== '') {
            if (filter_var($requestedIp, FILTER_VALIDATE_IP)) {
                $ip = $requestedIp;
            } else {
                $ipError = "\"{$requestedIp}\" is not a valid IP address.";
            }
        }

        $data = $visits->whoami($request, $ip);

        return view('visits::dashboard.whoami', compact('data', 'requestedIp', 'ipError'));
    }

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
        // "name" (the conversion event's name) only makes sense once metric=conversions (a
        // session has no single event name of its own).
        $breakdownMetric = $request->input('breakdown_metric') === StatDaily::METRIC_CONVERSIONS
            ? StatDaily::METRIC_CONVERSIONS
            : StatDaily::METRIC_SESSIONS;

        $breakdownDimensions = ['utm_source', 'referrer_host', 'country_code', 'device_type', 'client_type'];

        if ($breakdownMetric === StatDaily::METRIC_CONVERSIONS) {
            $breakdownDimensions[] = 'name';
        }

        $breakdowns = $this->computeBreakdowns($rows, $breakdownMetric, $breakdownDimensions);

        $tenants = $statDailyClass::distinct()->orderBy('tenant_id')->pluck('tenant_id');

        // rollups exclude bots entirely, so this reads the raw table directly instead of
        // extending visit_stats_daily with a bot-specific metric just for one summary line.
        // Not tenant-scoped — Session has no tenant_id column of its own (only Visitor does),
        // and joining through visitor for this one supplementary stat isn't worth it.
        $sessionClass = ModelResolver::session();
        $rangeEnd = $to->copy()->endOfDay();

        // "online now" — a live snapshot, independent of the selected ?from=/?to= range on
        // purpose (it answers "right now", not "during the filtered period").
        $onlineWindow = (int) config('visits.dashboard.online_window_minutes', 5);
        $onlineNow = (int) $sessionClass::query()
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', now()->subMinutes($onlineWindow))
            ->count();

        $botSessions = (int) $sessionClass::onlyBots()->whereBetween('started_at', [$from, $rangeEnd])->count();
        $totalSessionsRaw = $botSessions + (int) $sessionClass::query()->whereBetween('started_at', [$from, $rangeEnd])->count();
        $botPercentage = $totalSessionsRaw > 0 ? round($botSessions / $totalSessionsRaw * 100, 1) : 0.0;

        // point map — not aggregated through visit_stats_daily (lat/lng isn't a rollup
        // dimension), reads raw sessions directly like the bot summary above. Capped and
        // latest-first so a busy site doesn't try to render thousands of markers at once.
        $mapMarkers = $sessionClass::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('started_at', [$from, $rangeEnd])
            ->latest('started_at')
            ->limit((int) config('visits.dashboard.map_marker_limit', 300))
            ->get(['lat', 'lng', 'city', 'country_code', 'started_at'])
            ->map(fn ($session) => [
                'lat' => (float) $session->lat,
                'lng' => (float) $session->lng,
                'city' => $session->city,
                'country_code' => $session->country_code,
                'started_at' => $session->started_at?->toDateTimeString(),
            ])
            ->values();

        // most-visited pages — like the map/bot summary above, not worth a visit_stats_daily
        // dimension: url is unbounded cardinality, a daily rollup row per unique URL would
        // dwarf every other dimension combined. Read raw events directly instead, capped.
        $eventClass = ModelResolver::event();
        $topPages = $eventClass::query()
            ->where('type', Event::TYPE_PAGE_VIEW)
            ->whereNotNull('url')
            ->whereBetween('created_at', [$from, $rangeEnd])
            ->select(['url', DB::raw('count(*) as cnt')])
            ->groupBy('url')
            ->orderByDesc('cnt')
            ->limit((int) config('visits.dashboard.top_pages_limit', 10))
            ->get()
            ->map(fn ($row) => ['url' => $row->url, 'count' => (int) $row->cnt])
            ->values();

        return view('visits::dashboard.index', compact(
            'totals', 'trends', 'trendDates', 'breakdowns', 'breakdownMetric', 'from', 'to', 'tenantId', 'tenants',
            'botSessions', 'botPercentage', 'mapMarkers', 'topPages', 'onlineNow', 'onlineWindow'
        ));
    }

    /**
     * All UTM/ref dimensions in one place — index() only surfaces utm_source alongside
     * unrelated dimensions (country/device/etc), this is for drilling into campaign
     * attribution specifically (utm_medium, utm_campaign, utm_term, utm_content, ref).
     */
    public function campaigns(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);
        $tenantId = (string) $request->input('tenant', '');
        $statDailyClass = ModelResolver::statDaily();

        $rows = $statDailyClass::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('tenant_id', $tenantId)
            ->get();

        $breakdownMetric = $request->input('breakdown_metric') === StatDaily::METRIC_CONVERSIONS
            ? StatDaily::METRIC_CONVERSIONS
            : StatDaily::METRIC_SESSIONS;

        $breakdownDimensions = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'];

        $breakdowns = $this->computeBreakdowns($rows, $breakdownMetric, $breakdownDimensions);

        $tenants = $statDailyClass::distinct()->orderBy('tenant_id')->pluck('tenant_id');

        return view('visits::dashboard.campaigns', compact('breakdowns', 'breakdownMetric', 'from', 'to', 'tenantId', 'tenants'));
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
        $days = max(1, (int) config('visits.dashboard.default_range_days', 30));

        $to = $request->input('to') ? Carbon::parse($request->input('to')) : now();
        $from = $request->input('from') ? Carbon::parse($request->input('from')) : $to->copy()->subDays($days - 1);

        return [$from->startOfDay(), $to->startOfDay()];
    }

    /**
     * @param  Collection<int, StatDaily>  $rows
     * @param  string[]  $dimensions
     * @return array<string, Collection<string, int>>
     */
    private function computeBreakdowns(Collection $rows, string $breakdownMetric, array $dimensions): array
    {
        $breakdowns = [];

        foreach ($dimensions as $dimension) {
            $breakdowns[$dimension] = $rows->where('metric', $breakdownMetric)
                ->where('dimension', $dimension)
                ->groupBy('dimension_value')
                ->map(fn ($group) => $group->sum('count'))
                ->sortDesc()
                ->take(8);
        }

        return $breakdowns;
    }
}
