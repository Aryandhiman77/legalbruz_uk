<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class TrademarkSearchReportRequest extends Model
{
    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PAYMENT_RECEIVED = 'payment_received';
    public const IN_REVIEW = 'in_review';
    public const REPORT_READY = 'report_ready';
    public const COMPLETED = 'completed';

    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'email',
        'phone',
        'brand_name',
        'business_activity',
        'amount',
        'currency',
        'payment_status',
        'report_status',
        'admin_notes',
        'report_file_path',
        'report_file_name',
        'report_uploaded_at',
        'razorpay_order_id',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'report_uploaded_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TrademarkSearchReportDocument::class)->latest('uploaded_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            if (Schema::hasColumn('trademark_search_report_requests', 'user_id')) {
                $query->where('user_id', $user->id)->orWhere('email', $user->email);
                return;
            }

            $query->where('email', $user->email);
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::AWAITING_PAYMENT => 'Awaiting payment',
            self::PAYMENT_RECEIVED => 'Payment received',
            self::IN_REVIEW => 'Search in progress',
            self::REPORT_READY => 'Report ready',
            self::COMPLETED => 'Completed',
        ];
    }

    public function getReportStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->report_status] ?? str($this->report_status)->headline()->toString();
    }
}
