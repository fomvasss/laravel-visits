<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Console;

use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\StatDaily;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes visit_stats_daily for one or more days: delete existing rows for
 * (date, tenant_id) then bulk-insert freshly computed ones — idempotent, sidesteps the
 * NULL-uniqueness problem a real upsert would hit (tenant_id/dimension default to '', not NULL).
 *
 * Scheduled: every 5 min for --date=today (near-live dashboard, ~5 min lag), once after
 * midnight for --date=yesterday (final, after visits:close-stale-sessions has run).
 */
class AggregateCommand extends Command
{
    protected $signature = 'visits:aggregate {--date=} {--from=} {--to=}';

    protected $description = 'Recompute visit_stats_daily rollups for the given date(s)';

    public function handle(): int
    {
        foreach ($this->resolveDates() as $date) {
            $this->aggregateDate($date);
            $this->info("Aggregated {$date->toDateString()}");
        }

        return self::SUCCESS;
    }

    /**
     * @return Carbon[]
     */
    private function resolveDates(): array
    {
        if ($from = $this->option('from')) {
            $cursor = Carbon::parse($from)->startOfDay();
            $to = Carbon::parse((string) ($this->option('to') ?? $from))->startOfDay();
            $dates = [];

            while ($cursor->lte($to)) {
                $dates[] = $cursor->copy();
                $cursor->addDay();
            }

            return $dates;
        }

        $date = match ($this->option('date')) {
            null, 'today' => now(),
            'yesterday' => now()->subDay(),
            default => Carbon::parse((string) $this->option('date')),
        };

        return [$date->startOfDay()];
    }

    private function aggregateDate(Carbon $date): void
    {
        $tenantIds = DB::table('visit_visitors')->distinct()->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            $tenantIds = collect(['']);
        }

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;

            DB::transaction(function () use ($date, $tenantId) {
                DB::table('visit_stats_daily')
                    ->where('date', $date->toDateString())
                    ->where('tenant_id', $tenantId)
                    ->delete();

                $rows = $this->computeRows($date, $tenantId);

                if ($rows !== []) {
                    DB::table('visit_stats_daily')->insert($rows);
                }
            });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeRows(Carbon $date, string $tenantId): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $rows = [];

        $visitorsQuery = DB::table('visit_visitors')
            ->where('is_bot', false)
            ->where('tenant_id', $tenantId)
            ->whereBetween('first_seen_at', [$start, $end]);

        $rows = array_merge($rows, $this->rowsFor(StatDaily::METRIC_VISITORS, $visitorsQuery, [
            'utm_source' => 'utm_source',
            'utm_medium' => 'utm_medium',
            'utm_campaign' => 'utm_campaign',
            'referrer_host' => 'first_referrer_host',
            'country_code' => 'country_code',
            'device_type' => 'device_type',
            'browser' => 'browser',
            'client_type' => 'client_type',
        ], $date, $tenantId));

        $sessionsQuery = DB::table('visit_sessions as s')
            ->join('visit_visitors as v', 'v.id', '=', 's.visitor_id')
            ->where('s.is_bot', false)
            ->where('v.tenant_id', $tenantId)
            ->whereBetween('s.started_at', [$start, $end]);

        $sessionColumnMap = [
            'utm_source' => 's.utm_source',
            'utm_medium' => 's.utm_medium',
            'utm_campaign' => 's.utm_campaign',
            'referrer_host' => 's.referrer_host',
            'country_code' => 's.country_code',
            'device_type' => 's.device_type',
            'browser' => 's.browser',
            'client_type' => 's.client_type',
        ];

        $rows = array_merge($rows, $this->rowsFor(StatDaily::METRIC_SESSIONS, $sessionsQuery, $sessionColumnMap, $date, $tenantId));

        $eventsBase = DB::table('visit_events as e')
            ->join('visit_visitors as v', 'v.id', '=', 'e.visitor_id')
            ->leftJoin('visit_sessions as s', 's.id', '=', 'e.session_id')
            ->where('e.is_bot', false)
            ->where('v.tenant_id', $tenantId)
            ->whereBetween('e.created_at', [$start, $end]);

        $eventColumnMap = $sessionColumnMap + ['action' => 'e.action'];

        $pageViewsQuery = (clone $eventsBase)->where('e.type', Event::TYPE_PAGE_VIEW);
        $rows = array_merge($rows, $this->rowsFor(StatDaily::METRIC_PAGE_VIEWS, $pageViewsQuery, $eventColumnMap, $date, $tenantId));

        $conversionsQuery = (clone $eventsBase)->where('e.type', Event::TYPE_ACTION);
        $rows = array_merge($rows, $this->rowsFor(StatDaily::METRIC_CONVERSIONS, $conversionsQuery, $eventColumnMap, $date, $tenantId));

        return $rows;
    }

    /**
     * @param  array<string, string>  $columnMap  dimension name => qualified column
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(string $metric, Builder $baseQuery, array $columnMap, Carbon $date, string $tenantId): array
    {
        $dateStr = $date->toDateString();
        $rows = [];

        $rows[] = [
            'date' => $dateStr,
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'dimension' => '',
            'dimension_value' => '',
            'count' => (clone $baseQuery)->count(),
        ];

        foreach ((array) config('visits.aggregate.dimensions', []) as $dimension) {
            $column = $columnMap[$dimension] ?? null;

            if (! $column) {
                continue;
            }

            $grouped = (clone $baseQuery)
                ->select([$column . ' as dimension_value', DB::raw('count(*) as cnt')])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->get();

            foreach ($grouped as $row) {
                $rows[] = [
                    'date' => $dateStr,
                    'tenant_id' => $tenantId,
                    'metric' => $metric,
                    'dimension' => $dimension,
                    'dimension_value' => (string) $row->dimension_value,
                    'count' => (int) $row->cnt,
                ];
            }
        }

        return $rows;
    }
}
