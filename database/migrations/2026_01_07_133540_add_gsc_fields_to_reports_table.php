<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'gsc_connected')) {
                $table->boolean('gsc_connected')->default(false)->after('access_token');
            }
            if (!Schema::hasColumn('reports', 'gsc_property')) {
                $table->string('gsc_property', 255)->nullable()->after('gsc_connected');
            }
            if (!Schema::hasColumn('reports', 'gsc_connected_at')) {
                $table->timestamp('gsc_connected_at')->nullable()->after('gsc_property');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'gsc_connected_at')) $table->dropColumn('gsc_connected_at');
            if (Schema::hasColumn('reports', 'gsc_property')) $table->dropColumn('gsc_property');
            if (Schema::hasColumn('reports', 'gsc_connected')) $table->dropColumn('gsc_connected');
        });
    }
};
