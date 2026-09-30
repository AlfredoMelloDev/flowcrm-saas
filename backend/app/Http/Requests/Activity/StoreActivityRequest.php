<?php

namespace App\Http\Requests\Activity;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends ActivityRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Activity::class);
    }

    /**
     * "status" and "completed_at" are intentionally not accepted — every
     * Activity is created as "pending" (the model default), the same way a
     * new Lead/Client/Opportunity always starts at its own default. Reaching
     * "completed" requires an explicit PATCH .../complete.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'lead_id' => array_merge(['nullable'], $this->leadRule()),
            'client_id' => array_merge(['nullable'], $this->clientRule()),
            'opportunity_id' => array_merge(['nullable'], $this->opportunityRule()),
            'user_id' => $this->requiredActiveAssignmentRule(),
        ];
    }
}
