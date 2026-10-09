<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ConsultationBooking extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'contact_message_id',
        'name',
        'email',
        'phone',
        'business_name',
        'service_topic',
        'preferred_date',
        'preferred_time',
        'message',
        'amount',
        'currency',
        'payment_status',
        'razorpay_order_id',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            if (Schema::hasColumn('consultation_bookings', 'user_id')) {
                $query->where('user_id', $user->id)->orWhere('email', $user->email);
                return;
            }

            $query->where('email', $user->email);
        });
    }

    public static function topicOptions(): array
    {
        return [
            'trademark_search' => 'Trade mark search and availability',
            'trademark_application' => 'UK trade mark application',
            'examination_report' => 'Examination report',
            'opposition' => 'Opposition matter',
            'portfolio_advice' => 'Brand or portfolio advice',
            'other' => 'Other',
        ];
    }

    public static function timeSlotOptions(): array
    {
        return [
            '10_12' => '10:00 AM – 12:00 PM',
            '12_2' => '12:00 PM – 2:00 PM',
            '2_4' => '2:00 PM – 4:00 PM',
            '4_5' => '4:00 PM – 5:00 PM',
        ];
    }

    public function getTopicLabelAttribute(): string
    {
        return self::topicOptions()[$this->service_topic] ?? str($this->service_topic)->headline()->toString();
    }

    public function getTimeSlotLabelAttribute(): string
    {
        return self::timeSlotOptions()[$this->preferred_time] ?? $this->preferred_time;
    }

    public function getConsultationStatusLabelAttribute(): string
    {
        return $this->payment_status === 'paid' ? 'Awaiting slot confirmation' : 'Awaiting payment';
    }
}
