<?php

namespace App\Http\Requests\Activity;

use App\Enums\ActivityType;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends ActivityRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('activity'));
    }

    /**
     * Partial update (PATCH only). "status" and "completed_at" are
     * deliberately absent from these rules — an Activity only moves between
     * pending/completed through POST .../complete and .../reopen, which
     * derive both fields together. Accepting them here would let a client
     * set status=completed without completed_at ever being set (or the
     * reverse), an inconsistent state this API never allows.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', Rule::enum(ActivityType::class)],
            'description' => ['sometimes', 'nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
            'lead_id' => array_merge(['sometimes', 'nullable'], $this->leadRule()),
            'client_id' => array_merge(['sometimes', 'nullable'], $this->clientRule()),
            'opportunity_id' => array_merge(['sometimes', 'nullable'], $this->opportunityRule()),
            'user_id' => $this->requiredActiveAssignmentRule(required: false),
        ];
    }
}
