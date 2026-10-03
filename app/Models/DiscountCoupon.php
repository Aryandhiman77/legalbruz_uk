<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DiscountCoupon extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'applies_to',
        'applicable_users',
        'selected_user_ids',
        'usage_limit',
        'per_user_limit',
        'starts_at',
        'ends_at',
        'auto_apply',
        'stackable',
        'show_on_website',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'selected_user_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_apply' => 'boolean',
        'stackable' => 'boolean',
        'show_on_website' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getStatusLabelAttribute(): string
    {
        $currentTime = $this->currentCouponDateTime();

        if ($this->ends_at && $this->dateTimeValue($this->ends_at) <= $currentTime) {
            return 'Expired';
        }

        if (! $this->is_active) {
            return 'Inactive';
        }

        if ($this->starts_at && $this->dateTimeValue($this->starts_at) > $currentTime) {
            return 'Scheduled';
        }

        return 'Active';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status_label) {
            'Active' => 'bg-success',
            'Scheduled' => 'bg-info text-dark',
            'Expired' => 'bg-secondary',
            default => 'bg-warning text-dark',
        };
    }

    public function getDiscountLabelAttribute(): string
    {
        if ($this->discount_type === 'flat') {
            return '£' . number_format((float) $this->discount_value, 2);
        }

        return rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.') . '% OFF';
    }

    public function getAppliesToLabelAttribute(): string
    {
        return match ($this->applies_to) {
            'trademark_filing' => 'Trademark Filing',
            'uk_search' => 'UK Trade Mark Search',
            'uk_examination_response' => 'UK Examination Response',
            default => 'All Services',
        };
    }

    public function scopeVisibleOnWebsite(Builder $query): Builder
    {
        $currentTime = $this->currentCouponDateTime();

        return $query
            ->where('show_on_website', true)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($currentTime) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $currentTime);
            })
            ->where(function (Builder $query) use ($currentTime) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $currentTime);
            });
    }

    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->whereIn('applies_to', ['all_services', $service]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->where('applicable_users', 'all_users')
                ->orWhere(function (Builder $query) use ($userId) {
                    $query->where('applicable_users', 'specific_users')
                        ->where(function (Builder $query) use ($userId) {
                            $query->whereJsonContains('selected_user_ids', $userId)
                                ->orWhereJsonContains('selected_user_ids', (string) $userId);
                        });
                });
        });
    }

    public static function availableForPayment(string $service, int $userId)
    {
        if (! static::tableExists()) {
            return collect();
        }

        return static::query()
            ->visibleOnWebsite()
            ->forService($service)
            ->forUser($userId)
            ->latest()
            ->get();
    }

    public static function autoApplyForPayment(string $service, int $userId): ?self
    {
        if (! static::tableExists()) {
            return null;
        }

        return static::availableForPayment($service, $userId)
            ->where('auto_apply', true)
            ->sortByDesc(fn (self $coupon) => $coupon->discountAmountFor(100000))
            ->first();
    }

    public static function autoApplyForPublicService(string $service): ?self
    {
        if (! static::tableExists()) {
            return null;
        }

        return static::query()
            ->visibleOnWebsite()
            ->forService($service)
            ->where('applicable_users', 'all_users')
            ->where('auto_apply', true)
            ->get()
            ->sortByDesc(fn (self $coupon) => $coupon->discountAmountFor(100000))
            ->first();
    }

    public function discountedAmountFor(float $amount): float
    {
        return max($amount - $this->discountAmountFor($amount), 0);
    }

    public function discountAmountFor(float $amount): float
    {
        if ($this->discount_type === 'flat') {
            return min((float) $this->discount_value, $amount);
        }

        return min($amount, round($amount * ((float) $this->discount_value / 100), 2));
    }

    private function currentCouponDateTime(): string
    {
        return now(config('app.timezone', 'Europe/London'))->format('Y-m-d H:i:s');
    }

    private function dateTimeValue($dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    private static function tableExists(): bool
    {
        return Schema::hasTable((new static())->getTable());
    }
}
