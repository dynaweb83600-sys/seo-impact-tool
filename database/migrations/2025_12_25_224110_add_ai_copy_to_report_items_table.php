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
			$table->json('ai_tooltips')->nullable();
			$table->json('ai_diagnosis')->nullable();
			$table->json('ai_details')->nullable();
			$table->timestamp('ai_generated_at')->nullable();
		});
	}


    /**
     * Reverse the migrations.
     */
	public function down(): void
	{
		Schema::table('report_items', function (Blueprint $table) {
			$table->dropColumn([
				'ai_tooltips',
				'ai_diagnosis',
				'ai_details',
				'ai_generated_at',
			]);
		});
	}

};
