<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityCrudTest extends TestCase
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

    public function test_admin_can_create_an_activity_assigned_to_a_user(): void
    {
        $this->setTenantContext($this->company);
        $assignee = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Follow up call',
            'type' => 'call',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $assignee->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Follow up call');
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.assigned_to.id', $assignee->id);
        $this->assertDatabaseHas('activities', [
            'title' => 'Follow up call',
            'company_id' => $this->company->id,
            'user_id' => $assignee->id,
            'status' => 'pending',
        ]);
    }

    public function test_create_reports_validation_errors(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'type' => 'not-a-real-type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title', 'type', 'scheduled_at', 'user_id']);
    }

    public function test_user_id_is_required_to_create_an_activity(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'No owner',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_can_list_activities_paginated(): void
    {
        $this->setTenantContext($this->company);
        Activity::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_show_an_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $activity->id);
    }

    public function test_can_partially_update_an_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id, 'title' => 'Old title']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}", [
            'title' => 'New title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New title');
    }

    public function test_put_is_not_a_supported_method(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Should not apply',
        ]);

        $response->assertStatus(405);
    }

    public function test_admin_can_delete_activity_and_it_is_soft_deleted(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }

    public function test_deletion_is_never_a_hard_delete(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->deleteJson("/api/v1/activities/{$activity->id}")->assertOk();

        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    }

    public function test_manager_can_delete_a_completed_activity(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($manager)->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }
}
