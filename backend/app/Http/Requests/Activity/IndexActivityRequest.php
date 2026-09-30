<?php

namespace App\Http\Requests\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexActivityRequest extends FormRequest
{
    /**
     * Allowlist of columns the client may sort by — never pass the raw
     * "sort" input straight into orderBy().
     */
    public const SORTABLE_COLUMNS = ['title', 'type', 'status', 'scheduled_at', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Activity::class);
    }

    /**
     * "window" is computed entirely server-side (see ActivityController) —
     * the client only ever sends the literal string, never a computed date,
     * so no client clock/timezone is ever involved in deciding what "today"
     * or "overdue" means.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'type' => ['sometimes', 'nullable', Rule::enum(ActivityType::class)],
            'status' => ['sometimes', 'nullable', Rule::enum(ActivityStatus::class)],
            'user_id' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'lead_id' => [
                'sometimes',
                'nullable',
                Rule::exists('leads', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'client_id' => [
                'sometimes',
                'nullable',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'opportunity_id' => [
                'sometimes',
                'nullable',
                Rule::exists('opportunities', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ],
            'window' => ['sometimes', 'nullable', Rule::in(['today', 'upcoming', 'overdue'])],
            'sort' => ['sometimes', Rule::in(self::SORTABLE_COLUMNS)],
            'order' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
