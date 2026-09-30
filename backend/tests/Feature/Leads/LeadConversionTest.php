<?php

namespace Tests\Feature\Leads;

use App\Actions\Leads\ConvertLead;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsTenantContext;
use Tests\TestCase;
use Throwable;

class LeadConversionTest extends TestCase
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
        $seller = User::factory()->for($this->company)->create(['role' => UserRole::Seller]);
        $this->lead = Lead::factory()->create([
            'name' => 'Jane Prospect',
            'email' => 'jane@prospect.test',
            'phone' => '11999999999',
            'notes' => 'Interested in premium plan',
            'estimated_value' => '2500.00',
            'user_id' => $seller->id,
        ]);
    }

    public function test_converts_a_lead_successfully(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['lead', 'client', 'opportunity'], 'message']);
    }

    public function test_creates_a_client_with_fields_derived_from_the_lead(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $response->assertJsonPath('data.client.name', 'Jane Prospect');
        $response->assertJsonPath('data.client.email', 'jane@prospect.test');
        $response->assertJsonPath('data.client.phone', '11999999999');
        $response->assertJsonPath('data.client.notes', 'Interested in premium plan');
        $response->assertJsonPath('data.client.type', 'individual');
        $response->assertJsonPath('data.client.document', null);
        $response->assertJsonPath('data.client.assigned_to.id', $this->lead->user_id);
        $this->assertDatabaseHas('clients', [
            'name' => 'Jane Prospect',
            'company_id' => $this->company->id,
            'user_id' => $this->lead->user_id,
        ]);
    }

    public function test_client_document_and_type_can_be_provided_optionally(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'client_document' => '12345678900',
            'client_type' => 'company',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.client.document', '12345678900');
        $response->assertJsonPath('data.client.type', 'company');
    }

    public function test_creates_an_opportunity_with_stage_new_and_fields_derived_from_the_lead(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.stage', 'new');
        $response->assertJsonPath('data.opportunity.title', 'Jane Prospect');
        $response->assertJsonPath('data.opportunity.value', '2500.00');
        $response->assertJsonPath('data.opportunity.assigned_to.id', $this->lead->user_id);
        $response->assertJsonPath('data.opportunity.client.id', $response->json('data.client.id'));
    }

    public function test_opportunity_title_expected_close_date_and_notes_can_be_overridden(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'opportunity_title' => 'Custom Deal Title',
            'expected_close_date' => '2026-12-31',
            'notes' => 'Follow up next quarter',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.title', 'Custom Deal Title');
        $response->assertJsonPath('data.opportunity.notes', 'Follow up next quarter');
        $this->assertStringStartsWith('2026-12-31', $response->json('data.opportunity.expected_close_date'));
    }

    public function test_opportunity_value_can_be_customized_and_is_preserved_as_a_decimal_string(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'opportunity_value' => '9999.99',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.value', '9999.99');
        $this->assertIsString($response->json('data.opportunity.value'));
    }

    public function test_opportunity_value_defaults_to_the_leads_estimated_value_as_a_decimal_string(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $response->assertJsonPath('data.opportunity.value', '2500.00');
        $this->assertIsString($response->json('data.opportunity.value'));
    }

    public function test_converting_does_not_change_the_leads_own_estimated_value(): void
    {
        $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'opportunity_value' => '1.00',
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $this->lead->id,
            'estimated_value' => '2500.00',
        ]);
    }

    public function test_lead_status_becomes_converted(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $response->assertJsonPath('data.lead.status', 'converted');
        $this->assertDatabaseHas('leads', ['id' => $this->lead->id, 'status' => 'converted']);
    }

    public function test_lead_is_not_deleted_by_conversion(): void
    {
        $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $this->assertDatabaseHas('leads', ['id' => $this->lead->id, 'deleted_at' => null]);
    }

    public function test_records_traceability_in_lead_conversions(): void
    {
        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertCreated();
        $clientId = $response->json('data.client.id');
        $opportunityId = $response->json('data.opportunity.id');

        $this->assertDatabaseHas('lead_conversions', [
            'lead_id' => $this->lead->id,
            'client_id' => $clientId,
            'opportunity_id' => $opportunityId,
            'converted_by' => $this->admin->id,
            'company_id' => $this->company->id,
        ]);

        $conversion = LeadConversion::where('lead_id', $this->lead->id)->first();
        $this->assertNotNull($conversion->converted_at);
    }

    public function test_duplicate_client_document_within_the_same_tenant_returns_422(): void
    {
        Client::factory()->create(['document' => '12345678900']);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'client_document' => '12345678900',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['client_document']);
        $this->assertDatabaseHas('leads', ['id' => $this->lead->id, 'status' => 'new']);
    }

    public function test_client_document_can_repeat_across_different_tenants(): void
    {
        $otherCompany = Company::factory()->create();
        $this->setTenantContext($otherCompany);
        Client::factory()->create(['document' => '12345678900']);
        $this->setTenantContext($this->company);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert", [
            'client_document' => '12345678900',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.client.document', '12345678900');
    }

    public function test_converting_an_already_converted_lead_returns_409(): void
    {
        $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert")->assertCreated();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/leads/{$this->lead->id}/convert");

        $response->assertStatus(409);
        $this->assertSame(1, LeadConversion::where('lead_id', $this->lead->id)->count());
    }

    public function test_transaction_rolls_back_everything_if_client_creation_fails(): void
    {
        // Drives the Action directly (bypassing ConvertLeadRequest's own
        // duplicate-document pre-check) so we can force a genuine DB-level
        // failure *inside* the transaction — a unique constraint violation on
        // clients(company_id, document) always throws regardless of SQL
        // mode, unlike e.g. an over-length string, which MySQL may just
        // truncate. This proves the transaction itself is the real safety
        // net, not only the FormRequest validation in front of it.
        Client::factory()->create(['document' => '12345678900']);

        $convertLead = app(ConvertLead::class);

        try {
            $convertLead->handle($this->lead, [
                'client_document' => '12345678900',
            ], $this->admin);
            $this->fail('Expected Client creation to fail on the duplicate document and roll back the transaction.');
        } catch (Throwable) {
            // expected: a real unique-constraint QueryException
        }

        $this->assertDatabaseHas('leads', ['id' => $this->lead->id, 'status' => 'new']);
        $this->assertDatabaseCount('clients', 1); // only the pre-existing one
        $this->assertDatabaseCount('opportunities', 0);
        $this->assertDatabaseCount('lead_conversions', 0);
    }
}
