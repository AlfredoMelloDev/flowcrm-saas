<?php

namespace App\Actions\Dashboard;

use App\Enums\ClientStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Http\Resources\LeadResource;
use App\Http\Resources\OpportunityResource;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Carbon;

class BuildDashboardSummary
{
    /**
     * Stages that count as "still in play" — excludes the two closed
     * outcomes (won/lost), matching the definition approved for
     * "oportunidades abertas" and "valor total do pipeline".
     */
    private const OPEN_STAGES = [
        OpportunityStage::New,
        OpportunityStage::Contacted,
        OpportunityStage::Proposal,
        OpportunityStage::Negotiation,
    ];

    /**
     * Lead statuses that count as "leads ativos" — excludes the two
     * terminal outcomes (converted/unqualified).
     */
    private const ACTIVE_LEAD_STATUSES = [
        LeadStatus::New,
        LeadStatus::Contacted,
        LeadStatus::Qualified,
    ];

    private const CLOSING_SOON_DAYS = 7;

    private const LIST_LIMIT = 5;

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $leadCounts = $this->leadCountsByStatus($user);
        $stageStats = $this->opportunityStatsByStage($user);

        $totalLeads = array_sum($leadCounts);
        $convertedLeads = $leadCounts[LeadStatus::Converted->value] ?? 0;
        $leadsActive = collect(self::ACTIVE_LEAD_STATUSES)
            ->sum(fn (LeadStatus $status) => $leadCounts[$status->value] ?? 0);

        $openStageValues = collect(self::OPEN_STAGES)->map(fn (OpportunityStage $stage) => $stage->value);

        return [
            'leads_active' => $leadsActive,
            'clients_active' => $this->clientsActiveCount($user),
            'opportunities_open' => $openStageValues->sum(fn (string $stage) => $stageStats[$stage]['count']),
            'pipeline_value' => $this->openPipelineValue($user),
            'opportunities_won' => $stageStats[OpportunityStage::Won->value]['count'],
            'opportunities_lost' => $stageStats[OpportunityStage::Lost->value]['count'],
            'lead_conversion_rate' => $totalLeads > 0
                ? round(($convertedLeads / $totalLeads) * 100, 1)
                : 0,
            'pipeline_by_stage' => collect(OpportunityStage::cases())
                ->map(fn (OpportunityStage $stage) => [
                    'stage' => $stage->value,
                    'count' => $stageStats[$stage->value]['count'],
                    'value' => $stageStats[$stage->value]['value'],
                ])
                ->values()
                ->all(),
            'closing_soon' => OpportunityResource::collection($this->closingSoon($user))->resolve(),
            'recent_leads' => LeadResource::collection($this->recentLeads($user))->resolve(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function leadCountsByStatus(User $user): array
    {
        $rows = Lead::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            // Aliased so Eloquent doesn't cast this back into a LeadStatus
            // enum on the result row — a plain string is what we key by.
            ->selectRaw('status as status_value, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [$row->status_value => (int) $row->aggregate_count])->all();
    }

    private function clientsActiveCount(User $user): int
    {
        return Client::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            ->where('status', ClientStatus::Active)
            ->count();
    }

    /**
     * @return array<string, array{count: int, value: string}>
     */
    private function opportunityStatsByStage(User $user): array
    {
        $rows = Opportunity::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            ->selectRaw(
                'stage as stage_value, COUNT(*) as aggregate_count, '.
                // CONCAT(...,'') forces MySQL to hand the value back as a
                // string over the wire — without it, PDO's reported type
                // for a computed DECIMAL expression isn't reliable (this
                // was observed returning a PHP float in some contexts).
                "CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as aggregate_value"
            )
            ->groupBy('stage')
            ->get()
            ->keyBy('stage_value');

        return collect(OpportunityStage::cases())->mapWithKeys(function (OpportunityStage $stage) use ($rows) {
            $row = $rows->get($stage->value);

            return [
                $stage->value => [
                    'count' => $row ? (int) $row->aggregate_count : 0,
                    'value' => $row ? $this->formatMoney($row->aggregate_value) : '0.00',
                ],
            ];
        })->all();
    }

    /**
     * Summed separately (rather than derived from opportunityStatsByStage)
     * so the money arithmetic — adding the open stages together — happens
     * once, in SQL, as an exact DECIMAL sum, never as PHP float addition.
     */
    private function openPipelineValue(User $user): string
    {
        $total = Opportunity::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('stage', self::OPEN_STAGES)
            ->selectRaw("CONCAT(CAST(COALESCE(SUM(value), 0) AS DECIMAL(15,2)), '') as total")
            ->value('total');

        return $this->formatMoney($total);
    }

    /**
     * Guarantees a fixed 2-decimal string ("300" -> "300.00", "150.5" ->
     * "150.50") from whatever scalar type the SUM/CAST aggregate comes back
     * as (PDO returns DECIMAL aggregates as string, int, or float depending
     * on the query context — confirmed empirically to vary here). The
     * parameter is deliberately typed to include "float": a narrower
     * `string|int` union is actually dangerous — PHP's weak-mode coercion
     * silently truncates a non-integral float (e.g. 50.5) down to an int
     * (50) to satisfy that union *before* the function body ever runs,
     * which would quietly drop real cents. Converting to string here is
     * purely cosmetic padding of an already-exact DECIMAL value computed by
     * MySQL — never arithmetic — so it doesn't reintroduce float math.
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

    /**
     * Open opportunities due within the window, including ones already
     * overdue (expected_close_date in the past but still open) — those need
     * attention at least as much as the ones coming up.
     */
    private function closingSoon(User $user)
    {
        return Opportunity::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            ->whereIn('stage', self::OPEN_STAGES)
            ->whereNotNull('expected_close_date')
            ->where('expected_close_date', '<=', Carbon::today()->addDays(self::CLOSING_SOON_DAYS))
            ->orderBy('expected_close_date')
            ->limit(self::LIST_LIMIT)
            ->with(['client', 'user'])
            ->get();
    }

    private function recentLeads(User $user)
    {
        return Lead::query()
            ->when($user->isSeller(), fn ($query) => $query->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->with('user')
            ->get();
    }
}
