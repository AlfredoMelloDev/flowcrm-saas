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
        Schema::create('lead_conversions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->restrictOnDelete();
            // unique: the DB-level backstop against converting the same Lead
            // twice, even under a race that somehow slips past the
            // lockForUpdate() taken on the Lead row during conversion.
            $table->foreignUlid('lead_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUlid('client_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('converted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('converted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_conversions');
    }
};
