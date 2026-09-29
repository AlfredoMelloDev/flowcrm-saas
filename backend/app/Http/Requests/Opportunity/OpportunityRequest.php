<?php

namespace App\Http\Requests\Opportunity;

use App\Http\Requests\Concerns\ValidatesAssignment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class OpportunityRequest extends FormRequest
{
    use ValidatesAssignment;

    /**
     * "client_id" must exist, belong to the current tenant, and not be
     * soft-deleted — unlike the document-uniqueness check on Client (which
     * deliberately includes soft-deleted rows), a removed client is never a
     * valid target for a new or updated Opportunity. For a Seller, it must
     * also be one of their own clients — otherwise they could create an
     * Opportunity against a client they aren't allowed to see, sidestepping
     * ownership through an indirect path. The generic "invalid" message
     * covers all three failure reasons identically, so it never reveals
     * which one applied (existence, tenant, or ownership).
     *
     * @return array<int, mixed>
     */
    protected function clientRule(): array
    {
        $user = $this->user();

        return [
            Rule::exists('clients', 'id')->where(function ($query) use ($user) {
                $query->where('company_id', app(TenantContext::class)->id())
                    ->whereNull('deleted_at');

                if ($user->isSeller()) {
                    $query->where('user_id', $user->id);
                }
            }),
        ];
    }
}
