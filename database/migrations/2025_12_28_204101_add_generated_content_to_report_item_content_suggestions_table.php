<?php

// database/migrations/xxxx_add_generated_content_to_report_item_content_suggestions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_item_content_suggestions', function (Blueprint $table) {
            if (!Schema::hasColumn('report_item_content_suggestions', 'generated_html')) {
                $table->longText('generated_html')->nullable();
            }
            if (!Schema::hasColumn('report_item_content_suggestions', 'generated_at')) {
                $table->dateTime('generated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_item_content_suggestions', function (Blueprint $table) {
            if (Schema::hasColumn('report_item_content_suggestions', 'generated_html')) {
                $table->dropColumn('generated_html');
            }
            if (Schema::hasColumn('report_item_content_suggestions', 'generated_at')) {
                $table->dropColumn('generated_at');
            }
        });
    }
};

