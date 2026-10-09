<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('read_at')->index();
        });

        if (Schema::hasColumn('trademark_search_report_requests', 'contact_message_id')) {
            $contactMessageIds = DB::table('trademark_search_report_requests')
                ->whereNotNull('contact_message_id')
                ->pluck('contact_message_id');

            if ($contactMessageIds->isNotEmpty()) {
                DB::table('contact_messages')
                    ->whereIn('id', $contactMessageIds)
                    ->update(['archived_at' => now(), 'updated_at' => now()]);
            }

            Schema::table('trademark_search_report_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contact_message_id');
            });
        }

        Schema::table('trademark_search_report_requests', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('report_status');
            $table->string('report_file_path')->nullable()->after('admin_notes');
            $table->string('report_file_name')->nullable()->after('report_file_path');
            $table->timestamp('report_uploaded_at')->nullable()->after('report_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('trademark_search_report_requests', function (Blueprint $table) {
            $table->dropColumn(['admin_notes', 'report_file_path', 'report_file_name', 'report_uploaded_at']);

            if (! Schema::hasColumn('trademark_search_report_requests', 'contact_message_id')) {
                $table->foreignId('contact_message_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
