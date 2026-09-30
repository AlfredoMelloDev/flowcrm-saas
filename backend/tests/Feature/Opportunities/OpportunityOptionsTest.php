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

class OpportunityOptionsTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller1;

    private User $seller2;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);

        $this->setTenantContext($this->company);
        $this->client = Client::factory()->create();
    }

    public function test_admin_sees_all_opportunities_of_the_company(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_manager_sees_all_opportunities_of_the_company(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($manager)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_sees_only_own_opportunities(): void
    {
        $own = Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller1->id]);
        Opportunity::factory()->create(['client_id' => $this->client->id, 'user_id' => $this->seller2->id]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $own->id);
    }

    public function test_soft_deleted_opportunity_does_not_appear(): void
    {
        $deleted = Opportunity::factory()->create(['client_id' => $this->client->id]);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($deleted->id));
    }

    public function test_opportunity_from_another_company_does_not_appear(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id]);

        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderClient = Client::factory()->create();
        $outsiderOpportunity = Opportunity::factory()->create(['client_id' => $outsiderClient->id]);

        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($outsiderOpportunity->id));
    }

    public function test_response_only_exposes_id_and_title(): void
    {
        Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/opportunities/options');

        $response->assertOk();
        $this->assertSame(['id', 'title'], array_keys($response->json('data.0')));
    }
}
