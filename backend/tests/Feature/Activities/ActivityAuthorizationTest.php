<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $manager;

    private User $seller1;

    private User $seller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
    }

    public function test_seller_sees_only_own_activities_in_list(): void
    {
        $this->setTenantContext($this->company);
        $own = Activity::factory()->create(['user_id' => $this->seller1->id]);
        Activity::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $own->id);
    }

    public function test_manager_sees_activities_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Activity::factory()->create(['user_id' => $this->seller1->id]);
        Activity::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_sees_activities_of_both_sellers(): void
    {
        $this->setTenantContext($this->company);
        Activity::factory()->create(['user_id' => $this->seller1->id]);
        Activity::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_creating_activity_becomes_automatically_responsible(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/activities', [
            'title' => 'Self assigned',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.assigned_to.id', $this->seller1->id);
        $this->assertDatabaseHas('activities', ['title' => 'Self assigned', 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_forge_user_id_on_create(): void
    {
        $response = $this->actingAs($this->seller1)->postJson('/api/v1/activities', [
            'title' => 'Sneaky activity',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseMissing('activities', ['title' => 'Sneaky activity']);
    }

    public function test_admin_and_manager_can_reassign_within_company(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->manager)->patchJson("/api/v1/activities/{$activity->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assigned_to.id', $this->seller2->id);
    }

    public function test_reassigning_to_an_inactive_user_is_rejected(): void
    {
        $this->setTenantContext($this->company);
        $inactiveUser = User::factory()->for($this->company)->create([
            'role' => UserRole::Seller,
            'status' => 'inactive',
        ]);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}", [
            'user_id' => $inactiveUser->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_nobody_can_assign_user_from_another_company(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Cross tenant assignment',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $outsider->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
    }

    public function test_seller_cannot_reassign_own_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/activities/{$activity->id}", [
            'user_id' => $this->seller2->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'user_id' => $this->seller1->id]);
    }

    public function test_seller_cannot_view_another_sellers_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertForbidden();
    }

    public function test_seller_cannot_update_another_sellers_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller2->id, 'title' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'title' => 'Original']);
    }

    public function test_seller_can_update_own_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id, 'title' => 'Original']);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Updated by owner',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated by owner');
    }

    public function test_seller_receives_403_deleting_own_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'deleted_at' => null]);
    }

    public function test_seller_cannot_complete_another_sellers_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/activities/{$activity->id}/complete");

        $response->assertForbidden();
    }

    public function test_seller_can_complete_own_activity(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->seller1->id]);

        $response = $this->actingAs($this->seller1)->patchJson("/api/v1/activities/{$activity->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
    }
}
