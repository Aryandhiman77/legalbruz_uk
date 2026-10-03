<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TrademarkOppositionCase;
use App\Support\PostFilingJourney;
use App\Support\TrademarkWorkflow;

class Application extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'application_number',
        'entity_type',
        'applicant_name',
        'phone',
        'email',
        'nationality',
        'address',
        'gender',
        'brand_name',
        'logo_path',
        'description',
        'industry',
        'usage_type',
        'first_use_date',
        'currently_selling',
        'website',
        'status',
        'admin_review_note',
        'trademark_status',
        'rejection_reason',
        'filed_at',
        'registered_at',
        'classes',
        'goods_services',
        'usage',
        'members_details',
        'service_status',
        'registry_status',
        'workflow_meta',
        'opposition_application_id',
        'opposition_status',
        'opposition_defence_case_id',
        'opposition_defence_status',
        'final_opposition_result',
        'current_stage_started_at',
        'approved_at',
        'onboarding_completed_at',
        'kyc_verified_at',
        'strategy_completed_at',
        'draft_ready_at',
        'client_approved_at',
        'final_payment_completed_at',
        'post_filing_started_at',
        'filing_receipt_path',
        'assigned_admin_id',
    ];

    protected $casts = [
        'filed_at' => 'datetime',
        'registered_at' => 'datetime',
        'first_use_date' => 'date',
        'currently_selling' => 'boolean',
        'classes' => 'array',
        'members_details' => 'array',
        'workflow_meta' => 'array',
        'opposition_application_id' => 'integer',
        'opposition_defence_case_id' => 'integer',
        'current_stage_started_at' => 'datetime',
        'approved_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'kyc_verified_at' => 'datetime',
        'strategy_completed_at' => 'datetime',
        'draft_ready_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'final_payment_completed_at' => 'datetime',
        'post_filing_started_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    public function draftVersions(): HasMany
    {
        return $this->hasMany(DraftVersion::class);
    }

    public function oppositionApplication(): BelongsTo
    {
        return $this->belongsTo(TrademarkOppositionCase::class, 'opposition_application_id');
    }

    public function oppositionDefenceCase(): BelongsTo
    {
        return $this->belongsTo(TrademarkOppositionCase::class, 'opposition_defence_case_id');
    }

    public function getCurrentStatusAttribute(): string
    {
        return $this->service_status ?: $this->status ?: TrademarkWorkflow::DRAFT;
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->is_registered) {
            return 'Registered';
        }

        $oppositionStageStatus = data_get(
            $this->workflow_meta,
            'post_filing_journey.stages.accepted_advertised.status'
        );

        if (
            $this->current_status === TrademarkWorkflow::POST_FILING
            && $oppositionStageStatus === PostFilingJourney::OPPOSED
        ) {
            return 'Opposed';
        }

        return TrademarkWorkflow::label($this->current_status);
    }

    public function getIsRegisteredAttribute(): bool
    {
        return $this->registry_status === TrademarkWorkflow::REGISTRY_REGISTERED
            || filled($this->registered_at)
            || $this->current_status === TrademarkWorkflow::REGISTERED;
    }
}
