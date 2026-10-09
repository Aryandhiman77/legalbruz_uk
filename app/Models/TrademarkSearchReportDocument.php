<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrademarkSearchReportDocument extends Model
{
    protected $fillable = [
        'document_name',
        'file_path',
        'original_name',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function reportRequest(): BelongsTo
    {
        return $this->belongsTo(TrademarkSearchReportRequest::class, 'trademark_search_report_request_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->document_name ?: pathinfo($this->original_name, PATHINFO_FILENAME);
    }
}
