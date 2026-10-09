<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trademark_search_report_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->string('brand_name', 180);
            $table->text('business_activity');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('GBP');
            $table->string('payment_status', 30)->default('pending');
            $table->string('report_status', 40)->default('awaiting_payment');
            $table->string('razorpay_order_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payment_status', 'created_at']);
            $table->index(['report_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trademark_search_report_requests');
    }
};
