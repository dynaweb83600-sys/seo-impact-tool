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
			$table->integer('traffic_visits')->nullable()->after('traffic_etv');
		});
	}

	public function down(): void
	{
		Schema::table('report_items', function (Blueprint $table) {
			$table->dropColumn('traffic_visits');
		});
	}

};
