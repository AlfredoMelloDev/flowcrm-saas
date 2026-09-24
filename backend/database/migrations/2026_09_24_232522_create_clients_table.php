<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('document', 20)->nullable();
            $table->string('type', 20)->default('individual');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Per company, not global: the same document may legitimately be
            // a client of two different companies using FlowCRM. A soft-deleted
            // client's row still exists physically, so this naturally keeps
            // its document reserved within that company (not reusable) —
            // no extra handling needed for that behavior.
            $table->unique(['company_id', 'document']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
