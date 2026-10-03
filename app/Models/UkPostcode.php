<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UkPostcode extends Model
{
    protected $fillable = [
        'postcode_key',
        'postcode',
        'nation',
        'region',
        'town_city',
        'county',
        'raw_payload',
        'last_verified_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'last_verified_at' => 'datetime',
    ];
}
