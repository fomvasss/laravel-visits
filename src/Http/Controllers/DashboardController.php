<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Controllers;

use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\StatDaily;
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

        $rows = StatDaily::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('tenant_id', $tenantId)
            ->get();

        $totals = [];
        foreach ([StatDaily::METRIC_VISITORS, StatDaily::METRIC_SESSIONS, StatDaily::METRIC_PAGE_VIEWS, StatDaily::METRIC_CONVERSIONS] as $metric) {
            $totals[$metric] = (int) $rows->where('metric', $metric)->where('dimension', '')->sum('count');
        }

        $breakdowns = [];
        foreach (['utm_source', 'referrer_host', 'country_code', 'device_type'] as $dimension) {
            $breakdowns[$dimension] = $rows->where('metric', StatDaily::METRIC_SESSIONS)
                ->where('dimension', $dimension)
                ->groupBy('dimension_value')
                ->map(fn ($group) => $group->sum('count'))
                ->sortDesc()
                ->take(8);
        }

        $tenants = StatDaily::distinct()->orderBy('tenant_id')->pluck('tenant_id');

        return view('visits::dashboard.index', compact('totals', 'breakdowns', 'from', 'to', 'tenantId', 'tenants'));
    }

    public function sessions(Request $request): View
    {
        $query = Session::query()->with('visitor')->latest('started_at');

        if ($request->boolean('with_bots')) {
            $query->withBots();
        }

        if ($from = $request->input('from')) {
            $query->where('started_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->input('to')) {
            $query->where('started_at', '<=', Carbon::parse($to)->endOfDay());
        }

        foreach (['country_code', 'device_type', 'utm_source'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        $sessions = $query->paginate(50)->withQueryString();

        return view('visits::dashboard.sessions', compact('sessions'));
    }

    public function show(int $id): View
    {
        $session = Session::withoutGlobalScope(WithoutBotsScope::class)
            ->with(['visitor', 'events' => fn ($q) => $q->withBots()->oldest('created_at')])
            ->findOrFail($id);

        return view('visits::dashboard.show', compact('session'));
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
