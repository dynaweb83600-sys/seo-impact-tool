<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->json('seed_keywords')->nullable()->after('competitors');
            $table->json('topic_profile')->nullable()->after('seed_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->dropColumn(['seed_keywords', 'topic_profile']);
        });
    }
};
