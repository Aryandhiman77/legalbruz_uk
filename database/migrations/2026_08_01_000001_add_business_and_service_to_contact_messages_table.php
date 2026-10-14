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
            $table->string('business_name', 180)->nullable()->after('phone');
            $table->string('service_interested', 180)->nullable()->after('business_name');
        });

        DB::table('faqs')
            ->where('answer', 'like', '%support@legalbruz.com%')
            ->update([
                'answer' => DB::raw("REPLACE(answer, 'support@legalbruz.com', 'info@legalbruz.co.uk')"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('faqs')
            ->where('answer', 'like', '%info@legalbruz.co.uk%')
            ->update([
                'answer' => DB::raw("REPLACE(answer, 'info@legalbruz.co.uk', 'support@legalbruz.com')"),
                'updated_at' => now(),
            ]);

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['business_name', 'service_interested']);
        });
    }
};
