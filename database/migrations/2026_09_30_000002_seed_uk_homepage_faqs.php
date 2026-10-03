<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $questions = [
        'What is a UK trade mark?' => 'A UK trade mark can protect a brand name, logo or other sign for the goods and services listed in the registration. UK applications are filed with the UK Intellectual Property Office (UKIPO).',
        'Do you guarantee that my application will be registered?' => 'No. Filing does not guarantee registration. The UKIPO examines every application and earlier rights holders may oppose it. We help you prepare carefully and understand the relevant risks.',
        'Are UKIPO official fees included?' => 'UKIPO official fees are charged separately unless a written quote expressly says they are included. We show professional and official fees separately before filing.',
        'Can I approve the application before it is filed?' => 'Yes. You will see the prepared details, classes and specification and must give your approval before we submit the application.',
        'How many classes do I need?' => 'That depends on the goods and services your brand covers. We review your activities and help prepare an appropriate specification without adding classes you do not genuinely need.',
        'How can I follow progress?' => 'Your documents and application progress are available online. We also keep you informed about material UKIPO updates and actions required from you.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('faqs')) {
            return;
        }

        $order = 10;

        foreach ($this->questions as $question => $answer) {
            DB::table('faqs')->updateOrInsert(
                ['question' => $question],
                [
                    'answer' => $answer,
                    'category' => 'UK Trade Marks',
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $order += 10;
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('faqs')) {
            DB::table('faqs')->whereIn('question', array_keys($this->questions))->delete();
        }
    }
};
