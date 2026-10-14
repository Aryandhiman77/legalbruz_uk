<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');
            $table->string('category', 100)->default('General');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 30)->nullable();
            $table->string('subject', 180);
            $table->text('message');
            $table->string('status', 30)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        $now = now();
        DB::table('faqs')->insert([
            [
                'question' => 'What services does Legal Bruz provide?',
                'answer' => 'Legal Bruz assists with trademark searches, trademark applications, objection replies, opposition matters, and recovery of delayed or stuck trademark applications.',
                'category' => 'General',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question' => 'How long does trademark registration take in India?',
                'answer' => 'Timelines vary based on examination, objections, oppositions, and Registry processing. Filing can be completed quickly once the required information is ready, but final registration may take several months or longer.',
                'category' => 'Trademark Registration',
                'sort_order' => 20,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question' => 'Can I track my application online?',
                'answer' => 'Yes. Registered users can sign in to view their application progress, required actions, documents, and status updates from their dashboard.',
                'category' => 'Applications',
                'sort_order' => 30,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question' => 'Are government fees included in the displayed service fee?',
                'answer' => 'Government fees and professional service fees may be shown separately depending on the service. Review the payment summary before paying for the exact breakdown applicable to your request.',
                'category' => 'Payments',
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'question' => 'How can I contact the support team?',
                'answer' => 'Use the Contact page to send us a message, or email info@legalbruz.co.uk. Include your application number when contacting us about an existing matter.',
                'category' => 'Support',
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('faqs');
    }
};
