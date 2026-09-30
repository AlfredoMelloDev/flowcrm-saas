<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityTenantIsolationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $companyA;

    private Company $companyB;

    private User $adminA;

    private User $adminB;

    private Activity $activityA;

    private Activity $activityB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();

        $this->adminA = User::factory()->for($this->companyA)->create(['role' => UserRole::Admin]);
        $this->adminB = User::factory()->for($this->companyB)->create(['role' => UserRole::Admin]);

        $this->setTenantContext($this->companyA);
        $this->activityA = Activity::factory()->create(['user_id' => $this->adminA->id, 'title' => 'Activity of Company A']);

        $this->setTenantContext($this->companyB);
        $this->activityB = Activity::factory()->create(['user_id' => $this->adminB->id, 'title' => 'Activity of Company B']);
    }

    public function test_company_a_only_lists_its_own_activities(): void
    {
        $response = $this->actingAs($this->adminA)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->activityA->id);
    }

    public function test_accessing_activity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->getJson("/api/v1/activities/{$this->activityB->id}");

        $response->assertNotFound();
    }

    public function test_updating_activity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->patchJson("/api/v1/activities/{$this->activityB->id}", [
            'title' => 'Hijack attempt',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('activities', ['id' => $this->activityB->id, 'title' => 'Activity of Company B']);
    }

    public function test_deleting_activity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->deleteJson("/api/v1/activities/{$this->activityB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('activities', ['id' => $this->activityB->id, 'deleted_at' => null]);
    }

    public function test_completing_activity_of_another_company_returns_404(): void
    {
        $response = $this->actingAs($this->adminA)->patchJson("/api/v1/activities/{$this->activityB->id}/complete");

        $response->assertNotFound();
    }

    public function test_malicious_company_id_in_payload_is_ignored(): void
    {
        $response = $this->actingAs($this->adminA)->postJson('/api/v1/activities', [
            'title' => 'Spoofed Tenant Activity',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->adminA->id,
            'company_id' => $this->companyB->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('activities', [
            'title' => 'Spoofed Tenant Activity',
            'company_id' => $this->companyA->id,
        ]);
        $this->assertDatabaseMissing('activities', [
            'title' => 'Spoofed Tenant Activity',
            'company_id' => $this->companyB->id,
        ]);
    }
}
