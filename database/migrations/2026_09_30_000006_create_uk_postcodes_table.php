<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uk_postcodes', function (Blueprint $table) {
            $table->id();
            $table->string('postcode_key', 10)->unique();
            $table->string('postcode', 10);
            $table->string('nation', 32);
            $table->string('region')->nullable();
            $table->string('town_city');
            $table->string('county')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uk_postcodes');
    }
};
