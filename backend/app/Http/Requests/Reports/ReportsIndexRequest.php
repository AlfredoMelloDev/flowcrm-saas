<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Concerns\ValidatesAssignment;
use Illuminate\Foundation\Http\FormRequest;

class ReportsIndexRequest extends FormRequest
{
    use ValidatesAssignment;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * "date_from"/"date_to" are plain calendar dates (no time component) —
     * the Action turns them into a start-of-day/end-of-day range in the
     * application timezone (UTC), the same "server clock only, no client
     * timezone involved" rule already used by Activity's overdue/window
     * logic. "user_id" reuses the plain assignmentRule(): Admin/Manager may
     * filter by any user of the company, Seller gets "prohibited" (never
     * allowed to look at someone else's data) and is always scoped to their
     * own records regardless, in the Action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'user_id' => $this->assignmentRule(),
        ];
    }
}
