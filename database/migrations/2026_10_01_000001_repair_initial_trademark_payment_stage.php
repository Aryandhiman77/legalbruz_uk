<?php

use App\Support\TrademarkWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications') || ! Schema::hasTable('payments')) {
            return;
        }

        // Older UK applications were accidentally created in the final-payment
        // stage. Only reset records that have never completed any payment.
        DB::table('applications')
            ->where(function ($query) {
                $query->where('status', TrademarkWorkflow::PAYMENT_PENDING_FINAL)
                    ->orWhere('service_status', TrademarkWorkflow::PAYMENT_PENDING_FINAL);
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.application_id', 'applications.id')
                    ->whereIn('payments.status', ['completed', 'approved']);
            })
            ->update([
                'status' => TrademarkWorkflow::DRAFT,
                'service_status' => TrademarkWorkflow::DRAFT,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is a data correction. Restoring the invalid final-payment stage
        // would reintroduce the original payment bug.
    }
};
