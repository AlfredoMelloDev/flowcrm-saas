<?php

namespace App\Http\Requests\Lead;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class LeadRequest extends FormRequest
{
    /**
     * "user_id" is only settable by Admin/Manager, and only to a user of the
     * current company — the same generic "invalid" message covers both a
     * nonexistent id and one belonging to another tenant, so the response
     * never reveals whether that id exists elsewhere. Sellers get a hard
     * "prohibited": they never choose who a lead is assigned to.
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
