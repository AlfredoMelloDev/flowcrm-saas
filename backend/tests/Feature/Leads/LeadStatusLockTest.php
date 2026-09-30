<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadStatusLockTest extends TestCase
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

    public function test_creating_a_lead_with_status_converted_in_the_payload_is_ignored(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/leads', [
            'name' => 'Sneaky Lead',
            'status' => 'converted',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'new');
        $this->assertDatabaseHas('leads', ['name' => 'Sneaky Lead', 'status' => 'new']);
    }

    public function test_status_cannot_be_set_to_converted_directly_via_patch(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['status' => 'new']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/leads/{$lead->id}", [
            'status' => 'converted',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);
    }

    public function test_status_cannot_be_changed_once_converted(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['status' => 'converted']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/leads/{$lead->id}", [
            'status' => 'new',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'converted']);
    }

    public function test_other_fields_remain_editable_after_conversion(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['status' => 'converted', 'name' => 'Old Name']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/leads/{$lead->id}", [
            'name' => 'New Name',
            'phone' => '11888887777',
            'notes' => 'Updated after conversion',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.status', 'converted');
    }

    public function test_deleting_a_converted_lead_returns_409(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['status' => 'converted']);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/leads/{$lead->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_non_converted_lead_still_works(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create(['status' => 'qualified']);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/leads/{$lead->id}");

        $response->assertOk();
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }
}
