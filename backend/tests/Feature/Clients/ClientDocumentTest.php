<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientDocumentTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
    }

    public function test_multiple_clients_with_null_document_are_allowed_in_the_same_company(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/clients', ['name' => 'No Doc One'])->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/v1/clients', ['name' => 'No Doc Two'])->assertCreated();

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_same_document_is_allowed_across_different_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->for($otherCompany)->create(['role' => UserRole::Admin]);

        $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Client A',
            'document' => '12345678900',
        ])->assertCreated();

        $this->actingAs($otherAdmin)->postJson('/api/v1/clients', [
            'name' => 'Client B',
            'document' => '12345678900',
        ])->assertCreated();

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_duplicate_document_within_the_same_company_returns_422_on_create(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['document' => '12345678900']);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Duplicate Doc',
            'document' => '12345678900',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['document']);
    }

    public function test_duplicate_document_within_the_same_company_returns_422_on_update(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['document' => '11111111111']);
        $clientToUpdate = Client::factory()->create(['document' => '22222222222']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/clients/{$clientToUpdate->id}", [
            'document' => '11111111111',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['document']);
    }

    public function test_updating_a_clients_own_document_to_the_same_value_is_allowed(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['document' => '33333333333', 'name' => 'Original']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/clients/{$client->id}", [
            'document' => '33333333333',
            'name' => 'Renamed',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Renamed');
    }

    public function test_document_of_a_soft_deleted_client_remains_reserved_in_that_company(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['document' => '99999999999']);
        $client->delete();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Reusing Deleted Document',
            'document' => '99999999999',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['document']);
    }

    public function test_document_is_stored_as_plain_text_without_format_validation(): void
    {
        // No CPF/CNPJ checksum or length enforcement in this phase — any
        // string up to the column limit is accepted as-is.
        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Free Form Document',
            'document' => 'not-a-cpf-cnpj',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.document', 'not-a-cpf-cnpj');
    }
}
