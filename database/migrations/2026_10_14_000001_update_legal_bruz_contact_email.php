<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyAddress = implode('@', ['info', 'legalbruz.com']);
        $newAddress = 'info@legalbruz.co.uk';

        DB::table('faqs')
            ->where('answer', 'like', "%{$legacyAddress}%")
            ->orderBy('id')
            ->chunkById(100, function ($faqs) use ($legacyAddress, $newAddress): void {
                foreach ($faqs as $faq) {
                    DB::table('faqs')
                        ->where('id', $faq->id)
                        ->update([
                            'answer' => str_replace($legacyAddress, $newAddress, $faq->answer),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        $legacyAddress = implode('@', ['info', 'legalbruz.com']);
        $newAddress = 'info@legalbruz.co.uk';

        DB::table('faqs')
            ->where('answer', 'like', "%{$newAddress}%")
            ->orderBy('id')
            ->chunkById(100, function ($faqs) use ($legacyAddress, $newAddress): void {
                foreach ($faqs as $faq) {
                    DB::table('faqs')
                        ->where('id', $faq->id)
                        ->update([
                            'answer' => str_replace($newAddress, $legacyAddress, $faq->answer),
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
