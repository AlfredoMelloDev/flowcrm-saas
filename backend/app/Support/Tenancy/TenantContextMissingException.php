<?php

namespace App\Support\Tenancy;

use RuntimeException;

class TenantContextMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant context is not set. A company_id must be resolved before querying tenant-scoped models.');
    }
}
