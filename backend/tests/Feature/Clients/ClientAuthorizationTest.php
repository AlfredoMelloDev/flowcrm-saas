<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
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

    public function test_seller_sees_only_own_clients_in_list(): void
    {
        $this->setTenantContext($this->company);
        $ownClient = Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);
        Client::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownClient->id);
    }

    public function test_seller_does_not_see_other_sellers_client(): void
    {
        $this->setTenantContext($this->company);
        $otherClient = Client::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/clients');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherClient->id));
    }

    public function test_seller_does_not_see_unassigned_client(): void
    {
        $this->setTenantContext($this->company);
        $unassigned = Client::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/clients');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($unassigned->id));
    }

    public function test_manager_sees_clients_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sees_clients_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_creating_client_becomes_automatically_responsible(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/clients', [
            'name' => 'Self Assigned Client',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned_to.id', $this->seller1->id);
        $this->assertDatabaseHas('clients', ['name' => 'Self Assigned Client', 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_forge_user_id_on_create(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/clients', [
            'name' => 'Sneaky Client',
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseMissing('clients', ['name' => 'Sneaky Client']);
    }

    public function test_admin_and_manager_can_create_unassigned_client(): void
    {
        foreach ([$this->admin, $this->manager] as $actor) {
            $response = $this->actingAs($actor)->postJson('/api/v1/clients', [
                'name' => 'Unassigned by '.$actor->role->value,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.assigned_to', null);
        }
    }

    public function test_admin_and_manager_can_reassign_within_company(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->manager)->patchJson("/api/v1/clients/{$client->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assigned_to.id', $this->seller2->id);
    }

    public function test_nobody_can_assign_user_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Cross Tenant Assignment',
            'user_id' => $outsider->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_seller_cannot_reassign_own_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/clients/{$client->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_view_another_sellers_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/clients/{$client->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_view_unassigned_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/clients/{$client->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_update_another_sellers_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller2->id, 'name' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/clients/{$client->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Original']);
    }

    public function test_seller_can_update_own_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller1->id, 'name' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/clients/{$client->id}", [
            'name' => 'Updated by owner',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated by owner');
    }

    public function test_seller_receives_403_deleting_own_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }
}
