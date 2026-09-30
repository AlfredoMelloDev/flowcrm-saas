<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;

class LeadConversionConcurrencyTest extends TestCase
{
    use RefreshDatabase, SetsTenantContext;

    private Company $company;

    private User $admin;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['role' => UserRole::Admin]);

        $this->setTenantContext($this->company);
        $this->lead = Lead::factory()->create();
    }

    public function test_a_second_conversion_attempt_returns_409_and_creates_nothing_new(): void
    {
        $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert")->assertCreated();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertStatus(409);
        $this->assertSame(1, LeadConversion::where('lead_id', $this->lead->id)->count());
        $this->assertSame(1, Client::count());
        $this->assertSame(1, Opportunity::count());
    }

    /**
     * A genuine two-connections-racing scenario is not realistically
     * simulable inside a single PHPUnit test: RefreshDatabase wraps each
     * test in one transaction on one connection, so there is no second,
     * independent DB connection to race against this one without spinning up
     * real parallel processes — which would be flaky and environment-
     * dependent rather than a trustworthy assertion. A test that just calls
     * the endpoint twice *sequentially* (as above) proves the fast/common
     * path (already-converted rejected) but proves nothing about an actual
     * race between two overlapping requests.
     *
     * So instead of faking concurrency, this test proves the piece that
     * makes the race safe even if it happens: the UNIQUE(lead_id) constraint
     * on lead_conversions rejects a second row for the same Lead at the
     * database engine level, regardless of what any application code checked
     * beforehand.
     *
     * The other half of the guarantee — App\Actions\Leads\ConvertLead calling
     * Lead::whereKey($lead->id)->lockForUpdate() inside DB::transaction()
     * before checking status — is standard, well-documented InnoDB
     * row-locking behavior (a second transaction's own `SELECT ... FOR
     * UPDATE` against the same row blocks until the first commits or rolls
     * back) and is validated here by code review of that Action, not by a
     * PHPUnit assertion: see app/Actions/Leads/ConvertLead.php.
     */
    public function test_the_database_rejects_a_second_lead_conversions_row_for_the_same_lead(): void
    {
        $client = Client::factory()->create();
        $opportunity = Opportunity::factory()->create(['client_id' => $client->id]);

        LeadConversion::create([
            'lead_id' => $this->lead->id,
            'client_id' => $client->id,
            'opportunity_id' => $opportunity->id,
            'converted_by' => $this->admin->id,
            'converted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        LeadConversion::create([
            'lead_id' => $this->lead->id,
            'client_id' => $client->id,
            'opportunity_id' => $opportunity->id,
            'converted_by' => $this->admin->id,
            'converted_at' => now(),
        ]);
    }
}
