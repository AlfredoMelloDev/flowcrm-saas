<?php

namespace Tests\Feature\Opportunities;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class OpportunityTenantIsolationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private Opportunity $opportunityA;

    private Opportunity $opportunityB;

    private Client $clientA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->adminA = User::factory()->for($this->companyA)->create(['role' => UserRole::Admin]);
        $adminB = User::factory()->for($this->companyB)->create(['role' => UserRole::Admin]);

        $this->setTenantContext($this->companyA);
        $this->clientA = Client::factory()->create();
        $this->opportunityA = Opportunity::factory()->create(['client_id' => $this->clientA->id, 'title' => 'Deal A']);

        $this->setTenantContext($this->companyB);
        $clientB = Client::factory()->create();
        $this->opportunityB = Opportunity::factory()->create(['client_id' => $clientB->id, 'title' => 'Deal B']);
    }

    public function test_company_a_only_lists_its_own_opportunities(): void
    {
        $response = $this->actingAs($this->adminA)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->opportunityA->id);
    }

    public function test_accessing_opportunity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->getJson("/api/v1/opportunities/{$this->opportunityB->id}");

        $response->assertNotFound();
    }

    public function test_updating_opportunity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->patchJson("/api/v1/opportunities/{$this->opportunityB->id}", [
            'title' => 'Hijack attempt',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('opportunities', ['id' => $this->opportunityB->id, 'title' => 'Deal B']);
    }

    public function test_deleting_opportunity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->deleteJson("/api/v1/opportunities/{$this->opportunityB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('opportunities', ['id' => $this->opportunityB->id, 'deleted_at' => null]);
    }

    public function test_malicious_company_id_in_payload_is_ignored(): void
    {
        $response = $this->actingAs($this->adminA)->postJson('/api/v1/opportunities', [
            'title' => 'Spoofed Tenant Opportunity',
            'client_id' => $this->clientA->id,
            'company_id' => $this->companyB->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('opportunities', [
            'title' => 'Spoofed Tenant Opportunity',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('opportunities', [
            'title' => 'Spoofed Tenant Opportunity',
            'company_id' => $this->companyB->id,
        ]);
    }
}
