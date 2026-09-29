<?php

namespace Tests\Feature\Opportunities;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class OpportunityFilterTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($this->company);
        $this->client = Client::factory()->create();
    }

    public function test_search_matches_title(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Renovação Alice Corp']);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'No Match']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?search=alice');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_matches_client_name(): void
    {
        $matchingClient = Client::factory()->create(['name' => 'Alice Wonderland Ltda']);
        Opportunity::factory()->create(['client_id' => $matchingClient->id, 'title' => 'Generic Deal Title']);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Another Deal']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?search=alice');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_filter_by_valid_stage(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id, 'stage' => 'new']);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'stage' => 'proposal']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?stage=proposal');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.stage', 'proposal');
    }

    public function test_filter_by_invalid_stage_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?stage=not-a-stage');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['stage']);
    }

    public function test_filter_by_user_id_from_another_company_returns_422_with_generic_message(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->for($otherCompany)->create(['role' => UserRole::Seller]);

        $nonexistentIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?user_id=01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $otherCompanyIdResponse = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?user_id='.$outsider->id);

        $nonexistentIdResponse->assertUnprocessable();
        $otherCompanyIdResponse->assertUnprocessable();
        $this->assertSame(
            $nonexistentIdResponse->json('errors.user_id.0'),
            $otherCompanyIdResponse->json('errors.user_id.0'),
        );
    }

    public function test_sort_by_allowed_column(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Zeta']);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'title' => 'Alpha']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?sort=title&order=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'Alpha');
        $response->assertJsonPath('data.1.title', 'Zeta');
    }

    public function test_sort_by_disallowed_column_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?sort=lost_reason');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['sort']);
    }

    public function test_order_other_than_asc_or_desc_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?sort=title&order=sideways');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['order']);
    }

    public function test_per_page_within_limit_is_honored(): void
    {
        Opportunity::factory()->count(5)->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?per_page=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_per_page_above_maximum_returns_422(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities?per_page=101');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }
}
