<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
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
            'document' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'document')
                    ->where(fn ($query) => $query->where('company_id', app(TenantContext::class)->id()))
                    ->ignore($this->route('client')),
            ],
            'type' => ['sometimes', Rule::enum(ClientType::class)],
            'status' => ['sometimes', Rule::enum(ClientStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
