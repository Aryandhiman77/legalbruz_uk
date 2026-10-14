<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $contentColumns = [
            'cms_pages' => ['title', 'content'],
            'blogs' => ['title', 'excerpt', 'content', 'meta_title', 'meta_description'],
            'faqs' => ['question', 'answer'],
        ];

        foreach ($contentColumns as $table => $columns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $columns = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            DB::table($table)
                ->select(array_merge(['id'], $columns))
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $columns): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            $value = $row->{$column} ?? null;

                            if (! is_string($value)) {
                                continue;
                            }

                            $updated = preg_replace(
                                '/Legal\s*Bruz\s*(?:Pvt\.?\s*Ltd\.?|Private\s+(?:Ltd\.?|Limited))/i',
                                'Legal Bruz Ltd.',
                                $value,
                            );

                            if ($updated !== null && $updated !== $value) {
                                $updates[$column] = $updated;
                            }
                        }

                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }

        if (Schema::hasTable('cache') && Schema::hasColumn('cache', 'key') && Schema::hasColumn('cache', 'value')) {
            $legacyPvtName = implode(' ', ['%Legal', 'Bruz', 'Pvt%']);
            $legacyCompactPvtName = implode(' ', ['%LegalBruz', 'Pvt%']);
            $legacyPrivateName = implode(' ', ['%Legal', 'Bruz', 'Private%']);
            $legacyCompactPrivateName = implode(' ', ['%LegalBruz', 'Private%']);

            DB::table('cache')
                ->where('key', 'like', '%legal-bruz-pvt%')
                ->orWhere('value', 'like', $legacyPvtName)
                ->orWhere('value', 'like', $legacyCompactPvtName)
                ->orWhere('value', 'like', $legacyPrivateName)
                ->orWhere('value', 'like', $legacyCompactPrivateName)
                ->delete();
        }
    }

    public function down(): void
    {
        // Legacy company names and stale cache entries are intentionally not restored.
    }
};
