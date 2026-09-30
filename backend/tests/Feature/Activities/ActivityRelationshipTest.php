<?php

namespace Tests\Feature\Activities;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class ActivityRelationshipTest extends TestCase
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

    public function test_activity_can_be_created_with_no_relation_at_all(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Generic internal task',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.lead', null);
        $response->assertJsonPath('data.client', null);
        $response->assertJsonPath('data.opportunity', null);
    }

    public function test_activity_can_be_related_to_a_lead(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Call about lead',
            'type' => 'call',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'lead_id' => $lead->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.lead.id', $lead->id);
    }

    public function test_activity_can_be_related_to_a_client(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Client check-in',
            'type' => 'meeting',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'client_id' => $client->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.client.id', $client->id);
    }

    public function test_activity_can_be_related_to_an_opportunity(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Opportunity follow-up',
            'type' => 'follow_up',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'opportunity_id' => $opportunity->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.id', $opportunity->id);
    }

    public function test_more_than_one_relation_is_rejected_with_422(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Two relations',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'lead_id' => $lead->id,
            'client_id' => $client->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['lead_id', 'client_id']);
        $this->assertDatabaseMissing('activities', ['title' => 'Two relations']);
    }

    public function test_database_check_constraint_rejects_more_than_one_relation_even_bypassing_the_request_layer(): void
    {
        $this->setTenantContext($this->company);
        $lead = Lead::factory()->create();
        $client = Client::factory()->create();

        $this->expectException(QueryException::class);

        Activity::create([
            'title' => 'Bypassing validation',
            'type' => 'task',
            'user_id' => $this->admin->id,
            'scheduled_at' => now(),
            'lead_id' => $lead->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_lead_from_another_company_is_rejected(): void
    {
        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        $outsiderLead = Lead::factory()->create();
        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Cross tenant lead',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'lead_id' => $outsiderLead->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['lead_id']);
    }

    public function test_soft_deleted_client_cannot_be_related(): void
    {
        $this->setTenantContext($this->company);
        $client = Client::factory()->create();
        $client->delete();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/activities', [
            'title' => 'Deleted client',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'user_id' => $this->admin->id,
            'client_id' => $client->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_id']);
    }

    public function test_seller_can_only_relate_activity_to_their_own_client(): void
    {
        $this->setTenantContext($this->company);
        $ownClient = Client::factory()->create(['user_id' => $this->seller1->id]);
        $otherClient = Client::factory()->create(['user_id' => $this->seller2->id]);

        $rejected = $this->actingAs($this->seller1)->postJson('/api/v1/activities', [
            'title' => 'Wrong client',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'client_id' => $otherClient->id,
        ]);
        $rejected->assertUnprocessable();
        $rejected->assertJsonValidationErrors(['client_id']);

        $accepted = $this->actingAs($this->seller1)->postJson('/api/v1/activities', [
            'title' => 'Own client',
            'type' => 'task',
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'client_id' => $ownClient->id,
        ]);
        $accepted->assertCreated();
    }
}
