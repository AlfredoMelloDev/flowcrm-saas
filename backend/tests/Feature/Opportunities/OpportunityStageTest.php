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

class OpportunityStageTest extends TestCase
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

    public function test_new_can_move_directly_to_won_no_state_machine(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id, 'stage' => 'new']);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'won',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'won');
    }

    public function test_moving_to_lost_requires_lost_reason(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'lost',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['lost_reason']);
    }

    public function test_moving_to_lost_with_reason_sets_closed_at(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'lost',
            'lost_reason' => 'Preço muito alto',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'lost');
        $response->assertJsonPath('data.lost_reason', 'Preço muito alto');
        $this->assertNotNull($response->json('data.closed_at'));
    }

    public function test_moving_to_won_sets_closed_at_and_forces_lost_reason_null(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'won',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'won');
        $response->assertJsonPath('data.lost_reason', null);
        $this->assertNotNull($response->json('data.closed_at'));
    }

    public function test_moving_from_won_back_to_an_open_stage_clears_closed_at(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->client->id,
            'stage' => 'won',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'negotiation',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'negotiation');
        $response->assertJsonPath('data.closed_at', null);
        $response->assertJsonPath('data.lost_reason', null);
    }

    public function test_moving_from_lost_back_to_an_open_stage_clears_closed_at_and_lost_reason(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->client->id,
            'stage' => 'lost',
            'lost_reason' => 'Sem orçamento',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'contacted',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'contacted');
        $response->assertJsonPath('data.closed_at', null);
        $response->assertJsonPath('data.lost_reason', null);
    }

    public function test_moving_from_lost_to_won_clears_lost_reason_and_refreshes_closed_at(): void
    {
        $originalClosedAt = now()->subDays(5);
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->client->id,
            'stage' => 'lost',
            'lost_reason' => 'Foi para o concorrente',
            'closed_at' => $originalClosedAt,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'won',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'won');
        $response->assertJsonPath('data.lost_reason', null);
        $this->assertNotNull($response->json('data.closed_at'));
        // closed_at represents *this* closure, not the stale lost timestamp.
        $this->assertNotEquals(
            $originalClosedAt->toISOString(),
            $response->json('data.closed_at'),
        );
    }

    public function test_partial_patch_on_already_lost_opportunity_does_not_require_lost_reason_again(): void
    {
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->client->id,
            'stage' => 'lost',
            'lost_reason' => 'Preço',
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'title' => 'Just renaming, not touching stage',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Just renaming, not touching stage');
        $response->assertJsonPath('data.stage', 'lost');
        $response->assertJsonPath('data.lost_reason', 'Preço');
    }

    public function test_partial_patch_on_already_lost_opportunity_does_not_change_closed_at(): void
    {
        $originalClosedAt = now()->subDays(3)->startOfSecond();
        $opportunity = Opportunity::factory()->create([
            'client_id' => $this->client->id,
            'stage' => 'lost',
            'lost_reason' => 'Preço',
            'closed_at' => $originalClosedAt,
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'value' => '999.00',
        ]);

        $response->assertOk();
        $this->assertSame(
            $originalClosedAt->toISOString(),
            $response->json('data.closed_at'),
        );
    }

    public function test_closed_at_is_never_accepted_from_the_request(): void
    {
        $opportunity = Opportunity::factory()->create(['client_id' => $this->client->id]);
        $forgedDate = now()->subYear()->toISOString();

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/opportunities/{$opportunity->id}", [
            'stage' => 'won',
            'closed_at' => $forgedDate,
        ]);

        $response->assertOk();
        $this->assertNotEquals($forgedDate, $response->json('data.closed_at'));
    }
}
