<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadAuthorizationTest extends TestCase
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

    public function test_seller_sees_only_own_leads_in_list(): void
    {
        $this->setTenantContext($this->company);
        $ownLead = Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);
        Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownLead->id);
    }

    public function test_seller_does_not_see_other_sellers_lead(): void
    {
        $this->setTenantContext($this->company);
        $otherLead = Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherLead->id));
    }

    public function test_seller_does_not_see_unassigned_lead(): void
    {
        $this->setTenantContext($this->company);
        $unassigned = Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($unassigned->id));
    }

    public function test_manager_sees_leads_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sees_leads_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_creating_lead_becomes_automatically_responsible(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/leads', [
            'name' => 'Self Assigned Lead',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned_to.id', $this->seller1->id);
        $this->assertDatabaseHas('leads', ['name' => 'Self Assigned Lead', 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_forge_user_id_on_create(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/leads', [
            'name' => 'Sneaky Lead',
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseMissing('leads', ['name' => 'Sneaky Lead']);
    }

    public function test_admin_and_manager_can_create_unassigned_lead(): void
    {
        foreach ([$this->admin, $this->manager] as $actor) {
            $response = $this->actingAs($actor)->postJson('/api/v1/leads', [
                'name' => 'Unassigned by '.$actor->role->value,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.assigned_to', null);
        }
    }

    public function test_admin_and_manager_can_reassign_within_company(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->manager)->patchJson("/api/v1/leads/{$lead->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assigned_to.id', $this->seller2->id);
    }

    public function test_nobody_can_assign_user_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/leads', [
            'name' => 'Cross Tenant Assignment',
            'user_id' => $outsider->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_seller_cannot_reassign_own_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/leads/{$lead->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_view_another_sellers_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/leads/{$lead->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_view_unassigned_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/leads/{$lead->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_update_another_sellers_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller2->id, 'name' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/leads/{$lead->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'name' => 'Original']);
    }

    public function test_seller_can_update_own_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id, 'name' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/leads/{$lead->id}", [
            'name' => 'Updated by owner',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated by owner');
    }

    public function test_seller_receives_403_deleting_own_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->deleteJson("/api/v1/leads/{$lead->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);
    }
}
