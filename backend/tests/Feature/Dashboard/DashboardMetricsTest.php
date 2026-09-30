<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
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

    public function test_leads_active_counts_only_non_terminal_statuses(): void
    {
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'contacted']);
        Lead::factory()->create(['status' => 'qualified']);
        Lead::factory()->create(['status' => 'converted']);
        Lead::factory()->create(['status' => 'unqualified']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.leads_active', 3);
    }

    public function test_clients_active_counts_only_active_status(): void
    {
        Client::factory()->create(['status' => 'active']);
        Client::factory()->create(['status' => 'active']);
        Client::factory()->create(['status' => 'inactive']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.clients_active', 2);
    }

    public function test_opportunities_open_won_lost_counts(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->count(2)->create(['client_id' => $client->id, 'stage' => 'new']);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'contacted']);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'proposal']);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'negotiation']);
        Opportunity::factory()->count(3)->create(['client_id' => $client->id, 'stage' => 'won']);
        Opportunity::factory()->count(2)->create(['client_id' => $client->id, 'stage' => 'lost']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.opportunities_open', 5);
        $response->assertJsonPath('data.opportunities_won', 3);
        $response->assertJsonPath('data.opportunities_lost', 2);
    }

    public function test_pipeline_value_sums_only_open_stages_and_ignores_null(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'new', 'value' => '100.00']);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'contacted', 'value' => null]);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'proposal', 'value' => '50.50']);
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'won', 'value' => '999.00']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.pipeline_value', '150.50');
    }

    public function test_pipeline_value_is_zero_dot_zero_zero_when_no_open_opportunities(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'won', 'value' => '500.00']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.pipeline_value', '0.00');
    }

    public function test_pipeline_by_stage_always_has_all_six_stages(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'new', 'value' => '10.00']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonCount(6, 'data.pipeline_by_stage');
        $stages = collect($response->json('data.pipeline_by_stage'))->keyBy('stage');
        $this->assertSame(1, $stages['new']['count']);
        $this->assertSame('10.00', $stages['new']['value']);
        foreach (['contacted', 'proposal', 'negotiation', 'won', 'lost'] as $emptyStage) {
            $this->assertSame(0, $stages[$emptyStage]['count']);
            $this->assertSame('0.00', $stages[$emptyStage]['value']);
        }
    }

    public function test_pipeline_values_are_decimal_strings_not_floats(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id, 'stage' => 'new', 'value' => '1234.56']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $this->assertIsString($response->json('data.pipeline_value'));
        foreach ($response->json('data.pipeline_by_stage') as $row) {
            $this->assertIsString($row['value']);
        }
    }

    public function test_lead_conversion_rate_formula(): void
    {
        Lead::factory()->create(['status' => 'converted']);
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'unqualified']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        // 1 converted out of 4 total = 25.0% — compared with assertEquals
        // (not assertJsonPath's strict type check): PHP's json_encode
        // renders a "round" float like 25.0 as 25 in the payload, which
        // JSON/JavaScript treats identically to 25.0 (JSON has no separate
        // int/float type), so this is not a real type mismatch to guard.
        $this->assertEquals(25.0, $response->json('data.lead_conversion_rate'));
    }

    public function test_lead_conversion_rate_rounds_to_one_decimal(): void
    {
        Lead::factory()->create(['status' => 'converted']);
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'new']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        // 1 out of 3 = 33.333... -> rounds to 33.3
        $response->assertJsonPath('data.lead_conversion_rate', 33.3);
    }

    public function test_lead_conversion_rate_is_zero_when_there_are_no_leads(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.lead_conversion_rate', 0);
    }

    public function test_closing_soon_includes_overdue_and_within_window_excludes_far_future_and_closed(): void
    {
        $client = Client::factory()->create();

        $overdue = Opportunity::factory()->create([
            'client_id' => $client->id, 'stage' => 'new',
            'expected_close_date' => now()->subDay(),
        ]);
        $withinWindow = Opportunity::factory()->create([
            'client_id' => $client->id, 'stage' => 'proposal',
            'expected_close_date' => now()->addDays(3),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'stage' => 'negotiation',
            'expected_close_date' => now()->addDays(30),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'stage' => 'won',
            'expected_close_date' => now()->addDays(1),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'stage' => 'new',
            'expected_close_date' => null,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $ids = collect($response->json('data.closing_soon'))->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertSame($overdue->id, $ids->first());
        $this->assertTrue($ids->contains($withinWindow->id));
    }

    public function test_closing_soon_is_limited_to_five_and_ordered_ascending(): void
    {
        $client = Client::factory()->create();
        $expected = [];
        for ($i = 0; $i < 7; $i++) {
            $expected[] = Opportunity::factory()->create([
                'client_id' => $client->id,
                'stage' => 'new',
                'expected_close_date' => now()->addDays($i),
            ])->id;
        }

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $ids = collect($response->json('data.closing_soon'))->pluck('id');
        $this->assertCount(5, $ids);
        $this->assertSame(array_slice($expected, 0, 5), $ids->all());
    }

    public function test_recent_leads_ordered_desc_and_limited_to_five(): void
    {
        $expectedNewestFirst = [];
        for ($i = 6; $i >= 0; $i--) {
            $expectedNewestFirst[$i] = Lead::factory()->create(['created_at' => now()->subMinutes($i)])->id;
        }
        // Newest (smallest subMinutes) first.
        ksort($expectedNewestFirst);
        $expectedOrder = array_values($expectedNewestFirst);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $ids = collect($response->json('data.recent_leads'))->pluck('id');
        $this->assertCount(5, $ids);
        $this->assertSame(array_slice($expectedOrder, 0, 5), $ids->all());
    }
}
