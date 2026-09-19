<?php

namespace Tests\Unit\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_helper_methods_reflect_the_users_role(): void
    {
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        $manager = User::factory()->make(['role' => UserRole::Manager]);
        $seller = User::factory()->make(['role' => UserRole::Seller]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isManager());

        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isSeller());

        $this->assertTrue($seller->isSeller());
        $this->assertFalse($seller->isAdmin());
    }

    public function test_has_any_role_matches_one_of_the_given_roles(): void
    {
        $seller = User::factory()->make(['role' => UserRole::Seller]);

        $this->assertTrue($seller->hasAnyRole(UserRole::Admin, UserRole::Seller));
        $this->assertFalse($seller->hasAnyRole(UserRole::Admin, UserRole::Manager));
    }

    public function test_view_reports_gate_allows_admin_and_manager_but_not_seller(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewReports'));
        $this->assertTrue(Gate::forUser($manager)->allows('viewReports'));
        $this->assertFalse(Gate::forUser($seller)->allows('viewReports'));
    }
}
