<?php

namespace App\Http\Requests\Activity;

use App\Http\Requests\Concerns\ValidatesAssignment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ActivityRequest extends FormRequest
{
    use ValidatesAssignment;

    /**
     * Must exist, belong to the current tenant, and not be soft-deleted —
     * same shape as OpportunityRequest::clientRule(). For a Seller, also
     * restricted to their own Lead, so they can't tie an Activity to a Lead
     * they aren't otherwise allowed to see.
     *
     * @return array<int, mixed>
     */
    protected function leadRule(): array
    {
        $user = $this->user();

        return [
            Rule::exists('leads', 'id')->where(function ($query) use ($user) {
                $query->where('company_id', app(TenantContext::class)->id())
                    ->whereNull('deleted_at');

                if ($user->isSeller()) {
                    $query->where('user_id', $user->id);
                }
            }),
        ];
    }

    /**
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

    /**
     * @return array<int, mixed>
     */
    protected function opportunityRule(): array
    {
        $user = $this->user();

        return [
            Rule::exists('opportunities', 'id')->where(function ($query) use ($user) {
                $query->where('company_id', app(TenantContext::class)->id())
                    ->whereNull('deleted_at');

                if ($user->isSeller()) {
                    $query->where('user_id', $user->id);
                }
            }),
        ];
    }

    /**
     * At most one of lead_id/client_id/opportunity_id may be present at
     * once — the FormRequest-level half of the rule (the other half is the
     * CHECK constraint on the activities table itself).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $present = collect(['lead_id', 'client_id', 'opportunity_id'])
                ->filter(fn (string $field) => $this->filled($field));

            if ($present->count() > 1) {
                foreach ($present as $field) {
                    $validator->errors()->add(
                        $field,
                        'An activity can only relate to one of lead, client, or opportunity at a time.'
                    );
                }
            }
        });
    }
}
