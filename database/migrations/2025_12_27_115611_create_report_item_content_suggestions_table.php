<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_item_content_suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_item_id')
                ->constrained('report_items')
                ->cascadeOnDelete();

            $table->string('type', 20); // page|article
            $table->unsignedTinyInteger('priority_score')->default(0); // 0-100
            $table->string('priority_label', 10)->nullable(); // P1|P2|P3
            $table->string('intent', 30)->nullable(); // transactionnel|commercial|informationnel|navigation

            $table->string('primary_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();

            $table->string('suggested_title')->nullable();
            $table->string('suggested_slug')->nullable();
            $table->string('target_url_hint')->nullable();

            $table->json('outline_h2')->nullable();
            $table->json('questions_faq')->nullable();

            $table->json('internal_links_to')->nullable();
            $table->json('internal_links_from')->nullable();

            $table->decimal('estimated_etv_gain', 10, 2)->nullable();
            $table->string('difficulty_hint', 20)->nullable(); // easy|medium|hard

            $table->text('why')->nullable();
            $table->json('proof')->nullable();
            $table->json('sources')->nullable();
            $table->json('raw_json')->nullable();

            $table->timestamp('ai_generated_at')->nullable();
            $table->timestamps();

            $table->index(['report_item_id', 'type']);
            $table->index(['report_item_id', 'priority_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_item_content_suggestions');
    }
};
