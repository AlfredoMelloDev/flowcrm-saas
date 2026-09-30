<?php

namespace App\Http\Requests\Lead;

use App\Enums\ClientType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('convert', $this->route('lead'));
    }

    /**
     * Every field here is optional — the Client and Opportunity are mostly
     * derived from the Lead itself (name/email/phone/notes/user_id/
     * estimated_value). None of company_id, lead_id, client_id, user_id,
     * stage, closed_at, lost_reason, converted_by or converted_at are
     * accepted: they're either implied by the route or always
     * server-derived.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_document' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'document')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'client_type' => ['sometimes', 'nullable', Rule::enum(ClientType::class)],
            'opportunity_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'opportunity_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
