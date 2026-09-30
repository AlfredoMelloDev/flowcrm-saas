<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityFilterTest extends TestCase
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

    public function test_filters_by_search_matching_title_or_description(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'title' => 'Call Alice', 'description' => null]);
        Activity::factory()->create(['user_id' => $this->admin->id, 'title' => 'Unrelated', 'description' => 'mentions alice here']);
        Activity::factory()->create(['user_id' => $this->admin->id, 'title' => 'Nothing relevant']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?search=alice');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_filters_by_type(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'type' => 'call']);
        Activity::factory()->create(['user_id' => $this->admin->id, 'type' => 'email']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?type=call');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'call');
    }

    public function test_filters_by_status(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'status' => 'pending']);
        Activity::factory()->create(['user_id' => $this->admin->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?status=completed');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');
    }

    public function test_filters_by_user_id(): void
    {
        $seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        Activity::factory()->create(['user_id' => $this->admin->id]);
        Activity::factory()->create(['user_id' => $seller->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?user_id='.$seller->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_window_today_matches_only_activities_scheduled_today(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->setTime(9, 0)]);
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->addDays(3)]);
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->subDays(3)]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?window=today');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_window_upcoming_matches_only_pending_future_activities(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->addDays(3), 'status' => 'pending']);
        Activity::factory()->create([
            'user_id' => $this->admin->id, 'scheduled_at' => now()->addDays(3),
            'status' => 'completed', 'completed_at' => now(),
        ]);
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->subDays(3), 'status' => 'pending']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?window=upcoming');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_window_overdue_matches_only_pending_past_activities(): void
    {
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->subDays(2), 'status' => 'pending']);
        Activity::factory()->create([
            'user_id' => $this->admin->id, 'scheduled_at' => now()->subDays(2),
            'status' => 'completed', 'completed_at' => now(),
        ]);
        Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->addDays(2), 'status' => 'pending']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities?window=overdue');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_sorts_by_scheduled_at_ascending_by_default(): void
    {
        $later = Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->addDays(5)]);
        $sooner = Activity::factory()->create(['user_id' => $this->admin->id, 'scheduled_at' => now()->addDay()]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/activities');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $sooner->id);
        $response->assertJsonPath('data.1.id', $later->id);
    }
}
