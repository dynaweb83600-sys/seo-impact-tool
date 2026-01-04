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
	Schema::create('reports', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->nullable()
        ->index()
        ->constrained()
        ->nullOnDelete();

    $table->string('status')->default('pending')->index();
    $table->unsignedInteger('requested_count')->default(0);
    $table->unsignedInteger('processed_count')->default(0); // utile pour ta barre de progression
    $table->string('access_token', 64)->nullable()->unique();
    $table->timestamp('completed_at')->nullable();

    $table->timestamps();
});

}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
