<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadOptionsTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private User $seller1;

    private User $seller2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);
        $this->seller1 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->seller2 = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
    }

    public function test_admin_sees_all_leads_of_the_company(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);
        Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_manager_sees_all_leads_of_the_company(): void
    {
        $manager = User::factory()->for($this->company)->create(['role' => UserRole::Manager]);
        $this->setTenantContext($this->company);
        Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);

        $response = $this->actingAs($manager)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_seller_sees_only_own_leads(): void
    {
        $this->setTenantContext($this->company);
        $ownLead = Lead::factory()->create(['user_id' => $this->seller1->id]);
        Lead::factory()->create(['user_id' => $this->seller2->id]);
        Lead::factory()->create(['user_id' => null]);

        $response = $this->actingAs($this->seller1)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownLead->id);
    }

    public function test_soft_deleted_lead_does_not_appear(): void
    {
        $this->setTenantContext($this->company);
        $deletedLead = Lead::factory()->create();
        $deletedLead->delete();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($deletedLead->id));
    }

    public function test_lead_from_another_company_does_not_appear(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create();

        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderLead = Lead::factory()->create();

        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($outsiderLead->id));
    }

    public function test_response_only_exposes_id_and_name(): void
    {
        $this->setTenantContext($this->company);
        Lead::factory()->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/leads/options');

        $response->assertOk();
        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
    }
}
