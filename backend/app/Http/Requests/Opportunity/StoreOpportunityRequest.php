<?php

namespace App\Http\Requests\Opportunity;

use App\Models\Opportunity;

class StoreOpportunityRequest extends OpportunityRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Opportunity::class);
    }

    /**
     * "stage" is intentionally not accepted here — every Opportunity is
     * created as "new" (the model default), the same way Lead/Client always
     * start at their own default status. Reaching lost/won requires an
     * explicit PATCH, which is where lost_reason/closed_at are derived.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'client_id' => array_merge(['required'], $this->clientRule()),
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
