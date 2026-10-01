<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ReportsMetricsTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($this->company);
    }

    public function test_default_period_is_last_30_days_ending_today(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports');

        $response->assertOk();
        $response->assertJsonPath('data.period.date_to', now()->toDateString());
        $response->assertJsonPath('data.period.date_from', now()->subDays(29)->toDateString());
        $response->assertJsonPath('data.period.timezone', 'UTC');
    }

    public function test_leads_created_total_and_daily_series_within_period(): void
    {
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(2)]);
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(2)]);
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(1)]);
        // Outside the requested window — must not count.
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.leads_created_total', 3);
        $series = collect($response->json('data.daily_series.leads_created'))->keyBy('date');
        $this->assertSame(2, $series[now()->subDays(2)->toDateString()]['count']);
        $this->assertSame(1, $series[now()->subDays(1)->toDateString()]['count']);
        $this->assertSame(0, $series[now()->toDateString()]['count']);
    }

    public function test_period_boundaries_are_inclusive(): void
    {
        $from = now()->subDays(3);
        $to = now();
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => $from->copy()->startOfDay()]);
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => $to->copy()->endOfDay()]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.leads_created_total', 2);
    }

    public function test_empty_period_returns_zeroed_metrics_not_an_error(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.leads_created_total', 0);
        $response->assertJsonPath('data.leads_converted_total', 0);
        $response->assertJsonPath('data.lead_conversion_rate', 0);
        $response->assertJsonPath('data.avg_conversion_time_hours', null);
        $response->assertJsonPath('data.avg_closing_time_hours', null);
        $response->assertJsonPath('data.value_won', '0.00');
        $response->assertJsonPath('data.value_lost', '0.00');
        $response->assertJsonPath('data.lost_reasons', []);
        $response->assertJsonPath('data.performance_by_user', []);
    }

    public function test_leads_converted_and_conversion_rate(): void
    {
        $lead1 = Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(2)]);
        Lead::factory()->create(['user_id' => $this->admin->id, 'created_at' => now()->subDays(1)]);
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);
        LeadConversion::factory()->create([
            'lead_id' => $lead1->id, 'client_id' => $client->id, 'opportunity_id' => $opportunity->id,
            'converted_by' => $this->admin->id, 'converted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.leads_created_total', 2);
        $response->assertJsonPath('data.leads_converted_total', 1);
        // assertEquals (not assertJsonPath's strict type check): a "round"
        // float like 50.0 renders as 50 in JSON — see the identical note in
        // DashboardMetricsTest::test_lead_conversion_rate_formula.
        $this->assertEquals(50.0, $response->json('data.lead_conversion_rate'));
        $this->assertIsNumeric($response->json('data.avg_conversion_time_hours'));
        $this->assertGreaterThan(0, $response->json('data.avg_conversion_time_hours'));
    }

    public function test_opportunities_won_lost_and_values(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'stage' => 'won',
            'value' => '1000.00', 'created_at' => now()->subDays(3), 'closed_at' => now()->subDay(),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'stage' => 'lost',
            'value' => '300.50', 'created_at' => now()->subDays(3), 'closed_at' => now()->subDay(),
            'lost_reason' => 'price',
        ]);
        // Won but outside the window — must not count.
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $this->admin->id, 'stage' => 'won',
            'value' => '999.00', 'closed_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.opportunities_won_total', 1);
        $response->assertJsonPath('data.opportunities_lost_total', 1);
        $response->assertJsonPath('data.value_won', '1000.00');
        $response->assertJsonPath('data.value_lost', '300.50');
        $this->assertIsString($response->json('data.value_won'));
        $response->assertJsonPath('data.lost_reasons', [['reason' => 'price', 'count' => 1]]);
        $this->assertIsNumeric($response->json('data.avg_closing_time_hours'));
        $this->assertGreaterThan(0, $response->json('data.avg_closing_time_hours'));
    }

    public function test_activities_created_completed_and_by_type(): void
    {
        Activity::factory()->create([
            'user_id' => $this->admin->id, 'type' => 'call', 'status' => 'completed',
            'scheduled_at' => now()->subDays(2), 'completed_at' => now()->subDay(), 'created_at' => now()->subDays(2),
        ]);
        Activity::factory()->create([
            'user_id' => $this->admin->id, 'type' => 'email', 'status' => 'completed',
            'scheduled_at' => now()->subDays(2), 'completed_at' => now()->subDay(), 'created_at' => now()->subDays(2),
        ]);
        // Still pending — must not count as completed.
        Activity::factory()->create([
            'user_id' => $this->admin->id, 'type' => 'task', 'status' => 'pending',
            'scheduled_at' => now()->addDay(), 'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.activities_created_total', 3);
        $response->assertJsonPath('data.activities_completed_total', 2);
        $byType = collect($response->json('data.activities_completed_by_type'))->keyBy('type');
        $this->assertSame(1, $byType['call']['count']);
        $this->assertSame(1, $byType['email']['count']);
        $this->assertArrayNotHasKey('task', $byType->all());
    }

    public function test_performance_by_user_aggregates_per_responsible(): void
    {
        $seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $client = Client::factory()->create();
        $lead = Lead::factory()->create(['user_id' => $seller->id, 'created_at' => now()->subDays(2)]);
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);
        LeadConversion::factory()->create([
            'lead_id' => $lead->id, 'client_id' => $client->id, 'opportunity_id' => $opportunity->id,
            'converted_by' => $this->admin->id, 'converted_at' => now()->subDay(),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $seller->id, 'stage' => 'won',
            'value' => '750.00', 'closed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?'.http_build_query([
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $rows = collect($response->json('data.performance_by_user'))->keyBy('user_id');
        $this->assertSame(1, $rows[$seller->id]['leads_converted']);
        $this->assertSame(1, $rows[$seller->id]['opportunities_won']);
        $this->assertSame('750.00', $rows[$seller->id]['value_won']);
    }

    public function test_pipeline_snapshot_matches_current_state(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id, 'user_id' => $this->admin->id, 'stage' => 'new', 'value' => '200.00']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports');

        $response->assertOk();
        $response->assertJsonCount(6, 'data.pipeline_snapshot.by_stage');
        $stages = collect($response->json('data.pipeline_snapshot.by_stage'))->keyBy('stage');
        $this->assertSame(1, $stages['new']['count']);
        $this->assertSame('200.00', $response->json('data.pipeline_snapshot.open_value'));
    }
}
