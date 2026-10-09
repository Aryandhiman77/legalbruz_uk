<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('contact_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->string('business_name', 180)->nullable();
            $table->string('service_topic', 120);
            $table->date('preferred_date');
            $table->string('preferred_time', 80);
            $table->text('message');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('GBP');
            $table->string('payment_status', 30)->default('pending');
            $table->string('razorpay_order_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payment_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_bookings');
    }
};
