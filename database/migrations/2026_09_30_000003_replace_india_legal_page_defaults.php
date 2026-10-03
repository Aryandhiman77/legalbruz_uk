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

            if (! $existing || ! $this->containsIndiaSiteCopy((string) $existing->content)) {
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

    private function containsIndiaSiteCopy(string $content): bool
    {
        return str_contains($content, 'laws of India')
            || str_contains($content, 'Indian Rupees')
            || str_contains($content, 'Bar Council of India')
            || str_contains($content, 'operating in India')
            || str_contains($content, 'Legal Bruz  LLP');
    }
};
