<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientDeletionProtectionTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($this->company);
    }

    public function test_client_with_an_active_opportunity_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }

    public function test_client_with_only_soft_deleted_opportunities_can_be_deleted(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);
        $opportunity->delete();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertOk();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_client_without_opportunities_can_be_deleted(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertOk();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_deletion_is_never_a_hard_delete_or_cascade(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);
        $opportunity->delete();

        $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}")->assertOk();

        // Both rows still physically exist — only deleted_at was set on each.
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id]);
    }
}
