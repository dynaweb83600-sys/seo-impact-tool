<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {

            // 🔹 Domaine : date de création
            if (!Schema::hasColumn('report_items', 'domain_created_at')) {
                $table->dateTime('domain_created_at')->nullable()->after('domain');
            }

            // 🔹 GSC – clics
            if (Schema::hasColumn('report_items', 'gsc_clicks_30d')) {
                $table->unsignedBigInteger('gsc_clicks_30d')->nullable()->change();
            } else {
                $table->unsignedBigInteger('gsc_clicks_30d')->nullable();
            }

            // 🔹 GSC – impressions
            if (Schema::hasColumn('report_items', 'gsc_impressions_30d')) {
                $table->unsignedBigInteger('gsc_impressions_30d')->nullable()->change();
            } else {
                $table->unsignedBigInteger('gsc_impressions_30d')->nullable();
            }

            // 🔹 GSC – position moyenne
            if (Schema::hasColumn('report_items', 'gsc_position_30d')) {
                $table->decimal('gsc_position_30d', 6, 2)->nullable()->change();
            } else {
                $table->decimal('gsc_position_30d', 6, 2)->nullable();
            }
        });

        // 🧹 Nettoyage des valeurs fantômes
        DB::statement("UPDATE report_items SET gsc_clicks_30d = NULL WHERE gsc_clicks_30d = 'gsc_clicks_30d'");
        DB::statement("UPDATE report_items SET gsc_impressions_30d = NULL WHERE gsc_impressions_30d = 'gsc_impressions_30d'");
        DB::statement("UPDATE report_items SET gsc_position_30d = NULL WHERE gsc_position_30d = 'gsc_position_30d'");
    }

    public function down(): void
    {
        // Pas de rollback en prod
    }
};
