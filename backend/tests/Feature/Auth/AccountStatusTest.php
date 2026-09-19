<?php

namespace Tests\Feature\Auth;

use App\Enums\CompanyStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Forces the next Auth::guard() resolution to hit the database again
     * instead of returning an in-memory guard cached from an earlier
     * simulated request in this same test (Laravel testing reuses one
     * container across calls; a real production request never does).
     */
    private function forceFreshAuthResolution(): void
    {
        $this->app->forgetInstance('auth');
        Auth::clearResolvedInstance('auth');
    }

    public function test_active_user_in_active_company_can_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Inactive,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_user_in_suspended_company_cannot_login(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Suspended]);
        $user = User::factory()->for($company)->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_denial_reason_is_not_revealed_and_matches_invalid_credentials(): void
    {
        $inactiveUser = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => UserStatus::Inactive,
        ]);

        $suspendedCompany = Company::factory()->create(['status' => CompanyStatus::Suspended]);
        $suspendedCompanyUser = User::factory()->for($suspendedCompany)->create([
            'password' => Hash::make('password123'),
        ]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $inactiveUser->email,
            'password' => 'not-the-password',
        ]);

        $inactiveUserResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $inactiveUser->email,
            'password' => 'password123',
        ]);

        $suspendedCompanyResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $suspendedCompanyUser->email,
            'password' => 'password123',
        ]);

        $message = $wrongPassword->json('errors.email.0');

        $this->assertSame($message, $inactiveUserResponse->json('errors.email.0'));
        $this->assertSame($message, $suspendedCompanyResponse->json('errors.email.0'));
    }

    public function test_user_deactivated_after_login_is_denied_on_protected_route(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        // Updated via the query builder, not $user->update(): "status" is
        // intentionally absent from User's mass-assignable attributes (no
        // admin endpoint exists yet to change it), so this simulates that
        // future endpoint mutating the row directly, the same way it will.
        User::where('id', $user->id)->update(['status' => UserStatus::Inactive]);
        $this->forceFreshAuthResolution();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_company_suspended_after_login_is_denied_on_protected_route(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        Company::where('id', $user->company_id)->update(['status' => CompanyStatus::Suspended]);
        $this->forceFreshAuthResolution();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_deactivation_after_login_also_terminates_the_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        User::where('id', $user->id)->update(['status' => UserStatus::Inactive]);
        $this->forceFreshAuthResolution();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        // The stale session was killed as a side effect of the rejected
        // request above, not just refused for that one call.
        $this->forceFreshAuthResolution();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
