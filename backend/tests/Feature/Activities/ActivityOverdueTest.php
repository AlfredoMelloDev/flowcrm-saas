<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityOverdueTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($this->company);
    }

    public function test_pending_activity_with_past_scheduled_at_is_overdue(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'scheduled_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.is_overdue', true);
    }

    public function test_pending_activity_with_future_scheduled_at_is_not_overdue(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.is_overdue', false);
    }

    public function test_completed_activity_with_past_scheduled_at_is_not_overdue(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'completed',
            'completed_at' => now(),
            'scheduled_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.is_overdue', false);
    }

    public function test_is_overdue_is_never_a_stored_column(): void
    {
        // Guards against ever accidentally persisting it: the schema only
        // has "status", never a literal "overdue" value.
        $activity = Activity::factory()->create([
            'user_id' => $this->admin->id,
            'status' => 'pending',
            'scheduled_at' => now()->subDay(),
        ]);

        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('activities', ['id' => $activity->id, 'status' => 'overdue']);
    }
}
