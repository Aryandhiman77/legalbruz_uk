<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trademark_pricings')) {
            return;
        }

        $now = now();
        $plans = [
            ['key' => 'uk_search', 'label' => 'UK Trade Mark Search', 'amount' => 149, 'sort_order' => 1],
            ['key' => 'uk_application', 'label' => 'UK Trade Mark Application', 'amount' => 399, 'sort_order' => 2],
            ['key' => 'uk_examination_response', 'label' => 'Examination Response', 'amount' => 249, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            DB::table('trademark_pricings')->updateOrInsert(
                ['key' => $plan['key']],
                $plan + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trademark_pricings')) {
            DB::table('trademark_pricings')
                ->whereIn('key', ['uk_search', 'uk_application', 'uk_examination_response'])
                ->delete();
        }
    }
};
