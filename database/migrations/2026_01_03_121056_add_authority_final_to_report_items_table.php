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
			$table->unsignedTinyInteger('authority_final')->nullable()->after('da');
			$table->string('authority_source', 20)->nullable()->after('authority_final');
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
