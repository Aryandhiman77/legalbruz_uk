<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['consultation_bookings', 'trademark_search_report_requests'] as $tableName) {
            if (Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'razorpay_order_id')
                && ! Schema::hasColumn($tableName, 'payment_reference')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('razorpay_order_id', 'payment_reference');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['consultation_bookings', 'trademark_search_report_requests'] as $tableName) {
            if (Schema::hasTable($tableName)
                && Schema::hasColumn($tableName, 'payment_reference')
                && ! Schema::hasColumn($tableName, 'razorpay_order_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->renameColumn('payment_reference', 'razorpay_order_id');
                });
            }
        }
    }
};
