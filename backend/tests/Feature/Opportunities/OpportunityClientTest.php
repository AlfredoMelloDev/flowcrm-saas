<?php

namespace Tests\Feature\Opportunities;

use App\Enums\ClientStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class OpportunityClientTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller1;

    private User $seller2;

    private Client $clientOfSeller1;

    private Client $clientOfSeller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $this->setTenantContext($this->company);
        $this->clientOfSeller1 = Client::factory()->create(['user_id' => $this->seller1->id]);
        $this->clientOfSeller2 = Client::factory()->create(['user_id' => $this->seller2->id]);
    }

    public function test_client_id_is_required_on_create(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'No Client',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function test_client_from_another_company_is_rejected(): void
    {
        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderClient = Client::factory()->create();
        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Cross Tenant Client',
            'client_id' => $outsiderClient->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function test_seller_can_only_select_own_clients(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/opportunities', [
            'title' => 'Wrong Client',
            'client_id' => $this->clientOfSeller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function test_seller_can_select_own_client(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/opportunities', [
            'title' => 'Correct Client',
            'client_id' => $this->clientOfSeller1->id,
        ]);

        $response->assertCreated();
    }

    public function test_admin_can_select_any_client_in_the_company(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Admin Picks Any Client',
            'client_id' => $this->clientOfSeller2->id,
        ]);

        $response->assertCreated();
    }

    public function test_soft_deleted_client_cannot_be_selected(): void
    {
        $this->clientOfSeller1->delete();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Deleted Client',
            'client_id' => $this->clientOfSeller1->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function test_inactive_client_can_still_be_selected(): void
    {
        // "options" (the select list) hides inactive clients, but the
        // validation rule itself only excludes soft-deleted ones — an
        // inactive-but-existing client is a valid, if unusual, target
        // (e.g. reopening a deal via direct API/PATCH rather than the UI).
        $this->clientOfSeller1->update(['status' => ClientStatus::Inactive]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Inactive Client',
            'client_id' => $this->clientOfSeller1->id,
        ]);

        $response->assertCreated();
    }

    public function test_updating_client_id_is_scoped_the_same_way_as_create(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'client_id' => $this->clientOfSeller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }
}
