<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityCompletionTest extends TestCase
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

    public function test_complete_sets_status_and_completed_at(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($response->json('data.completed_at'));

        $activity->refresh();
        $this->assertSame('completed', $activity->status->value);
        $this->assertNotNull($activity->completed_at);
    }

    public function test_reopen_clears_status_and_completed_at(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}/reopen");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.completed_at', null);

        $activity->refresh();
        $this->assertSame('pending', $activity->status->value);
        $this->assertNull($activity->completed_at);
    }

    public function test_generic_patch_cannot_set_status_to_completed(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id, 'status' => 'pending']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}", [
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
        ]);

        // Not rejected — "status"/"completed_at" simply aren't recognized
        // fields on this endpoint, so they're silently dropped rather than
        // causing a validation error (same behavior as Lead's status in
        // Phase 5).
        $response->assertOk();

        $activity->refresh();
        $this->assertSame('pending', $activity->status->value);
        $this->assertNull($activity->completed_at);
    }

    public function test_completed_at_is_never_accepted_from_the_request(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create(['user_id' => $this->admin->id]);
        $forgedDate = now()->subYear()->toIso8601String();

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}/complete", [
            'completed_at' => $forgedDate,
        ]);

        $response->assertOk();
        $this->assertNotEquals($forgedDate, $response->json('data.completed_at'));
    }

    public function test_completing_an_already_completed_activity_refreshes_completed_at(): void
    {
        $this->setTenantContext($this->company);
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'completed_at' => now()->subWeek(),
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/activities/{$activity->id}/complete");

        $response->assertOk();
        $activity->refresh();
        $this->assertTrue($activity->completed_at->isAfter(now()->subMinute()));
    }
}
