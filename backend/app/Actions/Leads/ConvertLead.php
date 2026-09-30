<?php

namespace App\Actions\Leads;

use App\Enums\LeadStatus;
use App\Exceptions\LeadAlreadyConvertedException;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvertLead
{
    /**
     * Creates a Client and an Opportunity from a Lead, marks the Lead as
     * Converted, and records the event — all inside one transaction, so a
     * failure anywhere leaves no Client, no Opportunity, no status change,
     * and no lead_conversions row behind.
     *
     * lockForUpdate() re-reads the Lead row under a row lock: a second,
     * near-simultaneous call for the same Lead blocks here until the first
     * transaction commits (or rolls back), then observes the now-committed
     * status and fails cleanly instead of racing to create a second Client/
     * Opportunity. The UNIQUE constraint on lead_conversions.lead_id is the
     * backstop in case anything ever bypasses the lock.
     *
     * @param  array<string, mixed>  $data  Validated ConvertLeadRequest input.
     * @return array{lead: Lead, client: Client, opportunity: Opportunity, conversion: LeadConversion}
     */
    public function handle(Lead $lead, array $data, User $actor): array
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($lead->status === LeadStatus::Converted) {
                throw new LeadAlreadyConvertedException;
            }

            $client = Client::create([
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'notes' => $lead->notes,
                'user_id' => $lead->user_id,
                'document' => $data['client_document'] ?? null,
                'type' => $data['client_type'] ?? 'individual',
            ]);

            $opportunity = Opportunity::create([
                'title' => $data['opportunity_title'] ?? $lead->name,
                'client_id' => $client->id,
                'user_id' => $lead->user_id,
                'value' => array_key_exists('opportunity_value', $data)
                    ? $data['opportunity_value']
                    : $lead->estimated_value,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $lead->update(['status' => LeadStatus::Converted]);

            $conversion = LeadConversion::create([
                'lead_id' => $lead->id,
                'client_id' => $client->id,
                'opportunity_id' => $opportunity->id,
                'converted_by' => $actor->id,
                'converted_at' => now(),
            ]);

            return [
                'lead' => $lead,
                'client' => $client,
                'opportunity' => $opportunity,
                'conversion' => $conversion,
            ];
        });
    }
}
