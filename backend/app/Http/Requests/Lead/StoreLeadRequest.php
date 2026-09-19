<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Models\Lead;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends LeadRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lead::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
