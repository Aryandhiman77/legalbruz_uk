<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trademark_pricings')) {
            return;
        }

        if (! Schema::hasColumn('trademark_pricings', 'features')) {
            Schema::table('trademark_pricings', function (Blueprint $table): void {
                $table->json('features')->nullable()->after('amount');
            });
        }

        $cards = [
            'opposition_service' => [
                'label' => 'Opposition Service',
                'amount' => 0,
                'sort_order' => 10,
                'features' => ['Flow A: defend your mark', 'Flow B: oppose a conflicting mark', 'Evidence and document review', 'Online case tracking'],
            ],
            'uk_application' => [
                'label' => 'UK Trade Mark Application',
                'amount' => 399,
                'sort_order' => 2,
                'features' => ['Owner and mark review', 'Classes and specification', 'Client approval', 'UKIPO filing and tracking'],
            ],
            'uk_examination_response' => [
                'label' => 'Examination Response',
                'amount' => 249,
                'sort_order' => 3,
                'features' => ['Report and objection review', 'Reply strategy and drafting', 'Client draft approval', 'Registry filing and tracking'],
            ],
        ];

        foreach ($cards as $key => $card) {
            $existing = DB::table('trademark_pricings')->where('key', $key)->first();

            if ($existing) {
                if (blank($existing->features ?? null)) {
                    DB::table('trademark_pricings')->where('key', $key)->update([
                        'features' => json_encode($card['features']),
                        'updated_at' => now(),
                    ]);
                }

                continue;
            }

            DB::table('trademark_pricings')->insert([
                'key' => $key,
                'label' => $card['label'],
                'amount' => $card['amount'],
                'features' => json_encode($card['features']),
                'is_active' => true,
                'sort_order' => $card['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trademark_pricings') && Schema::hasColumn('trademark_pricings', 'features')) {
            Schema::table('trademark_pricings', function (Blueprint $table): void {
                $table->dropColumn('features');
            });
        }
    }
};
