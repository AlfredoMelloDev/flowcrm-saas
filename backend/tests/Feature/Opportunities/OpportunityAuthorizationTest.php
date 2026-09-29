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

class OpportunityAuthorizationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $manager;

    private User $seller1;

    private User $seller2;

    private Client $clientOfSeller1;

    private Client $clientOfSeller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $this->setTenantContext($this->company);
        $this->clientOfSeller1 = Client::factory()->create(['user_id' => $this->seller1->id]);
        $this->clientOfSeller2 = Client::factory()->create(['user_id' => $this->seller2->id]);
    }

    public function test_seller_sees_only_own_opportunities_in_list(): void
    {
        $ownOpportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);
        Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller2->id,
            'user_id' => $this->seller2->id,
        ]);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownOpportunity->id);
    }

    public function test_seller_does_not_see_other_sellers_opportunity(): void
    {
        $otherOpportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller2->id,
            'user_id' => $this->seller2->id,
        ]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherOpportunity->id));
    }

    public function test_seller_does_not_see_unassigned_opportunity(): void
    {
        $unassigned = Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($unassigned->id));
    }

    public function test_manager_sees_opportunities_of_both_sellers(): void
    {
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller2->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sees_opportunities_of_both_sellers(): void
    {
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller2->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_creating_opportunity_becomes_automatically_responsible(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/opportunities', [
            'title' => 'Self Assigned Deal',
            'client_id' => $this->clientOfSeller1->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned_to.id', $this->seller1->id);
        $this->assertDatabaseHas('opportunities', ['title' => 'Self Assigned Deal', 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_forge_user_id_on_create(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/opportunities', [
            'title' => 'Sneaky Deal',
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseMissing('opportunities', ['title' => 'Sneaky Deal']);
    }

    public function test_admin_and_manager_can_create_unassigned_opportunity(): void
    {
        foreach ([$this->admin, $this->manager] as $actor) {
            $response = $this->actingAs($actor)->postJson('/api/v1/opportunities', [
                'title' => 'Unassigned by '.$actor->role->value,
                'client_id' => $this->clientOfSeller1->id,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.assigned_to', null);
        }
    }

    public function test_admin_and_manager_can_reassign_within_company(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);

        $response = $this->actingAs($this->manager)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assigned_to.id', $this->seller2->id);
    }

    public function test_nobody_can_assign_user_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Cross Tenant Assignment',
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $outsider->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_seller_cannot_reassign_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'user_id' => $this->seller1->id]);
    }

    public function test_seller_can_change_stage_of_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'contacted',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'contacted');
    }

    public function test_seller_cannot_view_another_sellers_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller2->id,
            'user_id' => $this->seller2->id,
        ]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_view_unassigned_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_update_another_sellers_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller2->id,
            'user_id' => $this->seller2->id,
            'title' => 'Original',
        ]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'title' => 'Original']);
    }

    public function test_seller_receives_403_deleting_own_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);

        $response = $this->actingAs($this->seller1)->deleteJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'deleted_at' => null]);
    }
}
