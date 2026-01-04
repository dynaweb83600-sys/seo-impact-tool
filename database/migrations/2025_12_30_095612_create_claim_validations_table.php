<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('claim_validations', function (Blueprint $table) {
            $table->id();

            // Ce qu'on valide (clé de cache)
            $table->string('product', 191)->nullable();      // ex: "miel"
            $table->string('claim', 191)->nullable();        // ex: "sans gluten"
            $table->string('category', 191)->nullable();     // si tu veux plus tard (optionnel)

            // hash unique (pour cache stable)
            $table->string('cache_key', 64)->unique();

            // verdict
            $table->boolean('is_factually_correct')->nullable();     // true/false/null (incertain)
            $table->boolean('is_trivial_or_inherent')->nullable();   // true/false/null
            $table->boolean('is_good_topic')->nullable();            // true/false/null

            // raisons + propositions
            $table->text('reason')->nullable();
            $table->json('replacement_titles')->nullable();

            // trace
            $table->json('raw_validator_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_validations');
    }
};
