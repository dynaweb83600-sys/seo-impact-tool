<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            if (!Schema::hasColumn('report_items', 'gsc_clicks_30d')) {
                $table->unsignedBigInteger('gsc_clicks_30d')->nullable()->after('authority_source');
            }
            if (!Schema::hasColumn('report_items', 'gsc_impressions_30d')) {
                $table->unsignedBigInteger('gsc_impressions_30d')->nullable()->after('gsc_clicks_30d');
            }
            if (!Schema::hasColumn('report_items', 'gsc_position_30d')) {
                $table->decimal('gsc_position_30d', 6, 2)->nullable()->after('gsc_impressions_30d');
            }
            if (!Schema::hasColumn('report_items', 'gsc_site_url')) {
                $table->string('gsc_site_url', 255)->nullable()->after('gsc_position_30d');
            }
            if (!Schema::hasColumn('report_items', 'gsc_updated_at')) {
                $table->timestamp('gsc_updated_at')->nullable()->after('gsc_site_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            if (Schema::hasColumn('report_items', 'gsc_updated_at')) $table->dropColumn('gsc_updated_at');
            if (Schema::hasColumn('report_items', 'gsc_site_url')) $table->dropColumn('gsc_site_url');
            if (Schema::hasColumn('report_items', 'gsc_position_30d')) $table->dropColumn('gsc_position_30d');
            if (Schema::hasColumn('report_items', 'gsc_impressions_30d')) $table->dropColumn('gsc_impressions_30d');
            if (Schema::hasColumn('report_items', 'gsc_clicks_30d')) $table->dropColumn('gsc_clicks_30d');
        });
    }
};
