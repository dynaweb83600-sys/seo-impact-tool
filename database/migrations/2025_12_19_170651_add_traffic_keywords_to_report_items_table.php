<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->unsignedBigInteger('organic_keywords')->nullable()->after('inbound_links');
            $table->unsignedBigInteger('traffic_estimated')->nullable()->after('organic_keywords');
            $table->decimal('traffic_etv', 10, 2)->nullable()->after('traffic_estimated');
        });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->dropColumn([
                'organic_keywords',
                'traffic_estimated',
                'traffic_etv',
            ]);
        });
    }
};
