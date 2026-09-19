<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadCrudTest extends TestCase
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

    public function test_admin_can_create_unassigned_lead(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/leads', [
            'name' => 'Jane Prospect',
            'email' => 'jane@prospect.test',
            'source' => 'website',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Jane Prospect');
        $response->assertJsonPath('data.status', 'new');
        $response->assertJsonPath('data.assigned_to', null);
        $this->assertDatabaseHas('leads', [
            'name' => 'Jane Prospect',
            'company_id' => $this->company->id,
            'user_id' => null,
        ]);
    }

    public function test_create_reports_validation_errors(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/leads', [
            'email' => 'not-an-email',
            'source' => 'not-a-real-source',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'email', 'source']);
    }

    public function test_can_list_leads_paginated(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_show_a_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $lead->id);
    }

    public function test_can_update_multiple_fields_via_patch(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['name' => 'Old Name', 'status' => 'new']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/leads/{$lead->id}", [
            'name' => 'New Name',
            'status' => 'contacted',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.status', 'contacted');
    }

    public function test_can_partially_update_a_single_field_via_patch(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['name' => 'Keep Me', 'status' => 'new']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/leads/{$lead->id}", [
            'status' => 'qualified',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'qualified');
        $response->assertJsonPath('data.name', 'Keep Me');
    }

    public function test_put_is_not_a_supported_method(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();

        // This API intentionally only supports partial updates via PATCH —
        // no PUT, so we never have to document (or implement) full-replace
        // semantics we don't actually offer.
        $response = $this->actingAs($this->admin)->putJson("/api/v1/leads/{$lead->id}", [
            'name' => 'Should Not Apply',
        ]);

        $response->assertStatus(405);
    }

    public function test_admin_can_delete_lead_and_it_is_soft_deleted(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_manager_can_delete_lead(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();

        $response = $this->actingAs($manager)->deleteJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }
}
