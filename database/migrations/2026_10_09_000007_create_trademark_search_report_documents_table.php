<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trademark_search_report_documents')) {
            if (DB::table('trademark_search_report_documents')->exists()) {
                throw new RuntimeException('Cannot repair the incomplete report documents table because it contains records.');
            }

            Schema::drop('trademark_search_report_documents');
        }

        Schema::create('trademark_search_report_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trademark_search_report_request_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->foreign('trademark_search_report_request_id', 'tsr_documents_request_fk')
                ->references('id')
                ->on('trademark_search_report_requests')
                ->cascadeOnDelete();
        });

        DB::table('trademark_search_report_requests')
            ->whereNotNull('report_file_path')
            ->orderBy('id')
            ->each(function ($reportRequest): void {
                DB::table('trademark_search_report_documents')->insert([
                    'trademark_search_report_request_id' => $reportRequest->id,
                    'file_path' => $reportRequest->report_file_path,
                    'original_name' => $reportRequest->report_file_name ?: 'trademark-search-report.pdf',
                    'uploaded_at' => $reportRequest->report_uploaded_at ?: $reportRequest->updated_at,
                    'created_at' => $reportRequest->report_uploaded_at ?: $reportRequest->updated_at,
                    'updated_at' => $reportRequest->report_uploaded_at ?: $reportRequest->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('trademark_search_report_documents');
    }
};
