<?php

namespace App\Http\Requests\Opportunity;

use App\Models\Opportunity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PipelineOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Opportunity::class);
    }

    /**
     * No "stage" filter (the whole point is returning every stage at once
     * for the board) and no pagination params — the pipeline is meant to be
     * fetched in full and grouped into columns client-side.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'user_id' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
        ];
    }
}
