<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadConversionAuthorizationTest extends TestCase
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
    }

    public function test_admin_can_convert_any_lead_in_the_company(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$lead->id}/convert");

        $response->assertCreated();
    }

    public function test_manager_can_convert_any_lead_in_the_company(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->manager)->postJson("/api/v1/leads/{$lead->id}/convert");

        $response->assertCreated();
    }

    public function test_seller_can_convert_own_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->postJson("/api/v1/leads/{$lead->id}/convert");

        $response->assertCreated();
    }

    public function test_seller_cannot_convert_another_sellers_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->postJson("/api/v1/leads/{$lead->id}/convert");

        $response->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);
        $this->assertDatabaseCount('lead_conversions', 0);
    }

    public function test_seller_cannot_convert_unassigned_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->postJson("/api/v1/leads/{$lead->id}/convert");

        $response->assertForbidden();
    }

    public function test_converting_a_lead_from_another_company_returns_404(): void
    {
        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderLead = Lead::factory()->create();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$outsiderLead->id}/convert");

        $response->assertNotFound();
    }

    public function test_forged_company_id_in_conversion_request_is_ignored(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);
        $otherCompany = Company::factory()->create();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$lead->id}/convert", [
            'company_id' => $otherCompany->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('clients', [
            'name' => $lead->name,
            'company_id' => $this->company->id,
        ]);
        $this->assertDatabaseMissing('clients', ['company_id' => $otherCompany->id]);
    }

    public function test_client_id_user_id_and_stage_in_the_request_body_are_ignored(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);
        $existingClient = Client::factory()->create();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$lead->id}/convert", [
            'client_id' => $existingClient->id,
            'user_id' => $this->seller2->id,
            'stage' => 'won',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.stage', 'new');
        $response->assertJsonPath('data.client.assigned_to.id', $this->seller1->id);
        $this->assertNotEquals($existingClient->id, $response->json('data.client.id'));
    }
}
