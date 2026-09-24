<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientTenantIsolationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->adminA = User::factory()->for($this->companyA)->create(['role' => UserRole::Admin]);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => UserRole::Admin]);

        $this->setTenantContext($this->companyA);
        $this->clientA = Client::factory()->create(['name' => 'Client of Company A']);

        $this->setTenantContext($this->companyB);
        $this->clientB = Client::factory()->create(['name' => 'Client of Company B']);
    }

    public function test_company_a_only_lists_its_own_clients(): void
    {
        $response = $this->actingAs($this->adminA)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->clientA->id);
    }

    public function test_accessing_client_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->getJson("/api/v1/clients/{$this->clientB->id}");

        $response->assertNotFound();
    }

    public function test_updating_client_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->patchJson("/api/v1/clients/{$this->clientB->id}", [
            'name' => 'Hijack attempt',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('clients', ['id' => $this->clientB->id, 'name' => 'Client of Company B']);
    }

    public function test_deleting_client_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->deleteJson("/api/v1/clients/{$this->clientB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('clients', ['id' => $this->clientB->id, 'deleted_at' => null]);
    }

    public function test_malicious_company_id_in_payload_is_ignored(): void
    {
        $response = $this->actingAs($this->adminA)->postJson('/api/v1/clients', [
            'name' => 'Spoofed Tenant Client',
            'company_id' => $this->companyB->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('clients', [
            'name' => 'Spoofed Tenant Client',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('clients', [
            'name' => 'Spoofed Tenant Client',
            'company_id' => $this->companyB->id,
        ]);
    }
}
