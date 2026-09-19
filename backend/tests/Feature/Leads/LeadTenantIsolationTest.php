<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadTenantIsolationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private Lead $leadA;

    private Lead $leadB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->adminA = User::factory()->for($this->companyA)->create(['role' => UserRole::Admin]);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => UserRole::Admin]);

        $this->setTenantContext($this->companyA);
        $this->leadA = Lead::factory()->create(['name' => 'Lead of Company A']);

        $this->setTenantContext($this->companyB);
        $this->leadB = Lead::factory()->create(['name' => 'Lead of Company B']);
    }

    public function test_company_a_only_lists_its_own_leads(): void
    {
        $response = $this->actingAs($this->adminA)->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->leadA->id);
    }

    public function test_accessing_lead_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->getJson("/api/v1/leads/{$this->leadB->id}");

        $response->assertNotFound();
    }

    public function test_updating_lead_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->patchJson("/api/v1/leads/{$this->leadB->id}", [
            'name' => 'Hijack attempt',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('leads', ['id' => $this->leadB->id, 'name' => 'Lead of Company B']);
    }

    public function test_deleting_lead_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->deleteJson("/api/v1/leads/{$this->leadB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('leads', ['id' => $this->leadB->id, 'deleted_at' => null]);
    }

    public function test_malicious_company_id_in_payload_is_ignored(): void
    {
        $response = $this->actingAs($this->adminA)->postJson('/api/v1/leads', [
            'name' => 'Spoofed Tenant Lead',
            'company_id' => $this->companyB->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('leads', [
            'name' => 'Spoofed Tenant Lead',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('leads', [
            'name' => 'Spoofed Tenant Lead',
            'company_id' => $this->companyB->id,
        ]);
    }
}
