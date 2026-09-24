<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientRequest extends FormRequest
{
    /**
     * Allowlist of columns the client may sort by — never pass the raw
     * "sort" input straight into orderBy().
     */
    public const SORTABLE_COLUMNS = ['name', 'status', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Client::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', Rule::enum(ClientStatus::class)],
            'type' => ['sometimes', 'nullable', Rule::enum(ClientType::class)],
            'user_id' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'sort' => ['sometimes', Rule::in(self::SORTABLE_COLUMNS)],
            'order' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
