<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UkTimezoneDisplayTest extends TestCase
{
    public function test_application_uses_the_uk_timezone(): void
    {
        $this->assertSame('Europe/London', config('app.timezone'));
    }

    public function test_admin_timestamps_use_gmt_during_winter(): void
    {
        config(['app.timezone' => 'Europe/London']);

        $html = Blade::render(
            '<x-admin-date-time :value="$value" />',
            ['value' => Carbon::parse('2026-01-15 12:00:00', 'UTC')]
        );

        $this->assertStringContainsString('15 Jan 2026, 12:00 PM GMT', $html);
    }

    public function test_admin_timestamps_use_bst_during_summer(): void
    {
        config(['app.timezone' => 'Europe/London']);

        $html = Blade::render(
            '<x-admin-date-time :value="$value" />',
            ['value' => Carbon::parse('2026-07-15 12:00:00', 'UTC')]
        );

        $this->assertStringContainsString('15 Jul 2026, 01:00 PM BST', $html);
    }

    public function test_application_sources_do_not_hard_code_indian_time(): void
    {
        $sourceFiles = collect([
            ...File::allFiles(app_path()),
            ...File::allFiles(config_path()),
            ...File::allFiles(resource_path()),
        ])->filter(fn ($file) => in_array($file->getExtension(), ['php', 'js'], true));

        $sourceFiles->each(function ($file): void {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString('Asia/Kolkata', $contents, $file->getPathname());
            $this->assertStringNotContainsString('+05:30', $contents, $file->getPathname());
        });
    }
}
