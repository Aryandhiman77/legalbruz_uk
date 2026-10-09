<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'discount_coupon_id')) {
                $table->unsignedBigInteger('discount_coupon_id')->nullable()->after('payment_type')->index();
            }
            if (! Schema::hasColumn('payments', 'coupon_code')) {
                $table->string('coupon_code', 50)->nullable()->after('discount_coupon_id');
            }
            if (! Schema::hasColumn('payments', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->nullable()->after('coupon_code');
            }
            if (! Schema::hasColumn('payments', 'discounted_total_amount')) {
                $table->decimal('discounted_total_amount', 10, 2)->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $columns = collect(['discount_coupon_id', 'coupon_code', 'discount_amount', 'discounted_total_amount'])
            ->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('payments', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
