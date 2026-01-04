<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SQLite-friendly: no "after()", and avoid duplicate columns
        if (!Schema::hasColumn('report_items', 'domain_created_at')) {
            Schema::table('report_items', function (Blueprint $table) {
                $table->date('domain_created_at')->nullable();
            });
        }

        if (!Schema::hasColumn('report_items', 'domain_age_years')) {
            Schema::table('report_items', function (Blueprint $table) {
                $table->decimal('domain_age_years', 8, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Only drop if exists (safe)
        if (Schema::hasColumn('report_items', 'domain_created_at')) {
            Schema::table('report_items', function (Blueprint $table) {
                $table->dropColumn('domain_created_at');
            });
        }

        if (Schema::hasColumn('report_items', 'domain_age_years')) {
            Schema::table('report_items', function (Blueprint $table) {
                $table->dropColumn('domain_age_years');
            });
        }
    }
};
