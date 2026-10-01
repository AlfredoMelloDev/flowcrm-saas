<?php

namespace App\Actions\Reports;

use App\Enums\OpportunityStage;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildReportsSummary
{
    private const DEFAULT_RANGE_DAYS = 30;

    /**
     * Stages that count as "still in play" — mirrors BuildDashboardSummary's
     * OPEN_STAGES, duplicated rather than shared: each Action is
     * self-contained, and this list is small and unlikely to drift.
     */
    private const OPEN_STAGES = [
        OpportunityStage::New,
        OpportunityStage::Contacted,
        OpportunityStage::Proposal,
        OpportunityStage::Negotiation,
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, ?string $userId, ?string $dateFrom, ?string $dateTo): array
    {
        // Seller can never use user_id to look at someone else's data — the
        // FormRequest already rejects it with "prohibited", this is a second,
        // independent enforcement directly in the Action (defense in depth,
        // same reasoning as the CHECK constraint backing up FormRequest
        // validation elsewhere in this app).
        if ($user->isSeller()) {
            $userId = null;
        }

        // Plain calendar dates, turned into a start-of-day/end-of-day range
        // in the application's own timezone (UTC, config('app.timezone')) —
        // never the client's timezone, the same "server clock only" rule
        // already used by Activity's overdue/window logic. Default: the last
        // 30 calendar days, today included.
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $from = $dateFrom
            ? Carbon::parse($dateFrom)->startOfDay()
            : Carbon::now()->subDays(self::DEFAULT_RANGE_DAYS - 1)->startOfDay();

        $leadsCreatedSeries = $this->zeroFilledSeries($this->leadsCreatedByDay($user, $userId, $from, $to), $from, $to);
        $leadsCreatedTotal = array_sum(array_column($leadsCreatedSeries, 'count'));

        $leadsConvertedSeries = $this->zeroFilledSeries($this->leadsConvertedByDay($user, $userId, $from, $to), $from, $to);
        $leadsConvertedTotal = array_sum(array_column($leadsConvertedSeries, 'count'));

        $opportunitiesCreatedSeries = $this->zeroFilledSeries(
            $this->opportunitiesCreatedByDay($user, $userId, $from, $to), $from, $to
        );
        $opportunitiesCreatedTotal = array_sum(array_column($opportunitiesCreatedSeries, 'count'));

        $closedByDayAndStage = $this->closedByDayAndStage($user, $userId, $from, $to);
        $wonSeries = $this->zeroFilledSeries($closedByDayAndStage->get('won', collect()), $from, $to);
        $lostSeries = $this->zeroFilledSeries($closedByDayAndStage->get('lost', collect()), $from, $to);
        $opportunitiesWonTotal = array_sum(array_column($wonSeries, 'count'));
        $opportunitiesLostTotal = array_sum(array_column($lostSeries, 'count'));

        $closedValues = $this->closedValueTotals($user, $userId, $from, $to);

        $activitiesCreatedSeries = $this->zeroFilledSeries(
            $this->activitiesCreatedByDay($user, $userId, $from, $to), $from, $to
        );
        $activitiesCreatedTotal = array_sum(array_column($activitiesCreatedSeries, 'count'));

        $activitiesCompletedSeries = $this->zeroFilledSeries(
            $this->activitiesCompletedByDay($user, $userId, $from, $to), $from, $to
        );
        $activitiesCompletedTotal = array_sum(array_column($activitiesCompletedSeries, 'count'));

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'timezone' => config('app.timezone'),
            ],
            'leads_created_total' => $leadsCreatedTotal,
            'leads_converted_total' => $leadsConvertedTotal,
            // Ratio of two independently-windowed counts (converted_at vs.
            // created_at, both within the same period) — not a true cohort
            // rate (a lead created near the end of the period may convert
            // after it). Same approximation BuildDashboardSummary already
            // uses for its own (unwindowed) lead_conversion_rate.
            'lead_conversion_rate' => $leadsCreatedTotal > 0
                ? round(($leadsConvertedTotal / $leadsCreatedTotal) * 100, 1)
                : 0,
            'avg_conversion_time_hours' => $this->avgConversionTimeHours($user, $userId, $from, $to),
            'opportunities_created_total' => $opportunitiesCreatedTotal,
            'opportunities_won_total' => $opportunitiesWonTotal,
            'opportunities_lost_total' => $opportunitiesLostTotal,
            'value_won' => $closedValues['won'],
            'value_lost' => $closedValues['lost'],
            'avg_closing_time_hours' => $this->avgClosingTimeHours($user, $userId, $from, $to),
            'lost_reasons' => $this->lostReasons($user, $userId, $from, $to),
            'activities_created_total' => $activitiesCreatedTotal,
            'activities_completed_total' => $activitiesCompletedTotal,
            'activities_completed_by_type' => $this->activitiesCompletedByType($user, $userId, $from, $to),
            'daily_series' => [
                'leads_created' => $leadsCreatedSeries,
                'leads_converted' => $leadsConvertedSeries,
                'opportunities_won' => $wonSeries,
                'opportunities_lost' => $lostSeries,
                'activities_completed' => $activitiesCompletedSeries,
            ],
            'performance_by_user' => $this->performanceByUser($user, $userId, $from, $to),
            'pipeline_snapshot' => $this->pipelineSnapshot($user, $userId),
        ];
    }

    /**
     * Same ownership scoping used throughout the app's other controllers
     * (e.g. ActivityController::scopeToOwnership): Seller is always locked
     * to their own records; Admin/Manager get an optional explicit filter.
     */
    private function scopeOwnership(Builder $query, User $user, ?string $userId, string $column = 'user_id'): void
    {
        if ($user->isSeller()) {
            $query->where($column, $user->id);
        } elseif ($userId) {
            $query->where($column, $userId);
        }
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function zeroFilledSeries(Collection $countsByDay, Carbon $from, Carbon $to): array
    {
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());

        return collect($period)->map(function (Carbon $date) use ($countsByDay) {
            $key = $date->toDateString();

            return ['date' => $key, 'count' => (int) ($countsByDay[$key] ?? 0)];
        })->values()->all();
    }

    private function leadsCreatedByDay(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = Lead::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate_count')
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');
    }

    private function leadsConvertedByDay(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = LeadConversion::query()->join('leads', 'leads.id', '=', 'lead_conversions.lead_id');
        $this->scopeOwnership($query, $user, $userId, 'leads.user_id');

        return $query->whereBetween('lead_conversions.converted_at', [$from, $to])
            ->selectRaw('DATE(lead_conversions.converted_at) as day, COUNT(*) as aggregate_count')
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');
    }

    /**
     * Averaged in PHP (via Carbon), not SQL: TIMESTAMPDIFF is MySQL-only and
     * this app's test suite runs against SQLite (phpunit.xml), so any
     * database-specific date-diff function would pass in production but
     * fail every test. averageHoursBetween() streams the two raw timestamps
     * per row via a cursor rather than loading the whole result set (or
     * hydrating Eloquent models for it) into memory at once — the period
     * filter bounds this in practice, but nothing caps how wide a period can
     * be requested, so this stays correct even for a very large range.
     */
    private function avgConversionTimeHours(User $user, ?string $userId, Carbon $from, Carbon $to): ?float
    {
        $query = LeadConversion::query()->join('leads', 'leads.id', '=', 'lead_conversions.lead_id');
        $this->scopeOwnership($query, $user, $userId, 'leads.user_id');

        $query->whereBetween('lead_conversions.converted_at', [$from, $to])
            ->select('leads.created_at as started_at', 'lead_conversions.converted_at as ended_at');

        return $this->averageHoursBetween($query);
    }

    private function opportunitiesCreatedByDay(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate_count')
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');
    }

    /**
     * @return Collection<string, Collection<string, int>> keyed by "won"/"lost", each a day => count map
     */
    private function closedByDayAndStage(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        $rows = $query->whereIn('stage', [OpportunityStage::Won, OpportunityStage::Lost])
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw('DATE(closed_at) as day, stage as stage_value, COUNT(*) as aggregate_count')
            ->groupBy('day', 'stage_value')
            ->get();

        return $rows->groupBy('stage_value')
            ->map(fn (Collection $group) => $group->pluck('aggregate_count', 'day'));
    }

    /**
     * @return array{won: string, lost: string}
     */
    private function closedValueTotals(User $user, ?string $userId, Carbon $from, Carbon $to): array
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        $rows = $query->whereIn('stage', [OpportunityStage::Won, OpportunityStage::Lost])
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw(
                'stage as stage_value, '.
                "CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as aggregate_value"
            )
            ->groupBy('stage_value')
            ->pluck('aggregate_value', 'stage_value');

        return [
            'won' => $this->formatMoney($rows->get(OpportunityStage::Won->value, '0')),
            'lost' => $this->formatMoney($rows->get(OpportunityStage::Lost->value, '0')),
        ];
    }

    private function avgClosingTimeHours(User $user, ?string $userId, Carbon $from, Carbon $to): ?float
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        $query->whereIn('stage', [OpportunityStage::Won, OpportunityStage::Lost])
            ->whereBetween('closed_at', [$from, $to])
            ->select('created_at as started_at', 'closed_at as ended_at');

        return $this->averageHoursBetween($query);
    }

    /**
     * Streams rows via a cursor (lazy, one DB row at a time) through the
     * plain query builder (->toBase(), no Eloquent model hydration) — the
     * two call sites above only ever need two raw datetime strings per row.
     */
    private function averageHoursBetween(Builder $query): ?float
    {
        $count = 0;
        $totalSeconds = 0;

        foreach ($query->toBase()->cursor() as $row) {
            $totalSeconds += Carbon::parse($row->started_at)->diffInSeconds(Carbon::parse($row->ended_at));
            $count++;
        }

        return $count > 0 ? round(($totalSeconds / $count) / 3600, 1) : null;
    }

    /**
     * @return array<int, array{reason: string, count: int}>
     */
    private function lostReasons(User $user, ?string $userId, Carbon $from, Carbon $to): array
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->where('stage', OpportunityStage::Lost)
            ->whereBetween('closed_at', [$from, $to])
            ->whereNotNull('lost_reason')
            ->selectRaw('lost_reason as reason_value, COUNT(*) as aggregate_count')
            ->groupBy('reason_value')
            ->orderByDesc('aggregate_count')
            ->get()
            ->map(fn ($row) => ['reason' => $row->reason_value, 'count' => (int) $row->aggregate_count])
            ->all();
    }

    private function activitiesCreatedByDay(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = Activity::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate_count')
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');
    }

    private function activitiesCompletedByDay(User $user, ?string $userId, Carbon $from, Carbon $to): Collection
    {
        $query = Activity::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as aggregate_count')
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    private function activitiesCompletedByType(User $user, ?string $userId, Carbon $from, Carbon $to): array
    {
        $query = Activity::query();
        $this->scopeOwnership($query, $user, $userId);

        return $query->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('type as type_value, COUNT(*) as aggregate_count')
            ->groupBy('type_value')
            ->get()
            ->map(fn ($row) => ['type' => $row->type_value, 'count' => (int) $row->aggregate_count])
            ->all();
    }

    /**
     * Grouped by responsible user within the period — for a Seller this
     * naturally comes back as a single row (their own), since scopeOwnership()
     * already restricts every underlying query to $user->id. Same "same
     * output shape regardless of role" principle as Dashboard's recent_leads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function performanceByUser(User $user, ?string $userId, Carbon $from, Carbon $to): array
    {
        $leadsConvertedQuery = LeadConversion::query()->join('leads', 'leads.id', '=', 'lead_conversions.lead_id');
        $this->scopeOwnership($leadsConvertedQuery, $user, $userId, 'leads.user_id');
        $leadsConvertedByUser = $leadsConvertedQuery
            ->whereBetween('lead_conversions.converted_at', [$from, $to])
            ->selectRaw('leads.user_id as owner_id, COUNT(*) as aggregate_count')
            ->groupBy('owner_id')
            ->pluck('aggregate_count', 'owner_id');

        $wonQuery = Opportunity::query();
        $this->scopeOwnership($wonQuery, $user, $userId);
        $wonByUser = $wonQuery->where('stage', OpportunityStage::Won)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw(
                'user_id as owner_id, COUNT(*) as aggregate_count, '.
                "CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as aggregate_value"
            )
            ->groupBy('owner_id')
            ->get()
            ->keyBy('owner_id');

        $activitiesQuery = Activity::query();
        $this->scopeOwnership($activitiesQuery, $user, $userId);
        $activitiesByUser = $activitiesQuery->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('user_id as owner_id, COUNT(*) as aggregate_count')
            ->groupBy('owner_id')
            ->pluck('aggregate_count', 'owner_id');

        $userIds = collect()
            ->merge($leadsConvertedByUser->keys())
            ->merge($wonByUser->keys())
            ->merge($activitiesByUser->keys())
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $names = User::whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds->map(fn ($id) => [
            'user_id' => $id,
            'name' => $names->get($id, 'Unknown'),
            'leads_converted' => (int) $leadsConvertedByUser->get($id, 0),
            'opportunities_won' => (int) ($wonByUser->get($id)?->aggregate_count ?? 0),
            'value_won' => $this->formatMoney($wonByUser->get($id)?->aggregate_value ?? '0'),
            'activities_completed' => (int) $activitiesByUser->get($id, 0),
        ])
            // Sorted for display only — casting to float here is a plain
            // comparison, never arithmetic or storage, so it doesn't
            // reintroduce the float-money issue formatMoney() guards against.
            ->sortByDesc(fn (array $row) => (float) $row['value_won'])
            ->values()
            ->all();
    }

    /**
     * Current-moment snapshot (not historical) — same shape and computation
     * as BuildDashboardSummary's pipeline_by_stage/open pipeline value,
     * duplicated here rather than shared so this Action stays self-contained
     * and the money-safety trick can't be missed by only updating one copy.
     *
     * @return array{by_stage: array<int, array<string, mixed>>, open_value: string}
     */
    private function pipelineSnapshot(User $user, ?string $userId): array
    {
        $query = Opportunity::query();
        $this->scopeOwnership($query, $user, $userId);

        $rows = $query->selectRaw(
            'stage as stage_value, COUNT(*) as aggregate_count, '.
            "CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as aggregate_value"
        )
            ->groupBy('stage_value')
            ->get()
            ->keyBy('stage_value');

        $byStage = collect(OpportunityStage::cases())->map(function (OpportunityStage $stage) use ($rows) {
            $row = $rows->get($stage->value);

            return [
                'stage' => $stage->value,
                'count' => $row ? (int) $row->aggregate_count : 0,
                'value' => $row ? $this->formatMoney($row->aggregate_value) : '0.00',
            ];
        })->values()->all();

        $openQuery = Opportunity::query();
        $this->scopeOwnership($openQuery, $user, $userId);
        $openValue = $openQuery->whereIn('stage', self::OPEN_STAGES)
            ->selectRaw("CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as total")
            ->value('total');

        return [
            'by_stage' => $byStage,
            'open_value' => $this->formatMoney($openValue),
        ];
    }

    /**
     * Identical to BuildDashboardSummary::formatMoney() — see that method's
     * docblock for why the parameter must include "float" and why this is
     * safe cosmetic padding, never arithmetic.
     */
    private function formatMoney(string|int|float $value): string
    {
        $value = (string) $value;

        if (! str_contains($value, '.')) {
            return $value.'.00';
        }

        [$integer, $decimals] = explode('.', $value, 2);

        return $integer.'.'.str_pad(substr($decimals, 0, 2), 2, '0');
    }
}
