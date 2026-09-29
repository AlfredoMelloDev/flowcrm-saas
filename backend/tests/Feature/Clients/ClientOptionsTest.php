<?php

namespace Tests\Feature\Clients;

use App\Enums\ClientStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientOptionsTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller1;

    private User $seller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
    }

    public function test_admin_sees_all_active_clients_of_the_company(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);
        Client::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_manager_sees_all_active_clients_of_the_company(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->setTenantContext($this->company);
        Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($manager)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_sees_only_own_active_clients(): void
    {
        $this->setTenantContext($this->company);
        $ownClient = Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::factory()->create(['user_id' => $this->seller2->id]);
        Client::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownClient->id);
    }

    public function test_inactive_client_does_not_appear(): void
    {
        $this->setTenantContext($this->company);
        $inactiveClient = Client::factory()->create(['user_id' => $this->seller1->id]);
        Client::where('id', $inactiveClient->id)->update(['status' => ClientStatus::Inactive]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($inactiveClient->id));
    }

    public function test_soft_deleted_client_does_not_appear(): void
    {
        $this->setTenantContext($this->company);
        $deletedClient = Client::factory()->create(['user_id' => $this->seller1->id]);
        $deletedClient->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($deletedClient->id));
    }

    public function test_client_from_another_company_does_not_appear(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['user_id' => $this->seller1->id]);

        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderClient = Client::factory()->create();

        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($outsiderClient->id));
    }

    public function test_response_only_exposes_id_name_document(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients/options');

        $response->assertOk();
        $this->assertSame(['id', 'name', 'document'], array_keys($response->json('data.0')));
    }
}
