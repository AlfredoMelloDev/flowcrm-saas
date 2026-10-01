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
        Schema::table('opportunities', function (Blueprint $table) {
            $table->index(['company_id', 'closed_at']);
        });

        Schema::table('lead_conversions', function (Blueprint $table) {
            $table->index(['company_id', 'converted_at']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['company_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'closed_at']);
        });

        Schema::table('lead_conversions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'converted_at']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'completed_at']);
        });
    }
};
