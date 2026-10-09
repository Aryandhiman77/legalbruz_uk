<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discount_coupons') && Schema::hasColumn('discount_coupons', 'auto_apply')) {
            DB::table('discount_coupons')->where('auto_apply', false)->update([
                'auto_apply' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Previous values cannot be reconstructed safely, so rollback keeps
        // existing coupons enabled instead of silently disabling offers.
    }
};
