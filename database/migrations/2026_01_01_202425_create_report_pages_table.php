<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_pages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('url');
            $table->enum('type', ['page', 'article'])->default('page');

            $table->unsignedInteger('keywords_count')->default(0);
            $table->float('etv_sum')->default(0);
            $table->unsignedInteger('avg_position')->nullable();

            $table->string('intent')->nullable(); // info / commercial / transactionnel

            $table->timestamps();

            $table->unique(['report_item_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_pages');
    }
};
