<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_items', function (Blueprint $table) {
			$table->id();

			$table->foreignId('report_id')->constrained()->cascadeOnDelete();

			$table->string('domain');

			$table->float('da')->nullable();
			$table->float('pa')->nullable();

			$table->integer('linking_domains')->nullable();
			$table->integer('inbound_links')->nullable();

			$table->json('raw_json')->nullable();

			$table->timestamps();
		});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_items');
    }
};
