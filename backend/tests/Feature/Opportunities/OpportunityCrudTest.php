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

class OpportunityCrudTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($this->company);
        $this->client = Client::factory()->create();
    }

    public function test_admin_can_create_unassigned_opportunity(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Renovação anual',
            'client_id' => $this->client->id,
            'value' => '15000.00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Renovação anual');
        $response->assertJsonPath('data.stage', 'new');
        $response->assertJsonPath('data.value', '15000.00');
        $response->assertJsonPath('data.client.id', $this->client->id);
        $response->assertJsonPath('data.assigned_to', null);
        $this->assertDatabaseHas('opportunities', [
            'title' => 'Renovação anual',
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => null,
        ]);
    }

    public function test_value_is_persisted_as_decimal_string_not_float(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Precision Check',
            'client_id' => $this->client->id,
            'value' => '19999.99',
        ]);

        $response->assertCreated();
        // A decimal cast always returns a string — never a float/int — this
        // is the whole point of avoiding float storage for money.
        $this->assertIsString($response->json('data.value'));
        $this->assertSame('19999.99', $response->json('data.value'));
    }

    public function test_create_reports_validation_errors(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'value' => 'not-a-number',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title', 'client_id', 'value']);
    }

    public function test_stage_is_not_accepted_on_create(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/opportunities', [
            'title' => 'Attempted Stage Override',
            'client_id' => $this->client->id,
            'stage' => 'won',
        ]);

        $response->assertCreated();
        // Ignored — every Opportunity is created as "new", same as
        // Lead/Client always starting at their own default status.
        $response->assertJsonPath('data.stage', 'new');
    }

    public function test_can_list_opportunities_paginated(): void
    {
        Opportunity::factory()->count(3)->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_show_an_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $opportunity->id);
    }

    public function test_can_update_multiple_fields_via_patch(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Old']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'title' => 'New',
            'value' => '2500.50',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New');
        $response->assertJsonPath('data.value', '2500.50');
    }

    public function test_put_is_not_a_supported_method(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/opportunities/{$opportunity->id}", [
            'title' => 'Should Not Apply',
        ]);

        $response->assertStatus(405);
    }

    public function test_admin_can_delete_opportunity_and_it_is_soft_deleted(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertOk();
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_manager_can_delete_opportunity(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($manager)->deleteJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertOk();
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_soft_deleted_opportunity_is_not_found_by_show_update_or_delete(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);
        $opportunity->delete();

        $this->actingAs($this->admin)->getJson("/api/v1/opportunities/{$opportunity->id}")->assertNotFound();
        $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", ['title' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson("/api/v1/opportunities/{$opportunity->id}")->assertNotFound();
    }

    public function test_soft_deleted_opportunity_does_not_appear_in_listing(): void
    {
        $visible = Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Still Here']);
        $deleted = Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Gone']);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }
}
