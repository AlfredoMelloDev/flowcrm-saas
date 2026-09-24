<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientCrudTest extends TestCase
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

    public function test_admin_can_create_unassigned_client(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'name' => 'Jane Client',
            'email' => 'jane@client.test',
            'type' => 'individual',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Jane Client');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.assigned_to', null);
        $this->assertDatabaseHas('clients', [
            'name' => 'Jane Client',
            'company_id' => $this->company->id,
            'user_id' => null,
        ]);
    }

    public function test_create_reports_validation_errors(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
            'email' => 'not-an-email',
            'type' => 'not-a-real-type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'email', 'type']);
    }

    public function test_can_list_clients_paginated(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_show_a_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/v1/clients/{$client->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $client->id);
    }

    public function test_can_update_multiple_fields_via_patch(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['name' => 'Old Name', 'status' => 'active']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/clients/{$client->id}", [
            'name' => 'New Name',
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.status', 'inactive');
    }

    public function test_can_partially_update_a_single_field_via_patch(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create(['name' => 'Keep Me', 'status' => 'active']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/clients/{$client->id}", [
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'inactive');
        $response->assertJsonPath('data.name', 'Keep Me');
    }

    public function test_put_is_not_a_supported_method(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        // This API intentionally only supports partial updates via PATCH —
        // no PUT, so we never have to document (or implement) full-replace
        // semantics we don't actually offer.
        $response = $this->actingAs($this->admin)->putJson("/api/v1/clients/{$client->id}", [
            'name' => 'Should Not Apply',
        ]);

        $response->assertStatus(405);
    }

    public function test_admin_can_delete_client_and_it_is_soft_deleted(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertOk();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_manager_can_delete_client(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        $response = $this->actingAs($manager)->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertOk();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_delete_is_a_soft_delete_not_a_hard_delete(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}")->assertOk();

        // The row still physically exists — only deleted_at was set.
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_soft_deleted_client_is_not_found_by_show_update_or_delete(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();
        $client->delete();

        $this->actingAs($this->admin)->getJson("/api/v1/clients/{$client->id}")->assertNotFound();
        $this->actingAs($this->admin)->patchJson("/api/v1/clients/{$client->id}", ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson("/api/v1/clients/{$client->id}")->assertNotFound();
    }

    public function test_soft_deleted_client_does_not_appear_in_listing(): void
    {
        $this->setTenantContext($this->company);
        $visible = Client::factory()->create(['name' => 'Still Here']);
        $deleted = Client::factory()->create(['name' => 'Gone']);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }
}
