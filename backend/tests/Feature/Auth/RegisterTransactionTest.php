<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RegisterCompany;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_is_rolled_back_when_user_creation_fails(): void
    {
        User::factory()->create(['email' => 'taken@acme.test']);

        $this->assertDatabaseCount('companies', 1);

        $action = app(RegisterCompany::class);

        try {
            $action->handle(
                ['name' => 'Should Not Persist Inc'],
                [
                    'name' => 'Duplicate',
                    'email' => 'taken@acme.test', // violates the unique constraint at the DB level
                    'password' => 'password123',
                ],
            );

            $this->fail('Expected a QueryException to be thrown.');
        } catch (QueryException) {
            // expected
        }

        // Still only the one company/user created by the factory above — the
        // company created inside the failed transaction was rolled back.
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('companies', ['name' => 'Should Not Persist Inc']);
    }
}
