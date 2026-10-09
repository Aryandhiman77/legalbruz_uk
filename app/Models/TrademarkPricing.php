<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TrademarkPricing extends Model
{
    public const INDIVIDUAL = 'individual';
    public const COMPANY = 'company';
    public const SEARCH = 'uk_search';
    public const APPLICATION = 'uk_application';
    public const EXAMINATION_RESPONSE = 'uk_examination_response';
    public const CONSULTATION = 'consultation_call';
    public const OPPOSITION = 'opposition_service';

    private const CACHE_KEY = 'trademark_pricing.active_plans';

    protected $fillable = [
        'key',
        'label',
        'amount',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public static function defaults(): array
    {
        return [
            self::SEARCH => [
                'key' => self::SEARCH,
                'label' => 'UK Trade Mark Search',
                'amount' => 149.00,
                'is_active' => true,
                'sort_order' => 1,
            ],
            self::APPLICATION => [
                'key' => self::APPLICATION,
                'label' => 'UK Trade Mark Application',
                'amount' => 399.00,
                'features' => ['Owner and mark review', 'Classes and specification', 'Client approval', 'UKIPO filing and tracking'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            self::EXAMINATION_RESPONSE => [
                'key' => self::EXAMINATION_RESPONSE,
                'label' => 'Examination Response',
                'amount' => 249.00,
                'features' => ['Report and objection review', 'Reply strategy and drafting', 'Client draft approval', 'Registry filing and tracking'],
                'is_active' => true,
                'sort_order' => 3,
            ],
            self::CONSULTATION => [
                'key' => self::CONSULTATION,
                'label' => 'Consultation Call',
                'amount' => 25.00,
                'is_active' => true,
                'sort_order' => 4,
            ],
            self::OPPOSITION => [
                'key' => self::OPPOSITION,
                'label' => 'Opposition Service',
                'amount' => 0.00,
                'features' => ['Flow A: defend your mark', 'Flow B: oppose a conflicting mark', 'Evidence and document review', 'Online case tracking'],
                'is_active' => true,
                'sort_order' => 10,
            ],
            self::INDIVIDUAL => [
                'key' => self::INDIVIDUAL,
                'label' => 'Individual / Proprietor / Trader',
                'amount' => 7000.00,
                'is_active' => true,
                'sort_order' => 90,
            ],
            self::COMPANY => [
                'key' => self::COMPANY,
                'label' => 'Company / LLP / Partnership / NGO',
                'amount' => 9000.00,
                'is_active' => true,
                'sort_order' => 91,
            ],
        ];
    }

    public static function flushCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            //
        }
    }

    public static function ensureDefaults(): void
    {
        if (! self::ensureTable()) {
            return;
        }

        foreach (self::defaults() as $plan) {
            $values = [
                'label' => $plan['label'],
                'amount' => $plan['amount'],
                'is_active' => $plan['is_active'],
                'sort_order' => $plan['sort_order'],
            ];

            if (Schema::hasColumn('trademark_pricings', 'features')) {
                $values['features'] = $plan['features'] ?? null;
            }

            self::query()->firstOrCreate(
                ['key' => $plan['key']],
                $values,
            );
        }

        self::flushCache();
    }

    public static function activePlans(): array
    {
        if (! self::tableIsAvailable()) {
            return self::defaults();
        }

        try {
            return Cache::rememberForever(self::CACHE_KEY, function (): array {
                $plans = self::query()
                    ->whereIn('key', array_keys(self::defaults()))
                    ->orderBy('sort_order')
                    ->get()
                    ->keyBy('key')
                    ->map(fn (self $pricing): array => [
                        'key' => $pricing->key,
                        'label' => $pricing->label,
                        'amount' => (float) $pricing->amount,
                        'features' => $pricing->features ?: (self::defaults()[$pricing->key]['features'] ?? []),
                        'is_active' => (bool) $pricing->is_active,
                        'sort_order' => (int) $pricing->sort_order,
                    ])
                    ->all();

                return array_replace(self::defaults(), $plans);
            });
        } catch (\Throwable) {
            return self::defaults();
        }
    }

    public static function amountFor(string $key): float
    {
        $plans = self::activePlans();

        return (float) ($plans[$key]['amount'] ?? self::defaults()[$key]['amount'] ?? 0);
    }

    public static function amountForApplicantType(?string $applicantType): float
    {
        return self::amountFor(self::APPLICATION);
    }

    public static function allEditablePlans(): Collection
    {
        self::ensureDefaults();

        if (! self::tableIsAvailable()) {
            return new Collection();
        }

        return self::query()
            ->whereIn('key', [self::SEARCH, self::APPLICATION, self::EXAMINATION_RESPONSE, self::CONSULTATION, self::OPPOSITION])
            ->orderBy('sort_order')
            ->get();
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::flushCache();
        });
        static::deleted(function (): void {
            self::flushCache();
        });
    }

    private static function tableIsAvailable(): bool
    {
        try {
            return Schema::hasTable('trademark_pricings');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function ensureTable(): bool
    {
        if (self::tableIsAvailable()) {
            return true;
        }

        try {
            Schema::create('trademark_pricings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->decimal('amount', 10, 2);
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            return true;
        } catch (\Throwable) {
            return self::tableIsAvailable();
        }
    }
}
