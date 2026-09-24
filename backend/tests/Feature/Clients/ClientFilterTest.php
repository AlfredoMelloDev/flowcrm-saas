<?php

namespace Tests\Feature\Clients;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ClientFilterTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
    }

    public function test_search_matches_name_email_phone_or_document(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['name' => 'Alice Wonderland', 'email' => 'x@x.test', 'phone' => '000', 'document' => '111']);
        Client::factory()->create(['name' => 'Bob Builder', 'email' => 'alice@match.test', 'phone' => '111', 'document' => '222']);
        Client::factory()->create(['name' => 'Carl Carlson', 'email' => 'c@c.test', 'phone' => '222-alice', 'document' => '333']);
        Client::factory()->create(['name' => 'Dana Doc', 'email' => 'd@d.test', 'phone' => '444', 'document' => 'alice-doc']);
        Client::factory()->create(['name' => 'No Match', 'email' => 'n@n.test', 'phone' => '555', 'document' => '555']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?search=alice');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }

    public function test_filter_by_valid_status(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['status' => 'active']);
        Client::factory()->create(['status' => 'inactive']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?status=inactive');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'inactive');
    }

    public function test_filter_by_invalid_status_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?status=not-a-status');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_filter_by_valid_type(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['type' => 'individual']);
        Client::factory()->create(['type' => 'company']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?type=company');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'company');
    }

    public function test_filter_by_invalid_type_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?type=not-a-type');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_filter_by_user_id_from_another_company_returns_422_with_generic_message(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $nonexistentIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/clients?user_id=01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $otherCompanyIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/clients?user_id='.$outsider->id);

        $nonexistentIdResponse->assertUnprocessable();
        $otherCompanyIdResponse->assertUnprocessable();

        $this->assertSame(
            $nonexistentIdResponse->json('errors.user_id.0'),
            $otherCompanyIdResponse->json('errors.user_id.0'),
        );
    }

    public function test_filter_by_user_id_respects_seller_ownership(): void
    {
        $otherSeller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->setTenantContext($this->company);
        $ownClient = Client::factory()->create(['user_id' => $this->seller->id]);
        Client::factory()->create(['user_id' => $otherSeller->id]);

        $response = $this->actingAs($this->seller)->getJson('/api/v1/clients?user_id='.$this->seller->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownClient->id);
    }

    public function test_sort_by_allowed_column(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->create(['name' => 'Zeta']);
        Client::factory()->create(['name' => 'Alpha']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?sort=name&order=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha');
        $response->assertJsonPath('data.1.name', 'Zeta');
    }

    public function test_sort_by_disallowed_column_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?sort=email');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sort']);
    }

    public function test_order_other_than_asc_or_desc_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?sort=name&order=sideways');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    }

    public function test_per_page_within_limit_is_honored(): void
    {
        $this->setTenantContext($this->company);
        Client::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_per_page_above_maximum_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/clients?per_page=101');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }
}
