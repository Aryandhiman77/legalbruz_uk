<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'application_id',
        'user_id',
        'amount',
        'total_amount',
        'payment_type',
        'percentage',
        'transaction_id',
        'payment_method',
        'status',
        'paid_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'reference_number',
        'created_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoiceDiscountAmount(): float
    {
        $baseAmount = $this->invoiceDiscountBaseAmount();

        if ($baseAmount <= 0) {
            return 0.0;
        }

        $discountAmount = $baseAmount - (float) $this->amount;

        if ($discountAmount <= 0) {
            return 0.0;
        }

        return round($discountAmount, 2);
    }

    public function invoiceDiscountPercent(): ?float
    {
        $baseAmount = $this->invoiceDiscountBaseAmount();

        if ($baseAmount <= 0) {
            return null;
        }

        $discountAmount = $this->invoiceDiscountAmount();

        if ($discountAmount <= 0) {
            return null;
        }

        return round(($discountAmount / $baseAmount) * 100, 2);
    }

    public function invoiceDiscountLabel(): ?string
    {
        $percent = $this->invoiceDiscountPercent();

        if ($percent === null) {
            return null;
        }

        return rtrim(rtrim(number_format($percent, 2), '0'), '.') . '% OFF';
    }

    public function isAdvanceInvoice(): bool
    {
        $paymentType = strtolower((string) ($this->payment_type ?? ''));

        if ($paymentType !== '') {
            return $paymentType === 'advance';
        }

        // Retain support for records created before payment_type was added.
        return (string) $this->percentage === '50%';
    }

    public function invoiceAdvanceBaseAmount(): float
    {
        return round(((float) $this->total_amount) * 0.5, 2);
    }

    private function invoiceDiscountBaseAmount(): float
    {
        $paymentType = strtolower((string) ($this->payment_type ?? ''));

        if ($this->isAdvanceInvoice()) {
            return $this->invoiceAdvanceBaseAmount();
        }

        if ($paymentType === 'full') {
            return (float) $this->total_amount;
        }

        if ($paymentType === 'final' && $this->application_id && (float) $this->total_amount > 0) {
            $previousPaidAmount = static::query()
                ->where('application_id', $this->application_id)
                ->whereIn('status', ['completed', 'approved'])
                ->where('id', '<', $this->id)
                ->sum('amount');

            return max((float) $this->total_amount - (float) $previousPaidAmount, 0);
        }

        return (float) $this->amount;
    }
}
