<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

trait ValidatesAssignment
{
    /**
     * "user_id" is only settable by Admin/Manager, and only to a user of the
     * current company — the same generic "invalid" message covers both a
     * nonexistent id and one belonging to another tenant, so the response
     * never reveals whether that id exists elsewhere. Everyone else gets a
     * hard "prohibited": they never choose who a record is assigned to.
     *
     * @return array<int, mixed>
     */
    protected function assignmentRule(): array
    {
        if ($this->user()->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('company_id', app(TenantContext::class)->id())
                ),
            ];
        }

        return ['prohibited'];
    }
}
