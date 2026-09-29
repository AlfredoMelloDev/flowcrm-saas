<?php

namespace App\Http\Requests\Opportunity;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOpportunityRequest extends FormRequest
{
    /**
     * Allowlist of columns the client may sort by — never pass the raw
     * "sort" input straight into orderBy().
     */
    public const SORTABLE_COLUMNS = ['title', 'stage', 'value', 'expected_close_date', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Opportunity::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'stage' => ['sometimes', 'nullable', Rule::enum(OpportunityStage::class)],
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
