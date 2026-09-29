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

class OpportunityPipelineTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller1;

    private User $seller2;

    private Client $clientOfSeller1;

    private Client $clientOfSeller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $this->setTenantContext($this->company);
        $this->clientOfSeller1 = Client::factory()->create(['user_id' => $this->seller1->id]);
        $this->clientOfSeller2 = Client::factory()->create(['user_id' => $this->seller2->id]);
    }

    public function test_pipeline_returns_a_flat_unpaginated_list(): void
    {
        Opportunity::factory()->count(20)->create(['client_id' => $this->clientOfSeller1->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/pipeline');

        $response->assertOk();
        // No 15-per-page default, no "meta"/"links" pagination envelope —
        // every opportunity comes back in a single flat "data" array.
        $response->assertJsonCount(20, 'data');
        $response->assertJsonMissingPath('meta');
        $response->assertJsonMissingPath('links');
    }

    public function test_pipeline_respects_tenant_isolation(): void
    {
        $otherCompany = Company::factory()->create();
        $otherAdmin = User::factory()->for($otherCompany)->create(['role' => UserRole::Admin]);
        $this->setTenantContext($otherCompany);
        $otherClient = Client::factory()->create();
        Opportunity::factory()->create(['client_id' => $otherClient->id]);

        $this->setTenantContext($this->company);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/pipeline');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $otherResponse = $this->actingAs($otherAdmin)->getJson('/api/v1/opportunities/pipeline');
        $otherResponse->assertOk();
        $otherResponse->assertJsonCount(1, 'data');
    }

    public function test_pipeline_respects_seller_ownership(): void
    {
        $own = Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller1->id,
            'user_id' => $this->seller1->id,
        ]);
        Opportunity::factory()->create([
            'client_id' => $this->clientOfSeller2->id,
            'user_id' => $this->seller2->id,
        ]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/opportunities/pipeline');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $own->id);
    }

    public function test_pipeline_search_matches_title_or_client_name(): void
    {
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'title' => 'Alice Deal']);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller2->id, 'title' => 'Unrelated']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/pipeline?search=alice');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_pipeline_excludes_soft_deleted_opportunities(): void
    {
        $visible = Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id]);
        $deleted = Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id]);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/pipeline');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_pipeline_filters_by_user_id_for_admin(): void
    {
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller1->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->clientOfSeller2->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/pipeline?user_id='.$this->seller1->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
