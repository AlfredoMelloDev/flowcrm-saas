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
     * "status" can never be set to "converted" here — a Lead only reaches
     * that status through POST /leads/{lead}/convert, which bypasses this
     * FormRequest entirely and sets it directly on the model. And once a
     * Lead is already Converted, "status" is rejected outright (any target
     * value): the closure fires only when "status" is actually present in
     * the request (consistent with "sometimes"), so editing any other field
     * on an already-converted Lead is unaffected.
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
            'status' => [
                'sometimes',
                Rule::enum(LeadStatus::class)->except([LeadStatus::Converted]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->route('lead')->status === LeadStatus::Converted) {
                        $fail('A converted lead\'s status cannot be changed.');
                    }
                },
            ],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
