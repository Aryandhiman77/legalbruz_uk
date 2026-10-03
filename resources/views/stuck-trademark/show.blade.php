@extends('layouts.app')

@section('content')
    @php
        $statusColors = [
            'INTAKE_SUBMITTED' => 'secondary',
            'CLIENT_ONBOARDING' => 'info',
            'PROBLEM_IDENTIFIED' => 'warning',
            'AWAITING_DOCUMENTS' => 'warning',
            'DOCUMENTS_UPLOADED' => 'info',
            'DOCUMENTS_UNDER_VERIFICATION' => 'info',
            'REUPLOAD_REQUIRED' => 'danger',
            'DOCUMENTS_VERIFIED' => 'success',
            'AUDIT_PENDING' => 'warning',
            'AUDIT_IN_PROGRESS' => 'primary',
            'AUDIT_COMPLETED' => 'success',
            'AUDIT_REPORT_REVIEW' => 'warning',
            'AUDIT_REPORT_REUPLOAD_REQUESTED' => 'danger',
            'AWAITING_APPROVAL' => 'warning',
            'EXECUTION_PAYMENT_PENDING' => 'warning',
            'EXECUTION_ACTIVE' => 'primary',
            'REGISTRY_FOLLOW_UP' => 'info',
            'MONITORING' => 'dark',
            'RESOLVED' => 'success',
            'CLOSED' => 'secondary',
        ];

        $timelineSteps = [
            [
                'label' => 'Intake Submitted',
                'description' => 'You have submitted the intake form successfully.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::INTAKE_SUBMITTED],
                'event_titles' => ['Service discovery completed'],
                'icon' => 'file-pen',
                'meta_label' => 'Submitted On',
                'fallback' => $case->created_at,
            ],
            [
                'label' => 'Client Onboarding',
                'description' => 'We are collecting your information and case details.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::CLIENT_ONBOARDING],
                'event_titles' => ['Client onboarding completed', 'Client onboarding started'],
                'icon' => 'users',
                'meta_label' => 'Completed On',
                'fallback' => null,
            ],
            [
                'label' => 'Problem Identified',
                'description' => 'Your case issue has been identified and recorded.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::PROBLEM_IDENTIFIED, \App\Support\StuckTrademarkWorkflow::AWAITING_DOCUMENTS, \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED],
                'event_titles' => ['Problem identified'],
                'icon' => 'search-check',
                'meta_label' => 'Completed On',
                'fallback' => null,
            ],
            [
                'label' => 'Documents Uploaded',
                'description' => 'Your documents have been uploaded successfully.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::DOCUMENTS_UPLOADED, \App\Support\StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION],
                'event_titles' => ['Documents uploaded', 'Documents reuploaded'],
                'date_statuses' => [\App\Support\StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION],
                'icon' => 'file-check',
                'meta_label' => 'Last Updated',
                'fallback' => $case->documents->max('created_at'),
            ],
            [
                'label' => 'Documents Verified',
                'description' => 'Our team verifies your uploaded documents.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::DOCUMENTS_VERIFIED],
                'event_titles' => ['Documents verified'],
                'icon' => 'shield-check',
                'meta_label' => 'Expected By',
                'fallback' => null,
            ],
            [
                'label' => 'Audit Package',
                'description' => 'Pay for the trademark recovery audit package.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::AUDIT_PENDING],
                'event_titles' => ['Audit package purchased'],
                'icon' => 'clipboard-check',
                'meta_label' => 'Pending Action',
                'fallback' => null,
                'action_text' => $case->audit_payment_status === 'paid' ? 'Payment Received' : 'Select Package',
            ],
            [
                'label' => 'Audit In Progress',
                'description' => 'Legal experts review the case, documents and registry status.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS],
                'event_titles' => ['Audit in progress'],
                'icon' => 'bar-chart-3',
                'meta_label' => 'Expected By',
                'fallback' => null,
            ],
            [
                'label' => 'Audit Report Delivered',
                'description' => 'Receive audit report with findings, recommendations and roadmap.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::AUDIT_COMPLETED],
                'event_titles' => ['Audit report delivered', 'Audit report created'],
                'icon' => 'file-signature',
                'meta_label' => 'Pending Action',
                'fallback' => $case->audit_report_path ? $case->updated_at : null,
                'action_text' => $case->audit_report_path ? 'Report Available' : 'Awaiting Completion',
            ],
            [
                'label' => 'Client Approval',
                'description' => 'Approve the audit report or request a corrected upload from the legal team.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW, \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED],
                'event_titles' => ['Audit report approved by applicant', 'Audit report reupload requested', 'Audit report delivered'],
                'icon' => 'user-check',
                'meta_label' => 'Pending Action',
                'fallback' => $case->audit_report_reupload_requested_at ?? $case->audit_report_approved_at,
                'action_text' => $case->status === \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REUPLOAD_REQUESTED ? 'Reupload Requested' : 'Client Review Required',
            ],
            [
                'label' => 'Execution Package',
                'description' => 'Approve execution package for recovery actions and implementation.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL, \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING],
                'event_titles' => ['Execution approved by applicant', 'Audit report approved by applicant'],
                'icon' => 'rocket',
                'meta_label' => 'Pending Action',
                'fallback' => null,
                'action_text' => $case->status === \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL ? 'Client Approval Required' : 'Payment Pending',
            ],
            [
                'label' => 'Execution in Progress',
                'description' => 'Team executes strategy, follows up with registry and completes actions.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE, \App\Support\StuckTrademarkWorkflow::REGISTRY_FOLLOW_UP, \App\Support\StuckTrademarkWorkflow::ADDITIONAL_ACTION_REQUIRED],
                'event_titles' => ['Execution package skipped by applicant', 'Admin updated recovery case'],
                'icon' => 'shield',
                'meta_label' => 'Expected By',
                'fallback' => null,
                'action_text' => 'To be scheduled',
            ],
            [
                'label' => 'Monitoring & Updates',
                'description' => 'Track case progress, receive updates and registry movements.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::MONITORING],
                'event_titles' => ['Monitoring started', 'Monitoring Active', 'Monitoring Pending', 'Admin updated recovery case'],
                'icon' => 'trending-up',
                'meta_label' => 'Ongoing',
                'fallback' => $case->next_follow_up_at,
                'action_text' => 'Continuous Monitoring',
            ],
            [
                'label' => 'Resolved & Closed',
                'description' => 'Case is resolved successfully and closed with final report.',
                'statuses' => [\App\Support\StuckTrademarkWorkflow::RESOLVED, \App\Support\StuckTrademarkWorkflow::CLOSED],
                'event_titles' => ['Monitoring Completed', 'Admin updated recovery case'],
                'icon' => 'badge-check',
                'meta_label' => 'Upcoming',
                'fallback' => null,
                'action_text' => 'After Resolution',
            ],
        ];

        $statusRank = [];
        foreach ($timelineSteps as $index => $step) {
            foreach ($step['statuses'] as $status) {
                $statusRank[$status] = $index;
            }
        }
        $timelineStatus = \App\Support\StuckTrademarkWorkflow::effectiveStatus(
            $case->status,
            $case->execution_completed_at,
            $case->resolved_at,
            $case->closed_at,
        );
        $currentRank = $statusRank[$timelineStatus] ?? 0;
        $effectiveRank = $currentRank;

        if ($case->audit_payment_status === 'paid') {
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::AUDIT_IN_PROGRESS] ?? $effectiveRank);
        }

        if ($case->audit_report_path) {
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW] ?? $effectiveRank);
        }

        if (in_array($case->execution_payment_status, ['pending', 'paid'], true)) {
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING] ?? $effectiveRank);
        }

        if ($case->execution_payment_status === 'skipped') {
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE] ?? $effectiveRank);
        }

        $displayTimezone = config('app.timezone', 'Europe/London');
        $statusLogsChronological = $case->statusLogs->sortBy('created_at')->values();
        $timelineLogForStep = function (array $step) use ($statusLogsChronological) {
            $eventTitles = $step['event_titles'] ?? [];
            $dateStatuses = $step['date_statuses'] ?? ($step['statuses'] ?? []);

            if ($eventTitles !== []) {
                foreach ($eventTitles as $eventTitle) {
                    $matchingLog = $statusLogsChronological
                        ->filter(fn ($log) => $log->title === $eventTitle && in_array($log->to_status, $dateStatuses, true))
                        ->last();

                    if ($matchingLog) {
                        return $matchingLog;
                    }
                }
            }

            return $statusLogsChronological
                ->filter(fn ($log) => in_array($log->to_status, $dateStatuses, true))
                ->last();
        };
        $latestDocumentsByType = $case->documents->sortByDesc('id')->unique('document_type')->values();
        $verificationDocumentTypes = [
            'registry_status_screenshot',
            'notice_received',
            'missed_hearing_notice',
            'tm_acknowledgment_receipt',
            'status_screenshot',
            'authorization_letter',
            'pan_aadhaar_gst',
            'previous_notices',
            'reply_copies',
            'hearing_notices',
            'user_affidavit',
            'previous_attorney_communication',
        ];
        $latestVerificationDocuments = $latestDocumentsByType
            ->whereIn('document_type', $verificationDocumentTypes)
            ->values();
        $allUploadedVerificationDocumentsVerified = $latestVerificationDocuments->isNotEmpty()
            && $latestVerificationDocuments->every(fn ($document) => $document->status === 'verified');
        if ($allUploadedVerificationDocumentsVerified) {
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::AUDIT_PENDING] ?? $effectiveRank);
        }
        $auditSupportingDocuments = $case->documents
            ->where('document_type', 'audit_supporting_document')
            ->sortByDesc('id')
            ->values();
        $reuploadRequestedDocuments = $latestDocumentsByType->filter(fn ($document) => in_array($document->status, ['reupload_requested', 'rejected'], true))->values();
        $mandatoryDocumentTypes = [
            'tm_acknowledgment_receipt' => 'TM acknowledgment receipt',
            'status_screenshot' => 'Status screenshot',
            'authorization_letter' => 'Authorization letter',
            'pan_aadhaar_gst' => 'PAN/Aadhaar/GST (if amendment may happen)',
        ];
        $optionalDocumentTypes = [
            'previous_notices' => 'Previous notices',
            'reply_copies' => 'Reply copies',
            'hearing_notices' => 'Hearing notices',
            'user_affidavit' => 'User affidavit',
            'previous_attorney_communication' => 'Previous attorney communication',
        ];
        $documentTypeOptions = [
            ...$mandatoryDocumentTypes,
            ...$optionalDocumentTypes,
            'application_acknowledgement' => 'Application Acknowledgement',
            'examination_report' => 'Examination Report',
            'reply_or_submission' => 'Reply / Written Submission',
            'hearing_notice' => 'Hearing Notice',
            'power_of_attorney' => 'Power of Attorney',
            'attorney_communication' => 'Attorney Communication',
            'registry_screenshot' => 'Registry Screenshot',
            'audit_supporting_document' => 'Audit Supporting Document',
            'other' => 'Other Supporting Document',
        ];
        $canUploadDocuments = in_array($case->status, [
            \App\Support\StuckTrademarkWorkflow::PROBLEM_IDENTIFIED,
            \App\Support\StuckTrademarkWorkflow::AWAITING_DOCUMENTS,
            \App\Support\StuckTrademarkWorkflow::REUPLOAD_REQUIRED,
        ], true);
        $canContinueOnboarding = $case->status === \App\Support\StuckTrademarkWorkflow::CLIENT_ONBOARDING;
        $documentsReadyForAudit = $allUploadedVerificationDocumentsVerified;
        $canPurchaseAuditPackage = $case->audit_payment_status !== 'paid'
            && in_array($case->status, [
                \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING,
                \App\Support\StuckTrademarkWorkflow::DOCUMENTS_VERIFIED,
                \App\Support\StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION,
            ], true)
            && $documentsReadyForAudit;
        $shouldShowAuditPackageAction = $case->audit_payment_status !== 'paid'
            && in_array($case->status, [
                \App\Support\StuckTrademarkWorkflow::AUDIT_PENDING,
                \App\Support\StuckTrademarkWorkflow::DOCUMENTS_VERIFIED,
                \App\Support\StuckTrademarkWorkflow::DOCUMENTS_UNDER_VERIFICATION,
            ], true);
        $canReviewAuditReport = $case->status === \App\Support\StuckTrademarkWorkflow::AUDIT_REPORT_REVIEW
            && filled($case->audit_report_path);
        $shouldShowExecutionPackageSidebar = in_array($case->execution_payment_status, ['pending', 'paid', 'skipped'], true)
            || in_array($case->status, [
                \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL,
                \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING,
                \App\Support\StuckTrademarkWorkflow::EXECUTION_ACTIVE,
                \App\Support\StuckTrademarkWorkflow::REGISTRY_FOLLOW_UP,
                \App\Support\StuckTrademarkWorkflow::MONITORING,
                \App\Support\StuckTrademarkWorkflow::RESOLVED,
                \App\Support\StuckTrademarkWorkflow::CLOSED,
            ], true);
        $executionSubStage = $case->execution_sub_stage ?: ($case->execution_started_at ? 'required_actions' : null);
        $visibleExecutionSubStage = $executionSubStage === 'additional_action_progress' ? 'additional_action_review' : $executionSubStage;
        $executionSubSteps = [
            'execution_started' => ['label' => 'Execution Started', 'copy' => 'Your recovery execution has started.', 'icon' => 'play-circle'],
            'required_actions' => ['label' => 'Required Actions Confirmed', 'copy' => 'Required recovery actions are confirmed for your case.', 'icon' => 'list-checks'],
            'action_progress' => ['label' => 'Action in Progress', 'copy' => 'We are working on the selected recovery actions.', 'icon' => 'settings'],
            'status_monitoring' => ['label' => 'Status Monitoring Active', 'copy' => 'We are monitoring your trademark status for movement.', 'icon' => 'trending-up'],
            'additional_action_review' => ['label' => $case->execution_additional_action_name ?: ($case->execution_status === 'No Additional Action Required' ? 'No Additional Action Required' : 'Additional Action Required, if any'), 'copy' => $case->execution_additional_action_description ?: ($case->execution_status === 'No Additional Action Required' ? 'No additional action is required for this execution.' : 'We will notify you if any additional action is needed.'), 'icon' => 'bell'],
            'execution_completed' => ['label' => 'Execution Completed', 'copy' => 'Execution work completed and case moved for resolution.', 'icon' => 'flag'],
        ];
        $executionSubStageOrder = array_keys($executionSubSteps);
        $executionSubStageIndex = array_search($visibleExecutionSubStage, $executionSubStageOrder, true);
        $executionSubStageIndex = $executionSubStageIndex === false ? -1 : $executionSubStageIndex;
        $executionIsComplete = (bool) $case->execution_completed_at || $executionSubStage === 'execution_completed';

        if ($executionIsComplete) {
            $visibleExecutionSubStage = 'execution_completed';
            $executionSubStageIndex = array_search($visibleExecutionSubStage, $executionSubStageOrder, true);
            $effectiveRank = max($effectiveRank, $statusRank[\App\Support\StuckTrademarkWorkflow::MONITORING] ?? $effectiveRank);
        }

        $resolvedIsActive = in_array($timelineStatus, [
            \App\Support\StuckTrademarkWorkflow::RESOLVED,
            \App\Support\StuckTrademarkWorkflow::CLOSED,
        ], true);
        $monitoringIsActive = ! $resolvedIsActive && ($executionIsComplete
            || in_array($timelineStatus, [
                \App\Support\StuckTrademarkWorkflow::MONITORING,
            ], true));
        $monitoringUpdates = $case->executionUpdates
            ->where('visible_to_client', true)
            ->where('stage', 'Monitoring & Updates')
            ->sortByDesc('created_at')
            ->values();
        $monitoringDocuments = $case->executionDocuments
            ->where('visible_to_client', true)
            ->where('execution_stage', 'Monitoring & Updates')
            ->sortByDesc('created_at')
            ->values();
        $resolutionUpdates = $case->executionUpdates
            ->where('visible_to_client', true)
            ->where('stage', 'Resolved & Closed')
            ->sortByDesc('created_at')
            ->values();
        $resolutionDocuments = $case->executionDocuments
            ->where('visible_to_client', true)
            ->where('execution_stage', 'Resolved & Closed')
            ->sortByDesc('created_at')
            ->values();
        $finalReportDocuments = $resolutionDocuments
            ->where('document_type', 'Final Report')
            ->values();
        $resolutionOptionalDocuments = $resolutionDocuments
            ->where('document_type', '!=', 'Final Report')
            ->values();
        $auditOriginalAmount = (float) ($case->audit_original_fee ?: $case->audit_fee);
        $auditDiscountAmount = (float) ($case->audit_discount_amount ?: 0);
        $auditPaidAmount = (float) ($case->audit_paid_amount ?: max($auditOriginalAmount - $auditDiscountAmount, 0));
        $hasAuditDiscount = $auditDiscountAmount > 0 && $auditPaidAmount < $auditOriginalAmount;
        $adminSentDocuments = collect();

        if ($case->audit_report_path) {
            $adminSentDocuments->push([
                'title' => $case->audit_report_name ?: 'Audit Report',
                'type' => 'Audit Report',
                'stage' => 'Audit Report Delivered',
                'date' => $case->updated_at,
                'url' => route('stuck-trademark.audit-report', $case),
            ]);
        }

        foreach ($auditSupportingDocuments as $document) {
            $adminSentDocuments->push([
                'title' => $document->file_name,
                'type' => 'Audit Supporting Document',
                'stage' => 'Audit Report Delivered',
                'date' => $document->created_at,
                'url' => route('stuck-trademark.document.view', $document),
            ]);
        }

        foreach ($case->executionDocuments->where('visible_to_client', true) as $document) {
            $adminSentDocuments->push([
                'title' => $document->document_title,
                'type' => $document->document_type ?: 'Admin Sent Document',
                'stage' => $document->execution_stage ?: 'Recovery Status Tracking',
                'date' => $document->created_at,
                'url' => asset('storage/' . $document->file_path),
            ]);
        }

        $adminSentDocuments = $adminSentDocuments->sortByDesc('date')->values();
        $clientSubmittedDocuments = $case->documents
            ->reject(fn ($document) => $document->document_type === 'audit_supporting_document')
            ->sortByDesc('created_at')
            ->values();
        $executionDocumentStageLabels = collect($executionSubSteps)->pluck('label')->all();
        $documentsForRecoveryStep = function (string $label) use ($adminSentDocuments, $clientSubmittedDocuments, $executionDocumentStageLabels, $case) {
            $adminStages = match ($label) {
                'Audit Report Delivered' => ['Audit Report Delivered'],
                'Execution in Progress' => array_values(array_filter(array_unique([
                    ...$executionDocumentStageLabels,
                    'Additional Action Required',
                    'No Additional Action Required',
                    $case->execution_additional_action_name,
                ]))),
                'Monitoring & Updates' => ['Monitoring & Updates'],
                'Resolved & Closed' => ['Resolved & Closed'],
                default => [$label],
            };
            $adminDocuments = $adminSentDocuments
                ->filter(fn ($document) => in_array($document['stage'], $adminStages, true))
                ->values();
            $clientDocuments = $label === 'Documents Uploaded' ? $clientSubmittedDocuments : collect();

            return [
                'admin' => $adminDocuments,
                'client' => $clientDocuments,
            ];
        };
        $documentsForExecutionSubStep = function (string $subKey, array $subStep) use ($adminSentDocuments, $case) {
            $stages = [$subStep['label']];

            if ($subKey === 'additional_action_review') {
                $stages = array_values(array_filter(array_unique([
                    $subStep['label'],
                    'Additional Action Required',
                    'No Additional Action Required',
                    $case->execution_additional_action_name,
                ])));
            }

            return $adminSentDocuments
                ->filter(fn ($document) => in_array($document['stage'], $stages, true))
                ->values();
        };
    @endphp

    <style>
        .recovery-timeline-card {
            overflow: hidden;
            border: 1px solid #e6ebf2 !important;
            border-radius: 12px !important;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 36, 68, 0.07) !important;
        }

        .recovery-timeline-card:hover {
            transform: none;
        }

        .recovery-timeline-head {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 0.9rem;
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, #1d3557 0%, #2d4a73 100%);
        }

        .recovery-timeline-head h1,
        .recovery-timeline-head h2,
        .recovery-timeline-head h3,
        .recovery-timeline-head h4,
        .recovery-timeline-head h5,
        .recovery-timeline-head h6,
        .recovery-timeline-head p,
        .recovery-timeline-head .registry-pill {
            color: #ffffff !important;
        }

        .recovery-documents-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0;
            border: 0;
            color: rgba(255, 255, 255, 0.92);
            background: transparent;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 850;
            line-height: 1.2;
            text-decoration: underline;
            text-underline-offset: 5px;
        }

        .recovery-documents-link:hover,
        .recovery-documents-link:focus {
            color: #ffffff;
        }

        .recovery-documents-link svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.4;
        }

        .recovery-title-wrap {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .recovery-title-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.13);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .recovery-title-icon svg,
        .recovery-subtitle-icon svg,
        .recovery-step-icon svg,
        .recovery-status-pill svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.35;
        }

        .recovery-timeline-title {
            margin: 0;
            color: #ffffff !important;
            font-size: 1.35rem;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .recovery-subtitle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin: 0.2rem 0 0;
            padding-bottom: 0.06rem;
            color: #ffffff !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.38);
            font-size: 0.88rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .registry-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.25rem 0.55rem;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.15);
            border: 0;
            font-size: 0.82rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .registry-pill.is-status {
            justify-content: center;
            color: #0b57d0 !important;
            background: #ffffff;
        }

        .timeline-head-actions {
            display: grid;
            justify-items: end;
            gap: 0.45rem;
        }

        .recovery-timeline-body {
            padding: 1.25rem 1.35rem 1.45rem;
            background: #ffffff;
        }

        .recovery-timeline-row {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.85rem;
            position: relative;
            padding: 0.82rem 0;
            margin: 0;
            border-bottom: 0;
        }

        .recovery-timeline-row + .recovery-timeline-row {
            padding-top: 0.82rem;
        }

        .recovery-timeline-row:last-child {
            padding-bottom: 0.82rem;
        }

        .recovery-timeline-row.is-current {
            background: transparent;
            border-radius: 0;
            box-shadow: none;
        }

        .recovery-timeline-row.execution-expanded {
            align-items: flex-start;
            padding-right: 0.9rem;
        }

        .recovery-timeline-row:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 19px;
            top: 48px;
            bottom: -12px;
            border-left: 2px dashed #d8e1ee;
            z-index: 0;
        }

        .recovery-rail {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .recovery-step-number {
            display: none;
        }

        .recovery-step-icon {
            position: relative;
            z-index: 1;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #0a9a87;
            background: rgba(42, 157, 143, 0.11);
        }

        .recovery-timeline-row.is-current .recovery-step-icon {
            color: #0b57d0;
            background: #eef6ff;
        }

        .recovery-timeline-row.is-upcoming .recovery-step-icon {
            color: #7b8794;
            background: #f4f6f8;
        }

        .step-completed-check {
            position: absolute;
            top: 0;
            right: -2px;
            width: 17px;
            height: 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            border-radius: 50%;
            background: #10b981;
        }

        .step-completed-check svg {
            width: 12px;
            height: 12px;
            stroke-width: 3;
        }

        .recovery-step-copy h4 {
            margin: 0 0 0.16rem;
            font-size: 1rem;
            color: #10233f;
            line-height: 1.18;
        }

        .recovery-step-title-row,
        .execution-subtimeline-title-row {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.4rem 0.7rem;
            margin-bottom: 0.16rem;
        }

        .recovery-step-title-row h4,
        .execution-subtimeline-title-row h5 {
            margin-bottom: 0;
        }

        .recovery-step-title-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #10233f;
            text-decoration: underline;
            text-decoration-thickness: 2px;
            text-underline-offset: 3px;
        }

        .recovery-step-title-link:hover {
            color: #0a9a87;
        }

        .recovery-step-title-link svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.8;
        }

        .recovery-step-documents-link {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            padding: 0;
            border: 0;
            color: #155eef;
            background: transparent;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 900;
            line-height: 1.2;
            text-decoration: underline;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
        }

        .recovery-step-documents-link:hover,
        .recovery-step-documents-link:focus {
            color: #0b4dcc;
        }

        .recovery-step-documents-link svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.5;
        }

        .recovery-step-copy p {
            margin: 0;
            color: #44546a;
            max-width: 520px;
            font-size: 0.84rem;
            line-height: 1.35;
        }

        .timeline-status-wrap {
            display: grid;
            justify-items: end;
            justify-self: end;
            gap: 0.18rem;
            min-width: 128px;
            text-align: right;
        }

        .recovery-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            min-width: auto;
            justify-content: center;
            padding: 0.22rem 0.5rem;
            border-radius: 7px;
            font-size: 0.74rem;
            font-weight: 800;
        }

        .recovery-status-pill.is-completed {
            color: #047857;
            background: #e9fbf3;
            border: 1px solid #a7f3d0;
        }

        .recovery-status-pill.is-current {
            color: #155eef;
            background: #eff6ff;
            border: 1px solid #93c5fd;
        }

        .recovery-status-pill.is-pending {
            color: #9a5a00;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .recovery-status-pill.is-upcoming {
            color: #9a5a00;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .execution-subtimeline {
            grid-column: 1 / -1;
            position: relative;
            display: grid;
            gap: 0;
            margin-top: 0.7rem;
            margin-left: 0;
            padding: 0.65rem 0 0.75rem;
        }

        .execution-subtimeline::before {
            display: none;
        }

        .execution-subtimeline-row {
            position: relative;
            display: grid;
            grid-template-columns: 86px minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.75rem;
            min-height: auto;
            padding: 0.62rem 0;
        }

        .execution-subtimeline-row::before {
            content: '';
            position: absolute;
            left: 19px;
            top: 50%;
            width: 54px;
            border-top: 2px dashed #b8cff6;
        }

        .execution-subtimeline-icon {
            position: relative;
            z-index: 1;
            justify-self: end;
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #815416;
            border: 1px solid #ffd28a;
            background: #fff4db;
            font-size: 0.8rem;
            font-weight: 900;
        }

        .execution-subtimeline-row.is-completed .execution-subtimeline-icon {
            color: #047857;
            border-color: #9fe4be;
            background: #e7f8ef;
        }

        .execution-subtimeline-row.is-current .execution-subtimeline-icon {
            color: #0d62d6;
            border-color: #9cc2ff;
            background: #edf5ff;
        }

        .execution-subtimeline-title {
            margin: 0;
            color: #10233f;
            font-size: 0.86rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .execution-subtimeline-copy {
            margin: 0.15rem 0 0;
            color: #4d5b70;
            font-size: 0.8rem;
            line-height: 1.32;
            max-width: none;
        }

        .execution-subtimeline-status-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-self: end;
            gap: 0.15rem;
            min-width: 128px;
            text-align: right;
        }

        .recovery-timeline-row.is-skipped .recovery-step-copy h4 {
            color: #d97706;
        }

        .recovery-status-pill.is-skipped {
            color: #a16207;
            background: #fef9c3;
            border: 1px solid #fde68a;
        }

        .timeline-date {
            color: #667085;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .timeline-goto-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0a9a87;
            font-size: 0.76rem;
            font-weight: 900;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
        }

        .timeline-goto-action:hover {
            color: #087f73;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .timeline-goto-action svg {
            width: 14px;
            height: 14px;
            stroke-width: 2.6;
        }

        .sidebar-documents-list {
            display: grid;
            gap: 12px;
        }

        .sidebar-document-item {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
            border: 1px solid #edf1f5;
            border-radius: 10px;
            background: #ffffff;
        }

        .sidebar-document-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            color: #0a9a87;
            background: rgba(42, 157, 143, 0.1);
        }

        .sidebar-document-icon svg,
        .sidebar-document-meta svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.35;
        }

        .sidebar-document-name {
            display: block;
            color: #10233f;
            font-size: 0.86rem;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .sidebar-document-type,
        .sidebar-document-meta {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .sidebar-document-meta {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-document-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 10px;
        }

        .sidebar-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 7px;
            color: #9a5a00;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1;
        }

        .sidebar-status-pill.is-verified {
            color: #047857;
            background: #e9fbf3;
            border-color: #a7f3d0;
        }

        .sidebar-status-pill.is-reupload-requested {
            color: #a16207;
            background: #fef3c7;
            border-color: #fde68a;
        }

        .sidebar-document-view {
            padding: 5px 9px;
            border: 1px solid #dbe3ee;
            border-radius: 7px;
            color: #1d3557;
            font-size: 0.74rem;
            font-weight: 800;
            text-decoration: none;
        }

        .sidebar-document-view:hover {
            color: #0a9a87;
            border-color: rgba(42, 157, 143, 0.35);
        }

        .recovery-side-card {
            overflow: hidden;
            border: 0 !important;
            border-radius: 12px !important;
            background: #ffffff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08) !important;
        }

        .recovery-side-card:hover {
            transform: none;
        }

        .recovery-side-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px !important;
            background: #27466d !important;
            border: 0 !important;
        }

        .recovery-side-card .card-header h5 {
            margin: 0;
            color: #ffffff !important;
            font-size: 1rem;
            line-height: 1.15;
        }

        .recovery-side-card .card-body {
            padding: 14px 16px !important;
        }

        .activity-log-card .card-header {
            background: #27466d !important;
        }

        .activity-log-card .card-header h5 {
            color: #ffffff !important;
        }

        .side-card-header-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.13);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .side-card-header-icon svg,
        .summary-row-icon svg,
        .audit-feature-icon svg {
            width: 17px;
            height: 17px;
            stroke-width: 2.35;
        }

        .summary-list {
            display: grid;
            gap: 0;
            padding: 0;
        }

        .summary-row {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid #edf1f5;
        }

        .summary-row:first-child {
            padding-top: 0;
        }

        .summary-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .summary-row-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .summary-row-icon.is-issue {
            color: #d97706;
            background: #fff7ed;
        }

        .summary-row-icon.is-registry {
            color: #45617f;
            background: #eef6ff;
        }

        .summary-row-icon.is-urgency {
            color: #e11d48;
            background: #fff1f2;
        }

        .summary-row-icon.is-problem {
            color: #0a9a87;
            background: #e9fbf3;
        }

        .summary-row-icon.is-package {
            color: #0b8c56;
            background: #e9fbf3;
        }

        .summary-row-icon.is-payment {
            color: #1769f5;
            background: #eef6ff;
        }

        .summary-label {
            display: block;
            margin: 0 0 4px;
            color: #10233f;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .summary-value {
            margin: 0;
            color: #44546a;
            font-size: 0.82rem;
            font-weight: 650;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .execution-document-summary {
            display: grid;
            justify-items: start;
            text-align: left;
        }

        .execution-document-row {
            grid-template-columns: 40px minmax(0, 1fr) auto;
            align-items: center;
        }

        .execution-document-view {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            justify-self: end;
            padding: 0;
            border: 0;
            color: #047857;
            background: transparent;
            font-size: 0.86rem;
            font-weight: 900;
            line-height: 1.2;
            text-decoration: none;
        }

        .execution-document-view:hover,
        .execution-document-view:focus {
            color: #065f46;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .audit-price {
            margin: 0 0 6px;
            color: #10233f;
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1;
        }

        .audit-status {
            margin: 0;
            color: #44546a;
            font-size: 0.9rem;
            font-weight: 800;
        }

        .audit-status-value {
            color: #d97706;
        }

        .audit-status-value.is-paid {
            color: #059669;
        }

        .audit-divider {
            margin: 14px 0;
            border-top: 2px dashed #dbe3ee;
        }

        .audit-features {
            display: grid;
            gap: 9px;
            margin-bottom: 16px;
        }

        .audit-feature {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #44546a;
            font-size: 0.82rem;
            font-weight: 750;
            line-height: 1.3;
        }

        .audit-feature-icon {
            width: 24px;
            height: 24px;
            flex: 0 0 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0a9a87;
        }

        .audit-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 52px;
            border: 0;
            border-radius: 10px;
            background: #0a9a87;
            color: #ffffff;
            font-weight: 900;
            box-shadow: 0 10px 22px rgba(10, 154, 135, 0.22);
        }

        .audit-action-button:hover,
        .audit-action-button:focus {
            background: #087f73;
            color: #ffffff;
        }

        .audit-action-button svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.6;
        }

        .audit-action-button:disabled {
            background: #94a3b8;
            box-shadow: none;
            cursor: not-allowed;
            opacity: 0.85;
        }

        .audit-gate-note {
            margin: 10px 0 0;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .reupload-request-panel {
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #facc15;
            border-radius: 8px;
            background: #fefce8;
            color: #854d0e;
        }

        .reupload-request-panel h4 {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 8px;
            color: #854d0e;
            font-size: 0.82rem;
            line-height: 1.2;
        }

        .reupload-request-panel h4 svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.4;
        }

        .reupload-request-panel p,
        .reupload-request-panel li {
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .reupload-request-panel p {
            margin: 0 0 8px;
        }

        .reupload-request-panel ul {
            margin: 0;
            padding-left: 18px;
        }

        .reupload-request-list {
            display: grid;
            gap: 10px;
            margin-top: 10px;
        }

        .reupload-request-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(190px, 0.75fr) auto;
            align-items: end;
            gap: 10px;
            padding: 10px;
            border: 1px solid #fde68a;
            border-radius: 8px;
            background: #ffffff;
        }

        .reupload-request-name {
            display: block;
            color: #854d0e;
            font-size: 0.76rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .reupload-request-note {
            display: block;
            margin-top: 4px;
            color: #a16207;
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .audit-review-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .audit-review-actions form {
            margin: 0;
        }

        .audit-review-actions .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1.15;
            white-space: normal;
        }

        .audit-review-actions .btn svg {
            width: 17px;
            height: 17px;
            flex: 0 0 17px;
            stroke-width: 2.4;
        }

        .audit-review-actions .btn-ask-reupload {
            border-color: #ffc107;
            background: #ffc107;
            color: #ffffff;
        }

        .audit-review-actions .btn-ask-reupload:hover,
        .audit-review-actions .btn-ask-reupload:focus {
            border-color: #e0a800;
            background: #e0a800;
            color: #ffffff;
        }

        .client-approval-center {
            overflow: hidden;
            border: 1px solid #e6ebf2 !important;
            border-radius: 12px !important;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 36, 68, 0.07) !important;
        }

        .client-approval-center .card-header {
            padding: 16px 18px;
            border: 0;
            background: linear-gradient(135deg, #1f3f68 0%, #2d4a73 100%);
            color: #ffffff;
        }

        .client-approval-center .card-header h5 {
            margin: 0;
            color: #ffffff !important;
            font-size: 1.1rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .client-approval-center .card-body {
            padding: 18px;
        }

        .client-approval-center .card-body > .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 850;
            line-height: 1.15;
        }

        .client-approval-center .card-body > .btn svg {
            width: 17px;
            height: 17px;
            flex: 0 0 17px;
            stroke-width: 2.4;
        }

        .client-approval-guide {
            margin-bottom: 16px;
            padding: 16px;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #ffffff;
        }

        .client-approval-guide-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .client-approval-guide-head > span {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #eaf2ff;
            color: #1769f5;
        }

        .client-approval-guide-head > span svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.4;
        }

        .client-approval-guide-head h6 {
            margin: 0;
            color: #16233b;
            font-size: 1rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .client-approval-guide-head p {
            margin: 4px 0 0;
            color: #536176;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .client-approval-report-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 40px;
            margin-bottom: 14px;
            border: 1px solid #d8eadf;
            border-radius: 8px;
            color: #0b8c56;
            background: #f4fbf7;
            font-weight: 900;
            text-decoration: none;
        }

        .client-approval-report-link:hover {
            color: #057446;
            background: #eef9f3;
            text-decoration: none;
        }

        .client-approval-report-link svg {
            width: 17px;
            height: 17px;
            flex: 0 0 17px;
            stroke-width: 2.4;
        }

        .client-approval-admin-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 16px;
            padding: 13px 15px;
            border: 1px solid #facc15;
            border-radius: 8px;
            background: #fffbeb;
            color: #854d0e;
            font-size: 0.86rem;
            font-weight: 750;
            line-height: 1.45;
        }

        .client-approval-admin-note svg {
            width: 17px;
            height: 17px;
            flex: 0 0 17px;
            margin-top: 2px;
            color: #ca8a04;
            stroke-width: 2.4;
        }

        .client-approval-admin-note strong {
            display: block;
            margin-bottom: 3px;
            color: #713f12;
            font-weight: 950;
        }

        .client-approval-insights {
            display: grid;
            grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
            gap: 12px;
            margin: 0 0 16px;
        }

        .client-approval-insight {
            min-height: 74px;
            padding: 13px 15px;
            border: 1px solid #d8eadf;
            border-radius: 8px;
            background: #f4fbf7;
        }

        .client-approval-insight-label {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 6px;
            color: #0b6f45;
            font-size: 0.78rem;
            font-weight: 950;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .client-approval-insight-label svg {
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
            stroke-width: 2.4;
        }

        .client-approval-insight-value {
            margin: 0;
            color: #17324d;
            font-size: 0.92rem;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .client-package-offer {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 5px 9px;
            border-radius: 999px;
            background: linear-gradient(135deg, #5546ea, #6157f7);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 950;
            line-height: 1;
        }

        .client-package-offer svg {
            width: 13px;
            height: 13px;
            stroke-width: 2.8;
        }

        .client-package-price-stack {
            display: flex;
            align-items: baseline;
            gap: 9px;
            flex-wrap: wrap;
        }

        .client-package-price-original {
            position: relative;
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 850;
        }

        .client-package-price-original::after {
            content: "";
            position: absolute;
            left: -3px;
            right: -3px;
            top: 50%;
            height: 2px;
            border-radius: 999px;
            background: #64748b;
            transform: rotate(-5deg);
        }

        .client-package-price-discounted {
            color: #5546ea;
            font-size: 1rem;
            font-weight: 950;
        }

        .client-approval-documents {
            margin: 0 0 16px;
            padding: 14px;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #ffffff;
        }

        .client-approval-documents-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px;
            color: #16233b;
            font-size: 0.95rem;
            font-weight: 950;
        }

        .client-approval-documents-title svg {
            width: 17px;
            height: 17px;
            flex: 0 0 17px;
            color: #0b8c56;
            stroke-width: 2.4;
        }

        .client-approval-document-list {
            display: grid;
            gap: 9px;
        }

        .client-approval-document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 12px;
            border: 1px solid #d8eadf;
            border-radius: 8px;
            background: #f4fbf7;
        }

        .client-approval-document-name {
            display: block;
            color: #17324d;
            font-size: 0.9rem;
            font-weight: 900;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .client-approval-document-meta {
            display: block;
            margin-top: 3px;
            color: #5f6b7a;
            font-size: 0.78rem;
            font-weight: 750;
        }

        .client-approval-document-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 34px;
            padding: 7px 11px;
            border-radius: 7px;
            color: #0b8c56;
            font-size: 0.82rem;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }

        .client-approval-document-view:hover {
            color: #057446;
            background: #e9f8ef;
            text-decoration: none;
        }

        .client-approval-document-view svg {
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
            stroke-width: 2.4;
        }

        .monitoring-update-list {
            display: grid;
            gap: 12px;
            margin-bottom: 16px;
        }

        .monitoring-update-item {
            padding: 14px;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #ffffff;
        }

        .monitoring-update-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }

        .monitoring-update-title {
            margin: 0;
            color: #16233b;
            font-size: 0.95rem;
            font-weight: 950;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .monitoring-update-date {
            flex: 0 0 auto;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .monitoring-update-note {
            margin: 10px 0 0;
            padding: 11px 13px;
            border: 1px solid #facc15;
            border-radius: 8px;
            color: #854d0e;
            background: #fffbeb;
            font-size: 0.84rem;
            font-weight: 800;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .audit-review-modal .modal-footer .btn {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 850;
            line-height: 1.15;
        }

        .audit-review-modal .modal-footer .btn svg {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            stroke-width: 2.4;
        }

        .recovery-documents-modal .modal-dialog {
            max-width: min(1120px, calc(100vw - 32px));
        }

        .recovery-documents-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 12px;
        }

        .recovery-documents-modal .modal-header {
            padding: 22px 24px;
            border-bottom: 1px solid #dbe3ee;
        }

        .recovery-documents-modal .modal-title {
            margin: 0 0 4px;
            color: #10233f;
            font-size: 1.15rem;
            font-weight: 950;
            line-height: 1.2;
        }

        .recovery-documents-modal .modal-subtitle {
            margin: 0;
            color: #44546a;
            font-size: 0.86rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .recovery-documents-modal .modal-body {
            padding: 20px 24px 24px;
        }

        .recovery-documents-tabs {
            display: inline-flex;
            gap: 6px;
            padding: 6px;
            margin-bottom: 18px;
            border: 1px solid #dbe3ee;
            border-radius: 9px;
            background: #f8fafc;
        }

        .recovery-documents-tabs .nav-link {
            min-height: 38px;
            padding: 8px 14px;
            border: 0;
            border-radius: 7px;
            color: #44546a;
            font-size: 0.82rem;
            font-weight: 900;
        }

        .recovery-documents-tabs .nav-link.active {
            color: #ffffff;
            background: #1769f5;
        }

        .recovery-documents-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .recovery-document-card {
            min-height: 190px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            padding: 18px;
            border: 1px solid #dbe3ee;
            border-radius: 9px;
            background: #ffffff;
        }

        .recovery-document-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #1769f5;
            background: #eaf2ff;
        }

        .recovery-document-card:nth-child(3n + 2) .recovery-document-icon {
            color: #0a9a87;
            background: #e9fbf3;
        }

        .recovery-document-card:nth-child(3n) .recovery-document-icon {
            color: #7c3aed;
            background: #f0e7ff;
        }

        .recovery-document-icon svg {
            width: 22px;
            height: 22px;
            stroke-width: 2.3;
        }

        .recovery-document-title {
            display: block;
            margin-top: 16px;
            color: #10233f;
            font-size: 0.95rem;
            font-weight: 950;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .recovery-document-meta {
            display: block;
            margin-top: 6px;
            color: #44546a;
            font-size: 0.8rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .recovery-document-view {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 34px;
            padding: 7px 11px;
            border: 1px solid #8bb3ff;
            border-radius: 7px;
            color: #1769f5;
            font-size: 0.8rem;
            font-weight: 900;
            text-decoration: none;
        }

        .recovery-document-view:hover {
            color: #0b57d0;
            background: #f1f7ff;
        }

        .recovery-documents-empty {
            padding: 24px;
            border: 1px dashed #cfd8e6;
            border-radius: 9px;
            color: #64748b;
            background: #fbfcfe;
            font-size: 0.88rem;
            font-weight: 800;
            text-align: center;
        }

        .reupload-file-input {
            min-height: 36px;
            font-size: 0.72rem;
            font-weight: 750;
        }

        .reupload-submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 36px;
            padding: 6px 12px;
            border: 0;
            border-radius: 7px;
            background: #0a9a87;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .reupload-submit-button:hover,
        .reupload-submit-button:focus {
            background: #087f73;
            color: #ffffff;
        }

        .reupload-submit-button svg {
            width: 14px;
            height: 14px;
            stroke-width: 2.5;
        }

        .documents-workspace-card {
            overflow: hidden;
            border: 0 !important;
            border-radius: 10px !important;
            background: #ffffff;
            padding: 0 !important;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08) !important;
        }

        .action-center-card {
            margin-top: 0 !important;
        }

        .action-center-card > .documents-workspace-head {
            margin-top: 0 !important;
            border-radius: 10px 10px 0 0;
        }

        .documents-workspace-card:hover {
            transform: none;
        }

        .documents-workspace-head {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 28px;
            overflow: hidden;
            background: linear-gradient(135deg, #00534c 0%, #007f73 100%);
        }

        .documents-workspace-head::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.22;
            background-image: radial-gradient(circle, rgba(255, 255, 255, 0.9) 2px, transparent 2px);
            background-size: 20px 20px;
            background-position: right 18px top 14px;
            mask-image: linear-gradient(to left, #000 0 190px, transparent 380px);
            pointer-events: none;
        }

        .documents-head-icon {
            position: relative;
            z-index: 1;
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #ffffff;
            background: rgba(10, 154, 135, 0.55);
        }

        .documents-head-icon svg {
            width: 23px;
            height: 23px;
            stroke-width: 2.3;
        }

        .documents-workspace-title {
            position: relative;
            z-index: 1;
            margin: 0 0 4px;
            color: #ffffff !important;
            font-size: 1.2rem;
            line-height: 1.1;
        }

        .documents-workspace-subtitle {
            position: relative;
            z-index: 1;
            margin: 0;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.82rem;
            font-weight: 650;
        }

        .documents-workspace-body {
            padding: 18px 22px 20px;
        }

        .documents-workspace-card .documents-workspace-body {
            padding-top: 0;
        }

        .documents-workspace-card .documents-list-head {
            margin: 0 -22px 18px;
            padding: 16px 22px;
            border-radius: 10px 10px 0 0;
            background: linear-gradient(135deg, #1f3f68 0%, #2d4a73 100%);
        }

        .documents-workspace-card .documents-list-title {
            color: #ffffff !important;
            font-size: 1.1rem;
        }

        .documents-workspace-card .documents-list-title svg {
            color: #ffffff;
        }

        .documents-workspace-card .documents-search input {
            border-color: rgba(255, 255, 255, 0.28);
            background: #ffffff;
        }

        .documents-action-center {
            margin-bottom: 18px;
            padding: 18px 20px;
            border: 1.5px dashed #cfd8e6;
            border-radius: 10px;
            background: #ffffff;
        }

        .documents-action-center.is-locked {
            background: #fbfcfe;
        }

        .action-center-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .action-center-kicker {
            display: block;
            margin-bottom: 8px;
            color: #0a9a87;
            font-size: 0.72rem;
            font-weight: 950;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .action-center-title {
            margin: 0 0 8px;
            color: #10233f;
            font-size: 1.05rem;
            line-height: 1.15;
        }

        .action-center-copy {
            margin: 0;
            color: #4b5b70;
            font-size: 0.82rem;
            font-weight: 750;
            line-height: 1.45;
        }

        .action-center-lock {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            padding: 9px 14px;
            border: 1px solid #cfd8e6;
            border-radius: 999px;
            color: #44546a;
            background: #f4f7fb;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .action-center-lock svg,
        .action-center-note svg {
            width: 16px;
            height: 16px;
            stroke-width: 2.4;
        }

        .action-center-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 12px;
            padding: 12px 14px;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            color: #44546a;
            background: #ffffff;
            font-size: 0.78rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .action-center-fields {
            display: grid;
            gap: 12px;
        }

        .action-document-group-title {
            margin-top: 4px;
            color: #0a9a87;
            font-size: 0.84rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .action-document-field {
            display: grid;
            grid-template-columns: minmax(180px, 0.72fr) minmax(240px, 1fr);
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px solid #edf1f5;
            border-radius: 9px;
            background: #fbfcfe;
        }

        .action-document-label {
            margin: 0;
            color: #10233f;
            font-size: 0.82rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .action-document-note {
            display: block;
            margin-top: 6px;
            color: #a16207;
            font-size: 0.72rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .action-document-file {
            min-height: 40px;
            font-size: 0.8rem;
            font-weight: 750;
        }

        .action-submit-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .action-submit-row .document-upload-button {
            width: auto;
            min-width: 160px;
            padding-inline: 18px;
        }

        .document-upload-grid {
            display: grid;
            grid-template-columns: minmax(300px, 1.1fr) minmax(220px, 0.85fr) 168px;
            align-items: center;
            gap: 12px;
            padding: 14px 16px 12px;
            border: 1.5px dashed #cfd8e6;
            border-radius: 9px;
            background: #ffffff;
        }

        .document-field-label {
            display: block;
            margin-bottom: 5px;
            color: #10233f;
            font-size: 0.76rem;
            font-weight: 900;
        }

        .required-mark {
            color: #ef4444;
        }

        .document-type-select-wrap {
            position: relative;
        }

        .document-type-select-wrap::before {
            content: '';
            position: absolute;
            left: 13px;
            top: 50%;
            width: 16px;
            height: 16px;
            transform: translateY(-50%);
            background: currentColor;
            color: #64748b;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='black' stroke-width='2'%3E%3Cpath d='M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z'/%3E%3Cpath d='M14 2v4a2 2 0 0 0 2 2h4'/%3E%3Cpath d='M10 9H8'/%3E%3Cpath d='M16 13H8'/%3E%3Cpath d='M16 17H8'/%3E%3C/svg%3E") center / contain no-repeat;
            pointer-events: none;
        }

        .document-type-select {
            width: 100%;
            height: 40px;
            min-height: 40px;
            padding-left: 40px;
            padding-right: 32px;
            border: 1px solid #dbe3ee;
            border-radius: 7px;
            color: #44546a;
            font-size: 0.8rem;
            font-weight: 800;
            box-shadow: none;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .document-type-select:focus {
            border-color: #0a9a87;
            box-shadow: 0 0 0 4px rgba(10, 154, 135, 0.13);
        }

        .document-dropzone {
            height: 44px;
            min-height: 44px;
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            align-items: center;
            gap: 9px;
            padding: 7px 11px;
            border: 1.5px dashed #cfd8e6;
            border-radius: 9px;
            background: #ffffff;
        }

        .document-dropzone-icon {
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0a9a87;
        }

        .document-dropzone-icon svg {
            width: 20px;
            height: 20px;
            stroke-width: 2.1;
        }

        .document-dropzone-title {
            display: block;
            color: #10233f;
            font-size: 0.76rem;
            font-weight: 800;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .document-dropzone-subtitle {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 650;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .document-file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .document-file-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 14px;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            color: #10233f;
            background: #ffffff;
            font-size: 0.78rem;
            font-weight: 850;
            white-space: nowrap;
            cursor: pointer;
        }

        .document-upload-button {
            width: 100%;
            min-width: 0;
            height: 40px;
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 0;
            border-radius: 7px;
            background: #0a9a87;
            color: #ffffff;
            font-size: 0.74rem;
            font-weight: 900;
            box-shadow: 0 10px 22px rgba(10, 154, 135, 0.18);
            white-space: nowrap;
        }

        .document-upload-button:hover,
        .document-upload-button:focus {
            color: #ffffff;
            background: #087f73;
        }

        .document-upload-button svg,
        .document-format-note svg,
        .document-action-link svg,
        .document-date-cell svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.4;
        }

        .document-upload-button span {
            min-width: 0;
            overflow: visible;
            text-overflow: clip;
            white-space: nowrap;
        }

        .document-status-pill svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.4;
        }

        .document-format-note {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0 18px;
            color: #44546a;
            font-size: 0.74rem;
            font-weight: 650;
        }

        .documents-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 20px 0 10px;
        }

        .documents-list-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: #10233f;
            font-size: 0.84rem;
            font-weight: 900;
        }

        .documents-list-title svg {
            width: 18px;
            height: 18px;
            color: #0a9a87;
            stroke-width: 2.4;
        }

        .documents-search {
            position: relative;
            width: min(240px, 100%);
        }

        .documents-search svg {
            position: absolute;
            left: 13px;
            top: 50%;
            width: 17px;
            height: 17px;
            color: #64748b;
            transform: translateY(-50%);
        }

        .documents-search input {
            width: 100%;
            min-height: 34px;
            padding: 0 12px 0 38px;
            border: 1px solid #dbe3ee;
            border-radius: 9px;
            color: #44546a;
            font-size: 0.74rem;
            font-weight: 650;
        }

        .documents-table-wrap {
            overflow: hidden;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
        }

        .documents-table {
            margin: 0;
        }

        .documents-table thead th {
            padding: 10px 13px;
            color: #111827;
            background: #ffffff;
            border-bottom: 1px solid #dbe3ee;
            font-size: 0.72rem;
            font-weight: 900;
        }

        .documents-table tbody td {
            padding: 10px 13px;
            vertical-align: middle;
            border-bottom: 1px solid #edf1f5;
            color: #10233f;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .documents-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .document-file-cell {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
        }

        .document-file-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            color: #e11d48;
            background: #ffffff;
        }

        .document-file-icon svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.1;
        }

        .document-file-name {
            display: block;
            max-width: 210px;
            color: #10233f;
            font-size: 0.76rem;
            font-weight: 900;
            line-height: 1.25;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .document-file-size {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 650;
        }

        .document-type-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 8px;
            border: 1px solid rgba(10, 154, 135, 0.16);
            border-radius: 8px;
            color: #087f73;
            background: #e9fbf3;
            font-size: 0.7rem;
            font-weight: 900;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .document-type-pill::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #0a9a87;
        }

        .document-date-cell {
            display: inline-flex;
            align-items: flex-start;
            gap: 8px;
            color: #44546a;
            line-height: 1.28;
        }

        .document-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 7px;
            border-radius: 7px;
            color: #b45309;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            font-size: 0.62rem;
            line-height: 1.1;
            font-weight: 900;
        }

        .document-status-pill svg {
            width: 13px;
            height: 13px;
            stroke-width: 2.3;
            flex: 0 0 auto;
        }

        .document-status-pill.is-verified {
            color: #047857;
            background: #e9fbf3;
            border-color: #a7f3d0;
        }

        .document-status-pill.is-reupload-requested {
            color: #a16207;
            background: #fef3c7;
            border-color: #fde68a;
        }

        .document-status-pill.is-reuploaded {
            color: #a16207;
            background: #fef3c7;
            border-color: #fde68a;
        }

        .document-action-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #0b57d0;
            font-size: 0.8rem;
            font-weight: 900;
            text-decoration: none;
        }

        .document-action-link:hover {
            color: #087f73;
        }

        .document-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .document-eye-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: #0a9a87;
            font-size: 0.7rem;
            font-weight: 900;
            text-decoration: none;
        }

        .document-eye-link:hover {
            color: #087f73;
        }

        .document-reupload-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 30px;
            padding: 6px 10px;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            color: #92400e;
            background: #fffbeb;
            font-size: 0.7rem;
            font-weight: 950;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
        }

        .document-reupload-link:hover {
            color: #78350f;
            background: #fef3c7;
            text-decoration: none;
        }

        .document-reupload-link svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.5;
        }

        .document-eye-link svg {
            width: 17px;
            height: 17px;
            stroke-width: 2.5;
        }

        .documents-table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 13px;
            border-top: 1px solid #edf1f5;
            color: #44546a;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .documents-mobile-list {
            display: none;
        }

        .documents-mobile-card {
            display: grid;
            gap: 0;
            padding: 14px;
            border-bottom: 1px solid #edf1f5;
            background: #ffffff;
        }

        .documents-mobile-card:last-child {
            border-bottom: 0;
        }

        .documents-mobile-head {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) auto;
            gap: 10px;
            align-items: start;
            padding-bottom: 12px;
            border-bottom: 1px solid #e3ebf6;
        }

        .documents-mobile-head .document-status-pill {
            max-width: 112px;
            white-space: normal;
            overflow-wrap: anywhere;
            text-align: center;
            justify-content: center;
        }

        .documents-mobile-head-actions {
            display: grid;
            gap: 7px;
            justify-items: end;
        }

        .documents-mobile-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: #0a9a87;
            font-size: 0.72rem;
            font-weight: 900;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
        }

        .documents-mobile-view:hover {
            color: #087f73;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .documents-mobile-view svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.5;
        }

        .documents-mobile-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-bottom: 1px solid #e3ebf6;
        }

        .documents-mobile-meta-item {
            min-width: 0;
            padding: 11px 0;
            border-bottom: 1px solid #e3ebf6;
        }

        .documents-mobile-meta-item:nth-child(odd) {
            padding-right: 10px;
        }

        .documents-mobile-meta-item:nth-child(even) {
            padding-left: 10px;
            border-left: 1px solid #e3ebf6;
        }

        .documents-mobile-meta-item:nth-last-child(-n + 2) {
            border-bottom: 0;
        }

        .documents-mobile-meta-item span:first-child {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .documents-mobile-meta-item strong,
        .documents-mobile-meta-item div {
            display: block;
            color: #10233f;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.28;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .documents-mobile-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 11px;
        }

        .documents-mobile-empty {
            padding: 18px;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 800;
            text-align: center;
        }

        .documents-pagination {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .documents-page-button {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dbe3ee;
            border-radius: 8px;
            color: #64748b;
            background: #ffffff;
        }

        .documents-page-button svg {
            width: 16px;
            height: 16px;
        }

        .documents-page-button.is-current {
            color: #ffffff;
            background: #0a9a87;
            border-color: #0a9a87;
            font-weight: 900;
        }

        @media (max-width: 1100px) {
            .document-upload-grid {
                grid-template-columns: minmax(290px, 1fr) minmax(220px, 0.85fr);
            }

            .document-upload-button {
                grid-column: 1 / -1;
                justify-self: end;
                max-width: 168px;
            }
        }

        @media (max-width: 991px) {
            .recovery-timeline-row {
                grid-template-columns: 42px minmax(0, 1fr);
                gap: 0.7rem;
                align-items: flex-start;
                padding: 0.9rem 0;
            }

            .timeline-status-wrap {
                grid-column: 2;
                justify-self: start;
                min-width: 0;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 0.65rem;
                flex-wrap: wrap;
            }

            .execution-subtimeline {
                margin-left: 0;
                padding-left: 0;
            }

            .execution-subtimeline::before,
            .execution-subtimeline-row::before {
                display: none;
            }

            .execution-subtimeline-row {
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
                gap: 0.65rem;
                border: 1px solid #e5edf7;
                border-radius: 10px;
                padding: 0.75rem;
                margin-top: 0.6rem;
                background: #fbfdff;
            }

            .execution-subtimeline-icon {
                justify-self: start;
            }

            .execution-subtimeline-status-wrap {
                grid-column: 2;
                align-items: flex-start;
                min-width: 0;
                text-align: left;
                display: flex;
                gap: 0.55rem;
                flex-direction: row;
                flex-wrap: wrap;
            }

            .document-upload-grid {
                grid-template-columns: 1fr;
            }

            .document-dropzone {
                grid-template-columns: 34px minmax(0, 1fr);
            }

            .document-file-button {
                grid-column: 1 / -1;
            }

            .documents-list-head {
                align-items: stretch;
                flex-direction: column;
            }

            .client-approval-insights {
                grid-template-columns: 1fr;
            }

            .documents-search {
                width: 100%;
            }

            .documents-table-wrap .table-responsive {
                display: none;
            }

            .documents-mobile-list {
                display: block;
            }

            .reupload-request-item {
                grid-template-columns: 1fr;
                align-items: stretch;
            }

            .action-document-field {
                grid-template-columns: 1fr;
                align-items: stretch;
            }

            .recovery-documents-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .documents-workspace-head {
                padding: 16px 18px;
            }

            .documents-workspace-body {
                padding: 18px;
            }

            .documents-mobile-card {
                padding: 12px;
            }

            .documents-mobile-meta-item {
                padding: 10px 0;
            }

            .documents-workspace-title {
                font-size: 1.08rem;
            }

            .documents-workspace-subtitle {
                font-size: 0.8rem;
            }

            .documents-action-center {
                padding: 15px;
            }

            .action-center-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .action-submit-row {
                justify-content: stretch;
            }

            .action-submit-row .document-upload-button {
                max-width: none;
            }

            .recovery-timeline-head {
                align-items: flex-start;
                grid-template-columns: 1fr;
                gap: 0.75rem;
                padding: 1rem;
            }

            .timeline-head-actions {
                justify-items: start;
            }

            .recovery-timeline-body {
                padding: 1rem;
            }

            .recovery-timeline-title {
                font-size: 1.35rem;
            }

            .recovery-step-icon {
                width: 42px;
                height: 42px;
            }

            .recovery-step-copy h4 {
                font-size: 1rem;
            }

            .recovery-step-copy p,
            .timeline-date {
                font-size: 0.82rem;
            }

            .execution-subtimeline-row {
                grid-template-columns: 28px minmax(0, 1fr);
                gap: 0.55rem;
                padding: 0.65rem;
            }

            .execution-subtimeline-icon {
                width: 24px;
                height: 24px;
                font-size: 0.74rem;
            }

            .recovery-documents-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="container py-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="mb-1">{{ $case->trademark_name }}</h2>
                <p class="text-muted mb-0">{{ $case->case_number }} · Application {{ $case->application_number ?: 'not provided' }}</p>
            </div>
            <span class="badge bg-{{ $statusColors[$timelineStatus] ?? 'secondary' }} fs-6">{{ \App\Support\StuckTrademarkWorkflow::label($timelineStatus) }}</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card recovery-timeline-card mb-4">
                    <div class="recovery-timeline-head">
                        <div class="recovery-title-wrap">
                            <span class="recovery-title-icon"><x-lucide-clipboard-check /></span>
                            <div>
                                <h3 class="recovery-timeline-title">Recovery Status Tracking</h3>
                                <button type="button" class="recovery-documents-link" data-bs-toggle="modal" data-bs-target="#recoveryDocumentsModal">
                                    <x-lucide-list-checks />
                                    View Recovery Details & Documents
                                </button>
                            </div>
                        </div>
                        <div class="timeline-head-actions">
                            <span class="registry-pill is-status">{{ \App\Support\StuckTrademarkWorkflow::label($timelineStatus) }}</span>
                            <span class="registry-pill">Registry: {{ $case->registry_status ?: 'Not Filed' }}</span>
                        </div>
                    </div>
                    <div class="recovery-timeline-body">
                        @foreach ($timelineSteps as $step)
                            @php
                                $stepRank = $loop->index;
                                $matchedLog = $timelineLogForStep($step);
                                $dateValue = $matchedLog?->created_at ?? $step['fallback'];
                                $formattedDate = $dateValue
                                    ? ($dateValue instanceof \Carbon\CarbonInterface ? $dateValue->copy() : \Illuminate\Support\Carbon::parse($dateValue))->timezone($displayTimezone)->format('d M Y, h:i A')
                                    : null;
                                $isCurrent = in_array($timelineStatus, $step['statuses'], true) && $stepRank === $effectiveRank;
                                $isCompleted = !$isCurrent && $stepRank < $effectiveRank;
                                if ($step['label'] === 'Documents Verified' && $allUploadedVerificationDocumentsVerified) {
                                    $isCurrent = false;
                                    $isCompleted = true;
                                }
                                if ($step['label'] === 'Resolved & Closed' && $timelineStatus === \App\Support\StuckTrademarkWorkflow::CLOSED) {
                                    $isCurrent = false;
                                    $isCompleted = true;
                                }
                                $isCurrent = $isCurrent || (!$isCompleted && $stepRank === $effectiveRank);
                                $isUpcoming = !$isCurrent && !$isCompleted;
                                $isSkipped = $step['label'] === 'Execution Package' && $case->execution_payment_status === 'skipped';
                                $stateClass = $isCurrent ? 'is-current' : ($isCompleted ? 'is-completed' : 'is-upcoming');
                                $pillLabel = $isCompleted ? 'Completed' : ($isCurrent ? 'Active' : 'Pending');
                                $pillIcon = $isCompleted ? 'circle-check' : ($isCurrent ? 'user-check' : 'clock');
                                if ($isSkipped) {
                                    $stateClass = 'is-skipped';
                                    $pillLabel = 'Skipped';
                                    $pillIcon = 'skip-forward';
                                }
                                $metaValue = $formattedDate
                                    ? $formattedDate
                                    : ($step['action_text'] ?? ($isCompleted ? 'Completed' : 'Awaiting Completion'));
                                $stepDocuments = $documentsForRecoveryStep($step['label']);
                                $stepHasAdminDocuments = $stepDocuments['admin']->isNotEmpty();
                                $stepHasClientDocuments = $stepDocuments['client']->isNotEmpty();
                                $stepDocumentsTab = $stepHasAdminDocuments ? 'admin' : 'client';
                            @endphp
                            <div class="recovery-timeline-row {{ $isCurrent ? 'is-current' : '' }} {{ $isUpcoming ? 'is-upcoming' : '' }} {{ $isSkipped ? 'is-skipped' : '' }} {{ $step['label'] === 'Execution in Progress' && $case->execution_payment_status === 'paid' ? 'execution-expanded' : '' }}">
                                <div class="recovery-rail">
                                    <span class="recovery-step-icon">
                                        <x-dynamic-component :component="'lucide-' . $step['icon']" />
                                        @if ($isCompleted && !$isSkipped)
                                            <span class="step-completed-check"><x-lucide-check /></span>
                                        @endif
                                    </span>
                                </div>
                                <div class="recovery-step-copy">
                                    <div class="recovery-step-title-row">
                                        <h4>
                                            @if ($canContinueOnboarding && $step['label'] === 'Client Onboarding')
                                                <a href="{{ route('stuck-trademark.onboarding', $case) }}" class="recovery-step-title-link">
                                                    {{ $step['label'] }} <x-lucide-arrow-right />
                                                </a>
                                            @elseif ($canUploadDocuments && $step['label'] === 'Documents Uploaded')
                                                <a href="{{ route('stuck-trademark.documents', $case) }}" class="recovery-step-title-link">
                                                    Document Uploads <x-lucide-arrow-right />
                                                </a>
                                            @else
                                                {{ $step['label'] }}
                                            @endif
                                        </h4>
                                        @if ($stepHasAdminDocuments || $stepHasClientDocuments)
                                            <button type="button" class="recovery-step-documents-link" data-bs-toggle="modal" data-bs-target="#recoveryDocumentsModal" data-recovery-doc-tab="{{ $stepDocumentsTab }}">
                                                <x-lucide-file-text />
                                                View documents
                                            </button>
                                        @endif
                                    </div>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                                <div class="timeline-status-wrap">
                                    <span class="recovery-status-pill {{ $stateClass }}">
                                        <x-dynamic-component :component="'lucide-' . $pillIcon" />
                                        {{ $pillLabel }}
                                    </span>
                                    @if ($formattedDate)
                                        <span class="timeline-date">{{ $metaValue }}</span>
                                    @endif
                                </div>
                                @if ($step['label'] === 'Execution in Progress' && $case->execution_payment_status === 'paid')
                                    <div class="execution-subtimeline">
                                        @foreach ($executionSubSteps as $subKey => $subStep)
                                            @php
                                                $subIndex = array_search($subKey, $executionSubStageOrder, true);
                                                $subCompleted = $executionIsComplete
                                                    || $executionSubStageIndex > $subIndex
                                                    || ($subKey === 'execution_started' && $case->execution_started_at)
                                                    || ($subKey === 'execution_completed' && $case->execution_completed_at);
                                                $subCurrent = ! $executionIsComplete && $executionSubStageIndex === $subIndex;
                                                $subUpdate = $case->executionUpdates->firstWhere('stage', $subStep['label']);
                                                $subDate = $subUpdate?->created_at
                                                    ? $subUpdate->created_at->timezone($displayTimezone)->format('d M Y, h:i A')
                                                    : null;
                                                $subStateClass = $subCompleted ? 'is-completed' : ($subCurrent ? 'is-current' : 'is-upcoming');
                                                $subPillLabel = $subCompleted ? 'Completed' : ($subCurrent ? 'Active' : 'Pending');
                                                $subPillIcon = $subCompleted ? 'circle-check' : ($subCurrent ? 'user-check' : 'clock');
                                                $subStepDocuments = $documentsForExecutionSubStep($subKey, $subStep);
                                            @endphp
                                            <div class="execution-subtimeline-row {{ $subCompleted ? 'is-completed' : '' }} {{ $subCurrent ? 'is-current' : '' }}">
                                                <span class="execution-subtimeline-icon">{{ $loop->iteration }}</span>
                                                <div>
                                                    <div class="execution-subtimeline-title-row">
                                                        <h5 class="execution-subtimeline-title">{{ $subStep['label'] }}</h5>
                                                        @if ($subStepDocuments->isNotEmpty())
                                                            <button type="button" class="recovery-step-documents-link" data-bs-toggle="modal" data-bs-target="#recoveryDocumentsModal" data-recovery-doc-tab="admin">
                                                                <x-lucide-file-text />
                                                                View documents
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <p class="execution-subtimeline-copy">{{ $subStep['copy'] }}</p>
                                                </div>
                                                <div class="execution-subtimeline-status-wrap">
                                                    <span class="recovery-status-pill {{ $subStateClass }}">
                                                        <x-dynamic-component :component="'lucide-' . $subPillIcon" />
                                                        {{ $subPillLabel }}
                                                    </span>
                                                    @if ($subDate)
                                                        <span class="timeline-date">{{ $subDate }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($shouldShowAuditPackageAction)
                    @php
                        $auditActionCoupons = $auditPaymentCoupons ?? collect();
                        $auditActionCoupon = $auditActionCoupons->first();
                        $auditOriginalFee = (float) $case->audit_fee;
                        $auditDiscountedFee = $auditActionCoupon
                            ? $auditActionCoupon->discountedAmountFor($auditOriginalFee)
                            : $auditOriginalFee;
                        $showAuditDiscount = $auditActionCoupon && $auditDiscountedFee < $auditOriginalFee;
                    @endphp
                    <div class="card client-approval-center mb-4" id="stage-action">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                        <div class="client-approval-guide">
                            <div class="client-approval-guide-head">
                                <span><x-lucide-chart-pie /></span>
                                <div>
                                    <h6>Audit Package</h6>
                                    <p>Activate the recovery audit package once your uploaded documents are verified by the admin team.</p>
                                </div>
                            </div>
                        </div>

                        <div class="client-approval-insights">
                            <div class="client-approval-insight">
                                <div class="client-approval-insight-label">
                                    <x-lucide-indian-rupee />
                                    Package Fee
                                </div>
                                <p class="client-approval-insight-value">
                                    @if ($showAuditDiscount)
                                        <span class="client-package-price-stack">
                                            <span class="client-package-price-original">₹{{ number_format($auditOriginalFee, 2) }}</span>
                                            <span class="client-package-price-discounted">₹{{ number_format($auditDiscountedFee, 2) }}</span>
                                        </span>
                                    @else
                                        ₹{{ number_format($auditOriginalFee, 2) }}
                                    @endif
                                </p>
                                @if ($auditActionCoupon)
                                    <div class="client-package-offer">
                                        <x-lucide-badge-percent />
                                        Special offer: {{ $auditActionCoupon->discount_label }}
                                    </div>
                                @endif
                            </div>
                            <div class="client-approval-insight">
                                <div class="client-approval-insight-label">
                                    <x-lucide-clock />
                                    Payment Status
                                </div>
                                <p class="client-approval-insight-value">
                                    {{ ucfirst($case->audit_payment_status) }}
                                </p>
                            </div>
                        </div>

                        <div class="audit-features">
                            <div class="audit-feature"><span class="audit-feature-icon"><x-lucide-circle-check /></span> Comprehensive Case Audit</div>
                            <div class="audit-feature"><span class="audit-feature-icon"><x-lucide-circle-check /></span> Legal Expert Review</div>
                            <div class="audit-feature"><span class="audit-feature-icon"><x-lucide-circle-check /></span> Detailed Recovery Roadmap</div>
                            <div class="audit-feature"><span class="audit-feature-icon"><x-lucide-circle-check /></span> Recommendations</div>
                        </div>

                        @include('partials.government-fee-notice')

                        <a href="{{ route('stuck-trademark.audit-package', $case) }}" class="btn audit-action-button w-100 {{ ! $canPurchaseAuditPackage ? 'disabled' : '' }}" @if (! $canPurchaseAuditPackage) aria-disabled="true" tabindex="-1" @endif>
                            <x-lucide-rocket /> Activate Audit Package
                        </a>
                        @unless ($canPurchaseAuditPackage)
                            <p class="audit-gate-note">Audit package purchase unlocks after all current uploaded documents are verified by the admin team.</p>
                        @endunless
                        </div>
                    </div>
                @endif

                @if ($canReviewAuditReport)
                    <div class="card client-approval-center mb-4" id="stage-action">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                            <div class="client-approval-guide">
                                <div class="client-approval-guide-head">
                                    <span><x-lucide-file-check /></span>
                                    <div>
                                        <h6>Client Approval</h6>
                                        <p>Review the delivered audit report. You can approve it to unlock the execution package, or request a corrected upload from the legal team.</p>
                                    </div>
                                </div>
                            </div>

                            @if (filled($case->audit_summary))
                                <div class="client-approval-admin-note">
                                    <x-lucide-message-square-warning />
                                    <div>
                                        <strong>Admin Note</strong>
                                        <span>{{ $case->audit_summary }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="client-approval-insights">
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-shield-alert />
                                        Risk
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->risk_level ? ucfirst($case->risk_level) : 'Not marked' }}
                                    </p>
                                </div>
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-lightbulb />
                                        Professional Recommendation
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->execution_recommendation ?: 'Recommendation not added yet.' }}
                                    </p>
                                </div>
                            </div>

                            @if ($auditSupportingDocuments->isNotEmpty())
                                <div class="client-approval-documents">
                                    <h6 class="client-approval-documents-title">
                                        <x-lucide-paperclip />
                                        Optional Documents Sent by Admin
                                    </h6>
                                    <div class="client-approval-document-list">
                                        @foreach ($auditSupportingDocuments as $document)
                                            @php
                                                $sizeInKb = max(1, (int) ceil($document->file_size / 1024));
                                                $displaySize = $sizeInKb >= 1024
                                                    ? number_format($sizeInKb / 1024, 1) . ' MB'
                                                    : number_format($sizeInKb) . ' KB';
                                            @endphp
                                            <div class="client-approval-document-item">
                                                <span>
                                                    <span class="client-approval-document-name">{{ $document->file_name }}</span>
                                                    <span class="client-approval-document-meta">{{ $displaySize }} | Shared by legal team</span>
                                                </span>
                                                <a href="{{ route('stuck-trademark.document.view', $document) }}" target="_blank" class="client-approval-document-view">
                                                    <x-lucide-eye /> View
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <a href="{{ route('stuck-trademark.audit-report', $case) }}" target="_blank" class="client-approval-report-link">
                                <x-lucide-file-text /> View Audit Report
                            </a>

                            <div class="audit-review-actions">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveAuditReportModal">
                                    <x-lucide-check-circle /> Approve
                                </button>
                                <button type="button" class="btn btn-ask-reupload" data-bs-toggle="modal" data-bs-target="#requestAuditReportReuploadModal">
                                    <x-lucide-refresh-cw /> Ask for Reupload
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade audit-review-modal" id="approveAuditReportModal" tabindex="-1" aria-labelledby="approveAuditReportModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form action="{{ route('stuck-trademark.audit-report.approve', $case) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="approveAuditReportModalLabel">Approve Audit Report</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted">Approve this audit report to unlock the execution package recommendation.</p>
                                        <label class="form-label">Optional Note</label>
                                        <textarea name="audit_report_client_note" rows="3" class="form-control" placeholder="Add an optional note for the legal team">{{ old('audit_report_client_note') }}</textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">
                                            <x-lucide-check-circle /> Approve
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade audit-review-modal" id="requestAuditReportReuploadModal" tabindex="-1" aria-labelledby="requestAuditReportReuploadModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form action="{{ route('stuck-trademark.audit-report.request-reupload', $case) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="requestAuditReportReuploadModalLabel">Request Reupload</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted">Ask the legal team to upload a corrected audit report.</p>
                                        <label class="form-label">Optional Note</label>
                                        <textarea name="audit_report_client_note" rows="4" class="form-control @error('audit_report_client_note') is-invalid @enderror" placeholder="Describe what needs to be corrected or clarified">{{ old('audit_report_client_note') }}</textarea>
                                        @error('audit_report_client_note')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">
                                            <x-lucide-refresh-cw /> Reject & Request Reupload
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($case->status === \App\Support\StuckTrademarkWorkflow::AWAITING_APPROVAL)
                    <div class="card client-approval-center mb-4">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                            <div class="client-approval-guide">
                                <div class="client-approval-guide-head">
                                    <span><x-lucide-rocket /></span>
                                    <div>
                                        <h6>Execution Package</h6>
                                        <p>Approve the recommended execution path to begin recovery work, or skip this package and continue to the next recovery step.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="client-approval-insights">
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-indian-rupee />
                                        Quoted Fee
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->execution_fee ? '₹' . number_format((float) $case->execution_fee, 2) : 'Not quoted yet' }}
                                    </p>
                                </div>
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-lightbulb />
                                        Professional Recommendation
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->execution_recommendation ?: 'Recommendation not added yet.' }}
                                    </p>
                                </div>
                            </div>

                            <div class="audit-review-actions">
                                <form action="{{ route('stuck-trademark.approve-execution', $case) }}" method="POST" data-swal-confirm data-swal-title="Approve execution?" data-swal-text="The operations team will issue execution payment instructions." data-swal-icon="question" data-swal-confirm-text="Approve">
                                    @csrf
                                    <button class="btn btn-success w-100" type="submit">
                                        <x-lucide-check-circle /> Approve Execution
                                    </button>
                                </form>
                                <form action="{{ route('stuck-trademark.skip-execution', $case) }}" method="POST" data-swal-confirm data-swal-title="Skip execution package?" data-swal-text="This will mark the execution package as skipped and move the case to the next recovery step." data-swal-icon="warning" data-swal-confirm-text="Skip Package">
                                    @csrf
                                    <button class="btn btn-ask-reupload w-100" type="submit">
                                        <x-lucide-skip-forward /> Skip Execution Package
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($case->status === \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING && $case->execution_payment_status === 'pending')
                    <div class="card client-approval-center mb-4">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                            <div class="client-approval-guide">
                                <div class="client-approval-guide-head">
                                    <span><x-lucide-credit-card /></span>
                                    <div>
                                        <h6>Execution Payment</h6>
                                        <p>Your execution package is approved. Complete payment to activate recovery work.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="client-approval-insights">
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-indian-rupee />
                                        Amount Payable
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->execution_fee ? '₹' . number_format((float) $case->execution_fee, 2) : 'Not quoted yet' }}
                                    </p>
                                </div>
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-lightbulb />
                                        Professional Recommendation
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->execution_recommendation ?: 'Recommendation not added yet.' }}
                                    </p>
                                </div>
                            </div>

                            @include('partials.payment-coupons', [
                                'paymentCoupons' => $executionPaymentCoupons ?? collect(),
                                'couponInputName' => 'execution_discount_coupon_id',
                                'paymentAmount' => (float) $case->execution_fee,
                            ])

                            @include('partials.government-fee-notice')

                            <button type="button" class="btn btn-success w-100" id="execution-pay-button" @if (! $case->execution_fee) disabled @endif>
                                <x-lucide-credit-card /> Pay Execution Package Fee
                            </button>
                        </div>
                    </div>
                @endif

                @if ($monitoringIsActive)
                    <div class="card client-approval-center mb-4" id="stage-action">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                            <div class="client-approval-guide">
                                <div class="client-approval-guide-head">
                                    <span><x-lucide-trending-up /></span>
                                    <div>
                                        <h6>Monitoring & Updates</h6>
                                        <p>Admin monitoring updates and registry movement notes will appear here as the team tracks your case.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="client-approval-insights">
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-activity />
                                        Monitoring Status
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ in_array($case->monitoring_status, ['Active', 'Completed'], true) ? $case->monitoring_status : 'Active' }}
                                    </p>
                                </div>
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-calendar />
                                        Next Follow-up
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->next_follow_up_at ? $case->next_follow_up_at->timezone($displayTimezone)->format('d M Y, h:i A') : 'To be updated by admin' }}
                                    </p>
                                </div>
                            </div>

                            @if ($monitoringUpdates->isNotEmpty())
                                <div class="monitoring-update-list">
                                    @foreach ($monitoringUpdates as $update)
                                        <div class="monitoring-update-item">
                                            <div class="monitoring-update-head">
                                                <h6 class="monitoring-update-title">{{ $update->title }}</h6>
                                                <span class="monitoring-update-date">{{ $update->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</span>
                                            </div>
                                            @if ($update->note)
                                                <p class="monitoring-update-note"><strong>Admin Note:</strong> {{ $update->note }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="client-approval-admin-note">
                                    <x-lucide-info />
                                    <div>
                                        <strong>No monitoring update yet</strong>
                                        <span>The admin team has activated monitoring. Updates will be shared here when available.</span>
                                    </div>
                                </div>
                            @endif

                            @if ($monitoringDocuments->isNotEmpty())
                                <div class="client-approval-documents mb-0">
                                    <h6 class="client-approval-documents-title">
                                        <x-lucide-paperclip />
                                        Monitoring Documents Sent by Admin
                                    </h6>
                                    <div class="client-approval-document-list">
                                        @foreach ($monitoringDocuments as $document)
                                            <div class="client-approval-document-item">
                                                <span>
                                                    <span class="client-approval-document-name">{{ $document->document_title }}</span>
                                                    <span class="client-approval-document-meta">Shared by legal team</span>
                                                </span>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="client-approval-document-view">
                                                    <x-lucide-eye /> View
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($resolvedIsActive)
                    <div class="card client-approval-center mb-4" id="stage-action">
                        <div class="card-header">
                            <h5>Action Center</h5>
                        </div>
                        <div class="card-body">
                            <div class="client-approval-guide">
                                <div class="client-approval-guide-head">
                                    <span><x-lucide-badge-check /></span>
                                    <div>
                                        <h6>Resolved & Closed</h6>
                                        <p>Your case resolution details, final report and closing documents shared by the admin team are available here.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="client-approval-insights">
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-activity />
                                        Resolution Status
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->status === \App\Support\StuckTrademarkWorkflow::CLOSED ? 'Completed' : 'Active' }}
                                    </p>
                                </div>
                                <div class="client-approval-insight">
                                    <div class="client-approval-insight-label">
                                        <x-lucide-calendar />
                                        Closed On
                                    </div>
                                    <p class="client-approval-insight-value">
                                        {{ $case->closed_at ? $case->closed_at->timezone($displayTimezone)->format('d M Y, h:i A') : 'Final closure pending' }}
                                    </p>
                                </div>
                            </div>

                            @if ($resolutionUpdates->isNotEmpty())
                                <div class="monitoring-update-list">
                                    @foreach ($resolutionUpdates as $update)
                                        <div class="monitoring-update-item">
                                            <div class="monitoring-update-head">
                                                <h6 class="monitoring-update-title">{{ $update->title }}</h6>
                                                <span class="monitoring-update-date">{{ $update->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</span>
                                            </div>
                                            @if ($update->note)
                                                <p class="monitoring-update-note"><strong>Admin Note:</strong> {{ $update->note }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($finalReportDocuments->isNotEmpty())
                                <div class="client-approval-documents">
                                    <h6 class="client-approval-documents-title">
                                        <x-lucide-file-check />
                                        Final Report
                                    </h6>
                                    <div class="client-approval-document-list">
                                        @foreach ($finalReportDocuments as $document)
                                            <div class="client-approval-document-item">
                                                <span>
                                                    <span class="client-approval-document-name">{{ $document->document_title }}</span>
                                                    <span class="client-approval-document-meta">Shared by legal team</span>
                                                </span>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="client-approval-document-view">
                                                    <x-lucide-eye /> View
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($resolutionOptionalDocuments->isNotEmpty())
                                <div class="client-approval-documents mb-0">
                                    <h6 class="client-approval-documents-title">
                                        <x-lucide-paperclip />
                                        Additional Closing Documents
                                    </h6>
                                    <div class="client-approval-document-list">
                                        @foreach ($resolutionOptionalDocuments as $document)
                                            <div class="client-approval-document-item">
                                                <span>
                                                    <span class="client-approval-document-name">{{ $document->document_title }}</span>
                                                    <span class="client-approval-document-meta">Shared by legal team</span>
                                                </span>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="client-approval-document-view">
                                                    <x-lucide-eye /> View
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($resolutionUpdates->isEmpty() && $resolutionDocuments->isEmpty())
                                <div class="client-approval-admin-note mb-0">
                                    <x-lucide-info />
                                    <div>
                                        <strong>No final report shared yet</strong>
                                        <span>The admin team will share resolution data and closing documents here.</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <section class="card documents-workspace-card mb-4" aria-labelledby="stuck-trademark-uploaded-documents-title">
                    <div class="documents-workspace-body">

                        <div class="documents-list-head">
                            <h4 class="documents-list-title" id="stuck-trademark-uploaded-documents-title"><x-lucide-file-text /> Uploaded Documents</h4>
                            <div class="documents-search">
                                <x-lucide-search />
                                <input type="search" id="documents-search-input" placeholder="Search documents..." aria-label="Search documents">
                            </div>
                        </div>

                        <div class="documents-table-wrap">
                            <div class="table-responsive">
                                <table class="table documents-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Type</th>
                                            <th>Uploaded On</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($case->documents as $document)
                                            @php
                                                $documentStatus = strtolower($document->status ?? 'pending');
                                                $documentStatus = $documentStatus === 'rejected' ? 'reupload_requested' : $documentStatus;
                                                $statusState = in_array($documentStatus, ['verified', 'reupload_requested', 'reuploaded'], true) ? $documentStatus : 'pending';
                                                $statusClass = str_replace('_', '-', $statusState);
                                                $documentStatusLabel = ucwords(str_replace('_', ' ', $documentStatus));
                                                $sizeInKb = max(1, (int) ceil(((int) $document->file_size) / 1024));
                                                $displaySize = $sizeInKb >= 1024
                                                    ? number_format($sizeInKb / 1024, 1) . ' MB'
                                                    : number_format($sizeInKb) . ' KB';
                                                $documentTypeLabel = $documentTypeOptions[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));
                                            @endphp
                                            <tr data-document-row data-search-text="{{ strtolower($document->file_name . ' ' . $displaySize . ' ' . $documentTypeLabel . ' ' . $documentStatus . ' ' . $document->created_at->timezone($displayTimezone)->format('d M Y h:i A')) }}">
                                                <td>
                                                    <div class="document-file-cell">
                                                        <span class="document-file-icon"><x-lucide-file-text /></span>
                                                        <span>
                                                            <span class="document-file-name">{{ $document->file_name }}</span>
                                                            <span class="document-file-size">{{ $displaySize }}</span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="document-type-pill">{{ $documentTypeLabel }}</span>
                                                </td>
                                                <td>
                                                    <span class="document-date-cell">
                                                        <x-lucide-calendar />
                                                        <span>{{ $document->created_at->timezone($displayTimezone)->format('d M Y') }}<br>{{ $document->created_at->timezone($displayTimezone)->format('h:i A') }}</span>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="document-status-pill is-{{ $statusClass }}">
                                                        @if ($statusState === 'verified')
                                                            <x-lucide-circle-check />
                                                        @elseif (in_array($statusState, ['reupload_requested', 'reuploaded'], true))
                                                            <x-lucide-refresh-cw />
                                                        @else
                                                            <x-lucide-clock />
                                                        @endif
                                                        {{ $documentStatusLabel }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="document-actions">
                                                        @if ($statusState === 'reupload_requested')
                                                            <a href="{{ route('stuck-trademark.documents', $case) }}" class="document-reupload-link" aria-label="Reupload {{ $document->file_name }}">
                                                                <x-lucide-refresh-cw />
                                                                <span>Reupload</span>
                                                            </a>
                                                        @endif
                                                        <a href="{{ route('stuck-trademark.document.view', $document) }}" target="_blank" class="document-eye-link" aria-label="View {{ $document->file_name }}">
                                                            <x-lucide-eye />
                                                            <span>View</span>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No documents uploaded yet.</td>
                                            </tr>
                                        @endforelse
                                        @if ($case->documents->count())
                                            <tr data-documents-no-results hidden>
                                                <td colspan="5" class="text-center text-muted py-4">No matching documents found.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="documents-mobile-list">
                                @forelse ($case->documents as $document)
                                    @php
                                        $documentStatus = strtolower($document->status ?? 'pending');
                                        $documentStatus = $documentStatus === 'rejected' ? 'reupload_requested' : $documentStatus;
                                        $statusState = in_array($documentStatus, ['verified', 'reupload_requested', 'reuploaded'], true) ? $documentStatus : 'pending';
                                        $statusClass = str_replace('_', '-', $statusState);
                                        $documentStatusLabel = ucwords(str_replace('_', ' ', $documentStatus));
                                        $sizeInKb = max(1, (int) ceil(((int) $document->file_size) / 1024));
                                        $displaySize = $sizeInKb >= 1024
                                            ? number_format($sizeInKb / 1024, 1) . ' MB'
                                            : number_format($sizeInKb) . ' KB';
                                        $documentTypeLabel = $documentTypeOptions[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));
                                        $documentSearchText = strtolower($document->file_name . ' ' . $displaySize . ' ' . $documentTypeLabel . ' ' . $documentStatus . ' ' . $document->created_at->timezone($displayTimezone)->format('d M Y h:i A'));
                                    @endphp
                                    <article class="documents-mobile-card" data-document-card data-search-text="{{ $documentSearchText }}">
                                        <div class="documents-mobile-head">
                                            <span class="document-file-icon"><x-lucide-file-text /></span>
                                            <span>
                                                <span class="document-file-name">{{ $document->file_name }}</span>
                                                <span class="document-file-size">{{ $displaySize }}</span>
                                            </span>
                                            <span class="documents-mobile-head-actions">
                                                <span class="document-status-pill is-{{ $statusClass }}">
                                                    @if ($statusState === 'verified')
                                                        <x-lucide-circle-check />
                                                    @elseif (in_array($statusState, ['reupload_requested', 'reuploaded'], true))
                                                        <x-lucide-refresh-cw />
                                                    @else
                                                        <x-lucide-clock />
                                                    @endif
                                                    {{ $documentStatusLabel }}
                                                </span>
                                                <a href="{{ route('stuck-trademark.document.view', $document) }}" target="_blank" class="documents-mobile-view" aria-label="View {{ $document->file_name }}">
                                                    <x-lucide-eye />
                                                    <span>View</span>
                                                </a>
                                                @if ($statusState === 'reupload_requested')
                                                    <a href="{{ route('stuck-trademark.documents', $case) }}" class="document-reupload-link" aria-label="Reupload {{ $document->file_name }}">
                                                        <x-lucide-refresh-cw />
                                                        <span>Reupload</span>
                                                    </a>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="documents-mobile-meta">
                                            <div class="documents-mobile-meta-item">
                                                <span>Type</span>
                                                <strong>{{ $documentTypeLabel }}</strong>
                                            </div>
                                            <div class="documents-mobile-meta-item">
                                                <span>Uploaded On</span>
                                                <strong>{{ $document->created_at->timezone($displayTimezone)->format('d M Y') }} {{ $document->created_at->timezone($displayTimezone)->format('h:i A') }}</strong>
                                            </div>
                                        </div>
                                    </article>
                                @empty
                                    <div class="documents-mobile-empty">No documents uploaded yet.</div>
                                @endforelse
                                @if ($case->documents->count())
                                    <div class="documents-mobile-empty" data-documents-no-results-card hidden>No matching documents found.</div>
                                @endif
                            </div>
                            <div class="documents-table-footer">
                                <span data-documents-count-summary>Showing {{ $case->documents->count() ? '1' : '0' }} to {{ $case->documents->count() }} of {{ $case->documents->count() }} document{{ $case->documents->count() === 1 ? '' : 's' }}</span>
                                <div class="documents-pagination" aria-label="Documents pagination">
                                    <span class="documents-page-button"><x-lucide-chevron-left /></span>
                                    <span class="documents-page-button is-current">1</span>
                                    <span class="documents-page-button"><x-lucide-chevron-right /></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="card shadow-sm border-0 activity-log-card">
                    <div class="card-header">
                        <h5 class="mb-0">Activity Log</h5>
                    </div>
                    <div class="card-body">
                        @forelse ($case->statusLogs as $log)
                            <div class="border-start border-3 ps-3 pb-3">
                                <div class="fw-bold">{{ $log->title }}</div>
                                <small class="text-muted">{{ $log->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }} · {{ \App\Support\StuckTrademarkWorkflow::label($log->to_status) }}</small>
                                @if ($log->message)
                                    <p class="mb-0 mt-1">{{ $log->message }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card recovery-side-card mb-4">
                    <div class="card-header">
                        <span class="side-card-header-icon"><x-lucide-file-search /></span>
                        <h5 class="mb-0">Case Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="summary-list">
                            <div class="summary-row">
                                <span class="summary-row-icon is-issue"><x-lucide-triangle-alert /></span>
                                <div>
                                    <span class="summary-label">Issue:</span>
                                    <p class="summary-value">{{ $case->issue_summary ?: 'Not classified' }}</p>
                                </div>
                            </div>
                            <div class="summary-row">
                                <span class="summary-row-icon is-registry"><x-lucide-landmark /></span>
                                <div>
                                    <span class="summary-label">Registry:</span>
                                    <p class="summary-value">{{ $case->registry_status ? ucwords(str_replace('_', ' ', $case->registry_status)) : 'Not provided' }}</p>
                                    <p class="summary-value mt-1">Class: {{ $case->trademark_class ?: 'Not provided' }}</p>
                                </div>
                            </div>
                            <div class="summary-row">
                                <span class="summary-row-icon is-urgency"><x-lucide-alert-triangle /></span>
                                <div>
                                    <span class="summary-label">Urgency:</span>
                                    <p class="summary-value">{{ ucfirst($case->urgency) }}</p>
                                </div>
                            </div>
                            <div class="summary-row">
                                <span class="summary-row-icon is-problem"><x-lucide-circle-help /></span>
                                <div>
                                    <span class="summary-label">Problem:</span>
                                    <p class="summary-value">{{ $case->problem_summary }}</p>
                                    @if ($case->status_unchanged_since)
                                        <p class="summary-value mt-1">Unchanged since: {{ $case->status_unchanged_since }}</p>
                                    @endif
                                    @if ($case->previous_attorney_details)
                                        <p class="summary-value mt-1">Previous attorney: {{ $case->previous_attorney_details }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($case->audit_payment_status === 'paid')
                    <div class="card recovery-side-card mb-4">
                        <div class="card-header">
                            <span class="side-card-header-icon"><x-lucide-chart-pie /></span>
                            <h5 class="mb-0">Audit Package</h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-list">
                                <div class="summary-row">
                                    <span class="summary-row-icon is-package"><x-lucide-circle-check /></span>
                                    <div>
                                        <span class="summary-label">Status:</span>
                                        <p class="summary-value">Paid{{ $case->audit_paid_at ? ' on ' . $case->audit_paid_at->timezone($displayTimezone)->format('d M Y, h:i A') : '' }}</p>
                                    </div>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-row-icon is-payment"><x-lucide-indian-rupee /></span>
                                    <div>
                                        <span class="summary-label">Package Fee:</span>
                                        <p class="summary-value">
                                            @if ($hasAuditDiscount)
                                                <span class="text-decoration-line-through text-muted">₹{{ number_format($auditOriginalAmount, 2) }}</span>
                                                <span class="d-block text-success">Paid ₹{{ number_format($auditPaidAmount, 2) }}</span>
                                            @else
                                                ₹{{ number_format($auditPaidAmount, 2) }}
                                            @endif
                                        </p>
                                        @if ($hasAuditDiscount)
                                            <p class="summary-value mt-1 text-success">Discount: ₹{{ number_format($auditDiscountAmount, 2) }}{{ $case->audit_coupon_label ? ' (' . $case->audit_coupon_label . ')' : '' }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($case->audit_transaction_id)
                                    <div class="summary-row">
                                        <span class="summary-row-icon is-payment"><x-lucide-receipt-text /></span>
                                        <div>
                                            <span class="summary-label">Transaction:</span>
                                            <p class="summary-value">{{ $case->audit_transaction_id }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <a href="{{ route('stuck-trademark.audit-payment.invoice', $case) }}" target="_blank" class="btn btn-outline-success w-100 mt-3">
                                View Audit Invoice
                            </a>
                        </div>
                    </div>
                @endif

                @if ($shouldShowExecutionPackageSidebar)
                    <div class="card recovery-side-card mb-4">
                        <div class="card-header">
                            <span class="side-card-header-icon"><x-lucide-rocket /></span>
                            <h5 class="mb-0">Execution Package</h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-list">
                                <div class="summary-row">
                                    <span class="summary-row-icon is-package"><x-lucide-activity /></span>
                                    <div>
                                        <span class="summary-label">Status:</span>
                                        <p class="summary-value">{{ $case->execution_payment_status ? ucwords(str_replace('_', ' ', $case->execution_payment_status)) : \App\Support\StuckTrademarkWorkflow::label($case->status) }}</p>
                                    </div>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-row-icon is-payment"><x-lucide-indian-rupee /></span>
                                    <div>
                                        <span class="summary-label">Package Fee:</span>
                                        <p class="summary-value">{{ $case->execution_fee ? '₹' . number_format((float) $case->execution_fee, 2) : 'Not quoted yet' }}</p>
                                    </div>
                                </div>
                                @if ($case->execution_paid_at)
                                    <div class="summary-row">
                                        <span class="summary-row-icon is-package"><x-lucide-circle-check /></span>
                                        <div>
                                            <span class="summary-label">Paid On:</span>
                                            <p class="summary-value">{{ $case->execution_paid_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</p>
                                        </div>
                                    </div>
                                @endif
                                @if ($case->execution_recommendation)
                                    <div class="summary-row">
                                        <span class="summary-row-icon is-problem"><x-lucide-lightbulb /></span>
                                        <div>
                                            <span class="summary-label">Recommendation:</span>
                                            <p class="summary-value">{{ $case->execution_recommendation }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($case->executionDocuments->where('visible_to_client', true)->isNotEmpty())
                    <div class="card recovery-side-card mb-4">
                        <div class="card-header">
                            <span class="side-card-header-icon"><x-lucide-paperclip /></span>
                            <h5 class="mb-0">Execution Documents</h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-list">
                                @foreach ($case->executionDocuments->where('visible_to_client', true) as $document)
                                    <div class="summary-row execution-document-row">
                                        <span class="summary-row-icon is-package"><x-lucide-file-text /></span>
                                        <div class="execution-document-summary">
                                            <span class="summary-label">{{ $document->execution_stage ?: 'Execution document' }}</span>
                                            <p class="summary-value">{{ $document->document_title }}</p>
                                        </div>
                                        <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="execution-document-view">View</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    @if ($case->status === \App\Support\StuckTrademarkWorkflow::EXECUTION_PAYMENT_PENDING && $case->execution_payment_status === 'pending')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @endif

    <div class="modal fade recovery-documents-modal" id="recoveryDocumentsModal" tabindex="-1" aria-labelledby="recoveryDocumentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="recoveryDocumentsModalLabel">Recovery Status Documents</h5>
                        <p class="modal-subtitle">View admin-sent recovery documents and client-submitted documents.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav recovery-documents-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="admin-sent-documents-tab" data-bs-toggle="tab" data-bs-target="#admin-sent-documents-panel" type="button" role="tab" aria-controls="admin-sent-documents-panel" aria-selected="true">
                                Admin Sent Documents
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="client-submitted-documents-tab" data-bs-toggle="tab" data-bs-target="#client-submitted-documents-panel" type="button" role="tab" aria-controls="client-submitted-documents-panel" aria-selected="false">
                                Client Submitted Documents
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="admin-sent-documents-panel" role="tabpanel" aria-labelledby="admin-sent-documents-tab" tabindex="0">
                            @if ($adminSentDocuments->isNotEmpty())
                                <div class="recovery-documents-grid">
                                    @foreach ($adminSentDocuments as $document)
                                        <article class="recovery-document-card">
                                            <div>
                                                <span class="recovery-document-icon"><x-lucide-file-text /></span>
                                                <span class="recovery-document-title">{{ $document['title'] }}</span>
                                                <span class="recovery-document-meta">{{ $document['type'] }} · {{ $document['stage'] }}</span>
                                                @if ($document['date'])
                                                    <span class="recovery-document-meta">{{ $document['date']->timezone($displayTimezone)->format('d M Y, h:i A') }}</span>
                                                @endif
                                            </div>
                                            <a href="{{ $document['url'] }}" target="_blank" class="recovery-document-view">
                                                <x-lucide-eye /> View
                                            </a>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <div class="recovery-documents-empty">No admin-sent documents are available yet.</div>
                            @endif
                        </div>

                        <div class="tab-pane fade" id="client-submitted-documents-panel" role="tabpanel" aria-labelledby="client-submitted-documents-tab" tabindex="0">
                            @if ($clientSubmittedDocuments->isNotEmpty())
                                <div class="recovery-documents-grid">
                                    @foreach ($clientSubmittedDocuments as $document)
                                        @php
                                            $documentTypeLabel = $documentTypeOptions[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type));
                                            $documentStatus = ucwords(str_replace('_', ' ', $document->status ?? 'pending'));
                                        @endphp
                                        <article class="recovery-document-card">
                                            <div>
                                                <span class="recovery-document-icon"><x-lucide-file-text /></span>
                                                <span class="recovery-document-title">{{ $document->file_name }}</span>
                                                <span class="recovery-document-meta">{{ $documentTypeLabel }} · {{ $documentStatus }}</span>
                                                <span class="recovery-document-meta">{{ $document->created_at->timezone($displayTimezone)->format('d M Y, h:i A') }}</span>
                                            </div>
                                            <a href="{{ route('stuck-trademark.document.view', $document) }}" target="_blank" class="recovery-document-view">
                                                <x-lucide-eye /> View
                                            </a>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <div class="recovery-documents-empty">No client-submitted documents are available yet.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const documentFilesInput = document.getElementById('recovery-document-files');
            const documentUploadTitle = document.querySelector('[data-document-upload-title]');
            const documentUploadSubtitle = document.querySelector('[data-document-upload-subtitle]');
            const documentsSearchInput = document.getElementById('documents-search-input');
            const documentRows = Array.from(document.querySelectorAll('[data-document-row]'));
            const documentCards = Array.from(document.querySelectorAll('[data-document-card]'));
            const documentsNoResultsRow = document.querySelector('[data-documents-no-results]');
            const documentsNoResultsCard = document.querySelector('[data-documents-no-results-card]');
            const documentsCountSummary = document.querySelector('[data-documents-count-summary]');
            const executionPayButton = document.getElementById('execution-pay-button');
            const recoveryDocumentTriggers = Array.from(document.querySelectorAll('[data-recovery-doc-tab]'));

            recoveryDocumentTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const tabId = trigger.dataset.recoveryDocTab === 'client'
                        ? 'client-submitted-documents-tab'
                        : 'admin-sent-documents-tab';
                    const tabButton = document.getElementById(tabId);

                    if (!tabButton) {
                        return;
                    }

                    window.setTimeout(() => {
                        if (window.bootstrap?.Tab) {
                            window.bootstrap.Tab.getOrCreateInstance(tabButton).show();
                        } else {
                            tabButton.click();
                        }
                    }, 0);
                });
            });

            documentFilesInput?.addEventListener('change', () => {
                const count = documentFilesInput.files?.length || 0;

                if (!documentUploadTitle) {
                    return;
                }

                    if (count === 1) {
                        documentUploadTitle.textContent = documentFilesInput.files[0].name;
                        if (documentUploadSubtitle) {
                            documentUploadSubtitle.textContent = 'Ready to upload';
                        }
                    } else if (count > 1) {
                        documentUploadTitle.textContent = `${count} files selected`;
                        if (documentUploadSubtitle) {
                            documentUploadSubtitle.textContent = 'Ready to upload';
                        }
                    } else {
                        documentUploadTitle.textContent = 'Choose files or drag & drop';
                        if (documentUploadSubtitle) {
                            documentUploadSubtitle.textContent = 'No file chosen';
                        }
                    }
                });

            documentsSearchInput?.addEventListener('input', () => {
                const query = documentsSearchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                documentRows.forEach((row) => {
                    const rowText = row.dataset.searchText || row.textContent.toLowerCase();
                    const isVisible = query === '' || rowText.includes(query);
                    row.hidden = !isVisible;

                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                documentCards.forEach((card) => {
                    const cardText = card.dataset.searchText || card.textContent.toLowerCase();
                    card.hidden = !(query === '' || cardText.includes(query));
                });

                if (documentsNoResultsRow) {
                    documentsNoResultsRow.hidden = visibleCount !== 0;
                }

                if (documentsNoResultsCard) {
                    documentsNoResultsCard.hidden = visibleCount !== 0;
                }

                if (documentsCountSummary) {
                    const totalCount = documentRows.length;
                    const plural = totalCount === 1 ? 'document' : 'documents';
                    documentsCountSummary.textContent = visibleCount
                        ? `Showing 1 to ${visibleCount} of ${totalCount} ${plural}`
                        : `Showing 0 to 0 of ${totalCount} ${plural}`;
                }
            });

            const showExecutionPaymentError = (message) => {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Error',
                        text: message
                    });
                } else {
                    alert(message);
                }
            };

            const resetExecutionPayButton = () => {
                if (!executionPayButton) {
                    return;
                }

                executionPayButton.disabled = false;
                window.LegalBruzButtonLoading?.reset(executionPayButton);
            };

            executionPayButton?.addEventListener('click', () => {
                if (!window.Razorpay) {
                    showExecutionPaymentError('Razorpay checkout is not available. Please refresh and try again.');
                    return;
                }

                const selectedCoupon = document.querySelector('input[name="execution_discount_coupon_id"]:checked');
                executionPayButton.disabled = true;
                window.LegalBruzButtonLoading?.set(executionPayButton, 'Creating Order...');

                fetch('{{ route('stuck-trademark.execution-payment.create-order', $case) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        execution_discount_coupon_id: selectedCoupon?.value || null
                    })
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status !== 'success') {
                            throw new Error(data.message || 'Unable to create Razorpay order.');
                        }

                        const checkout = new Razorpay({
                            key: data.key,
                            amount: data.amount,
                            currency: data.currency,
                            name: '{{ config('app.name') }}',
                            description: data.description,
                            order_id: data.order_id,
                            prefill: {
                                name: data.user_name,
                                email: data.user_email,
                                contact: data.user_phone
                            },
                            theme: {
                                color: '#2A9D8F'
                            },
                            modal: {
                                ondismiss: resetExecutionPayButton
                            },
                            handler: (response) => {
                                window.LegalBruzButtonLoading?.set(executionPayButton, 'Verifying Payment...');

                                fetch('{{ route('stuck-trademark.execution-payment.verify-signature', $case) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature
                                    })
                                })
                                    .then((verifyResponse) => verifyResponse.json())
                                    .then((verifyData) => {
                                        if (verifyData.status !== 'success') {
                                            throw new Error(verifyData.message || 'Payment verification failed.');
                                        }

                                        if (window.Swal) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Payment Successful',
                                                text: 'Execution work is now active. Redirecting...',
                                                allowOutsideClick: false
                                            }).then(() => {
                                                window.location.href = verifyData.redirect_url;
                                            });
                                        } else {
                                            window.location.href = verifyData.redirect_url;
                                        }
                                    })
                                    .catch((error) => {
                                        resetExecutionPayButton();
                                        showExecutionPaymentError(error.message || 'Payment verification failed.');
                                    });
                            }
                        });

                        checkout.open();
                    })
                    .catch((error) => {
                        resetExecutionPayButton();
                        showExecutionPaymentError(error.message || 'Unable to start payment.');
                    });
            });
        });
    </script>

@endsection
