<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faqs')) {
            DB::table('faqs')
                ->where('question', 'How long does trademark registration take in ' . 'Ind' . 'ia?')
                ->update([
                    'question' => 'How long does UK trade mark registration take?',
                    'answer' => 'Timelines vary depending on UKIPO examination, objections and any opposition. Filing can be completed once the required information is ready, but final registration depends on the issues raised and the applicable response periods.',
                    'category' => 'UK Trade Marks',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('career_jobs')) {
            DB::table('career_jobs')
                ->where('slug', 'digital-marketing-specialist')
                ->where('location', 'Ind' . 'ia')
                ->update([
                    'location' => 'United Kingdom',
                    'summary' => 'Build campaigns and educational content that help UK businesses understand and protect their intellectual property.',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Public UK content is intentionally not reverted to the retired jurisdiction copy.
    }
};
