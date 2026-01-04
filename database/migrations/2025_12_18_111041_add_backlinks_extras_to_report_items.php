<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->unsignedInteger('dofollow_links')->nullable();
            $table->unsignedInteger('nofollow_links')->nullable();
            $table->json('top_anchors')->nullable();
            $table->unsignedInteger('new_backlinks_30d')->nullable();
            $table->unsignedInteger('lost_backlinks_30d')->nullable();

            $table->date('domain_created_at')->nullable();
            $table->decimal('domain_age_years', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->dropColumn([
                'dofollow_links',
                'nofollow_links',
                'top_anchors',
                'new_backlinks_30d',
                'lost_backlinks_30d',
                'domain_created_at',
                'domain_age_years',
            ]);
        });
    }
};
