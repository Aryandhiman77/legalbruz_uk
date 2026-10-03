<?php

namespace App\Support;

class TrademarkWorkflow
{
    public const DRAFT = 'DRAFT';
    public const APPLICATION_SUBMITTED = 'APPLICATION_SUBMITTED';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const REJECTED = 'REJECTED';
    public const ONBOARDING_PENDING = 'ONBOARDING_PENDING';
    public const ONBOARDING_COMPLETED = 'ONBOARDING_COMPLETED';
    public const KYC_PENDING = 'KYC_PENDING';
    public const KYC_VERIFIED = 'KYC_VERIFIED';
    public const STRATEGY_IN_PROGRESS = 'STRATEGY_IN_PROGRESS';
    public const STRATEGY_COMPLETED = 'STRATEGY_COMPLETED';
    public const DRAFT_READY = 'DRAFT_READY';
    public const AWAITING_APPROVAL = 'AWAITING_APPROVAL';
    public const CHANGES_REQUESTED = 'CHANGES_REQUESTED';
    public const APPROVED_FOR_FILING = 'APPROVED_FOR_FILING';
    public const PAYMENT_PENDING_FINAL = 'PAYMENT_PENDING_FINAL';
    public const PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';
    public const FILED = 'FILED';
    public const POST_FILING = 'POST_FILING';
    public const PUBLISHED_FOR_OPPOSITION = 'PUBLISHED_FOR_OPPOSITION';
    public const OPPOSED = 'OPPOSED';
    public const REGISTERED = 'REGISTERED';
    public const WITHDRAWN_OR_CLOSED = 'WITHDRAWN_OR_CLOSED';

    // UK client-facing lifecycle aliases. Legacy constant names remain available
    // so historic records and route handlers continue to resolve safely.
    public const PAYMENT_PENDING = self::PAYMENT_PENDING_FINAL;
    public const SUBMITTED_TO_LEGAL_BRUZ = self::APPLICATION_SUBMITTED;
    public const INFORMATION_REQUIRED = self::REJECTED;
    public const SEARCH_IN_PROGRESS = self::STRATEGY_IN_PROGRESS;
    public const SPECIFICATION_PREPARED = self::STRATEGY_COMPLETED;
    public const AWAITING_CLIENT_APPROVAL = self::AWAITING_APPROVAL;
    public const APPROVED_BY_CLIENT = self::APPROVED_FOR_FILING;
    public const READY_TO_FILE = self::PAYMENT_COMPLETED;
    public const FILED_WITH_UKIPO = self::FILED;
    public const EXAMINATION = self::POST_FILING;

    public const REGISTRY_NOT_FILED = 'NOT_FILED';
    public const REGISTRY_FILED = 'FILED_WITH_REGISTRY';
    public const REGISTRY_EXAM_PENDING = 'EXAM_PENDING';
    public const REGISTRY_OBJECTED = 'OBJECTED';
    public const REGISTRY_ACCEPTED = 'ACCEPTED_AND_ADVERTISED';
    public const REGISTRY_OPPOSITION = 'OPPOSITION_WINDOW';
    public const REGISTRY_REGISTERED = 'REGISTERED';

    public static function labels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::APPLICATION_SUBMITTED => 'Submitted to Legal Bruz',
            self::UNDER_REVIEW => 'Under Review',
            self::REJECTED => 'Information Required',
            self::ONBOARDING_PENDING => 'Engagement Letter Pending',
            self::ONBOARDING_COMPLETED => 'Engagement Letter Signed',
            self::KYC_PENDING => 'Information Required',
            self::KYC_VERIFIED => 'Under Review',
            self::STRATEGY_IN_PROGRESS => 'Search in Progress',
            self::STRATEGY_COMPLETED => 'Specification Prepared',
            self::DRAFT_READY => 'Specification Prepared',
            self::AWAITING_APPROVAL => 'Awaiting Client Approval',
            self::CHANGES_REQUESTED => 'Information Required',
            self::APPROVED_FOR_FILING => 'Approved by Client',
            self::PAYMENT_PENDING_FINAL => 'Payment Pending',
            self::PAYMENT_COMPLETED => 'Ready to File',
            self::FILED => 'Filed with UKIPO',
            self::POST_FILING => 'Examination',
            self::PUBLISHED_FOR_OPPOSITION => 'Published for Opposition',
            self::OPPOSED => 'Opposed',
            self::REGISTERED => 'Registered',
            self::WITHDRAWN_OR_CLOSED => 'Withdrawn or Closed',
        ];
    }

    public static function label(?string $status): string
    {
        return static::labels()[$status] ?? str_replace('_', ' ', (string) $status);
    }

    public static function statusOptions(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::PAYMENT_PENDING => 'Payment Pending',
            self::SUBMITTED_TO_LEGAL_BRUZ => 'Submitted to Legal Bruz',
            self::UNDER_REVIEW => 'Under Review',
            self::INFORMATION_REQUIRED => 'Information Required',
            self::SEARCH_IN_PROGRESS => 'Search in Progress',
            self::SPECIFICATION_PREPARED => 'Specification Prepared',
            self::AWAITING_CLIENT_APPROVAL => 'Awaiting Client Approval',
            self::APPROVED_BY_CLIENT => 'Approved by Client',
            self::READY_TO_FILE => 'Ready to File',
            self::FILED_WITH_UKIPO => 'Filed with UKIPO',
            self::EXAMINATION => 'Examination',
            self::PUBLISHED_FOR_OPPOSITION => 'Published for Opposition',
            self::OPPOSED => 'Opposed',
            self::REGISTERED => 'Registered',
            self::WITHDRAWN_OR_CLOSED => 'Withdrawn or Closed',
        ];
    }

    public static function timeline(): array
    {
        return [
            self::DRAFT,
            self::PAYMENT_PENDING,
            self::APPLICATION_SUBMITTED,
            self::UNDER_REVIEW,
            self::REJECTED,
            self::STRATEGY_IN_PROGRESS,
            self::STRATEGY_COMPLETED,
            self::AWAITING_APPROVAL,
            self::APPROVED_FOR_FILING,
            self::PAYMENT_COMPLETED,
            self::FILED,
            self::POST_FILING,
            self::PUBLISHED_FOR_OPPOSITION,
            self::OPPOSED,
            self::REGISTERED,
            self::WITHDRAWN_OR_CLOSED,
        ];
    }
}
