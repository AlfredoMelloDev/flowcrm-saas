<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@acme.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'alice@acme.test');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        User::factory()->create([
            'email' => 'alice@acme.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_with_nonexistent_email_fails_with_same_generic_message(): void
    {
        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@acme.test',
            'password' => 'whatever123',
        ]);

        User::factory()->create([
            'email' => 'alice@acme.test',
            'password' => Hash::make('password123'),
        ]);

        $wrongEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@acme.test',
            'password' => 'incorrect123',
        ]);

        $wrongPassword->assertUnprocessable();
        $wrongEmail->assertUnprocessable();
        $this->assertSame(
            $wrongPassword->json('errors.email.0'),
            $wrongEmail->json('errors.email.0'),
        );
        $this->assertGuest();
    }
}
