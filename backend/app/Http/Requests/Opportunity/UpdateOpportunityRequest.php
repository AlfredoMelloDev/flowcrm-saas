<?php

namespace App\Http\Requests\Opportunity;

use App\Enums\OpportunityStage;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends OpportunityRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    /**
     * Partial update (PATCH only): every field is "sometimes". "lost_reason"
     * is required only when *this request* sets stage to "lost" — if stage
     * is omitted (e.g. a request that only changes "title"), required_if
     * never triggers, so an already-lost Opportunity is never forced to
     * resend its reason just to edit an unrelated field.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'client_id' => array_merge(['sometimes'], $this->clientRule()),
            'stage' => ['sometimes', Rule::enum(OpportunityStage::class)],
            'value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            // No "sometimes" here on purpose: "sometimes" skips validation
            // entirely (including required_if) when the field is absent from
            // the request — which is exactly the case we need to catch
            // (stage=lost sent without lost_reason). required_if is one of
            // Laravel's "implicit" rules, designed to fire even when the
            // field is missing, precisely for this kind of conditional
            // requirement.
            'lost_reason' => ['nullable', 'string', 'max:255', 'required_if:stage,lost'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
