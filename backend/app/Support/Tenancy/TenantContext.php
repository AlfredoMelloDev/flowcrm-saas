<?php

namespace App\Support\Tenancy;

class TenantContext
{
    private ?string $companyId = null;

    public function set(string $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }

    public function check(): bool
    {
        return $this->companyId !== null;
    }

    public function get(): ?string
    {
        return $this->companyId;
    }

    public function id(): string
    {
        return $this->companyId ?? throw new TenantContextMissingException;
    }
}
