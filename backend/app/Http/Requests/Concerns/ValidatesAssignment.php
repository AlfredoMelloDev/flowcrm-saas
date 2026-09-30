<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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

    /**
     * Variant of assignmentRule() for entities where a responsible user is
     * mandatory (Activity): never nullable, and the target must be an
     * *active* user of the current company — not just any user, unlike the
     * plain assignmentRule() above. A Seller still can't set this at all
     * (the controller auto-fills it with their own id on create); Admin/
     * Manager must always leave a valid, active, same-tenant user in place.
     *
     * @return array<int, mixed>
     */
    protected function requiredActiveAssignmentRule(bool $required = true): array
    {
        if ($this->user()->hasAnyRole(UserRole::Admin, UserRole::Manager)) {
            return [
                ...($required ? ['required'] : ['sometimes', 'required']),
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('company_id', app(TenantContext::class)->id())
                        ->where('status', UserStatus::Active);
                }),
            ];
        }

        return ['prohibited'];
    }
}
