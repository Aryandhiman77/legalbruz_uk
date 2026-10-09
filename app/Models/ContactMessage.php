<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'business_name',
        'service_interested',
        'subject',
        'message',
        'status',
        'internal_notes',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function consultationBooking(): HasOne
    {
        return $this->hasOne(ConsultationBooking::class);
    }

}
