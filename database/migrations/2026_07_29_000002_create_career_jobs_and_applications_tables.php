<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('location');
            $table->string('employment_type', 60)->default('Full-time');
            $table->string('workplace_type', 60)->default('On-site');
            $table->string('experience_level', 100)->nullable();
            $table->string('salary_range', 120)->nullable();
            $table->text('summary');
            $table->longText('description');
            $table->longText('responsibilities')->nullable();
            $table->longText('requirements');
            $table->longText('benefits')->nullable();
            $table->date('application_deadline')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('career_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_job_id')->constrained('career_jobs')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 190);
            $table->string('phone', 30);
            $table->string('current_location', 180)->nullable();
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->longText('cover_letter');
            $table->string('resume_path', 500);
            $table->string('resume_original_name', 255);
            $table->string('status', 40)->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['career_job_id', 'email']);
        });

        $now = now();
        $jobs = [
            [
                'title' => 'Trademark Legal Associate',
                'location' => 'Ambala Cantt, Haryana',
                'employment_type' => 'Full-time',
                'workplace_type' => 'On-site',
                'experience_level' => '1–3 years',
                'summary' => 'Support trademark filings, prosecution, objections, and client matters as part of our growing intellectual property practice.',
                'description' => 'You will work with our legal and operations teams to manage trademark matters from intake through filing and post-filing follow-up. This role suits someone who enjoys clear drafting, careful research, and client-focused problem solving.',
                'responsibilities' => "Conduct trademark searches and prepare clear findings.\nDraft and review applications, replies, and supporting documents.\nTrack deadlines and maintain accurate matter records.\nCoordinate with clients to collect instructions and documentation.\nMonitor Registry updates and communicate next steps.",
                'requirements' => "LL.B. from a recognised institution.\n1–3 years of relevant legal or intellectual property experience.\nStrong legal research, drafting, and communication skills.\nExcellent attention to detail and ownership of deadlines.\nComfort working with online legal and case-management systems.",
                'benefits' => "Structured learning and mentorship.\nExposure to a broad range of trademark matters.\nCollaborative and technology-forward work environment.\nPerformance-led growth opportunities.",
                'sort_order' => 10,
            ],
            [
                'title' => 'Client Success Executive',
                'location' => 'Ambala Cantt, Haryana',
                'employment_type' => 'Full-time',
                'workplace_type' => 'Hybrid',
                'experience_level' => '0–2 years',
                'summary' => 'Guide clients through their Legal Bruz journey and ensure every interaction is timely, helpful, and easy to understand.',
                'description' => 'You will be the bridge between clients and our legal operations team. You will help clients understand process steps, collect required information, and keep matters moving with thoughtful follow-up.',
                'responsibilities' => "Respond to client enquiries across phone, email, and platform channels.\nExplain service stages and required next actions in plain language.\nMaintain accurate communication and follow-up records.\nWork with internal teams to resolve client issues promptly.\nIdentify recurring questions and improve support resources.",
                'requirements' => "Graduate in any discipline.\nClear written and spoken English and Hindi.\nEmpathy, patience, and confident problem-solving skills.\nStrong organisation and follow-up habits.\nPrior customer success or legal-services experience is helpful.",
                'benefits' => "Hands-on training.\nHybrid working flexibility after onboarding.\nSupportive team culture.\nClear performance and growth pathways.",
                'sort_order' => 20,
            ],
            [
                'title' => 'Digital Marketing Specialist',
                'location' => 'United Kingdom',
                'employment_type' => 'Full-time',
                'workplace_type' => 'Remote',
                'experience_level' => '2–4 years',
                'summary' => 'Build campaigns and educational content that help UK businesses understand and protect their intellectual property.',
                'description' => 'You will own performance marketing and content distribution across key digital channels, working closely with leadership to turn complex legal topics into useful, measurable campaigns.',
                'responsibilities' => "Plan and optimise paid search and social campaigns.\nBuild content calendars with the legal team.\nTrack acquisition, conversion, and campaign performance.\nImprove landing pages through testing and insight.\nMaintain consistent brand messaging across channels.",
                'requirements' => "2–4 years of hands-on digital marketing experience.\nWorking knowledge of paid media, analytics, SEO, and conversion optimisation.\nStrong copy and communication judgment.\nAbility to turn performance data into practical actions.\nExperience in legal, fintech, or professional services is a plus.",
                'benefits' => "Remote-first collaboration.\nOwnership of meaningful growth projects.\nLearning budget and performance incentives.\nFlexible, outcome-focused environment.",
                'sort_order' => 30,
            ],
        ];

        foreach ($jobs as $job) {
            DB::table('career_jobs')->insert(array_merge($job, [
                'slug' => Str::slug($job['title']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('career_applications');
        Schema::dropIfExists('career_jobs');
    }
};
