<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gsc_token_json')) {
                $table->json('gsc_token_json')->nullable();
            }
            if (!Schema::hasColumn('users', 'gsc_connected')) {
                $table->boolean('gsc_connected')->default(false);
            }
            if (!Schema::hasColumn('users', 'gsc_property')) {
                $table->string('gsc_property')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'gsc_token_json')) {
                $table->dropColumn('gsc_token_json');
            }
            if (Schema::hasColumn('users', 'gsc_connected')) {
                $table->dropColumn('gsc_connected');
            }
            if (Schema::hasColumn('users', 'gsc_property')) {
                $table->dropColumn('gsc_property');
            }
        });
    }
};
