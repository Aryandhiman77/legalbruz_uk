<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trademark_search_report_documents', function (Blueprint $table) {
            $table->string('document_name')->nullable()->after('trademark_search_report_request_id');
        });

        DB::table('trademark_search_report_documents')
            ->whereNull('document_name')
            ->orderBy('id')
            ->each(function ($document): void {
                DB::table('trademark_search_report_documents')
                    ->where('id', $document->id)
                    ->update([
                        'document_name' => pathinfo($document->original_name, PATHINFO_FILENAME),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('trademark_search_report_documents', function (Blueprint $table) {
            $table->dropColumn('document_name');
        });
    }
};
