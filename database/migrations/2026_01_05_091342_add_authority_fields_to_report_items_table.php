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
        Schema::table('report_items', function (Blueprint $table) {
            //
			 if (!Schema::hasColumn('report_items', 'da')) {
            $table->unsignedSmallInteger('da')->nullable();
			}
			if (!Schema::hasColumn('report_items', 'authority_final')) {
				$table->unsignedSmallInteger('authority_final')->nullable();
			}
			if (!Schema::hasColumn('report_items', 'authority_source')) {
				$table->string('authority_source', 50)->nullable();
			}
			if (!Schema::hasColumn('report_items', 'traffic_estimated')) {
				$table->unsignedInteger('traffic_estimated')->nullable();
			}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            //
        });
    }
};
