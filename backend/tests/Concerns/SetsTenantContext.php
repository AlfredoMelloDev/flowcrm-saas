<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;

trait SetsTenantContext
{
    /**
     * BelongsToCompany assigns company_id authoritatively from TenantContext
     * on create — factories for tenant-scoped models need it set explicitly
     * before use, the same way the real SetTenantContext middleware would.
     */
    protected function setTenantContext(Company $company): void
    {
        app(TenantContext::class)->set($company->id);
    }
}
