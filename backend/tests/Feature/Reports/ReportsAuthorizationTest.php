<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ReportsAuthorizationTest extends TestCase
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
        $client = Client::factory()->create();
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $this->seller1->id, 'stage' => 'won',
            'value' => '100.00', 'closed_at' => now()->subDay(),
        ]);
        Opportunity::factory()->create([
            'client_id' => $client->id, 'user_id' => $this->seller2->id, 'stage' => 'won',
            'value' => '200.00', 'closed_at' => now()->subDay(),
        ]);
        Activity::factory()->create([
            'user_id' => $this->seller1->id, 'status' => 'completed',
            'scheduled_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);
    }

    public function test_admin_and_manager_see_company_wide_totals(): void
    {
        $adminResponse = $this->actingAs($this->admin)->getJson('/api/v1/reports');
        $managerResponse = $this->actingAs($this->manager)->getJson('/api/v1/reports');

        $adminResponse->assertJsonPath('data.opportunities_won_total', 2);
        $adminResponse->assertJsonPath('data.value_won', '300.00');
        $managerResponse->assertJsonPath('data.opportunities_won_total', 2);
    }

    public function test_admin_can_filter_by_user_id(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?user_id='.$this->seller1->id);

        $response->assertOk();
        $response->assertJsonPath('data.opportunities_won_total', 1);
        $response->assertJsonPath('data.value_won', '100.00');
    }

    public function test_seller_sees_only_their_own_data(): void
    {
        $response = $this->actingAs($this->seller1)->getJson('/api/v1/reports');

        $response->assertOk();
        $response->assertJsonPath('data.opportunities_won_total', 1);
        $response->assertJsonPath('data.value_won', '100.00');
        $response->assertJsonPath('data.activities_completed_total', 1);
    }

    public function test_seller_cannot_use_user_id_to_view_another_sellers_data(): void
    {
        $response = $this->actingAs($this->seller1)
            ->getJson('/api/v1/reports?user_id='.$this->seller2->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_admin_cannot_filter_by_a_user_id_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/reports?user_id='.$outsider->id);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_tenant_isolation_numbers_do_not_leak_across_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->for($otherCompany)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($otherCompany);
        $otherClient = Client::factory()->create();
        Opportunity::factory()->create([
            'client_id' => $otherClient->id, 'user_id' => $otherAdmin->id, 'stage' => 'won',
            'value' => '999.00', 'closed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($otherAdmin)->getJson('/api/v1/reports');

        $response->assertOk();
        $response->assertJsonPath('data.opportunities_won_total', 1);
        $response->assertJsonPath('data.value_won', '999.00');
    }
}
