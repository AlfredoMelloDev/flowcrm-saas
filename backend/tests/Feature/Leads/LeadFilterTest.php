<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadFilterTest extends TestCase
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

    public function test_search_matches_name_email_or_phone(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['name' => 'Alice Wonderland', 'email' => 'x@x.test', 'phone' => '000']);
        Lead::factory()->create(['name' => 'Bob Builder', 'email' => 'alice@match.test', 'phone' => '111']);
        Lead::factory()->create(['name' => 'Carl Carlson', 'email' => 'c@c.test', 'phone' => '222-alice']);
        Lead::factory()->create(['name' => 'No Match', 'email' => 'n@n.test', 'phone' => '333']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?search=alice');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_filter_by_valid_status(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'qualified']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?status=qualified');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'qualified');
    }

    public function test_filter_by_invalid_status_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?status=not-a-status');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_filter_by_invalid_source_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?source=not-a-source');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['source']);
    }

    public function test_filter_by_user_id_from_another_company_returns_422_with_generic_message(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $nonexistentIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/leads?user_id=01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $otherCompanyIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/leads?user_id='.$outsider->id);

        $nonexistentIdResponse->assertUnprocessable();
        $otherCompanyIdResponse->assertUnprocessable();

        // Same generic message either way — never reveals that the id exists
        // in a different tenant.
        $this->assertSame(
            $nonexistentIdResponse->json('errors.user_id.0'),
            $otherCompanyIdResponse->json('errors.user_id.0'),
        );
    }

    public function test_filter_by_user_id_respects_seller_ownership(): void
    {
        $otherSeller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->setTenantContext($this->company);
        $ownLead = Lead::factory()->create(['user_id' => $this->seller->id]);
        Lead::factory()->create(['user_id' => $otherSeller->id]);

        // Seller filtering by their own id still only sees their own lead —
        // the ownership scope from the role always applies underneath the filter.
        $response = $this->actingAs($this->seller)->getJson('/api/v1/leads?user_id='.$this->seller->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownLead->id);
    }

    public function test_sort_by_allowed_column(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['name' => 'Zeta']);
        Lead::factory()->create(['name' => 'Alpha']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?sort=name&order=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha');
        $response->assertJsonPath('data.1.name', 'Zeta');
    }

    public function test_sort_by_disallowed_column_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?sort=email');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sort']);
    }

    public function test_order_other_than_asc_or_desc_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?sort=name&order=sideways');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    }

    public function test_per_page_within_limit_is_honored(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_per_page_above_maximum_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads?per_page=101');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }
}
