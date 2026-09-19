<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_company_and_admin_user_and_authenticates(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company' => [
                'name' => 'Acme Inc',
            ],
            'user' => [
                'name' => 'Alice Admin',
                'email' => 'alice@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'alice@acme.test');
        $response->assertJsonPath('data.role', UserRole::Admin->value);
        $response->assertJsonPath('data.company.name', 'Acme Inc');

        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('users', 1);

        $user = User::first();
        $this->assertTrue($user->isAdmin());
        $this->assertSame(Company::first()->id, $user->company_id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@acme.test']);

        $response = $this->postJson('/api/v1/auth/register', [
            'company' => ['name' => 'Another Co'],
            'user' => [
                'name' => 'Bob',
                'email' => 'taken@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user.email']);
        $this->assertDatabaseCount('companies', 1); // only the factory-created one
    }

    public function test_register_fails_without_company_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company' => [],
            'user' => [
                'name' => 'Bob',
                'email' => 'bob@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['company.name']);
    }

    public function test_register_fails_with_unconfirmed_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company' => ['name' => 'Acme Inc'],
            'user' => [
                'name' => 'Bob',
                'email' => 'bob@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'different',
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user.password']);
    }

    public function test_register_ignores_client_supplied_company_id(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company' => ['name' => 'Acme Inc'],
            'user' => [
                'name' => 'Alice Admin',
                'email' => 'alice@acme.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'company_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', // arbitrary spoofed ULID
            ],
        ]);

        $response->assertCreated();

        $user = User::first();
        $company = Company::first();

        $this->assertSame($company->id, $user->company_id);
        $this->assertNotSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $user->company_id);
    }
}
