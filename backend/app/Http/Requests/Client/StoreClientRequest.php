<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientType;
use App\Models\Client;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class StoreClientRequest extends ClientRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
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
            'document' => [
                'nullable',
                'string',
                'max:20',
                // Scoped to the current tenant, not global — the same
                // document may legitimately belong to a client of a
                // different company. Laravel's unique rule checks all rows
                // by default (soft-deleted included), which is exactly what
                // we want: a soft-deleted client's document stays reserved.
                Rule::unique('clients', 'document')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'type' => ['nullable', Rule::enum(ClientType::class)],
            'notes' => ['nullable', 'string'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
