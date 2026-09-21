<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignableUsersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    public function test_admin_can_list_assignable_users(): void
    {
        $admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/assignable');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($seller->id));
    }

    public function test_manager_can_list_assignable_users(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($manager)->getJson('/api/v1/users/assignable');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_receives_403(): void
    {
        $seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($seller)->getJson('/api/v1/users/assignable');

        $response->assertForbidden();
    }

    public function test_inactive_user_does_not_appear(): void
    {
        $admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $inactiveSeller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        User::where('id', $inactiveSeller->id)->update(['status' => UserStatus::Inactive]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/assignable');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($inactiveSeller->id));
    }

    public function test_user_from_another_company_does_not_appear(): void
    {
        $admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);

        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/assignable');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($outsider->id));
    }

    public function test_response_only_exposes_id_name_email(): void
    {
        $admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->getJson('/api/v1/users/assignable');

        $response->assertOk();
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('data.0')));
    }
}
