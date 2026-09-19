<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk();

        // Checked on the "web" guard directly (not a follow-up HTTP call, and
        // not assertGuest()/default guard): auth:sanctum switches the app's
        // default guard to "sanctum" for the rest of the test process (Laravel
        // reuses one container across simulated requests in a test), which
        // would report stale state unrelated to what logout() actually did.
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }
}
