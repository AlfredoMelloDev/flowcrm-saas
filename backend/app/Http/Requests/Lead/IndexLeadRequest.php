<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLeadRequest extends FormRequest
{
    /**
     * Allowlist of columns the client may sort by — never pass the raw
     * "sort" input straight into orderBy().
     */
    public const SORTABLE_COLUMNS = ['name', 'status', 'estimated_value', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Lead::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'nullable', Rule::enum(LeadStatus::class)],
            'source' => ['sometimes', 'nullable', Rule::enum(LeadSource::class)],
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
