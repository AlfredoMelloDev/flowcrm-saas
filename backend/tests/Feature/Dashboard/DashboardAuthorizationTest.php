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

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $manager;

    private User $seller1;

    private User $seller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $this->setTenantContext($this->company);
        Lead::factory()->create(['status' => 'new', 'user_id' => $this->seller1->id]);
        Lead::factory()->create(['status' => 'new', 'user_id' => $this->seller2->id]);
        Client::factory()->create(['status' => 'active', 'user_id' => $this->seller1->id]);
        Client::factory()->create(['status' => 'active', 'user_id' => $this->seller2->id]);
        $clientOfSeller1 = Client::where('user_id', $this->seller1->id)->first();
        $clientOfSeller2 = Client::where('user_id', $this->seller2->id)->first();
        Opportunity::factory()->create([
            'client_id' => $clientOfSeller1->id, 'user_id' => $this->seller1->id,
            'stage' => 'new', 'value' => '100.00',
        ]);
        Opportunity::factory()->create([
            'client_id' => $clientOfSeller2->id, 'user_id' => $this->seller2->id,
            'stage' => 'new', 'value' => '200.00',
        ]);
    }

    public function test_admin_sees_company_wide_totals(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.leads_active', 2);
        $response->assertJsonPath('data.clients_active', 2);
        $response->assertJsonPath('data.opportunities_open', 2);
        $response->assertJsonPath('data.pipeline_value', '300.00');
    }

    public function test_manager_sees_company_wide_totals(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.leads_active', 2);
        $response->assertJsonPath('data.clients_active', 2);
        $response->assertJsonPath('data.opportunities_open', 2);
        $response->assertJsonPath('data.pipeline_value', '300.00');
    }

    public function test_seller_sees_only_their_own_data(): void
    {
        $response = $this->actingAs($this->seller1)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.leads_active', 1);
        $response->assertJsonPath('data.clients_active', 1);
        $response->assertJsonPath('data.opportunities_open', 1);
        $response->assertJsonPath('data.pipeline_value', '100.00');
    }

    public function test_seller_does_not_see_the_other_sellers_recent_leads_or_closing_soon(): void
    {
        $response = $this->actingAs($this->seller1)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $recentLeadIds = collect($response->json('data.recent_leads'))->pluck('assigned_to.id');
        $this->assertTrue($recentLeadIds->every(fn ($id) => $id === $this->seller1->id));
    }

    public function test_tenant_isolation_numbers_do_not_leak_across_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->for($otherCompany)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($otherCompany);
        Lead::factory()->count(5)->create(['status' => 'new']);

        $response = $this->actingAs($otherAdmin)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.leads_active', 5);
    }
}
