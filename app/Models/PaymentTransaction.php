<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'service',
        'provider',
        'provider_order_id',
        'provider_payment_id',
        'amount',
        'currency',
        'status',
        'checkout_url',
        'metadata',
        'provider_payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'provider_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getServiceLabelAttribute(): string
    {
        return match ($this->service) {
            'trademark_application_advance' => 'Trade mark application — advance',
            'trademark_application_final' => 'Trade mark application — final balance',
            'trademark_application_full' => 'Trade mark application — full payment',
            'consultation' => 'Consultation call',
            'trademark_search_report' => 'Trade mark search report',
            'opposition_defence' => 'Opposition defence',
            'opposition_filing' => 'Opposition filing',
            'stuck_trademark_audit' => 'Stuck trade mark audit',
            'stuck_trademark_execution' => 'Stuck trade mark execution',
            'examination_reply' => 'Trade mark objection reply',
            default => str($this->service)->replace('_', ' ')->headline()->toString(),
        };
    }
}
