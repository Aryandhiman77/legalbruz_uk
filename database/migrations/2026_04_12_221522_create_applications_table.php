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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('trademark'); // trademark, copyright, patent
            $table->string('application_number')->nullable()->unique();
            $table->string('entity_type'); // individual or company
            $table->string('applicant_name');
            $table->string('phone');
            $table->string('email');
            $table->string('brand_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable();
            $table->string('usage_type')->nullable(); // domestic, international, or both
            $table->date('first_use_date')->nullable();
            $table->boolean('currently_selling')->default(false);
            $table->string('website')->nullable();
            $table->string('status')->default('draft'); // draft, payment_pending, pending_admin, approved, filed, registered, rejected
            $table->string('trademark_status')->nullable(); // for API integration
            $table->text('rejection_reason')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
