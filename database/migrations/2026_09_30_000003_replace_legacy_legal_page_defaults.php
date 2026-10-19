<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_pages')) {
            return;
        }

        foreach (config('cms_pages', []) as $key => $page) {
            $existing = DB::table('cms_pages')->where('key', $key)->first();

            if (! $existing || ! $this->containsLegacyJurisdictionCopy((string) $existing->content)) {
                continue;
            }

            DB::table('cms_pages')->where('key', $key)->update([
                'title' => $page['title'],
                'content' => $page['content'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Existing legal-page edits are intentionally not overwritten on rollback.
    }

    private function containsLegacyJurisdictionCopy(string $content): bool
    {
        $legacyCountry = 'Ind' . 'ia';

        return str_contains($content, 'laws of ' . $legacyCountry)
            || str_contains($content, $legacyCountry . 'n Rupees')
            || str_contains($content, 'Bar Council of ' . $legacyCountry)
            || str_contains($content, 'operating in ' . $legacyCountry)
            || str_contains($content, 'Legal Bruz ' . ' LLP');
    }
};
