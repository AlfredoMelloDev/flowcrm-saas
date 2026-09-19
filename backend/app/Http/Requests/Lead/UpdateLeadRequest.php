<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends LeadRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lead'));
    }

    /**
     * Partial update (PATCH only — no PUT in this API): every field is
     * "sometimes", so the caller can send just the fields it wants changed.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'source' => ['sometimes', 'nullable', Rule::enum(LeadSource::class)],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
