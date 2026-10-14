<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyNames = [
            implode(' ', ['Legal', 'Bruz', 'Pvt.', 'Ltd.']),
            implode(' ', ['LEGAL', 'BRUZ', 'PVT.', 'LTD.']),
            implode('-', ['legal', 'bruz', 'pvt', 'ltd', 'logo.png']),
        ];
        $currentNames = [
            'Legal Bruz Ltd.',
            'LEGAL BRUZ LTD.',
            'legal-bruz-ltd-logo.png',
        ];
        $contentColumns = [
            'cms_pages' => ['title', 'content'],
            'blogs' => ['title', 'excerpt', 'content', 'meta_title', 'meta_description'],
            'faqs' => ['question', 'answer'],
        ];

        foreach ($contentColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($columns === [] || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::table($table)
                ->select(array_merge(['id'], $columns))
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $columns, $legacyNames, $currentNames): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            if (! is_string($row->{$column} ?? null)) {
                                continue;
                            }

                            $updated = str_replace($legacyNames, $currentNames, $row->{$column});

                            if ($updated !== $row->{$column}) {
                                $updates[$column] = $updated;
                            }
                        }

                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Company-name changes are intentionally not rolled back in stored public content.
    }
};
