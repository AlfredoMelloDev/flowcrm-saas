<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissingException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_it_starts_unset(): void
    {
        $context = new TenantContext;

        $this->assertFalse($context->check());
        $this->assertNull($context->get());
    }

    public function test_set_and_get_return_the_company_id(): void
    {
        $context = new TenantContext;

        $context->set('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertTrue($context->check());
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $context->get());
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $context->id());
    }

    public function test_id_throws_when_context_is_unset(): void
    {
        $context = new TenantContext;

        $this->expectException(TenantContextMissingException::class);

        $context->id();
    }

    public function test_clear_resets_the_context(): void
    {
        $context = new TenantContext;
        $context->set('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $context->clear();

        $this->assertFalse($context->check());
        $this->assertNull($context->get());
    }

    public function test_container_resolves_the_same_singleton_instance(): void
    {
        $first = app(TenantContext::class);
        $first->set('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $second = app(TenantContext::class);

        $this->assertSame($first, $second);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $second->id());
    }
}
