<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->restrictOnDelete();
            // Unlike Lead/Client/Opportunity, a responsible user is mandatory
            // for an Activity — it represents an action someone must
            // actually perform, so it's never left unassigned.
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            // At most one of these three may be set — enforced both here
            // (CHECK constraint, below) and in the FormRequest layer.
            // Nullable: an Activity may also relate to nothing at all (a
            // generic internal task).
            $table->foreignUlid('lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('opportunity_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'lead_id']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'opportunity_id']);
        });

        // Defense in depth, same reasoning as lead_conversions.lead_id being
        // UNIQUE in Phase 5: the FormRequest already rejects more than one
        // relation, this is the DB-level backstop that holds even if some
        // future code path ever bypasses that validation. MySQL evaluates
        // each "IS NOT NULL" as 1/0, so the sum is a plain relation count.
        DB::statement(
            'ALTER TABLE activities ADD CONSTRAINT chk_activities_single_relation CHECK ('.
            '(lead_id IS NOT NULL) + (client_id IS NOT NULL) + (opportunity_id IS NOT NULL) <= 1'.
            ')'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
