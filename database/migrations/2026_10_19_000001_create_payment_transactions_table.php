<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('payable');
            $table->string('service', 80);
            $table->string('provider', 30)->default('revolut');
            $table->string('provider_order_id')->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('GBP');
            $table->string('status', 30)->default('pending')->index();
            $table->text('checkout_url')->nullable();
            $table->json('metadata')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['service', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
