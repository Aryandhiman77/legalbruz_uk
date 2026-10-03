@extends('layouts.app')

@section('body_class', !empty($adminPreview) ? 'admin-client-preview' : '')

@section('content')
    @php
        $workflow = \App\Support\TrademarkWorkflow::class;
        $isAdminPreview = !empty($adminPreview);
        $client = $client ?? $application->user;
        $documentViewRoute = $isAdminPreview ? 'admin.document.view' : 'user.document.view';
        $documentDownloadRoute = $isAdminPreview ? 'admin.document.download' : 'user.document.download';
        $trademarkImageViewRoute = $isAdminPreview ? 'admin.trademark.image.view' : 'trademark.image.view';
        $trademarkProofOfUseViewRoute = $isAdminPreview ? 'admin.trademark.proof-of-use.view' : 'trademark.proof-of-use.view';
        $clientActionUrl = fn (string $routeName, $parameters = []) => $isAdminPreview ? '#' : route($routeName, $parameters);
        $currentStatus = $application->current_status;
        $stageActionOnly = request()->boolean('stage_action');
        $displayTimezone = config('app.timezone', 'Europe/London');
        $formatDateTime = fn ($timestamp, string $format = 'd M Y, h:i A T') => $timestamp
            ? \Illuminate\Support\Carbon::parse($timestamp)->timezone($displayTimezone)->format($format)
            : null;
        $details = $application->members_details ?? [];
        $submittedSections = [
            'Billing Details' => $details['billing_details'] ?? ($details['billing_company_details'] ?? []),
            'Applicant Details' => $details['applicant_details'] ?? ($details['trademark_applicant_details'] ?? []),
            'Authorised Person' => $details['authorised_person'] ?? ($details['details_of_signatory'] ?? []),
            'Additional Applicants' => $details['additional_applicants'] ?? ($details['details_of_co_applicant_or_partners'] ?? []),
            'Trademark Details' => $details['trademark_details'] ?? [],
            'Priority Details' => $details['priority_details'] ?? [],
        ];
        $formatSubmittedValue = function ($value) {
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_array($value)) {
                return collect($value)->map(function ($item) {
                    if (!is_array($item)) {
                        return $item;
                    }

                    return collect($item)
                        ->filter(fn ($nested) => $nested !== null && $nested !== '')
                        ->map(fn ($nested, $key) => ucwords(str_replace('_', ' ', $key)).': '.(is_bool($nested) ? ($nested ? 'Yes' : 'No') : $nested))
                        ->implode(', ');
                })->filter()->implode('; ') ?: 'N/A';
            }

            return filled($value) ? $value : 'N/A';
        };
        $documentLabel = function (string $type) {
            return match ($type) {
                'engagement_letter' => 'Engagement Letter',
                'engagement_letter (Signed)' => 'Signed Engagement Letter',
                'application_summary' => 'Application Summary',
                'final_specification' => 'Final Specification',
                'filing_authority' => 'Filing Authority',
                'filing_authority (Signed)' => 'Signed Filing Authority',
                'ukipo_filing_receipt' => 'UKIPO Filing Receipt',
                'search_report' => 'Search Report',
                'draft_pdf' => 'Draft PDF',
                'draft_pdf (Signed)' => 'Signed Draft PDF',
                'final_filing_signature' => 'Final Filing Signature',
                default => ucwords(str_replace('_', ' ', $type)),
            };
        };
        $statusBadgeClass = function (?string $status) {
            return match (strtolower((string) $status)) {
                'approved', 'verified', 'completed', 'paid' => 'success',
                'uploaded', 'reuploaded', 'pending', 'prepared' => 'warning',
                'reupload_requested', 'rejected', 'failed' => 'danger',
                default => 'info',
            };
        };
        $documentTypeClass = function (string $type) {
            return match (true) {
                str_contains($type, 'engagement_letter') => 'blue',
                str_contains($type, 'poa') => 'green',
                str_contains($type, 'affidavit') => 'purple',
                str_contains($type, 'draft') => 'amber',
                default => 'gray',
            };
        };
        $isAdminOnboardingDocument = function ($doc) {
            return in_array((string) $doc->document_type, ['engagement_letter', 'other_document'], true)
                && (
                    str_contains((string) $doc->file_path, 'workflow/admin/')
                    || str_contains(strtolower((string) $doc->verification_notes), 'uploaded by admin')
                    || str_contains(strtolower((string) $doc->verification_notes), 'resent by admin')
                    || str_contains(strtolower((string) $doc->verification_notes), 'sent by admin')
                );
        };
        $isGenericAdminOnboardingNote = function (?string $note) {
            $normalized = strtolower(trim((string) $note));

            return in_array($normalized, [
                'additional onboarding document uploaded by admin.',
                'additional onboarding document resent by admin.',
                'document sent by admin.',
            ], true);
        };
        $timeline = [
            $workflow::APPLICATION_SUBMITTED => ['title' => 'Application', 'icon' => 'file-signature', 'copy' => 'Application created and advance payment received.'],
            $workflow::UNDER_REVIEW => ['title' => 'Admin Review', 'icon' => 'user-check', 'copy' => 'Our team is reviewing the intake and filing fit.'],
            $workflow::ONBOARDING_PENDING => ['title' => 'Engagement Letter', 'icon' => 'file-signature', 'copy' => 'Review and electronically sign the Engagement Letter.'],
            $workflow::STRATEGY_IN_PROGRESS => ['title' => 'Strategy', 'icon' => 'search-check', 'copy' => 'Trademark search, risk review, classes, and goods/services drafting.'],
            $workflow::DRAFT_READY => ['title' => 'Draft Preparation', 'icon' => 'file-pen', 'copy' => 'TM-A draft and filing details are being prepared.'],
            $workflow::AWAITING_APPROVAL => ['title' => 'Client Approval', 'icon' => 'badge-check', 'copy' => 'Review the draft, request changes, or approve it for filing.'],
            $workflow::PAYMENT_PENDING_FINAL => ['title' => 'Final Payment', 'icon' => 'credit-card', 'copy' => 'Complete the remaining service balance. Government fees are payable separately.'],
            $workflow::FILED => ['title' => 'Application Filed', 'icon' => 'send-horizontal', 'copy' => 'The trademark application has been filed.'],
            $workflow::POST_FILING => ['title' => 'Post Filing Care', 'icon' => 'route', 'copy' => 'Track examination, publication, opposition, and registration milestones.'],
        ];
        $orderedStatuses = array_keys($timeline);
        $completedTimelineStatuses = match ($currentStatus) {
            $workflow::DRAFT => [],
            $workflow::APPLICATION_SUBMITTED => [],
            $workflow::UNDER_REVIEW => [$workflow::APPLICATION_SUBMITTED],
            $workflow::ONBOARDING_PENDING => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW],
            $workflow::ONBOARDING_COMPLETED => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING],
            $workflow::STRATEGY_IN_PROGRESS => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING],
            $workflow::STRATEGY_COMPLETED => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS],
            $workflow::DRAFT_READY => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS],
            $workflow::AWAITING_APPROVAL => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY],
            $workflow::CHANGES_REQUESTED => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY],
            $workflow::APPROVED_FOR_FILING => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY, $workflow::AWAITING_APPROVAL],
            $workflow::PAYMENT_PENDING_FINAL => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY, $workflow::AWAITING_APPROVAL],
            $workflow::PAYMENT_COMPLETED => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY, $workflow::AWAITING_APPROVAL, $workflow::PAYMENT_PENDING_FINAL],
            $workflow::FILED => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY, $workflow::AWAITING_APPROVAL, $workflow::PAYMENT_PENDING_FINAL],
            $workflow::POST_FILING => [$workflow::APPLICATION_SUBMITTED, $workflow::UNDER_REVIEW, $workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS, $workflow::DRAFT_READY, $workflow::AWAITING_APPROVAL, $workflow::PAYMENT_PENDING_FINAL, $workflow::FILED],
            default => [],
        };

        $activeTimelineStatus = match ($currentStatus) {
            $workflow::APPLICATION_SUBMITTED => $workflow::APPLICATION_SUBMITTED,
            $workflow::UNDER_REVIEW => $workflow::UNDER_REVIEW,
            $workflow::ONBOARDING_PENDING, $workflow::ONBOARDING_COMPLETED => $workflow::ONBOARDING_PENDING,
            $workflow::STRATEGY_IN_PROGRESS => $workflow::STRATEGY_IN_PROGRESS,
            $workflow::STRATEGY_COMPLETED => $workflow::DRAFT_READY,
            $workflow::DRAFT_READY => $workflow::DRAFT_READY,
            $workflow::AWAITING_APPROVAL, $workflow::CHANGES_REQUESTED => $workflow::AWAITING_APPROVAL,
            $workflow::PAYMENT_PENDING_FINAL, $workflow::APPROVED_FOR_FILING, $workflow::PAYMENT_COMPLETED => $workflow::PAYMENT_PENDING_FINAL,
            $workflow::FILED => $workflow::FILED,
            $workflow::POST_FILING => $workflow::POST_FILING,
            default => null,
        };
        $draft = $application->draftVersions->sortByDesc('id')->first();
        $tasks = $application->tasks->sortBy('id');
        $payments = $application->payments->sortByDesc('id');
        $statusLogs = $application->statusLogs->sortByDesc('id');
        $engagementLetter = $application->documents->where('document_type', 'engagement_letter')->sortByDesc('id')->first();
        $engagementLetterSigned = $application->documents->where('document_type', 'engagement_letter (Signed)')->sortByDesc('id')->first();
        $poaDocument = $application->documents->where('document_type', 'poa')->sortByDesc('id')->first();
        $poaSigned = $application->documents->where('document_type', 'poa (Signed)')->sortByDesc('id')->first();
        $searchReportDocument = $application->documents->where('document_type', 'search_report')->sortByDesc('id')->first();
        $affidavitDocument = $application->documents->where('document_type', 'affidavit')->sortByDesc('id')->first();
        $affidavitSigned = $application->documents->where('document_type', 'affidavit (Signed)')->sortByDesc('id')->first();
        $verifiedSignedOnboardingDocuments = collect([$engagementLetterSigned])
            ->filter(fn ($doc) => $doc && $doc->status === 'verified')
            ->values();
        $otherOnboardingDocuments = $application->documents->where('document_type', 'other_document')->sortByDesc('id')->values();
        $draftDocument = $application->documents->where('document_type', 'draft_pdf')->where('status', 'approved')->sortByDesc('id')->first();
        $visibleDocuments = $application->documents
            ->reject(fn ($doc) => $doc->status === 'archived')
            ->sortByDesc('id');
        $requiredOnboardingSignedDocuments = collect([$engagementLetterSigned]);
        $onboardingSubmittedDocuments = $requiredOnboardingSignedDocuments->filter();
        $hasDraftOnboardingDocuments = $onboardingSubmittedDocuments->contains(fn ($doc) => $doc->status === 'draft');
        $draftOnboardingDocumentTypes = $onboardingSubmittedDocuments
            ->filter(fn ($doc) => $doc->status === 'draft')
            ->map(function ($doc) {
                return str_replace(' (Signed)', '', (string) $doc->document_type);
            })
            ->values();
        $onboardingPackageSubmitted = $onboardingSubmittedDocuments->count() === $requiredOnboardingSignedDocuments->count()
            && filled(data_get($application->workflow_meta, 'onboarding_package_submitted_at'))
            && !$hasDraftOnboardingDocuments;
        $needsReupload = $onboardingSubmittedDocuments->contains(fn ($doc) => $doc->status === 'reupload_requested');
        $pendingOnboardingReupload = $needsReupload || (
            $hasDraftOnboardingDocuments
            && filled(data_get($application->workflow_meta, 'onboarding_package_submitted_at'))
        );
        $reuploadStatus = 'reupload_requested';
        $engagementNeedsReupload = $engagementLetterSigned?->status === $reuploadStatus;
        $poaNeedsReupload = $poaSigned?->status === $reuploadStatus;
        $affidavitNeedsReupload = $affidavitSigned?->status === $reuploadStatus;
        $engagementDraftAttached = $draftOnboardingDocumentTypes->contains('engagement_letter');
        $poaDraftAttached = $draftOnboardingDocumentTypes->contains('poa');
        $affidavitDraftAttached = $draftOnboardingDocumentTypes->contains('affidavit');
        $showEngagementSigner = !$pendingOnboardingReupload || $engagementNeedsReupload || $engagementDraftAttached;
        $showPoaSigner = false;
        $showAffidavitSigner = false;
        $signatureBoxInfo = function (string $documentType) use ($application) {
            $signatureField = collect(data_get($application->workflow_meta ?? [], 'signature_fields.' . $documentType, []))
                ->firstWhere('type', 'signature');

            if (!is_array($signatureField)) {
                return null;
            }

            $height = (float) ($signatureField['height'] ?? 0);
            $width = (float) ($signatureField['width'] ?? 0);

            if ($height <= 0 || $width <= 0) {
                return null;
            }

            $widthPx = max((int) round(($width / 210) * 760), 1);
            $heightPx = max((int) round(($height / 297) * ((760 * 297) / 210)), 1);

            return [
                'label' => $widthPx . ' x ' . $heightPx . ' px',
                'width' => $widthPx,
                'height' => $heightPx,
            ];
        };
        $isCompletedPayment = fn ($payment) => $payment
            && in_array(strtolower((string) $payment->status), ['completed', 'approved'], true);
        $fullPayment = $payments->first(function ($payment) use ($isCompletedPayment) {
            $paymentType = strtolower((string) ($payment->payment_type ?? ''));

            return $isCompletedPayment($payment)
                && (
                    $paymentType === 'full'
                    || ((float) $payment->total_amount > 0 && (float) $payment->amount >= (float) $payment->total_amount)
                );
        });
        $advancePayment = $payments->first(function ($payment) {
            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
            $percentage = strtolower((string) ($payment->percentage ?? ''));

            return $paymentType === 'advance' || $percentage === '50%';
        }) ?: $fullPayment;
        $finalPayment = $fullPayment ?: $payments->first(function ($payment) {
            $paymentType = strtolower((string) ($payment->payment_type ?? ''));
            $percentage = strtolower((string) ($payment->percentage ?? ''));

            return $paymentType === 'final' || ($paymentType !== 'advance' && $percentage === '100%');
        });
        $serviceTotalAmount = \App\Models\TrademarkPricing::amountForApplicantType($application->entity_type);
        $halfPaymentAmount = $serviceTotalAmount / 2;
        $hasFullPayment = (bool) $fullPayment;
        $advanceDisplayAmount = $hasFullPayment ? $serviceTotalAmount : $halfPaymentAmount;
        $advancePaymentStatus = $advancePayment
            ? ($isCompletedPayment($advancePayment) ? ($hasFullPayment ? 'Paid in Full' : 'Half Payment Done') : ucfirst($advancePayment->status))
            : ($currentStatus === $workflow::DRAFT ? 'Pending' : 'Requested');
        $finalPaymentStatus = $finalPayment
            ? ($isCompletedPayment($finalPayment) ? 'Final Payment Done' : ucfirst($finalPayment->status))
            : (in_array($currentStatus, [$workflow::PAYMENT_PENDING_FINAL, $workflow::PAYMENT_COMPLETED, $workflow::FILED, $workflow::POST_FILING], true) ? 'Pending' : 'Not Due Yet');

        if ($hasFullPayment) {
            $timeline[$workflow::PAYMENT_PENDING_FINAL] = [
                'title' => 'Full Payment',
                'icon' => 'credit-card',
                'copy' => 'Complete payment received with the application.',
            ];
            $timeline[$workflow::APPLICATION_SUBMITTED]['copy'] = 'Application created and complete payment received.';

            $timeline = [
                $workflow::APPLICATION_SUBMITTED => $timeline[$workflow::APPLICATION_SUBMITTED],
                $workflow::PAYMENT_PENDING_FINAL => $timeline[$workflow::PAYMENT_PENDING_FINAL],
                $workflow::UNDER_REVIEW => $timeline[$workflow::UNDER_REVIEW],
                $workflow::ONBOARDING_PENDING => $timeline[$workflow::ONBOARDING_PENDING],
                $workflow::STRATEGY_IN_PROGRESS => $timeline[$workflow::STRATEGY_IN_PROGRESS],
                $workflow::DRAFT_READY => $timeline[$workflow::DRAFT_READY],
                $workflow::AWAITING_APPROVAL => $timeline[$workflow::AWAITING_APPROVAL],
                $workflow::FILED => $timeline[$workflow::FILED],
                $workflow::POST_FILING => $timeline[$workflow::POST_FILING],
            ];

            if (!in_array($workflow::PAYMENT_PENDING_FINAL, $completedTimelineStatuses, true)) {
                $completedTimelineStatuses[] = $workflow::PAYMENT_PENDING_FINAL;
            }

            if ($activeTimelineStatus === $workflow::PAYMENT_PENDING_FINAL && $currentStatus !== $workflow::PAYMENT_PENDING_FINAL) {
                $activeTimelineStatus = null;
            }
        }

        $registryLabel = $application->registry_status ? str_replace('_', ' ', $application->registry_status) : 'NOT FILED';
        $actionGuidance = match (true) {
            $currentStatus === $workflow::DRAFT => [
                'icon' => 'credit-card',
                'title' => 'Complete your payment',
                'copy' => 'Pay the first amount to submit your trademark application to our team.',
                'steps' => ['Review the application summary.', 'Complete the secure payment.', 'After payment, your application goes to admin review.'],
            ],
            $currentStatus === $workflow::APPLICATION_SUBMITTED => [
                'icon' => 'hourglass-split',
                'title' => 'Application submitted',
                'copy' => 'Your payment is complete and the application is queued for admin review.',
                'steps' => ['No action is needed right now.', 'Watch for email or dashboard updates.'],
            ],
            $currentStatus === $workflow::UNDER_REVIEW => [
                'icon' => 'hourglass-split',
                'title' => 'Admin review is in progress',
                'copy' => 'Our team is checking your applicant details, mark details, and filing readiness.',
                'steps' => ['No action is needed right now.', 'Watch for email or dashboard updates.'],
            ],
            $currentStatus === $workflow::ONBOARDING_PENDING => [
                'icon' => 'file-signature',
                'title' => 'Sign the Engagement Letter',
                'copy' => 'Review the Engagement Letter, apply your electronic signature, and submit it for verification.',
                'steps' => ['Open and review the Engagement Letter.', 'Apply your E-Sign where shown.', 'Submit the signed letter for verification.'],
            ],
            $currentStatus === $workflow::STRATEGY_IN_PROGRESS => [
                'icon' => 'search',
                'title' => 'Strategy work is underway',
                'copy' => 'Our team is preparing trademark search, risk review, classes, and filing strategy.',
                'steps' => ['No action is needed right now.', 'Review any shared search report if available.', 'Wait for the draft PDF notification.'],
            ],
            $currentStatus === $workflow::STRATEGY_COMPLETED => [
                'icon' => 'file-earmark-text',
                'title' => 'Draft preparation has started',
                'copy' => 'The strategy stage is complete and the filing draft is being prepared.',
                'steps' => ['Review the search report if one is shared.', 'Wait for the draft PDF.', 'You will be asked to approve or request changes next.'],
            ],
            in_array($currentStatus, [$workflow::AWAITING_APPROVAL, $workflow::CHANGES_REQUESTED], true) => [
                'icon' => 'file-earmark-check',
                'title' => 'Review your draft',
                'copy' => 'Open the draft PDF first. Approve it only if everything looks correct.',
                'steps' => ['Open or download the draft PDF.', 'Click Approve Draft if it is correct.', 'Click Request Changes if something must be corrected.'],
            ],
            in_array($currentStatus, [$workflow::APPROVED_FOR_FILING, $workflow::PAYMENT_PENDING_FINAL], true) => [
                'icon' => 'wallet2',
                'title' => 'Complete final payment',
                'copy' => 'Your draft is approved. Pay the remaining balance so filing can proceed.',
                'steps' => ['Check the final payment amount.', 'Complete payment securely.', 'After payment, our team files the application.'],
            ],
            $currentStatus === $workflow::PAYMENT_COMPLETED => [
                'icon' => 'send-check',
                'title' => 'Ready for filing',
                'copy' => 'Payment is complete and the filing team can proceed with registry filing.',
                'steps' => ['No action is needed right now.', 'The team will file the application.', 'You will receive the filing details once available.'],
            ],
            in_array($currentStatus, [$workflow::FILED, $workflow::POST_FILING], true) => [
                'icon' => 'route',
                'title' => 'Track post-filing updates',
                'copy' => 'Your trademark has moved into registry tracking and post-filing care.',
                'steps' => ['Review the application number.', 'Track registry status updates.', 'Watch for examination, publication, opposition, or registration updates.'],
            ],
            default => [
                'icon' => 'info-circle',
                'title' => 'Current stage information',
                'copy' => 'Use the actions below to continue your trademark workflow.',
                'steps' => ['Read the instructions.', 'Complete the available action.', 'Watch for the next dashboard update.'],
            ],
        };
        $workflowActivity = collect();
        $activityStatusIndex = [];

        $pushActivity = function (?string $status, $timestamp, ?string $reason = null) use (&$workflowActivity, &$activityStatusIndex, $workflow, $timeline) {
            if (!$status || !$timestamp) {
                return;
            }

            $existingIndex = $activityStatusIndex[$status] ?? null;

            $item = [
                'status' => $status,
                'title' => $timeline[$status]['title'] ?? $workflow::label($status),
                'timestamp' => $timestamp,
                'reason' => $reason ?: ($timeline[$status]['copy'] ?? null),
                'source' => 'synthetic',
            ];

            if ($existingIndex !== null) {
                $existing = $workflowActivity->get($existingIndex);
                $existingTimestamp = $existing['timestamp'] ?? null;

                if ($existingTimestamp && $existingTimestamp >= $timestamp) {
                    return;
                }

                $workflowActivity->put($existingIndex, $item);
                return;
            }

            $activityStatusIndex[$status] = $workflowActivity->count();
            $workflowActivity->push($item);
        };

        foreach ($statusLogs as $log) {
            $eventTitle = data_get($log->metadata, 'title');

            if ($eventTitle) {
                $workflowActivity->push([
                    'status' => 'event_' . $log->id,
                    'title' => $eventTitle,
                    'timestamp' => $log->created_at,
                    'reason' => $log->reason,
                    'source' => 'status_log_event',
                ]);

                continue;
            }

            $pushActivity($log->to_status, $log->created_at, $log->reason);
        }

        $pushActivity(
            $workflow::APPLICATION_SUBMITTED,
            $advancePayment?->paid_at ?? ($currentStatus !== $workflow::DRAFT ? $application->created_at : null),
            $hasFullPayment ? 'Application submitted with full payment.' : 'Advance payment completed.'
        );
        $pushActivity($workflow::UNDER_REVIEW, $currentStatus === $workflow::UNDER_REVIEW ? $application->current_stage_started_at : null, 'Application submitted for admin review.');
        $pushActivity($workflow::ONBOARDING_PENDING, $application->approved_at ?? ($currentStatus === $workflow::ONBOARDING_PENDING ? $application->current_stage_started_at : null), 'Admin approved application and issued onboarding package.');
        $pushActivity($workflow::ONBOARDING_COMPLETED, $application->onboarding_completed_at, 'User completed onboarding package.');
        $pushActivity($workflow::STRATEGY_IN_PROGRESS, $application->onboarding_completed_at ?? ($currentStatus === $workflow::STRATEGY_IN_PROGRESS ? $application->current_stage_started_at : null), 'Trademark search and strategy work started.');
        $pushActivity($workflow::STRATEGY_COMPLETED, $application->strategy_completed_at, 'Strategy report completed.');
        $pushActivity($workflow::DRAFT_READY, $application->draft_ready_at, 'Draft prepared.');
        $pushActivity($workflow::AWAITING_APPROVAL, $application->draft_ready_at ?? ($currentStatus === $workflow::AWAITING_APPROVAL ? $application->current_stage_started_at : null), 'Draft shared with client for approval.');
        $pushActivity($workflow::APPROVED_FOR_FILING, $application->client_approved_at, 'Client approved the draft for filing.');
        $pushActivity(
            $workflow::PAYMENT_PENDING_FINAL,
            $hasFullPayment
                ? ($fullPayment?->paid_at ?? $fullPayment?->approved_at ?? $fullPayment?->created_at)
                : ($application->client_approved_at ?? ($currentStatus === $workflow::PAYMENT_PENDING_FINAL ? $application->current_stage_started_at : null)),
            $hasFullPayment ? 'Full payment completed with the application.' : 'Final service balance requested. Government fees are payable separately.'
        );
        $pushActivity($workflow::PAYMENT_COMPLETED, $application->final_payment_completed_at, 'Final payment completed.');
        $pushActivity($workflow::FILED, $application->filed_at, 'Trademark application filed.');
        $pushActivity($workflow::POST_FILING, $application->post_filing_started_at ?? ($currentStatus === $workflow::POST_FILING ? $application->current_stage_started_at : null), 'Post-filing care started.');

        $workflowActivity = $workflowActivity
            ->filter(fn ($item) => !empty($item['timestamp']))
            ->sortByDesc('timestamp')
            ->take(6)
            ->values();
        $timelineDates = [
            $workflow::APPLICATION_SUBMITTED => $advancePayment?->paid_at ?? ($currentStatus !== $workflow::DRAFT ? $application->created_at : null),
            $workflow::UNDER_REVIEW => $application->statusLogs->where('to_status', $workflow::UNDER_REVIEW)->sortByDesc('id')->first()?->created_at,
            $workflow::ONBOARDING_PENDING => $application->approved_at,
            $workflow::STRATEGY_IN_PROGRESS => $application->onboarding_completed_at,
            $workflow::DRAFT_READY => $application->draft_ready_at,
            $workflow::AWAITING_APPROVAL => $application->draft_ready_at,
            $workflow::PAYMENT_PENDING_FINAL => $hasFullPayment
                ? ($fullPayment?->paid_at ?? $fullPayment?->approved_at ?? $fullPayment?->created_at)
                : $application->client_approved_at,
            $workflow::FILED => $application->filed_at,
            $workflow::POST_FILING => $application->post_filing_started_at,
        ];
        $postFilingJourney = \App\Support\PostFilingJourney::class;
        $postFilingStages = $postFilingJourney::stages($application);
        $postFilingActiveStageKey = $postFilingJourney::activeStageKey($application);
        $activePostFilingStage = $postFilingActiveStageKey
            ? $postFilingJourney::stage($application, $postFilingActiveStageKey)
            : null;
        $filingAdminNote = data_get($application->workflow_meta, 'filing.admin_note');
        $postFilingDocumentsByType = $application->documents
            ->filter(fn ($doc) => str_starts_with((string) $doc->document_type, 'post_filing_'))
            ->groupBy('document_type');
        $isPostFilingAdminDocument = function ($doc) {
            return str_contains((string) $doc->file_path, 'workflow/admin/post-filing/')
                || str_contains(strtolower((string) $doc->verification_notes), 'sent by admin')
                || str_contains(strtolower((string) $doc->verification_notes), 'document sent by admin');
        };
        $registeredAdminDocuments = $postFilingDocumentsByType
            ->get($postFilingJourney::documentType('registered'), collect())
            ->filter(fn ($doc) => $isPostFilingAdminDocument($doc));
        $acceptedAdvertisedStageMeta = data_get($application->workflow_meta, 'post_filing_journey.stages.accepted_advertised', []);
        $acceptedAdvertisedStageStatus = $postFilingJourney::statusFor($application, 'accepted_advertised');
        $acceptedAdvertisedAdminDocuments = $postFilingDocumentsByType
            ->get($postFilingJourney::documentType('accepted_advertised'), collect())
            ->filter(fn ($doc) => $isPostFilingAdminDocument($doc));
        $storedFinalOppositionResult = $application->final_opposition_result
            ?: data_get($acceptedAdvertisedStageMeta, 'final_opposition_result');
        $postFilingIsOpposed = $currentStatus === $workflow::POST_FILING
            && $acceptedAdvertisedStageStatus === $postFilingJourney::OPPOSED;
        $oppositionDefenceCase = \App\Models\TrademarkOppositionCase::query()
            ->where('user_id', $application->user_id)
            ->where('flow_type', \App\Support\TrademarkOppositionWorkflow::FLOW_DEFEND)
            ->where('application_number', (string) $application->application_number)
            ->latest('id')
            ->first();
        $oppositionDefenceOutcomeLabel = $oppositionDefenceCase
            ? \App\Support\TrademarkOppositionWorkflow::defenceOutcomeLabel($oppositionDefenceCase->defence_case_status)
            : null;
        $oppositionDefenceResultReady = $oppositionDefenceCase
            && filled($oppositionDefenceCase->filing_acknowledgment_path)
            && in_array($oppositionDefenceCase->defence_case_status, [
                \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED,
                \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED,
            ], true);
        $oppositionDefenceSucceeded = $oppositionDefenceResultReady
            && $oppositionDefenceCase?->defence_case_status === \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_SUCCEEDED;
        $oppositionDefenceFailed = $oppositionDefenceResultReady
            && $oppositionDefenceCase?->defence_case_status === \App\Support\TrademarkOppositionWorkflow::DEFENCE_CASE_FAILED_TO_SUCCEED;
        $finalOppositionResult = match (true) {
            $oppositionDefenceSucceeded => \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL,
            $oppositionDefenceFailed => \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL,
            filled($storedFinalOppositionResult) => $storedFinalOppositionResult,
            default => null,
        };
        $finalOppositionResultLabel = \App\Support\TrademarkOppositionWorkflow::applicationFinalOppositionResultLabel($finalOppositionResult);
        $finalOppositionResultCopy = match ($finalOppositionResult) {
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL => 'The opposition was allowed. This trademark did not succeed.',
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL => 'The opposition was dismissed. This trademark can continue.',
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_SETTLEMENT_CLOSED => 'This matter was closed through settlement.',
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_WITHDRAWN => 'The opposition was withdrawn. The trademark may continue.',
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_APPLICATION_WITHDRAWN => 'The trademark application was withdrawn.',
            \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OTHER => 'The matter has been closed with a custom final result.',
            default => null,
        };
        $postFilingHasOppositionHistory = $currentStatus === $workflow::POST_FILING
            && (
                $acceptedAdvertisedStageStatus === $postFilingJourney::OPPOSED
                || filled(data_get($acceptedAdvertisedStageMeta, 'opposed_at'))
                || filled($storedFinalOppositionResult)
                || $oppositionDefenceCase
            );
        $oppositionJourneyOutcome = match (true) {
            in_array($finalOppositionResult, [
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL,
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_WITHDRAWN,
            ], true),
            $oppositionDefenceSucceeded => 'victory',
            in_array($finalOppositionResult, [
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OPPOSITION_SUCCESSFUL,
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_APPLICATION_WITHDRAWN,
            ], true),
            $oppositionDefenceFailed => 'lost',
            in_array($finalOppositionResult, [
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_SETTLEMENT_CLOSED,
                \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_OTHER,
            ], true) => 'closed',
            $acceptedAdvertisedStageStatus === $postFilingJourney::COMPLETED
                && filled(data_get($acceptedAdvertisedStageMeta, 'opposed_at')) => 'victory',
            default => 'opposed',
        };
        $oppositionJourneyOutcomeLabel = match ($oppositionJourneyOutcome) {
            'victory' => 'Victory',
            'lost' => 'Lost',
            'closed' => 'Closed',
            default => 'Opposed',
        };
        $oppositionJourneyVisualStatus = match ($oppositionJourneyOutcome) {
            'victory' => 'case-victory',
            'lost' => 'case-lost',
            'closed' => 'case-closed',
            default => 'opposed',
        };
        $oppositionDefenceCreateUrl = route('trademark-opposition.create', array_filter([
            'applicant_name' => $application->applicant_name,
            'trademark_name' => $application->brand_name,
            'application_number' => $application->application_number,
            'trademark_class' => is_array($application->classes) ? implode(', ', array_filter($application->classes)) : $application->classes,
            'mobile_number' => $application->phone,
            'email' => $application->email,
            'notice_receipt_date' => data_get($acceptedAdvertisedStageMeta, 'opposition_received_on'),
        ], fn ($value) => filled($value)));
        $oppositionOpposeCreateUrl = route('trademark-opposition.oppose.create');
        $postFilingHasRequestedDocuments = $currentStatus === $workflow::POST_FILING
            && $postFilingActiveStageKey
            && $postFilingJourney::documentsRequested($application, $postFilingActiveStageKey)
            && !$postFilingJourney::documentsSubmitted($application, $postFilingActiveStageKey);
        $postFilingIsFullyCompleted = $currentStatus === $workflow::POST_FILING
            && collect($postFilingStages)->isNotEmpty()
            && collect($postFilingStages)->every(fn ($stage) => $postFilingJourney::statusFor($application, $stage['key']) === $postFilingJourney::COMPLETED);
        if ($postFilingIsFullyCompleted) {
            $completedTimelineStatuses[] = $workflow::POST_FILING;
            $activeTimelineStatus = null;
        }
        $hasActionAvailable = match ($currentStatus) {
            $workflow::DRAFT,
            $workflow::AWAITING_APPROVAL,
            $workflow::PAYMENT_PENDING_FINAL => true,
            $workflow::ONBOARDING_PENDING => !$onboardingPackageSubmitted || $pendingOnboardingReupload,
            $workflow::POST_FILING => $postFilingHasRequestedDocuments || $postFilingIsOpposed,
            default => false,
        };
        $hideStageGuide = in_array($currentStatus, [$workflow::ONBOARDING_PENDING, $workflow::STRATEGY_IN_PROGRESS], true)
            && $onboardingPackageSubmitted
            && !$pendingOnboardingReupload;
        $hideStageGuide = $hideStageGuide || $postFilingIsFullyCompleted;
    @endphp

    <div class="container py-3 status-page">
        @if ($isAdminPreview)
            <div class="alert alert-warning d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4 admin-preview-banner" role="status">
                <div>
                    <div class="fw-bold"><i class="bi bi-eye me-2"></i>Read-only client action center</div>
                    <div class="small">This is the current view for {{ $client->name }} ({{ $client->email }}). Inputs and client actions are disabled, so this preview cannot change application data.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.application.client-dashboard', $application->id) }}" class="btn btn-sm btn-outline-dark" data-admin-preview-link>
                        <i class="bi bi-grid me-1"></i>Client Dashboard
                    </a>
                    <a href="{{ route('admin.view-application', $application->id) }}" class="btn btn-sm btn-outline-dark" data-admin-preview-link>
                        <i class="bi bi-arrow-left me-1"></i>Admin Application
                    </a>
                </div>
            </div>
        @endif
        @unless ($hasActionAvailable || $postFilingIsFullyCompleted)
            <div class="stage-no-action-notice">
                <span><i class="bi bi-info-circle"></i></span>
                <div>
                    @if ($currentStatus === $workflow::STRATEGY_IN_PROGRESS)
                        <strong>Strategy stage is active.</strong>
                        <p>Our team is preparing your trademark strategy. We will notify you within 24-48 hours for further actions.</p>
                    @else
                        <strong>No action is required.</strong>
                        <p>You will be notified through email for further steps within 24-48 hours.</p>
                    @endif
                </div>
            </div>
        @endunless

        <div class="row g-4">
            <div class="{{ $stageActionOnly ? 'col-lg-10 mx-auto' : 'col-lg-8' }}">
                @unless ($stageActionOnly)
                    <div class="card status-shell-card mb-4">
                        <div class="status-hero">
                            <div class="status-hero-icon">
                                <i class="bi bi-file-earmark-check"></i>
                            </div>
                            <div>
                                <h3>Application Status Tracking</h3>
                                <p>
                                    <button type="button" class="status-hero-link" data-bs-toggle="modal" data-bs-target="#applicationDetailsModal">
                                        <i class="bi bi-card-checklist"></i>
                                        View Application Details & Documents
                                    </button>
                                </p>
                            </div>
                            <div class="status-hero-badges">
                                <span>{{ $application->status_label }}</span>
                                <small>Registry: {{ ucwords(strtolower($registryLabel)) }}</small>
                            </div>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger">{{ session('error') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="timeline status-timeline">
                                @foreach ($timeline as $status => $step)
                                    @php
                                        $state = in_array($status, $completedTimelineStatuses, true)
                                            ? 'completed'
                                            : ($status === $activeTimelineStatus ? 'active' : 'pending');
                                        $timestamp = $timelineDates[$status] ?? null;
                                        $showTimelineAction = $state === 'active' && $status === $activeTimelineStatus && $hasActionAvailable;
                                    @endphp
                                    <div class="timeline-item {{ $state }} {{ $status === $workflow::POST_FILING && $currentStatus === $workflow::POST_FILING ? 'post-filing-expanded' : '' }}">
                                        <div class="timeline-icon">
                                            <x-dynamic-component :component="'lucide-' . $step['icon']" class="timeline-stage-icon" />
                                            @if ($state === 'completed')
                                                <span class="timeline-check"><i class="bi bi-check"></i></span>
                                            @endif
                                        </div>
                                        <div class="timeline-content">
                                            <div>
                                                <div class="timeline-title-row">
                                                    <h5>{{ $step['title'] }}</h5>
                                                    @if ($status === $workflow::ONBOARDING_PENDING)
                                                        <button type="button" class="timeline-documents-link" data-bs-toggle="modal" data-bs-target="#onboardingDocumentsModal">
                                                            View onboarding documents
                                                        </button>
                                                    @endif
                                                    @if ($status === $workflow::STRATEGY_IN_PROGRESS && $searchReportDocument)
                                                        <a href="{{ route($documentViewRoute, $searchReportDocument->id) }}" target="_blank" class="timeline-documents-link">
                                                            View search report
                                                        </a>
                                                    @endif
                                                </div>
                                                <p>{{ $step['copy'] }}</p>
                                            </div>
                                            <div class="timeline-state">
                                                <div class="timeline-state-status">
                                                    <span class="status-pill status-pill-{{ $state }}">{{ ucfirst($state) }}</span>
                                                    @if ($showTimelineAction)
                                                        <a href="#stage-action" class="timeline-action-indicator" data-bs-toggle="tooltip" data-bs-title="Take action" aria-label="Take action">
                                                            <i class="bi bi-arrow-right-circle-fill"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                                @if ($timestamp)
                                                    <small>{{ $formatDateTime($timestamp) }}</small>
                                                @endif
                                            </div>
                                            @if ($status === $workflow::POST_FILING && $currentStatus === $workflow::POST_FILING)
                                                <div class="timeline-post-filing-tracker">
                                                    @foreach ($postFilingStages as $postFilingStage)
                                                        @php
                                                            $postStageKey = $postFilingStage['key'];
                                                            $postStageStatus = $postFilingJourney::statusFor($application, $postStageKey);
                                                            $postStageMeta = data_get($application->workflow_meta, "post_filing_journey.stages.$postStageKey", []);
                                                            $postStageVisualStatus = $postStageStatus;
                                                            $postStageStatusLabel = ucwords($postStageStatus);
                                                            if ($postStageKey === 'accepted_advertised' && filled(data_get($postStageMeta ?? [], 'opposed_at'))) {
                                                                $postStageVisualStatus = $oppositionJourneyVisualStatus;
                                                                $postStageStatusLabel = $oppositionJourneyOutcomeLabel;
                                                            }
                                                            $postStageDocuments = $postFilingDocumentsByType->get($postFilingJourney::documentType($postStageKey), collect());
                                                            $postStageAdminDocuments = $postStageDocuments->filter(fn ($doc) => $isPostFilingAdminDocument($doc));
                                                            $postStageApplicantDocuments = $postStageDocuments->reject(fn ($doc) => $postStageAdminDocuments->contains('id', $doc->id))->filter(function ($doc) {
                                                                return $doc->status === 'uploaded'
                                                                    || str_contains(strtolower((string) $doc->verification_notes), 'applicant upload')
                                                                    || str_contains((string) $doc->file_path, 'documents/post-filing/');
                                                            });
                                                            $postStageHasApplicantDocuments = $postStageApplicantDocuments->isNotEmpty();
                                                            $postStageHasAdminDocuments = $postStageAdminDocuments->isNotEmpty();
                                                            $postStageModalId = 'post-filing-documents-' . $postStageKey;
                                                            $postStageDate = match ($postStageStatus) {
                                                                'completed' => data_get($postStageMeta, 'completed_at') ?: ($postStageKey === 'filed' ? $application->filed_at : null),
                                                                'processing' => data_get($postStageMeta, 'processing_at') ?: data_get($postStageMeta, 'updated_at'),
                                                                'opposed' => data_get($postStageMeta, 'opposed_at') ?: data_get($postStageMeta, 'opposition_received_on') ?: data_get($postStageMeta, 'updated_at'),
                                                                default => null,
                                                            };
                                                        @endphp
                                                        <div class="timeline-post-filing-stage {{ $postStageStatus }} {{ $postStageVisualStatus }}">
                                                            <div class="timeline-post-stage-number">{{ $postFilingStage['number'] }}</div>
                                                            <div class="timeline-post-stage-copy">
                                                                <div class="timeline-post-stage-title-row">
                                                                    <h6>Stage {{ $postFilingStage['number'] }}: {{ $postFilingStage['title'] }}</h6>
                                                                    @if ($postStageDocuments->isNotEmpty())
                                                                        <button type="button" class="post-filing-stage-documents-link" data-bs-toggle="modal" data-bs-target="#{{ $postStageModalId }}">
                                                                            <i class="bi bi-folder2-open"></i>
                                                                            View documents
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                                <p>{{ $postFilingStage['copy'] }}</p>
                                                            </div>
                                                            <div class="timeline-post-stage-state">
                                                                <span class="post-filing-status-badge {{ $postStageStatus }} {{ $postStageVisualStatus }}">{{ $postStageStatusLabel }}</span>
                                                                <small>{{ $postStageDate ? $formatDateTime($postStageDate) : '-' }}</small>
                                                            </div>
                                                        </div>
                                                        @if ($postStageDocuments->isNotEmpty())
                                                            <div class="modal fade post-filing-documents-modal" id="{{ $postStageModalId }}" tabindex="-1" aria-labelledby="{{ $postStageModalId }}-label" aria-hidden="true">
                                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <div>
                                                                                <h5 class="modal-title" id="{{ $postStageModalId }}-label">{{ $postFilingStage['title'] }} Documents</h5>
                                                                                <p class="mb-0 text-muted">Stage {{ $postFilingStage['number'] }} document history</p>
                                                                            </div>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            @if ($postStageHasApplicantDocuments && $postStageHasAdminDocuments)
                                                                                <ul class="nav nav-pills post-filing-document-tabs" id="{{ $postStageModalId }}-tabs" role="tablist">
                                                                                    <li class="nav-item" role="presentation">
                                                                                        <button class="nav-link active" id="{{ $postStageModalId }}-applicant-tab" data-bs-toggle="pill" data-bs-target="#{{ $postStageModalId }}-applicant" type="button" role="tab" aria-controls="{{ $postStageModalId }}-applicant" aria-selected="true">
                                                                                            Applicant Submitted
                                                                                        </button>
                                                                                    </li>
                                                                                    <li class="nav-item" role="presentation">
                                                                                        <button class="nav-link" id="{{ $postStageModalId }}-admin-tab" data-bs-toggle="pill" data-bs-target="#{{ $postStageModalId }}-admin" type="button" role="tab" aria-controls="{{ $postStageModalId }}-admin" aria-selected="false">
                                                                                            Admin Sent
                                                                                        </button>
                                                                                    </li>
                                                                                </ul>
                                                                            @endif

                                                                            <div class="tab-content post-filing-document-tab-content">
                                                                                @if ($postStageHasApplicantDocuments)
                                                                                    <div class="tab-pane fade show active" id="{{ $postStageModalId }}-applicant" role="tabpanel" aria-labelledby="{{ $postStageModalId }}-applicant-tab" tabindex="0">
                                                                                        <div class="post-filing-modal-document-list">
                                                                                            @foreach ($postStageApplicantDocuments as $postStageDocument)
                                                                                                <div class="post-filing-modal-document-row">
                                                                                                    <div>
                                                                                                        <strong>{{ $postStageDocument->file_name }}</strong>
                                                                                                        <small>
                                                                                                            Submitted {{ $postStageDocument->created_at ? $formatDateTime($postStageDocument->created_at) : '-' }}
                                                                                                            @if ($postStageDocument->verification_notes)
                                                                                                                <span>{{ $postStageDocument->verification_notes }}</span>
                                                                                                            @endif
                                                                                                        </small>
                                                                                                    </div>
                                                                                                    <a href="{{ route($documentViewRoute, $postStageDocument->id) }}" target="_blank">
                                                                                                        <i class="bi bi-eye"></i> View
                                                                                                    </a>
                                                                                                </div>
                                                                                            @endforeach
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if ($postStageHasAdminDocuments)
                                                                                    <div class="tab-pane fade {{ $postStageHasApplicantDocuments ? '' : 'show active' }}" id="{{ $postStageModalId }}-admin" role="tabpanel" aria-labelledby="{{ $postStageModalId }}-admin-tab" tabindex="0">
                                                                                        <div class="post-filing-modal-document-list">
                                                                                            @foreach ($postStageAdminDocuments as $postStageDocument)
                                                                                                <div class="post-filing-modal-document-row">
                                                                                                    <div>
                                                                                                        <strong>{{ $postStageDocument->file_name }}</strong>
                                                                                                        <small>
                                                                                                            Sent {{ $postStageDocument->created_at ? $formatDateTime($postStageDocument->created_at) : '-' }}
                                                                                                            @if ($postStageDocument->verification_notes)
                                                                                                                <span>{{ $postStageDocument->verification_notes }}</span>
                                                                                                            @endif
                                                                                                        </small>
                                                                                                    </div>
                                                                                                    <a href="{{ route($documentViewRoute, $postStageDocument->id) }}" target="_blank">
                                                                                                        <i class="bi bi-eye"></i> View
                                                                                                    </a>
                                                                                                </div>
                                                                                            @endforeach
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endunless

                <div class="card shadow-sm border-0 mb-4" id="stage-action">
                    <div class="card-header section-card-header">
                        @if ($currentStatus === $workflow::ONBOARDING_PENDING)
                            <div class="action-center-title">
                                <span><i class="bi bi-clipboard-check"></i></span>
                                <div>
                                    <h5 class="mb-1">Action Center</h5>
                                    <p class="mb-0">Review and sign the Engagement Letter before search and specification work begins.</p>
                                </div>
                            </div>
                        @else
                            <h5 class="mb-0">{{ $stageActionOnly ? $application->status_label : 'Action Center' }}</h5>
                        @endif
                    </div>
                    <div class="card-body">
                        @unless ($hideStageGuide)
                            <div class="stage-guide-card">
                                <div class="stage-guide-head">
                                    <span><i class="bi bi-{{ $actionGuidance['icon'] }}"></i></span>
                                    <div>
                                        <h6>{{ $actionGuidance['title'] }}</h6>
                                        <p>{{ $actionGuidance['copy'] }}</p>
                                    </div>
                                </div>
                                @unless (in_array($currentStatus, [$workflow::FILED, $workflow::POST_FILING], true))
                                    <ol class="stage-guide-steps">
                                        @foreach ($actionGuidance['steps'] as $step)
                                            <li>{{ $step }}</li>
                                        @endforeach
                                    </ol>
                                @endunless
                            </div>
                        @endunless

                        @if ($currentStatus === $workflow::DRAFT)
                            <div class="alert alert-info mb-3">
                                Pay 50% of the professional fee now. The remaining 50% is requested only after you approve the filing draft.
                            </div>
                            @include('partials.government-fee-notice')
                            <a href="{{ route('payment.show', $application->id) }}" class="btn btn-primary">Pay 50% Advance</a>
                        @elseif ($currentStatus === $workflow::ONBOARDING_PENDING)
                            @if ($onboardingPackageSubmitted && !$pendingOnboardingReupload)
                                <div class="action-empty-state">
                                    <div class="action-empty-icon">
                                        <i class="bi bi-bell"></i>
                                    </div>
                                    <div>
                                        <strong>No action is required right now.</strong>
                                        <p>You will get notified for the next steps within 24 to 48 hours.</p>
                                    </div>
                                </div>
                            @else
                                <div class="onboarding-action-shell">
                                    @if ($pendingOnboardingReupload)
                                        <div class="onboarding-reupload-alert">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <span><strong>Reupload required.</strong> Kindly re-attach or re-sign the documents shown below.</span>
                                        </div>
                                    @endif

                                    <form id="onboardingPackageForm" action="{{ $clientActionUrl('workflow.onboarding.submit', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Submit signed Engagement Letter?" data-swal-text="Please confirm that you have reviewed the letter and applied your signature." data-swal-icon="question" data-swal-confirm-text="Yes, submit">
                                        @csrf
                                        <section class="onboarding-section-card onboarding-upload-section">
                                            <div class="onboarding-section-heading">
                                                <span><i class="bi bi-cloud-arrow-up"></i></span>
                                                <div class="onboarding-section-copy">
                                                    <h5>Review and Sign</h5>
                                                    <p>Review the Engagement Letter and apply your electronic signature.</p>
                                                </div>
                                            </div>
                                        @if ($showEngagementSigner)
                                        <div class="onboarding-signature-card engagement-signature-card">
                                            <div class="signature-card-heading engagement-signature-heading">
                                                <div class="signature-card-icon blue"><i class="bi bi-vector-pen"></i></div>
                                                <div>
                                                    <h5>Engagement Letter Signature <span>Required</span></h5>
                                                    <p>{{ $engagementNeedsReupload ? 'Admin requested a fresh signature for the Engagement Letter.' : 'Create and apply your signature for the Engagement Letter.' }}</p>
                                                </div>
                                                <span class="badge rounded-pill {{ $engagementLetterSigned && !$engagementNeedsReupload ? 'text-bg-success' : 'text-bg-warning' }} {{ $engagementNeedsReupload ? 'reupload' : '' }}" data-signature-status-for="engagement-signature-{{ $application->id }}">
                                                    {{ $engagementNeedsReupload ? 'Re-sign Required' : ($engagementLetterSigned ? 'Signed' : 'Not Signed') }}
                                                </span>
                                            </div>
                                            @if ($engagementNeedsReupload && $engagementLetterSigned?->verification_notes)
                                                <div class="reupload-reason">
                                                    <strong>Admin note:</strong> {{ $engagementLetterSigned->verification_notes }}
                                                </div>
                                            @endif
                                            <div class="engagement-signature-footer">
                                                <div class="engagement-signature-actions">
                                                    @if ($engagementLetter)
                                                        <a href="{{ route($documentViewRoute, $engagementLetter->id) }}" target="_blank" class="signature-outline-action">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    @endif
                                                    <button type="button" class="signature-modal-trigger {{ $engagementLetterSigned && !$engagementNeedsReupload ? 'd-none' : '' }}" data-signature-open-for="engagement-signature-{{ $application->id }}" data-bs-toggle="modal" data-bs-target="#engagementSignatureModal" data-preview-document-url="{{ $engagementLetter ? route($documentViewRoute, $engagementLetter->id) : '' }}" data-preview-document-name="{{ $engagementLetter?->file_name }}" data-preview-open-url="{{ $engagementLetter ? route($documentViewRoute, $engagementLetter->id) : '' }}">
                                                        <i class="bi bi-pencil"></i> E-Sign
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="signed-document-actions {{ $engagementLetterSigned && !$engagementNeedsReupload ? '' : 'd-none' }}" data-signed-actions-for="engagement-signature-{{ $application->id }}">
                                                <span><i class="bi bi-check-circle-fill"></i> Signed Engagement Letter is ready.</span>
                                                <div class="signed-document-action-row">
                                                    <a id="engagement-signed-view-{{ $application->id }}" href="{{ $engagementLetterSigned ? route($documentViewRoute, $engagementLetterSigned->id) : '#' }}" target="_blank">
                                                        <i class="bi bi-eye"></i> View Signed Letter
                                                    </a>
                                                    <a id="engagement-signed-download-{{ $application->id }}" href="{{ $engagementLetterSigned ? route($documentDownloadRoute, $engagementLetterSigned->id) : '#' }}">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                    <button type="button" class="signed-document-resign" data-bs-toggle="modal" data-bs-target="#engagementSignatureModal" data-preview-document-url="{{ $engagementLetterSigned ? route($documentViewRoute, $engagementLetterSigned->id) : ($engagementLetter ? route($documentViewRoute, $engagementLetter->id) : '') }}" data-preview-document-name="{{ $engagementLetterSigned?->file_name ?: $engagementLetter?->file_name }}" data-preview-open-url="{{ $engagementLetterSigned ? route($documentViewRoute, $engagementLetterSigned->id) : ($engagementLetter ? route($documentViewRoute, $engagementLetter->id) : '') }}">
                                                        <i class="bi bi-arrow-repeat"></i> Re-sign
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="modal signature-document-modal" id="engagementSignatureModal" tabindex="-1" aria-labelledby="engagementSignatureModalLabel" aria-hidden="true" data-bs-focus="false">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <div>
                                                                <h5 class="modal-title" id="engagementSignatureModalLabel">Sign Engagement Letter</h5>
                                                                <p class="mb-0">Review the document and apply your signature from the panel.</p>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="signature-modal-grid">
                                                                <section class="signature-document-viewer">
                                                                    <div class="signature-document-toolbar">
                                                                        <div>
                                                                            <strong>Engagement Letter</strong>
                                                                            <span data-signature-doc-name>{{ $engagementLetter ? $engagementLetter->file_name : 'Awaiting document' }}</span>
                                                                        </div>
                                                                        @if ($engagementLetter)
                                                                            <a href="{{ route($documentViewRoute, $engagementLetter->id) }}" target="_blank" data-signature-doc-open-link>
                                                                                <i class="bi bi-box-arrow-up-right"></i> Open
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                    @if ($engagementLetter)
                                                                        <div class="signature-document-loading" data-signature-doc-loading>
                                                                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                                                            Loading document...
                                                                        </div>
                                                                        <iframe data-signature-doc-frame data-src="{{ route($documentViewRoute, $engagementLetter->id) }}#toolbar=0&navpanes=0&scrollbar=1&view=FitH" data-original-src="{{ route($documentViewRoute, $engagementLetter->id) }}#toolbar=0&navpanes=0&scrollbar=1&view=FitH" title="Engagement Letter preview"></iframe>
                                                                    @else
                                                                        <div class="signature-document-empty">
                                                                            <i class="bi bi-file-earmark-lock"></i>
                                                                            <strong>Engagement Letter not available yet</strong>
                                                                            <p>The admin must upload the document before signing can begin.</p>
                                                                        </div>
                                                                    @endif
                                                                </section>
                                                                <aside class="signature-modal-panel">
                                                                    <div class="signature-modal-panel-head">
                                                                        <span><i class="bi bi-vector-pen"></i></span>
                                                                        <div>
                                                                            <strong>Your Signature</strong>
                                                                            <p>Draw, type, or upload your signature and apply it to the Engagement Letter.</p>
                                                                        </div>
                                                                    </div>
                                                                    @include('trademark.partials.signature-pad', [
                                                                        'id' => 'engagement-signature-' . $application->id,
                                                                        'label' => '',
                                                                        'defaultName' => $client->name,
                                                                        'namePrefix' => 'engagement',
                                                                        'theme' => 'blue',
                                                                        'signUrl' => $clientActionUrl('workflow.onboarding.apply-signature', [$application->id, 'engagement_letter']),
                                                                        'signedViewTarget' => 'engagement-signed-view-' . $application->id,
                                                                        'signedDownloadTarget' => 'engagement-signed-download-' . $application->id,
                                                                        'formId' => 'onboardingPackageForm',
                                                                        'applied' => (bool) $engagementLetterSigned && !$engagementNeedsReupload,
                                                                        'signatureBoxInfo' => $signatureBoxInfo('engagement_letter'),
                                                                    ])
                                                                </aside>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer signature-modal-footer">
                                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                                                Done
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        @if ($showPoaSigner && (!$pendingOnboardingReupload || $poaNeedsReupload || $poaDraftAttached))
                                        <div class="onboarding-signature-card physical-upload-card">
                                            <div class="signature-card-heading">
                                                <div class="signature-card-icon green"><i class="bi bi-upload"></i></div>
                                                <div>
                                                    <h5>Signed POA Upload <span>Required</span></h5>
                                                    <p>{{ $poaNeedsReupload ? 'Admin requested a fresh physically signed POA.' : 'Download the POA, sign it physically, then upload the signed copy.' }}</p>
                                                </div>
                                                <div class="signature-heading-actions">
                                                    <span class="badge rounded-pill {{ $poaSigned && !$poaNeedsReupload ? 'text-bg-success' : 'text-bg-warning' }}">
                                                        {{ $poaNeedsReupload ? 'Reupload Required' : ($poaSigned ? ($poaSigned->status === 'draft' ? 'Attached' : 'Uploaded') : 'Not Uploaded') }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if ($poaNeedsReupload && $poaSigned?->verification_notes)
                                                <div class="reupload-reason">
                                                    <strong>Admin note:</strong> {{ $poaSigned->verification_notes }}
                                                </div>
                                            @endif
                                            <div class="physical-signature-note">
                                                <i class="bi bi-exclamation-circle-fill"></i>
                                                <span>Download the POA, print it on Rs. 100 stamp paper, sign it physically, then upload the signed copy here.</span>
                                            </div>
                                            @if ($poaSigned && !$poaNeedsReupload)
                                                <div class="signed-document-actions">
                                                    <span data-signed-document-status><i class="bi bi-check-circle-fill"></i> Signed POA has been {{ $poaSigned->status === 'draft' ? 'attached' : 'uploaded' }}.</span>
                                                    <div class="signed-document-action-row">
                                                        <a href="{{ route($documentViewRoute, $poaSigned->id) }}" target="_blank" data-signed-view-link><i class="bi bi-eye"></i> View Signed POA</a>
                                                        <a href="{{ route($documentDownloadRoute, $poaSigned->id) }}" data-signed-download-link><i class="bi bi-download"></i> Download</a>
                                                        <label class="signed-document-resign" for="poa-signed-reupload-{{ $application->id }}">
                                                            <span data-reupload-action-label><i class="bi bi-arrow-repeat"></i> Re-upload</span>
                                                            <input id="poa-signed-reupload-{{ $application->id }}" type="file" name="poa_signed_file" class="physical-upload-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-draft-upload-url="{{ $clientActionUrl('workflow.onboarding.draft-upload', [$application->id, 'poa']) }}">
                                                        </label>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="physical-upload-footer">
                                                    <label class="physical-upload-box" for="poa-signed-file-{{ $application->id }}">
                                                        <div class="physical-upload-copy">
                                                            <span class="physical-upload-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                                                            <div>
                                                                <strong data-upload-file-name>Choose file</strong>
                                                                <small>PDF, JPG, PNG, DOC, DOCX</small>
                                                            </div>
                                                        </div>
                                                        <div class="physical-upload-actions">
                                                            <span class="physical-upload-choose">Choose File</span>
                                                        </div>
                                                        <input id="poa-signed-file-{{ $application->id }}" type="file" name="poa_signed_file" class="physical-upload-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-draft-upload-url="{{ $clientActionUrl('workflow.onboarding.draft-upload', [$application->id, 'poa']) }}" required>
                                                    </label>
                                                    @if ($poaDocument)
                                                        <a href="{{ route($documentViewRoute, $poaDocument->id) }}" target="_blank" class="signature-outline-action physical-upload-view">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        @endif

                                        @if ($showAffidavitSigner && (!$pendingOnboardingReupload || $affidavitNeedsReupload || $affidavitDraftAttached))
                                        <div class="onboarding-signature-card physical-upload-card">
                                            <div class="signature-card-heading">
                                                <div class="signature-card-icon purple"><i class="bi bi-upload"></i></div>
                                                <div>
                                                    <h5>Signed Affidavit Upload <span>Required</span></h5>
                                                    <p>{{ $affidavitNeedsReupload ? 'Admin requested a fresh physically signed Affidavit.' : 'Download the Affidavit, sign it physically, then upload the signed copy.' }}</p>
                                                </div>
                                                <div class="signature-heading-actions">
                                                    <span class="badge rounded-pill {{ $affidavitSigned && !$affidavitNeedsReupload ? 'text-bg-success' : 'text-bg-warning' }}">
                                                        {{ $affidavitNeedsReupload ? 'Reupload Required' : ($affidavitSigned ? ($affidavitSigned->status === 'draft' ? 'Attached' : 'Uploaded') : 'Not Uploaded') }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if ($affidavitNeedsReupload && $affidavitSigned?->verification_notes)
                                                <div class="reupload-reason">
                                                    <strong>Admin note:</strong> {{ $affidavitSigned->verification_notes }}
                                                </div>
                                            @endif
                                            <div class="physical-signature-note">
                                                <i class="bi bi-exclamation-circle-fill"></i>
                                                <span>Download the Affidavit, print it on Rs. 10 stamp paper, sign it physically, then upload the signed copy here.</span>
                                            </div>
                                            @if ($affidavitSigned && !$affidavitNeedsReupload)
                                                <div class="signed-document-actions">
                                                    <span data-signed-document-status><i class="bi bi-check-circle-fill"></i> Signed Affidavit has been {{ $affidavitSigned->status === 'draft' ? 'attached' : 'uploaded' }}.</span>
                                                    <div class="signed-document-action-row">
                                                        <a href="{{ route($documentViewRoute, $affidavitSigned->id) }}" target="_blank" data-signed-view-link><i class="bi bi-eye"></i> View Signed Affidavit</a>
                                                        <a href="{{ route($documentDownloadRoute, $affidavitSigned->id) }}" data-signed-download-link><i class="bi bi-download"></i> Download</a>
                                                        <label class="signed-document-resign" for="affidavit-signed-reupload-{{ $application->id }}">
                                                            <span data-reupload-action-label><i class="bi bi-arrow-repeat"></i> Re-upload</span>
                                                            <input id="affidavit-signed-reupload-{{ $application->id }}" type="file" name="affidavit_signed_file" class="physical-upload-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-draft-upload-url="{{ $clientActionUrl('workflow.onboarding.draft-upload', [$application->id, 'affidavit']) }}">
                                                        </label>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="physical-upload-footer">
                                                    <label class="physical-upload-box" for="affidavit-signed-file-{{ $application->id }}">
                                                        <div class="physical-upload-copy">
                                                            <span class="physical-upload-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                                                            <div>
                                                                <strong data-upload-file-name>Choose file</strong>
                                                                <small>PDF, JPG, PNG, DOC, DOCX</small>
                                                            </div>
                                                        </div>
                                                        <div class="physical-upload-actions">
                                                            <span class="physical-upload-choose">Choose File</span>
                                                        </div>
                                                        <input id="affidavit-signed-file-{{ $application->id }}" type="file" name="affidavit_signed_file" class="physical-upload-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-draft-upload-url="{{ $clientActionUrl('workflow.onboarding.draft-upload', [$application->id, 'affidavit']) }}" required>
                                                    </label>
                                                    @if ($affidavitDocument)
                                                        <a href="{{ route($documentViewRoute, $affidavitDocument->id) }}" target="_blank" class="signature-outline-action physical-upload-view">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        @endif

                                        @if ($showPoaSigner)
                                        <div class="onboarding-signature-card">
                                            <div class="signature-card-heading">
                                                <div class="signature-card-icon green"><i class="bi bi-vector-pen"></i></div>
                                                <div>
                                                    <h5>POA Signature <span>Required</span></h5>
                                                    <p>{{ $poaNeedsReupload ? 'Admin requested a fresh signature for the Power of Attorney.' : 'Create and apply your signature for the Power of Attorney.' }}</p>
                                                </div>
                                                <div class="signature-heading-actions">
                                                    <span class="badge rounded-pill {{ $poaSigned && !$poaNeedsReupload ? 'text-bg-success' : 'text-bg-warning' }} {{ $poaNeedsReupload ? 'reupload' : '' }}" data-signature-status-for="poa-signature-{{ $application->id }}">
                                                        {{ $poaNeedsReupload ? 'Re-sign Required' : ($poaSigned ? 'Signed' : 'Not Signed') }}
                                                    </span>
                                                    <button type="button" class="signature-modal-trigger signature-modal-trigger-green {{ $poaSigned && !$poaNeedsReupload ? 'd-none' : '' }}" data-signature-open-for="poa-signature-{{ $application->id }}" data-bs-toggle="modal" data-bs-target="#poaSignatureModal">
                                                        <i class="bi bi-window-sidebar"></i> Open Signer
                                                    </button>
                                                </div>
                                            </div>
                                            @if ($poaNeedsReupload && $poaSigned?->verification_notes)
                                                <div class="reupload-reason">
                                                    <strong>Admin note:</strong> {{ $poaSigned->verification_notes }}
                                                </div>
                                            @endif
                                            <div class="signed-document-actions {{ $poaSigned && !$poaNeedsReupload ? '' : 'd-none' }}" data-signed-actions-for="poa-signature-{{ $application->id }}">
                                                <span><i class="bi bi-check-circle-fill"></i> Signed POA is ready.</span>
                                                <a id="poa-signed-view-{{ $application->id }}" href="{{ $poaSigned ? route($documentViewRoute, $poaSigned->id) : '#' }}" target="_blank">
                                                    <i class="bi bi-eye"></i> View Signed POA
                                                </a>
                                                <a id="poa-signed-download-{{ $application->id }}" href="{{ $poaSigned ? route($documentDownloadRoute, $poaSigned->id) : '#' }}">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                                <button type="button" class="signed-document-resign" data-bs-toggle="modal" data-bs-target="#poaSignatureModal">
                                                    <i class="bi bi-arrow-repeat"></i> Re-sign
                                                </button>
                                            </div>
                                            <div class="modal signature-document-modal" id="poaSignatureModal" tabindex="-1" aria-labelledby="poaSignatureModalLabel" aria-hidden="true" data-bs-focus="false">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header signature-modal-header-green">
                                                            <div>
                                                                <h5 class="modal-title" id="poaSignatureModalLabel">Sign POA</h5>
                                                                <p class="mb-0">Review the Power of Attorney and apply your signature from the panel.</p>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="signature-modal-grid">
                                                                <section class="signature-document-viewer">
                                                                    <div class="signature-document-toolbar">
                                                                        <div>
                                                                            <strong>Power of Attorney</strong>
                                                                            <span>{{ $poaDocument ? $poaDocument->file_name : 'Awaiting document' }}</span>
                                                                        </div>
                                                                        @if ($poaDocument)
                                                                            <a href="{{ route($documentViewRoute, $poaDocument->id) }}" target="_blank">
                                                                                <i class="bi bi-box-arrow-up-right"></i> Open
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                    @if ($poaDocument)
                                                                        <div class="signature-document-loading" data-signature-doc-loading>
                                                                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                                                            Loading document...
                                                                        </div>
                                                                        <iframe data-signature-doc-frame data-src="{{ route($documentViewRoute, $poaDocument->id) }}#toolbar=0&navpanes=0&scrollbar=1&view=FitH" title="POA preview"></iframe>
                                                                    @else
                                                                        <div class="signature-document-empty">
                                                                            <i class="bi bi-file-earmark-lock"></i>
                                                                            <strong>POA not available yet</strong>
                                                                            <p>The admin must upload the document before signing can begin.</p>
                                                                        </div>
                                                                    @endif
                                                                </section>
                                                                <aside class="signature-modal-panel">
                                                                    <div class="signature-modal-panel-head signature-modal-panel-head-green">
                                                                        <span><i class="bi bi-vector-pen"></i></span>
                                                                        <div>
                                                                            <strong>Your Signature</strong>
                                                                            <p>Draw, type, or upload your signature and apply it to the POA.</p>
                                                                        </div>
                                                                    </div>
                                                                    @include('trademark.partials.signature-pad', [
                                                                        'id' => 'poa-signature-' . $application->id,
                                                                        'label' => '',
                                                                        'defaultName' => $client->name,
                                                                        'namePrefix' => 'poa',
                                                                        'theme' => 'green',
                                                                        'signUrl' => $clientActionUrl('workflow.onboarding.apply-signature', [$application->id, 'poa']),
                                                                        'signedViewTarget' => 'poa-signed-view-' . $application->id,
                                                                        'signedDownloadTarget' => 'poa-signed-download-' . $application->id,
                                                                        'formId' => 'onboardingPackageForm',
                                                                        'applied' => (bool) $poaSigned && !$poaNeedsReupload,
                                                                        'signatureBoxInfo' => $signatureBoxInfo('poa'),
                                                                    ])
                                                                </aside>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer signature-modal-footer">
                                                            <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                                                Done
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        @if ($showAffidavitSigner)
                                        <div class="onboarding-signature-card">
                                            <div class="signature-card-heading">
                                                <div class="signature-card-icon purple"><i class="bi bi-vector-pen"></i></div>
                                                <div>
                                                    <h5>Affidavit Signature <span>Required</span></h5>
                                                    <p>{{ $affidavitNeedsReupload ? 'Admin requested a fresh signature for the Affidavit.' : 'Create and apply your signature for the Affidavit.' }}</p>
                                                </div>
                                                <div class="signature-heading-actions">
                                                    <span class="badge rounded-pill {{ $affidavitSigned && !$affidavitNeedsReupload ? 'text-bg-success' : 'text-bg-warning' }} {{ $affidavitNeedsReupload ? 'reupload' : '' }}" data-signature-status-for="affidavit-signature-{{ $application->id }}">
                                                        {{ $affidavitNeedsReupload ? 'Re-sign Required' : ($affidavitSigned ? 'Signed' : 'Not Signed') }}
                                                    </span>
                                                    <button type="button" class="signature-modal-trigger {{ $affidavitSigned && !$affidavitNeedsReupload ? 'd-none' : '' }}" data-signature-open-for="affidavit-signature-{{ $application->id }}" data-bs-toggle="modal" data-bs-target="#affidavitSignatureModal">
                                                        <i class="bi bi-window-sidebar"></i> Open Signer
                                                    </button>
                                                </div>
                                            </div>
                                            @if ($affidavitNeedsReupload && $affidavitSigned?->verification_notes)
                                                <div class="reupload-reason">
                                                    <strong>Admin note:</strong> {{ $affidavitSigned->verification_notes }}
                                                </div>
                                            @endif
                                            <div class="signed-document-actions {{ $affidavitSigned && !$affidavitNeedsReupload ? '' : 'd-none' }}" data-signed-actions-for="affidavit-signature-{{ $application->id }}">
                                                <span><i class="bi bi-check-circle-fill"></i> Signed Affidavit is ready.</span>
                                                <a id="affidavit-signed-view-{{ $application->id }}" href="{{ $affidavitSigned ? route($documentViewRoute, $affidavitSigned->id) : '#' }}" target="_blank">
                                                    <i class="bi bi-eye"></i> View Signed Affidavit
                                                </a>
                                                <a id="affidavit-signed-download-{{ $application->id }}" href="{{ $affidavitSigned ? route($documentDownloadRoute, $affidavitSigned->id) : '#' }}">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                                <button type="button" class="signed-document-resign" data-bs-toggle="modal" data-bs-target="#affidavitSignatureModal">
                                                    <i class="bi bi-arrow-repeat"></i> Re-sign
                                                </button>
                                            </div>
                                            <div class="modal signature-document-modal" id="affidavitSignatureModal" tabindex="-1" aria-labelledby="affidavitSignatureModalLabel" aria-hidden="true" data-bs-focus="false">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="modal-header signature-modal-header-purple">
                                                            <div>
                                                                <h5 class="modal-title" id="affidavitSignatureModalLabel">Sign Affidavit</h5>
                                                                <p class="mb-0">Review the Affidavit and apply your signature from the panel.</p>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="signature-modal-grid">
                                                                <section class="signature-document-viewer">
                                                                    <div class="signature-document-toolbar">
                                                                        <div>
                                                                            <strong>Affidavit</strong>
                                                                            <span>{{ $affidavitDocument ? $affidavitDocument->file_name : 'Awaiting document' }}</span>
                                                                        </div>
                                                                        @if ($affidavitDocument)
                                                                            <a href="{{ route($documentViewRoute, $affidavitDocument->id) }}" target="_blank">
                                                                                <i class="bi bi-box-arrow-up-right"></i> Open
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                    @if ($affidavitDocument)
                                                                        <div class="signature-document-loading" data-signature-doc-loading>
                                                                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                                                            Loading document...
                                                                        </div>
                                                                        <iframe data-signature-doc-frame data-src="{{ route($documentViewRoute, $affidavitDocument->id) }}#toolbar=0&navpanes=0&scrollbar=1&view=FitH" title="Affidavit preview"></iframe>
                                                                    @else
                                                                        <div class="signature-document-empty">
                                                                            <i class="bi bi-file-earmark-lock"></i>
                                                                            <strong>Affidavit not available yet</strong>
                                                                            <p>The admin must upload the document before signing can begin.</p>
                                                                        </div>
                                                                    @endif
                                                                </section>
                                                                <aside class="signature-modal-panel">
                                                                    <div class="signature-modal-panel-head signature-modal-panel-head-purple">
                                                                        <span><i class="bi bi-vector-pen"></i></span>
                                                                        <div>
                                                                            <strong>Your Signature</strong>
                                                                            <p>Draw, type, or upload your signature and apply it to the Affidavit.</p>
                                                                        </div>
                                                                    </div>
                                                                    @include('trademark.partials.signature-pad', [
                                                                        'id' => 'affidavit-signature-' . $application->id,
                                                                        'label' => '',
                                                                        'defaultName' => $client->name,
                                                                        'namePrefix' => 'affidavit',
                                                                        'theme' => 'blue',
                                                                        'signUrl' => $clientActionUrl('workflow.onboarding.apply-signature', [$application->id, 'affidavit']),
                                                                        'signedViewTarget' => 'affidavit-signed-view-' . $application->id,
                                                                        'signedDownloadTarget' => 'affidavit-signed-download-' . $application->id,
                                                                        'formId' => 'onboardingPackageForm',
                                                                        'applied' => (bool) $affidavitSigned && !$affidavitNeedsReupload,
                                                                        'signatureBoxInfo' => $signatureBoxInfo('affidavit'),
                                                                    ])
                                                                </aside>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer signature-modal-footer">
                                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                                                Done
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif

                                        </section>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Notes</label>
                                            <textarea name="onboarding_notes" rows="3" class="form-control" placeholder="Optional note for the onboarding package submission"></textarea>
                                        </div>

                                        <div class="action-submit-card">
                                            <!-- <div class="alert alert-info mb-3">
                                                Use the form above to submit your electronic signature for the Engagement Letter and upload physically signed POA and Affidavit copies. The application will move to strategy after the signed versions are verified by the admin.
                                            </div> -->
                                            <button type="submit" class="btn btn-primary w-100" id="onboardingPackageSubmitBtn" disabled>
                                                <i class="bi bi-cloud-arrow-up-fill me-2"></i> Submit Signed Engagement Letter
                                            </button>
                                            <div class="small text-muted mt-2 text-center"><i class="bi bi-lock-fill me-1"></i> Apply the required signature to enable submission.</div>
                                        </div>
                                    </form>

                                </div>
                            @endif
                        @elseif ($currentStatus === $workflow::STRATEGY_COMPLETED)
                            <div class="alert alert-success mb-3">
                                <strong>Strategy completed.</strong> Draft preparation is now active. We will notify you when your draft PDF is ready for review.
                            </div>
                            @php
                                $strategyAdminNote = data_get($application->workflow_meta, 'strategy.search_summary')
                                    ?: ($searchReportDocument?->verification_notes ?? null);
                            @endphp
                            @if (filled($strategyAdminNote))
                                <div class="strategy-admin-note mb-3">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <div>
                                        <strong>Admin Note:</strong>
                                        <span>{{ $strategyAdminNote }}</span>
                                    </div>
                                </div>
                            @endif
                            @if ($searchReportDocument)
                                <div class="p-3 border rounded mb-3">
                                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                        <div>
                                            <div class="fw-semibold">Search Report</div>
                                            <div class="small text-muted">A manual search report has been shared with you.</div>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route($documentViewRoute, $searchReportDocument->id) }}" target="_blank" class="btn btn-outline-primary">View Search Report</a>
                                            <a href="{{ route($documentDownloadRoute, $searchReportDocument->id) }}" class="btn btn-outline-success">Download Search Report</a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!-- <div class="p-3 border rounded bg-light">
                                <div class="fw-semibold mb-2">Strategy Summary</div>
                                <div class="small text-muted mb-2">Risk Level: {{ data_get($application->workflow_meta, 'strategy.risk_level', 'Not shared yet') }}</div>
                                <div>{{ data_get($application->workflow_meta, 'strategy.search_summary', 'The strategy report has been completed.') }}</div>
                            </div> -->
                        @elseif (in_array($currentStatus, [$workflow::AWAITING_APPROVAL, $workflow::CHANGES_REQUESTED], true))
                            <div class="draft-review-panel draft-compact-panel">
                                <div class="draft-compact-card">
                                    <div class="draft-compact-main">
                                        <div class="draft-review-icon draft-review-icon-blue">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <div class="draft-compact-copy">
                                            <h4>Draft Version {{ $draft?->version_no ?? 1 }}</h4>
                                            <p>Last updated:
                                                {{ $formatDateTime($draft?->published_at ?? $draft?->created_at ?? $application->updated_at, 'M d, Y') }}
                                                ·
                                                {{ $formatDateTime($draft?->published_at ?? $draft?->created_at ?? $application->updated_at, 'h:i A') }}
                                            </p>
                                        </div>
                                    </div>

                                    @if ($draftDocument)
                                        <div class="draft-document-actions draft-compact-actions">
                                            <a href="{{ route($documentViewRoute, $draftDocument->id) }}" target="_blank" class="draft-action-btn draft-action-primary">
                                                <i class="bi bi-eye"></i>
                                                View Draft
                                            </a>
                                            <a href="{{ route($documentDownloadRoute, $draftDocument->id) }}" class="draft-action-btn draft-action-secondary">
                                                <i class="bi bi-download"></i>
                                                Download PDF
                                            </a>
                                        </div>
                                    @endif

                                    @if ($draft?->goods_services)
                                        <div class="draft-admin-note">
                                            <i class="bi bi-info-circle"></i>
                                            <div>
                                                <strong>Note from admin</strong>
                                                <p>{{ $draft->goods_services }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                @if ($currentStatus === $workflow::CHANGES_REQUESTED)
                                    <div class="alert alert-warning draft-compact-alert mb-0">
                                        <strong>Changes requested.</strong>
                                        <div class="mt-2">
                                            Your request is with the drafting team. We will notify you within 24-48 hours when the revised draft is ready.
                                        </div>
                                        @if ($draft?->client_comments)
                                            <div class="mt-3">
                                                <strong>Your note:</strong>
                                                <div>{{ $draft->client_comments }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="draft-decision-row">
                                        <button type="button" class="draft-decision-btn draft-decision-approve" data-bs-toggle="modal" data-bs-target="#approveDraftModal">
                                            <i class="bi bi-check2-circle"></i>
                                            Approve Application
                                        </button>
                                        <button type="button" class="draft-decision-btn draft-decision-reject" data-bs-toggle="modal" data-bs-target="#requestDraftChangesModal">
                                            <i class="bi bi-chat-left-text"></i>
                                            Request Changes
                                        </button>
                                    </div>

                                    <div class="modal fade draft-review-modal" id="requestDraftChangesModal" tabindex="-1" aria-labelledby="requestDraftChangesModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content draft-action-modal">
                                                <form action="{{ $clientActionUrl('workflow.draft.request-changes', $application->id) }}" method="POST" data-swal-confirm data-swal-title="Request draft changes?" data-swal-text="Your comments will be sent to the drafting team for review." data-swal-icon="warning" data-swal-confirm-text="Yes, request changes">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <div class="draft-section-heading mb-0">
                                                            <div class="draft-review-icon draft-review-icon-orange">
                                                                <i class="bi bi-chat-left"></i>
                                                            </div>
                                                            <div>
                                                                <h5 id="requestDraftChangesModalLabel">Request Changes</h5>
                                                                <p>Tell the admin what must be corrected before reupload.</p>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="draft-textarea-wrap">
                                                            <textarea name="change_request" rows="5" class="form-control draft-review-textarea js-counted-textarea" maxlength="1000" minlength="10" required placeholder="Example: Please correct the applicant address or class description...">{{ old('change_request') }}</textarea>
                                                            <span class="draft-counter">0/1000</span>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="draft-request-btn">
                                                            <i class="bi bi-send"></i>
                                                            Send Request
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade draft-review-modal" id="approveDraftModal" tabindex="-1" aria-labelledby="approveDraftModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content draft-action-modal">
                                                <form id="draftApprovalForm" action="{{ $clientActionUrl('workflow.draft.approve', $application->id) }}" method="POST" enctype="multipart/form-data" data-swal-confirm data-swal-title="Approve application?" data-swal-text="By continuing, you confirm the applicant, mark, classes, specification and filing authority." data-swal-icon="question" data-swal-confirm-text="Yes, approve application">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <div class="draft-section-heading mb-0">
                                                            <div class="draft-review-icon draft-review-icon-purple">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </div>
                                                            <div>
                                                                <h5 id="approveDraftModalLabel">Approve Application</h5>
                                                                <p>Confirm every item before authorising UKIPO filing.</p>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="d-grid gap-2 mb-4">
                                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="approve_applicant" value="1" required><span class="form-check-label ms-2">I approve the applicant details.</span></label>
                                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="approve_mark" value="1" required><span class="form-check-label ms-2">I approve the trade mark wording and/or logo.</span></label>
                                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="approve_classes_specification" value="1" required><span class="form-check-label ms-2">I approve the final classes and goods and services specification.</span></label>
                                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_genuine_use" value="1" required><span class="form-check-label ms-2">I confirm genuine current use or a genuine intention to use the mark.</span></label>
                                                            <label class="form-check"><input class="form-check-input" type="checkbox" name="authorise_filing" value="1" required><span class="form-check-label ms-2">I authorise Legal Bruz Pvt. Ltd. to submit the approved application.</span></label>
                                                        </div>
                                                        <div class="row g-3 mb-4">
                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold">Signed Filing Authority <span class="text-danger">*</span></label>
                                                                <input type="file" name="signed_filing_authority" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                                            </div>
                                                            <div class="col-12"><small class="text-muted">Your Engagement Letter was signed during onboarding. Upload only the completed Filing Authority supplied by the filing team. PDF, JPG or PNG, maximum 15 MB.</small></div>
                                                        </div>
                                                        <div class="draft-textarea-wrap">
                                                            <textarea name="approval_notes" rows="5" class="form-control draft-review-textarea js-counted-textarea" maxlength="1000" placeholder="Optional: add a note for the filing team...">{{ old('approval_notes') }}</textarea>
                                                            <span class="draft-counter">0/1000</span>
                                                        </div>
                                                        <p class="draft-confirmation mb-0 mt-3"><i class="bi bi-shield-check"></i> Nothing will be filed until these approvals are recorded and payment is complete.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="draft-approve-btn">
                                                            <i class="bi bi-check2-circle"></i>
                                                            Approve Application
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @elseif ($currentStatus === $workflow::PAYMENT_PENDING_FINAL)
                            <p class="text-muted">The draft has been approved. Complete the final balance to proceed with filing.</p>
                            @include('partials.government-fee-notice')
                            <a href="{{ route('payment.show', $application->id) }}" class="btn btn-primary">Pay Final Balance</a>
                        @elseif (in_array($currentStatus, [$workflow::FILED, $workflow::POST_FILING], true))
                            @if ($postFilingIsFullyCompleted)
                                <div class="alert alert-success mb-3">
                                    <strong>Trademark registered successfully.</strong>
                                    <div class="mt-1">Your trademark for {{ $application->brand_name ?: 'your trademark' }} has been registered successfully.</div>
                                </div>
                                <p class="mb-2"><strong>Application Number:</strong> {{ $application->application_number ?? 'Awaiting assignment' }}</p>
                                @if ($registeredAdminDocuments->isNotEmpty())
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#post-filing-documents-registered">
                                        <i class="bi bi-folder2-open"></i> View registration documents
                                    </button>
                                @endif
                            @else
                                <div class="alert alert-success mb-3">
                                    <strong>Application filed.</strong> We will keep you updated on examination, publication, opposition, and registration events.
                                </div>
                                @if (filled($filingAdminNote))
                                    <div class="strategy-admin-note mb-3">
                                        <i class="bi bi-info-circle-fill"></i>
                                        <div>
                                            <strong>Admin Note:</strong>
                                            <span>{{ $filingAdminNote }}</span>
                                        </div>
                                    </div>
                                @endif
                                <p class="mb-2"><strong>Application Number:</strong> {{ $application->application_number ?? 'Awaiting assignment' }}</p>
                            @endif
                            @if (!$postFilingIsFullyCompleted && $postFilingIsOpposed)
                                <div class="alert {{ $finalOppositionResult === \App\Support\TrademarkOppositionWorkflow::APPLICATION_FINAL_RESULT_DEFENCE_SUCCESSFUL || $oppositionDefenceSucceeded ? 'alert-success' : 'alert-danger' }} mb-3">
                                    <strong>{{ $finalOppositionResultLabel ?: ($oppositionDefenceSucceeded ? 'Case victory recorded.' : 'Opposition notice received.') }}</strong>
                                    <div class="mt-1">{{ $finalOppositionResultCopy ?: 'See the Opposition Details section on the right for the full timeline, admin note, and supporting documents.' }}</div>
                                </div>
                            @elseif (!$postFilingIsFullyCompleted && $postFilingHasRequestedDocuments && $postFilingActiveStageKey)
                                @php
                                    $activePostFilingStageMeta = data_get($application->workflow_meta, "post_filing_journey.stages.$postFilingActiveStageKey", []);
                                    $activePostFilingAdminNote = data_get($activePostFilingStageMeta, 'admin_note');
                                @endphp
                                <form action="{{ $clientActionUrl('workflow.post-filing.documents', [$application->id, $postFilingActiveStageKey]) }}" method="POST" enctype="multipart/form-data" class="post-filing-upload-form" data-swal-confirm data-swal-title="Submit post-filing documents?" data-swal-text="These files will be sent to the admin team for this registry stage." data-swal-icon="question" data-swal-confirm-text="Yes, submit">
                                    @csrf
                                    <div class="fw-semibold mb-2">{{ $activePostFilingStage['title'] ?? 'Post Filing' }} documents requested</div>
                                    @if (filled($activePostFilingAdminNote))
                                        <div class="alert alert-warning mb-3">
                                            <strong>Admin Note:</strong>
                                            <div class="mt-1">{!! nl2br(e($activePostFilingAdminNote)) !!}</div>
                                        </div>
                                    @endif
                                    <label class="form-label fw-semibold">Upload documents <span class="text-danger fw-normal">*</span></label>
                                    <input type="file" name="post_filing_documents[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                    <label class="form-label fw-semibold mt-2">Note <span class="text-muted fw-normal">(optional)</span></label>
                                    <textarea name="post_filing_note" class="form-control" rows="3" maxlength="1000" placeholder="Add any context for the admin team...">{{ old('post_filing_note') }}</textarea>
                                    <button type="submit" class="btn btn-primary mt-3">
                                        <i class="bi bi-cloud-arrow-up"></i> Submit Stage Documents
                                    </button>
                                </form>
                            @elseif (!$postFilingIsFullyCompleted)
                                <div class="post-filing-active-highlight">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <div>
                                        <strong>The post-filing stage, {{ $activePostFilingStage['title'] ?? 'Post Filing' }}, is currently active.</strong>
                                        <p>We will notify you with updates on the post-filing tracking as we move through the process.</p>
                                    </div>
                                </div>
                            @endif
                        @elseif ($currentStatus === $workflow::STRATEGY_IN_PROGRESS)
                            <div class="strategy-active-highlight">
                                <span><i class="bi bi-search-heart"></i></span>
                                <div>
                                    <strong>Search and specification review is active.</strong>
                                    <p>Our team is reviewing the UK register and preparing the classes and goods and services specification. We will notify you when the application is ready for approval.</p>
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">We will notify you within <b>24-48 hours</b> for further actions.</p>
                        @endif
                    </div>
                </div>

                @unless ($stageActionOnly)
                    <div class="card shadow-sm border-0">
                        <div class="card-header section-card-header">
                            <h5 class="mb-0">Recent Workflow Activity</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($workflowActivity as $activity)
                                <div class="{{ !$loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                                    <div class="fw-semibold">{{ $activity['title'] }}</div>
                                    <div class="text-muted small">{{ $formatDateTime($activity['timestamp']) }}</div>
                                    @if ($activity['reason'])
                                        <div class="mt-1">{{ $activity['reason'] }}</div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">Workflow activity will appear here as your application progresses.</p>
                            @endforelse
                        </div>
                    </div>
                @endunless
            </div>

            @unless ($stageActionOnly)
                <div class="col-lg-4">
                    <div class="card status-side-card mb-4">
                        <div class="card-header section-card-header side-card-header">
                            <h5 class="mb-0">Checklist</h5>
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div class="card-body">
                            @forelse ($tasks as $task)
                                <div class="side-list-item {{ !$loop->last ? 'with-border' : '' }}">
                                    <div class="side-list-icon">
                                        <i class="bi bi-pencil"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $task->title }}</div>
                                        <small class="text-muted">{{ $task->assignee_type === 'user' ? 'Applicant' : ucfirst($task->assignee_type) }} • {{ ucfirst(str_replace('_', ' ', $task->task_group ?? 'workflow')) }}</small>
                                    </div>
                                    <span class="status-pill status-pill-{{ $task->status === 'completed' ? 'completed' : 'pending' }}">
                                        {{ ucfirst($task->status) }}
                                    </span>
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Checklist items will appear as the application advances.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="card status-side-card">
                        <div class="card-header section-card-header side-card-header">
                            <h5 class="mb-0">Payments</h5>
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="card-body">
                            <div class="payment-status-row {{ $isCompletedPayment($advancePayment) ? 'completed' : 'pending' }}">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="payment-icon"><i class="bi bi-currency-pound"></i></div>
                                    <div>
                                        <div class="fw-semibold">{{ $hasFullPayment ? 'Application Payment' : '50% Advance Payment' }}</div>
                                        <div class="small text-muted">£{{ number_format($advanceDisplayAmount, 2) }}</div>
                                    </div>
                                    <span class="badge bg-{{ $isCompletedPayment($advancePayment) ? 'success' : (strtolower((string) ($advancePayment->status ?? $advancePaymentStatus)) === 'rejected' ? 'danger' : 'warning text-dark') }}">
                                        {{ $advancePaymentStatus }}
                                    </span>
                                </div>
                                @if ($advancePayment?->paid_at)
                                    <div class="small text-muted mt-2">Paid on {{ $formatDateTime($advancePayment->paid_at, 'd M Y') }}</div>
                                @endif
                                @if ($isCompletedPayment($advancePayment))
                                    <a href="{{ route('payment.invoice', $advancePayment->id) }}" target="_blank" class="payment-invoice-link">
                                        <i class="bi bi-receipt"></i> View Invoice
                                    </a>
                                @endif
                            </div>

                            @unless ($hasFullPayment)
                                <div class="payment-status-row {{ $isCompletedPayment($finalPayment) ? 'completed' : 'pending' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="payment-icon"><i class="bi bi-currency-pound"></i></div>
                                        <div>
                                            <div class="fw-semibold">Next 50% Payment</div>
                                            <div class="small text-muted">£{{ number_format($halfPaymentAmount, 2) }}</div>
                                        </div>
                                        <span class="badge bg-{{ $isCompletedPayment($finalPayment) ? 'success' : (strtolower((string) ($finalPayment->status ?? $finalPaymentStatus)) === 'rejected' ? 'danger' : 'warning text-dark') }}">
                                            {{ $finalPaymentStatus }}
                                        </span>
                                    </div>
                                    @if ($finalPayment?->paid_at)
                                        <div class="small text-muted mt-2">Paid on {{ $formatDateTime($finalPayment->paid_at, 'd M Y') }}</div>
                                    @endif
                                    @if ($isCompletedPayment($finalPayment))
                                        <a href="{{ route('payment.invoice', $finalPayment->id) }}" target="_blank" class="payment-invoice-link">
                                            <i class="bi bi-receipt"></i> View Invoice
                                        </a>
                                    @endif
                                </div>
                            @endunless

                            @include('partials.government-fee-notice')

                            @if ($currentStatus === $workflow::DRAFT && !$isCompletedPayment($advancePayment))
                                <a href="{{ route('payment.show', $application->id) }}" class="btn btn-primary w-100 mt-2">Pay 50% Advance</a>
                            @elseif (!$hasFullPayment && $currentStatus === $workflow::PAYMENT_PENDING_FINAL && !$isCompletedPayment($finalPayment))
                                <a href="{{ route('payment.show', $application->id) }}" class="btn btn-primary w-100 mt-2">Complete Final Payment</a>
                            @endif
                        </div>
                    </div>

                    @if ($postFilingHasOppositionHistory)
                        @php
                            $oppositionReceivedOn = data_get($acceptedAdvertisedStageMeta, 'opposition_received_on');
                            $counterStatementDueOn = data_get($acceptedAdvertisedStageMeta, 'counter_statement_due_on');
                            $opposedAdminNote = data_get($acceptedAdvertisedStageMeta, 'admin_note');
                            $oppositionAlertClass = match ($oppositionJourneyOutcome) {
                                'victory' => 'alert-success',
                                'closed' => 'alert-secondary',
                                default => 'alert-danger',
                            };
                        @endphp
                        <div class="card status-side-card mt-4">
                            <div class="card-header section-card-header side-card-header">
                                <h5 class="mb-0">Opposition Details</h5>
                                <i class="bi bi-shield-exclamation"></i>
                            </div>
                            <div class="card-body">
                                <div class="alert {{ $oppositionAlertClass }} mb-3" data-opposition-outcome="{{ $oppositionJourneyOutcome }}">
                                    <strong>{{ $oppositionJourneyOutcomeLabel }}</strong>
                                    <div class="mt-2"><strong>Trademark:</strong> {{ $application->brand_name ?: 'N/A' }}</div>
                                    <div><strong>Application No.:</strong> {{ $application->application_number ?: 'Awaiting assignment' }}</div>
                                    <div><strong>Opposition received on:</strong> {{ $oppositionReceivedOn ? $formatDateTime($oppositionReceivedOn, 'd M Y') : 'N/A' }}</div>
                                    <div><strong>Counter Statement Due:</strong> {{ $counterStatementDueOn ? $formatDateTime($counterStatementDueOn, 'd M Y') : 'N/A' }}</div>
                                    @if($finalOppositionResultCopy)
                                        <div class="mt-2">{{ $finalOppositionResultCopy }}</div>
                                    @endif
                                </div>

                                @if (filled($opposedAdminNote))
                                    <div class="strategy-admin-note mb-3">
                                        <i class="bi bi-info-circle-fill"></i>
                                        <div>
                                            <strong>Admin Note:</strong>
                                            <span>{!! nl2br(e($opposedAdminNote)) !!}</span>
                                        </div>
                                    </div>
                                @endif

                                @if ($oppositionDefenceResultReady && $oppositionDefenceOutcomeLabel)
                                    <div class="alert {{ $oppositionDefenceSucceeded ? 'alert-success' : 'alert-warning' }} mb-3">
                                        <strong>Opposition Defence Result:</strong> {{ $oppositionDefenceOutcomeLabel }}
                                        @if ($oppositionDefenceSucceeded)
                                            <div class="mt-1">Your defence case has succeeded for this trademark filing.</div>
                                        @else
                                            <div class="mt-1">Your defence case did not succeed. Please review the linked case for the latest documents and updates.</div>
                                        @endif
                                    </div>
                                @endif

                                @if ($acceptedAdvertisedAdminDocuments->isNotEmpty())
                                    <div class="post-filing-opposed-documents mb-3">
                                        <div class="post-filing-opposed-documents-title">
                                            <span><i class="bi bi-folder2-open"></i></span>
                                            <div>
                                                <strong>Documents Sent By Admin</strong>
                                                <small>{{ $acceptedAdvertisedAdminDocuments->count() }} {{ \Illuminate\Support\Str::plural('document', $acceptedAdvertisedAdminDocuments->count()) }}</small>
                                            </div>
                                        </div>
                                        @foreach ($acceptedAdvertisedAdminDocuments as $document)
                                            <div class="post-filing-opposed-document-row">
                                                <div class="post-filing-opposed-document-icon">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div class="post-filing-opposed-document-info">
                                                    <strong title="{{ $document->file_name }}">{{ $document->file_name }}</strong>
                                                    <small>
                                                        Sent {{ $document->created_at ? $formatDateTime($document->created_at) : '-' }}
                                                    </small>
                                                </div>
                                                <div class="post-filing-opposed-document-actions">
                                                    <a href="{{ route($documentViewRoute, $document->id) }}" target="_blank" rel="noopener">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="{{ route($documentDownloadRoute, $document->id) }}">
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($oppositionDefenceCase)
                                    <a href="{{ route('trademark-opposition.show', $oppositionDefenceCase) }}" class="btn btn-primary w-100">
                                        <i class="bi bi-shield-check"></i> View Opposition Defence Case
                                    </a>
                                @elseif ($oppositionJourneyOutcome === 'opposed')
                                    <a href="{{ $oppositionDefenceCreateUrl }}" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Create Opposition Defence Case
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endunless
        </div>
    </div>

    @if ($engagementLetter || $engagementLetterSigned || $otherOnboardingDocuments->isNotEmpty())
    <div class="modal fade onboarding-documents-modal" id="onboardingDocumentsModal" tabindex="-1" aria-labelledby="onboardingDocumentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="onboardingDocumentsModalLabel">Onboarding Package Documents</h5>
                        <p class="mb-0">View admin-sent documents and verified signed onboarding documents.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="onboarding-documents-switch" role="tablist" aria-label="Onboarding document groups">
                        <button class="active" id="admin-onboarding-documents-tab" data-bs-toggle="pill" data-bs-target="#admin-onboarding-documents-panel" type="button" role="tab" aria-controls="admin-onboarding-documents-panel" aria-selected="true">
                            Admin Sent Documents
                        </button>
                        <button id="signed-onboarding-documents-tab" data-bs-toggle="pill" data-bs-target="#signed-onboarding-documents-panel" type="button" role="tab" aria-controls="signed-onboarding-documents-panel" aria-selected="false">
                            Signed Onboarding Documents
                        </button>
                    </div>

                    <div class="tab-content onboarding-documents-tab-content">
                        <div class="tab-pane fade show active" id="admin-onboarding-documents-panel" role="tabpanel" aria-labelledby="admin-onboarding-documents-tab" tabindex="0">
                            <div class="onboarding-section-copy onboarding-section-title-row mb-3">
                                <span><i class="bi bi-file-earmark-text-fill"></i></span>
                                <div>
                                    <h5>Documents from Admin</h5>
                                    <p>These documents are sent by admin for your review.</p>
                                </div>
                            </div>
                            <div class="onboarding-documents-card onboarding-documents-card-modal">
                                <div class="onboarding-document-tile">
                                    <div class="document-tile-icon blue"><i class="bi bi-file-earmark-text"></i></div>
                                    <div>
                                        <h6>Engagement Letter</h6>
                                        <p>PDF Document</p>
                                        <div class="document-tile-actions">
                                            @if ($engagementLetter)
                                                <a href="{{ route($documentViewRoute, $engagementLetter->id) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a href="{{ route($documentDownloadRoute, $engagementLetter->id) }}"><i class="bi bi-download"></i> Download</a>
                                            @else
                                                <span>Awaiting issue</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @foreach ($otherOnboardingDocuments as $otherOnboardingDocument)
                                    <div class="onboarding-document-tile">
                                        <div class="document-tile-icon orange"><i class="bi bi-folder2-open"></i></div>
                                        <div>
                                            <h6>{{ $otherOnboardingDocuments->count() > 1 ? 'Other Document ' . $loop->iteration : 'Other Document' }}</h6>
                                            <p>{{ $otherOnboardingDocument->file_name ?: strtoupper($otherOnboardingDocument->file_type ?: 'Document') }}</p>
                                            <div class="document-tile-actions">
                                                <a href="{{ route($documentViewRoute, $otherOnboardingDocument->id) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                <a href="{{ route($documentDownloadRoute, $otherOnboardingDocument->id) }}"><i class="bi bi-download"></i> Download</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="signed-onboarding-documents-panel" role="tabpanel" aria-labelledby="signed-onboarding-documents-tab" tabindex="0">
                            <div class="onboarding-section-copy onboarding-section-title-row mb-3">
                                <span><i class="bi bi-check2-circle"></i></span>
                                <div>
                                    <h5>Signed Onboarding Documents</h5>
                                    <p>Verified signed documents appear here after admin approval.</p>
                                </div>
                            </div>

                            @if ($verifiedSignedOnboardingDocuments->isNotEmpty())
                                <div class="onboarding-documents-card onboarding-documents-card-modal">
                                    @foreach ($verifiedSignedOnboardingDocuments as $signedDocument)
                                        <div class="onboarding-document-tile">
                                            <div class="document-tile-icon {{ $documentTypeClass($signedDocument->document_type) }}"><i class="bi bi-file-earmark-check"></i></div>
                                            <div>
                                                <h6>{{ $documentLabel($signedDocument->document_type) }}</h6>
                                                <p>Verified Signed Document</p>
                                                <div class="document-tile-actions">
                                                    <a href="{{ route($documentViewRoute, $signedDocument->id) }}" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                    <a href="{{ route($documentDownloadRoute, $signedDocument->id) }}"><i class="bi bi-download"></i> Download</a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="stage-empty-state">
                                    <i class="bi bi-file-earmark-lock"></i>
                                    <strong>No verified signed onboarding documents yet.</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade stage-common-modal" id="applicationDetailsModal" tabindex="-1" aria-labelledby="applicationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="stage-section-badge stage-section-badge-blue">Application #{{ $application->id }}</span>
                        <h5 class="modal-title" id="applicationDetailsModalLabel">Application Details & Documents</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-pills stage-modal-tabs" id="applicationDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="application-data-tab" data-bs-toggle="pill" data-bs-target="#application-data-panel" type="button" role="tab" aria-controls="application-data-panel" aria-selected="true">Application Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="application-documents-tab" data-bs-toggle="pill" data-bs-target="#application-documents-panel" type="button" role="tab" aria-controls="application-documents-panel" aria-selected="false">Documents</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="application-data-panel" role="tabpanel" aria-labelledby="application-data-tab" tabindex="0">
                            <div class="stage-summary-grid mb-3">
                                <div><span>Applicant</span><strong>{{ $application->applicant_name ?? 'N/A' }}</strong><em class="summary-mini-badge">Client</em></div>
                                <div><span>Trademark</span><strong>{{ $application->brand_name ?? 'N/A' }}</strong><em class="summary-mini-badge summary-mini-badge-teal">Mark</em></div>
                                <div><span>Email</span><strong>{{ $application->email ?? $client->email }}</strong></div>
                                <div><span>Phone</span><strong>{{ $application->phone ?? 'N/A' }}</strong></div>
                                <div><span>Applicant Type</span><strong>{{ ucwords(str_replace('_', ' ', data_get($details, 'applicant_details.applicant_type', $application->entity_type ?? 'N/A'))) }}</strong><em class="summary-mini-badge summary-mini-badge-indigo">Type</em></div>
                                <div><span>Submitted</span><strong>{{ $formatDateTime($application->created_at, 'd M Y') }}</strong></div>
                            </div>

                            <div class="accordion stage-accordion" id="stageDataAccordion">
                                @foreach ($submittedSections as $title => $sectionData)
                                    @php
                                        $hasSectionData = !empty(array_filter($sectionData, fn ($value) => $value !== null && $value !== '' && $value !== []));
                                        $sectionId = 'status-stage-section-' . $loop->index;
                                    @endphp
                                    @if ($hasSectionData)
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="{{ $sectionId }}-heading">
                                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $sectionId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $sectionId }}">
                                                    <span class="accordion-title-dot"></span>
                                                    {{ $title }}
                                                </button>
                                            </h2>
                                            <div id="{{ $sectionId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="{{ $sectionId }}-heading" data-bs-parent="#stageDataAccordion">
                                                <div class="accordion-body">
                                                    <div class="stage-field-grid">
                                                        @foreach ($sectionData as $field => $value)
                                                            <div class="stage-field">
                                                                <span>{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                                                @if (in_array($field, ['image_of_trademark', 'logo_mark_file'], true) && $value)
                                                                    <a href="{{ route($trademarkImageViewRoute, ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank">View Trademark Image</a>
                                                                @elseif (in_array($field, ['proof_of_use_of_trademark', 'supporting_evidence', 'priority_document'], true) && $value)
                                                                    <a href="{{ route($trademarkProofOfUseViewRoute, ['id' => $application->id, 'file' => base64_encode((string) $value)]) }}" target="_blank">View Supporting Document</a>
                                                                @else
                                                                    <strong>{{ $formatSubmittedValue($value) }}</strong>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="tab-pane fade" id="application-documents-panel" role="tabpanel" aria-labelledby="application-documents-tab" tabindex="0">
                            @forelse ($visibleDocuments as $doc)
                                @php
                                    $isAdminSentOnboardingDocument = $isAdminOnboardingDocument($doc);
                                    $displayDocumentStatus = $isAdminSentOnboardingDocument
                                        ? 'Admin Sent'
                                        : ucwords(str_replace('_', ' ', $doc->status));
                                    $displayDocumentStatusClass = $isAdminSentOnboardingDocument
                                        ? 'info'
                                        : $statusBadgeClass($doc->status);
                                @endphp
                                <div class="stage-document-row">
                                    <div class="stage-document-main">
                                        <span class="stage-document-type stage-document-type-{{ $documentTypeClass($doc->document_type) }}">{{ $documentLabel($doc->document_type) }}</span>
                                        <strong>{{ $documentLabel($doc->document_type) }}</strong>
                                        <small>{{ $doc->file_name }}</small>
                                        @if ($doc->verification_notes && !$isGenericAdminOnboardingNote($doc->verification_notes))
                                            <small>{{ $doc->verification_notes }}</small>
                                        @endif
                                    </div>
                                    <span class="stage-pill stage-pill-{{ $displayDocumentStatusClass }}">{{ $displayDocumentStatus }}</span>
                                    <div class="stage-document-actions">
                                        <a href="{{ route($documentViewRoute, $doc->id) }}" target="_blank">View</a>
                                        <a href="{{ route($documentDownloadRoute, $doc->id) }}">Download</a>
                                    </div>
                                </div>
                            @empty
                                <div class="stage-empty-state">
                                    <i class="bi bi-folder2-open"></i>
                                    <strong>No documents are available yet.</strong>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($isAdminPreview)
        <style>
            .admin-client-preview-disabled {
                cursor: not-allowed !important;
                filter: grayscale(.45);
                opacity: .58;
            }

            .admin-client-preview .status-page input:disabled,
            .admin-client-preview .status-page textarea:disabled,
            .admin-client-preview .status-page select:disabled,
            .admin-client-preview .status-page button:disabled {
                cursor: not-allowed;
            }
        </style>
        <script>
            (() => {
                const root = document.querySelector('.status-page');
                if (!root) return;

                root.querySelectorAll('form').forEach((form) => {
                    form.addEventListener('submit', (event) => event.preventDefault(), true);
                    form.querySelectorAll('input, textarea, select, button').forEach((control) => {
                        control.disabled = true;
                        control.setAttribute('aria-disabled', 'true');
                    });
                });

                root.querySelectorAll('button').forEach((button) => {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                });

                root.querySelectorAll('a').forEach((link) => {
                    const target = new URL(link.href, window.location.origin);
                    const isSafePreviewLink = link.hasAttribute('data-admin-preview-link')
                        || target.pathname.startsWith('/admin/documents/')
                        || target.pathname.startsWith('/admin/trademark/')
                        || (target.pathname === window.location.pathname && target.hash);

                    if (isSafePreviewLink) return;

                    link.classList.add('admin-client-preview-disabled');
                    link.setAttribute('aria-disabled', 'true');
                    link.removeAttribute('target');
                    link.addEventListener('click', (event) => event.preventDefault());
                });
            })();
        </script>
    @endif

    <script>
        function toggleApprovalSignatureMode(mode) {
            const digital = document.getElementById('digital-signature-field');
            const image = document.getElementById('image-signature-field');
            if (!digital || !image) {
                return;
            }
            if (mode === 'image') {
                image.classList.remove('d-none');
                digital.classList.add('d-none');
            } else {
                digital.classList.remove('d-none');
                image.classList.add('d-none');
            }
        }

        const onboardingPackageForm = document.getElementById('onboardingPackageForm');
        const onboardingSuccessToast = document.getElementById('onboardingSuccessToast');

        document.querySelectorAll('.physical-upload-input').forEach((input) => {
            input.addEventListener('change', async () => {
                const uploadBox = input.closest('.physical-upload-box');
                const label = uploadBox?.querySelector('[data-upload-file-name]');
                const helper = uploadBox?.querySelector('small');
                const icon = uploadBox?.querySelector('.physical-upload-icon i');
                const signedActions = input.closest('.signed-document-actions');
                const signedStatus = signedActions?.querySelector('[data-signed-document-status]');
                const signedViewLink = signedActions?.querySelector('[data-signed-view-link]');
                const signedDownloadLink = signedActions?.querySelector('[data-signed-download-link]');
                const reuploadAction = signedActions?.querySelector(`label[for="${input.id}"] [data-reupload-action-label]`);
                const action = uploadBox?.querySelector('.physical-upload-choose') || reuploadAction;
                const hasFile = Boolean(input.files?.length);
                uploadBox?.classList.toggle('has-file', Boolean(input.files?.length));
                if (label) {
                    label.textContent = input.files?.[0]?.name || 'Choose file';
                }
                if (helper) {
                    helper.textContent = hasFile ? 'Signed document is ready to submit.' : 'PDF, JPG, PNG, DOC, DOCX';
                }
                if (icon) {
                    icon.className = hasFile ? 'bi bi-check-circle-fill' : 'bi bi-cloud-arrow-up';
                }
                if (action) {
                    action.innerHTML = signedActions
                        ? '<i class="bi bi-arrow-repeat"></i> Re-upload'
                        : (hasFile ? 'Change File' : 'Choose File');
                }
                if (signedStatus && hasFile) {
                    signedStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> Saving signed document...';
                }

                const draftUploadUrl = input.dataset.draftUploadUrl;
                const selectedFile = input.files?.[0];

                if (!draftUploadUrl || !selectedFile) {
                    return;
                }

                if (helper) {
                    helper.textContent = 'Saving signed document...';
                }

                if (action) {
                    action.textContent = 'Saving...';
                }

                const formData = new FormData();
                formData.append(input.name, selectedFile);

                try {
                    const response = await fetch(draftUploadUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Unable to save this signed document.');
                    }

                    uploadBox?.classList.add('has-file');
                    if (signedViewLink && payload.view_url) {
                        signedViewLink.href = payload.view_url;
                    }
                    if (signedDownloadLink && payload.download_url) {
                        signedDownloadLink.href = payload.download_url;
                    }
                    if (signedStatus) {
                        signedStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> Signed document has been attached.';
                    }
                    if (helper) {
                        helper.textContent = 'Signed document saved. Ready to submit.';
                    }
                    if (action) {
                        action.innerHTML = signedActions ? '<i class="bi bi-arrow-repeat"></i> Re-upload' : 'Change File';
                    }
                    window.LegalBruzUpdateOnboardingSubmitState?.();
                } catch (error) {
                    input.value = '';
                    uploadBox?.classList.remove('has-file');
                    if (label) {
                        label.textContent = 'Choose file';
                    }
                    if (helper) {
                        helper.textContent = error.message || 'Could not save the signed document. Please try again.';
                    }
                    if (icon) {
                        icon.className = 'bi bi-cloud-arrow-up';
                    }
                    if (action) {
                        action.innerHTML = signedActions ? '<i class="bi bi-arrow-repeat"></i> Re-upload' : 'Choose File';
                    }
                    if (signedStatus) {
                        signedStatus.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Could not save the signed document. Please try again.';
                    }
                    window.LegalBruzUpdateOnboardingSubmitState?.();
                }
            });
        });

        if (onboardingPackageForm) {
            onboardingPackageForm.addEventListener('submit', function() {
                const submitBtn = document.getElementById('onboardingPackageSubmitBtn');
                if (submitBtn) {
                    window.LegalBruzButtonLoading?.set(submitBtn, 'Submitting...');
                }
            });
        }

        window.LegalBruzUpdateOnboardingSubmitState = () => {
            const form = document.getElementById('onboardingPackageForm');
            const submitButton = document.getElementById('onboardingPackageSubmitBtn');

            if (!form || !submitButton) {
                return;
            }

            const requiredPads = form.querySelectorAll('[data-signature-pad][data-signature-form-id="onboardingPackageForm"]');
            const requiredFileInputs = form.querySelectorAll('input[type="file"][required]');
            const allPadsApplied = Array.from(requiredPads).every((requiredPad) => requiredPad.dataset.signatureApplied === 'true');
            const allFilesAttached = Array.from(requiredFileInputs).every((input) => input.files && input.files.length > 0);

            submitButton.disabled = !(allPadsApplied && allFilesAttached);
        };

        onboardingPackageForm?.querySelectorAll('input[type="file"][required]').forEach((input) => {
            input.addEventListener('change', window.LegalBruzUpdateOnboardingSubmitState);
        });

        window.LegalBruzUpdateOnboardingSubmitState();

        if (onboardingSuccessToast) {
            onboardingSuccessToast.classList.add('show');
            window.setTimeout(() => onboardingSuccessToast.classList.remove('show'), 4500);
        }

        if (window.bootstrap?.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((trigger) => {
                new bootstrap.Tooltip(trigger, { container: 'body' });
            });
        }

        document.querySelectorAll('.signature-document-modal, #applicationDetailsModal, .draft-review-modal, #onboardingDocumentsModal, .post-filing-documents-modal').forEach((modal) => {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        const pdfPreviewUrl = (url, cacheBust = null) => {
            if (!url) {
                return url;
            }

            const [baseWithQuery, hash = ''] = url.split('#');
            const separator = baseWithQuery.includes('?') ? '&' : '?';
            const query = cacheBust ? `${separator}preview=${cacheBust}` : '';
            const viewerOptions = hash || 'toolbar=0&navpanes=0&scrollbar=1&view=FitH';

            return `${baseWithQuery}${query}#${viewerOptions}`;
        };

        document.querySelectorAll('.signature-document-modal').forEach((modal) => {
            const frame = modal.querySelector('[data-signature-doc-frame]');
            const loading = modal.querySelector('[data-signature-doc-loading]');
            const nameLabel = modal.querySelector('[data-signature-doc-name]');
            const openLink = modal.querySelector('[data-signature-doc-open-link]');
            let loadedOnce = false;
            let openReloadAttempted = false;

            if (!frame) {
                return;
            }

            const defaultFrameSource = frame.dataset.originalSrc || frame.dataset.src || '';
            const defaultName = nameLabel?.textContent || '';
            const defaultOpenHref = openLink?.getAttribute('href') || '';

            const setPreviewSource = (url = '', name = '', openUrl = '', force = false) => {
                const nextSource = url || defaultFrameSource;

                if (!nextSource) {
                    return;
                }

                if (nameLabel) {
                    nameLabel.textContent = name || defaultName;
                }

                if (openLink) {
                    openLink.href = openUrl || url || defaultOpenHref;
                }

                if (!force && frame.dataset.activeSrc === nextSource && frame.getAttribute('src')) {
                    return;
                }

                frame.dataset.activeSrc = nextSource;
                loading?.classList.remove('d-none');
                frame.classList.remove('is-visible', 'is-loaded');
                frame.src = pdfPreviewUrl(nextSource, Date.now());
            };

            setPreviewSource(defaultFrameSource, defaultName, defaultOpenHref);

            modal.addEventListener('show.bs.modal', (event) => {
                document.body.classList.add('signature-modal-open');
                loadedOnce = false;
                openReloadAttempted = false;

                const trigger = event.relatedTarget;
                const previewUrl = trigger?.dataset.previewDocumentUrl || defaultFrameSource;
                const previewName = trigger?.dataset.previewDocumentName || defaultName;
                const previewOpenUrl = trigger?.dataset.previewOpenUrl || previewUrl || defaultOpenHref;

                setPreviewSource(previewUrl, previewName, previewOpenUrl, true);
            });

            modal.addEventListener('shown.bs.modal', () => {
                if (!loadedOnce) {
                    window.setTimeout(() => {
                        if (!loadedOnce && !openReloadAttempted) {
                            openReloadAttempted = true;
                            setPreviewSource(frame.dataset.activeSrc || defaultFrameSource, nameLabel?.textContent || defaultName, openLink?.getAttribute('href') || defaultOpenHref, true);
                        }
                    }, 900);

                    window.setTimeout(() => {
                        if (!loadedOnce) {
                            loading?.classList.add('d-none');
                            frame.classList.add('is-visible');
                        }
                    }, 2500);
                }
            });

            modal.addEventListener('hidden.bs.modal', () => {
                document.body.classList.remove('signature-modal-open');
            });

            frame.addEventListener('load', () => {
                loadedOnce = true;
                openReloadAttempted = false;
                loading?.classList.add('d-none');
                window.requestAnimationFrame(() => {
                    frame.classList.add('is-visible', 'is-loaded');
                });
            });
        });

        document.querySelectorAll('.js-counted-textarea').forEach((textarea) => {
            const counter = textarea.closest('.draft-textarea-wrap')?.querySelector('.draft-counter');
            const max = textarea.getAttribute('maxlength') || 1000;
            const updateCounter = () => {
                if (counter) {
                    counter.textContent = `${textarea.value.length}/${max}`;
                }
            };

            textarea.addEventListener('input', updateCounter);
            updateCounter();
        });

        document.querySelectorAll('[data-signature-pad]').forEach((pad) => {
            const tabs = pad.querySelectorAll('[data-signature-tab]');
            const panels = pad.querySelectorAll('[data-signature-panel]');
            const modeInput = pad.querySelector('[data-signature-mode]');
            const signatureInput = pad.querySelector('[data-signature-value]');
            const imageDataInput = pad.querySelector('[data-signature-image-data]');
            const canvas = pad.querySelector('[data-signature-canvas]');
            const typeInput = pad.querySelector('[data-signature-type-input]');
            const typePreview = pad.querySelector('[data-signature-type-preview]');
            const uploadInput = pad.querySelector('[data-signature-upload]');
            const uploadPreview = pad.querySelector('[data-signature-upload-preview]');
            const uploadPreviewText = uploadPreview?.querySelector('span');
            const uploadPreviewImage = pad.querySelector('[data-signature-upload-image]');
            const cropper = pad.querySelector('[data-signature-cropper]');
            const cropStage = pad.querySelector('[data-signature-crop-stage]');
            const cropImage = pad.querySelector('[data-signature-crop-image]');
            const cropZoom = pad.querySelector('[data-signature-crop-zoom]');
            const cropUseButton = pad.querySelector('[data-signature-crop-use]');
            const agreeInput = pad.querySelector('[data-signature-agree]');
            const applyButton = pad.querySelector('[data-signature-apply]');
            const reapplyButton = pad.querySelector('[data-signature-reapply]');
            const appliedMessage = pad.querySelector('[data-signature-applied]');
            const signUrl = pad.dataset.signUrl;
            const expectedUploadWidth = Number(pad.dataset.signatureImageWidth || 0);
            const expectedUploadHeight = Number(pad.dataset.signatureImageHeight || 0);
            const signedActions = document.querySelector(`[data-signed-actions-for="${pad.id}"]`);
            const signedViewLink = pad.dataset.signedViewTarget ? document.getElementById(pad.dataset.signedViewTarget) : null;
            const signedDownloadLink = pad.dataset.signedDownloadTarget ? document.getElementById(pad.dataset.signedDownloadTarget) : null;
            const signerModal = pad.closest('.signature-document-modal');
            const signerFrame = signerModal?.querySelector('[data-signature-doc-frame]');
            const signerLoading = signerModal?.querySelector('[data-signature-doc-loading]');
            const openSignerButton = document.querySelector(`[data-signature-open-for="${pad.id}"]`);
            const context = canvas.getContext('2d');
            const strokes = [];
            let drawing = false;
            let currentStroke = [];
            let activeMode = 'draw';
            let cropObjectUrl = null;
            let cropState = {
                dragging: false,
                startX: 0,
                startY: 0,
                x: 0,
                y: 0,
                scale: 1,
                baseWidth: 0,
                baseHeight: 0,
            };

            const associatedForm = () => {
                if (pad.dataset.signatureFormId) {
                    return document.getElementById(pad.dataset.signatureFormId);
                }

                return pad.closest('form');
            };

            const previewSignedDocument = (url) => {
                if (!signerFrame || !url) {
                    return;
                }

                signerLoading?.classList.remove('d-none');
                signerFrame.classList.remove('is-visible', 'is-loaded');
                signerFrame.src = pdfPreviewUrl(url, Date.now());
            };

            const updateOnboardingSubmitState = window.LegalBruzUpdateOnboardingSubmitState;

            const clearUploadedSignature = (message = null) => {
                uploadInput.value = '';
                imageDataInput.value = '';
                cropper.hidden = true;
                if (cropObjectUrl) {
                    URL.revokeObjectURL(cropObjectUrl);
                    cropObjectUrl = null;
                }
                if (uploadPreviewText) {
                    uploadPreviewText.textContent = 'No signature image selected';
                }
                if (uploadPreviewImage) {
                    uploadPreviewImage.src = '';
                    uploadPreviewImage.hidden = true;
                }
                if (message) {
                    alert(message);
                }
            };

            const renderCropImage = () => {
                if (!cropImage) {
                    return;
                }

                cropImage.style.width = `${cropState.baseWidth * cropState.scale}px`;
                cropImage.style.height = `${cropState.baseHeight * cropState.scale}px`;
                cropImage.style.transform = `translate(${cropState.x}px, ${cropState.y}px)`;
            };

            const clampCropPosition = () => {
                if (!cropStage) {
                    return;
                }

                const stageWidth = cropStage.clientWidth;
                const stageHeight = cropStage.clientHeight;
                const imageWidth = cropState.baseWidth * cropState.scale;
                const imageHeight = cropState.baseHeight * cropState.scale;

                if (imageWidth <= stageWidth) {
                    cropState.x = (stageWidth - imageWidth) / 2;
                } else {
                    cropState.x = Math.min(0, Math.max(stageWidth - imageWidth, cropState.x));
                }

                if (imageHeight <= stageHeight) {
                    cropState.y = (stageHeight - imageHeight) / 2;
                } else {
                    cropState.y = Math.min(0, Math.max(stageHeight - imageHeight, cropState.y));
                }
            };

            const openSignatureCropper = (file) => new Promise((resolve) => {
                if (!file || !cropper || !cropStage || !cropImage || !expectedUploadWidth || !expectedUploadHeight) {
                    resolve(false);
                    return;
                }

                const image = new Image();
                cropObjectUrl = URL.createObjectURL(file);

                image.onload = () => {
                    cropper.hidden = false;
                    cropStage.style.aspectRatio = `${expectedUploadWidth} / ${expectedUploadHeight}`;
                    cropImage.src = cropObjectUrl;

                    window.requestAnimationFrame(() => {
                        const stageWidth = cropStage.clientWidth;
                        const stageHeight = cropStage.clientHeight;
                        const coverScale = Math.max(stageWidth / image.naturalWidth, stageHeight / image.naturalHeight);
                        cropState.baseWidth = image.naturalWidth * coverScale;
                        cropState.baseHeight = image.naturalHeight * coverScale;
                        cropState.scale = 1;
                        cropState.x = (stageWidth - cropState.baseWidth) / 2;
                        cropState.y = (stageHeight - cropState.baseHeight) / 2;
                        if (cropZoom) {
                            cropZoom.value = '1';
                        }
                        renderCropImage();
                        resolve(true);
                    });
                };

                image.onerror = () => {
                    URL.revokeObjectURL(cropObjectUrl);
                    cropObjectUrl = null;
                    clearUploadedSignature('Please upload a valid PNG or JPG signature image.');
                    resolve(false);
                };

                image.src = cropObjectUrl;
            });

            const useCroppedSignature = () => {
                if (!cropStage || !cropImage || !expectedUploadWidth || !expectedUploadHeight) {
                    return false;
                }

                if (!cropImage.complete || !cropImage.naturalWidth || !cropImage.naturalHeight) {
                    alert('Please wait for the signature image to load before cropping.');
                    return false;
                }

                const stageRect = cropStage.getBoundingClientRect();
                const imageRect = cropImage.getBoundingClientRect();
                const scaleX = cropImage.naturalWidth / imageRect.width;
                const scaleY = cropImage.naturalHeight / imageRect.height;
                const sourceX = Math.max((stageRect.left - imageRect.left) * scaleX, 0);
                const sourceY = Math.max((stageRect.top - imageRect.top) * scaleY, 0);
                const sourceWidth = Math.min(stageRect.width * scaleX, cropImage.naturalWidth - sourceX);
                const sourceHeight = Math.min(stageRect.height * scaleY, cropImage.naturalHeight - sourceY);

                const output = document.createElement('canvas');
                output.width = expectedUploadWidth;
                output.height = expectedUploadHeight;
                const outputContext = output.getContext('2d');
                outputContext.clearRect(0, 0, output.width, output.height);
                outputContext.imageSmoothingEnabled = true;
                outputContext.imageSmoothingQuality = 'high';
                outputContext.drawImage(cropImage, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, output.width, output.height);

                imageDataInput.value = output.toDataURL('image/png');
                if (uploadPreviewText) {
                    uploadPreviewText.textContent = `Cropped signature (${expectedUploadWidth} x ${expectedUploadHeight}px)`;
                }
                if (uploadPreviewImage) {
                    uploadPreviewImage.src = imageDataInput.value;
                    uploadPreviewImage.hidden = false;
                }
                cropper.hidden = true;
                markUnapplied();
                return true;
            };

            const markUnapplied = () => {
                pad.dataset.signatureApplied = 'false';
                pad.classList.remove('signature-is-applied');
                appliedMessage.hidden = true;
                const status = document.querySelector(`[data-signature-status-for="${pad.id}"]`);
                if (status) {
                    status.textContent = 'Not Signed';
                    status.classList.remove('signed', 'text-bg-success');
                    status.classList.add('text-bg-warning');
                }
                signedActions?.classList.add('d-none');
                updateOnboardingSubmitState();
            };

            const redraw = () => {
                context.clearRect(0, 0, canvas.width, canvas.height);
                context.lineWidth = Math.max(canvas.width / 260, 4);
                context.lineCap = 'round';
                context.lineJoin = 'round';
                context.strokeStyle = '#111827';
                strokes.forEach((stroke) => {
                    context.beginPath();
                    stroke.forEach((point, index) => {
                        if (index === 0) {
                            context.moveTo(point.x, point.y);
                        } else {
                            context.lineTo(point.x, point.y);
                        }
                    });
                    context.stroke();
                });
            };

            const signatureCanvasData = () => {
                const image = context.getImageData(0, 0, canvas.width, canvas.height);
                const padding = 12;
                let minX = canvas.width;
                let minY = canvas.height;
                let maxX = 0;
                let maxY = 0;

                for (let y = 0; y < canvas.height; y += 1) {
                    for (let x = 0; x < canvas.width; x += 1) {
                        const alpha = image.data[((y * canvas.width + x) * 4) + 3];

                        if (alpha > 0) {
                            minX = Math.min(minX, x);
                            minY = Math.min(minY, y);
                            maxX = Math.max(maxX, x);
                            maxY = Math.max(maxY, y);
                        }
                    }
                }

                if (minX > maxX || minY > maxY) {
                    return canvas.toDataURL('image/png');
                }

                minX = Math.max(minX - padding, 0);
                minY = Math.max(minY - padding, 0);
                maxX = Math.min(maxX + padding, canvas.width - 1);
                maxY = Math.min(maxY + padding, canvas.height - 1);

                const exportScale = 2;
                const croppedWidth = maxX - minX + 1;
                const croppedHeight = maxY - minY + 1;
                const croppedCanvas = document.createElement('canvas');
                croppedCanvas.width = croppedWidth * exportScale;
                croppedCanvas.height = croppedHeight * exportScale;
                const croppedContext = croppedCanvas.getContext('2d');
                croppedContext.imageSmoothingEnabled = true;
                croppedContext.imageSmoothingQuality = 'high';
                croppedContext.drawImage(canvas, minX, minY, croppedWidth, croppedHeight, 0, 0, croppedCanvas.width, croppedCanvas.height);

                return croppedCanvas.toDataURL('image/png');
            };

            const pointFromEvent = (event) => {
                const rect = canvas.getBoundingClientRect();
                const pointer = event.touches ? event.touches[0] : event;
                return {
                    x: (pointer.clientX - rect.left) * (canvas.width / rect.width),
                    y: (pointer.clientY - rect.top) * (canvas.height / rect.height),
                };
            };

            const startDrawing = (event) => {
                drawing = true;
                currentStroke = [pointFromEvent(event)];
                strokes.push(currentStroke);
                redraw();
                event.preventDefault();
            };

            const draw = (event) => {
                if (!drawing) {
                    return;
                }
                currentStroke.push(pointFromEvent(event));
                redraw();
                event.preventDefault();
            };

            const stopDrawing = () => {
                drawing = false;
            };

            const setActiveMode = (mode) => {
                activeMode = mode;
                modeInput.value = mode;
                if (mode !== 'upload') {
                    imageDataInput.value = '';
                    cropper.hidden = true;
                }
                markUnapplied();

                tabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.signatureTab === mode));
                panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.signaturePanel === mode));
            };

            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            window.addEventListener('touchend', stopDrawing);

            pad.querySelector('[data-signature-clear]').addEventListener('click', () => {
                strokes.length = 0;
                imageDataInput.value = '';
                markUnapplied();
                redraw();
            });

            pad.querySelector('[data-signature-undo]').addEventListener('click', () => {
                strokes.pop();
                imageDataInput.value = '';
                markUnapplied();
                redraw();
            });

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => setActiveMode(tab.dataset.signatureTab));
            });

            typeInput.addEventListener('input', () => {
                typePreview.textContent = typeInput.value || 'Your Signature';
                markUnapplied();
            });

            uploadInput.addEventListener('change', async () => {
                const file = uploadInput.files?.[0];
                imageDataInput.value = '';
                if (!file) {
                    clearUploadedSignature();
                    markUnapplied();
                    return;
                }

                if (uploadPreviewText) {
                    uploadPreviewText.textContent = 'Align and crop the signature image below.';
                }

                if (uploadPreviewImage) {
                    uploadPreviewImage.src = '';
                    uploadPreviewImage.hidden = true;
                }

                await openSignatureCropper(file);
                markUnapplied();
            });

            cropZoom?.addEventListener('input', () => {
                cropState.scale = Number(cropZoom.value || 1);
                clampCropPosition();
                renderCropImage();
            });

            cropStage?.addEventListener('pointerdown', (event) => {
                cropState.dragging = true;
                cropState.startX = event.clientX - cropState.x;
                cropState.startY = event.clientY - cropState.y;
                cropStage.setPointerCapture(event.pointerId);
            });

            cropStage?.addEventListener('pointermove', (event) => {
                if (!cropState.dragging) {
                    return;
                }
                cropState.x = event.clientX - cropState.startX;
                cropState.y = event.clientY - cropState.startY;
                clampCropPosition();
                renderCropImage();
            });

            cropStage?.addEventListener('pointerup', (event) => {
                cropState.dragging = false;
                cropStage.releasePointerCapture(event.pointerId);
            });

            cropUseButton?.addEventListener('click', useCroppedSignature);

            applyButton.addEventListener('click', async () => {
                if (!agreeInput.checked) {
                    alert('Please agree to the electronic signature declaration before applying your signature.');
                    return;
                }

                if (activeMode === 'draw') {
                    if (strokes.length === 0) {
                        alert('Please draw your signature before applying it.');
                        return;
                    }
                    imageDataInput.value = signatureCanvasData();
                    signatureInput.value = typeInput.value.trim() || signatureInput.value || @json($client->name);
                }

                if (activeMode === 'type') {
                    const typedSignature = typeInput.value.trim();
                    if (!typedSignature) {
                        alert('Please type your full legal name before applying your signature.');
                        return;
                    }
                    signatureInput.value = typedSignature;
                    imageDataInput.value = '';
                }

                if (activeMode === 'upload') {
                    if (!imageDataInput.value && cropper && !cropper.hidden) {
                        useCroppedSignature();
                    }
                    if (!imageDataInput.value) {
                        alert(`Please crop your signature image to ${expectedUploadWidth} x ${expectedUploadHeight}px before applying it.`);
                        return;
                    }
                    signatureInput.value = typeInput.value.trim() || signatureInput.value || @json($client->name);
                }

                modeInput.value = activeMode;

                if (signUrl) {
                    const formData = new FormData();
                    const token = associatedForm()?.querySelector('input[name="_token"]')?.value;

                    if (token) {
                        formData.append('_token', token);
                    }

                    formData.append('signature_mode', activeMode);
                    formData.append('digital_signature', signatureInput.value);

                    if (activeMode === 'draw' || activeMode === 'upload') {
                        formData.append('signature_image_data', imageDataInput.value);
                    }

                    if (activeMode === 'upload' && !imageDataInput.value && uploadInput.files?.[0]) {
                        formData.append('signature_image', uploadInput.files[0]);
                    }

                    window.LegalBruzButtonLoading?.set(applyButton, 'Applying...');

                    try {
                        const response = await fetch(signUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload.message || 'Could not apply the signature. Please try again.');
                        }

                        if (signedViewLink && payload.view_url) {
                            signedViewLink.href = payload.view_url;
                        }

                        if (signedDownloadLink && payload.download_url) {
                            signedDownloadLink.href = payload.download_url;
                        }

                        previewSignedDocument(payload.view_url);
                        signedActions?.classList.remove('d-none');
                        openSignerButton?.classList.add('d-none');
                    } catch (error) {
                        alert(error.message || 'Could not apply the signature. Please try again.');
                        window.LegalBruzButtonLoading?.reset(applyButton);
                        return;
                    }

                    window.LegalBruzButtonLoading?.reset(applyButton);
                }

                pad.dataset.signatureApplied = 'true';
                pad.classList.add('signature-is-applied');
                appliedMessage.hidden = false;
                const status = document.querySelector(`[data-signature-status-for="${pad.id}"]`);
                if (status) {
                    status.textContent = 'Signed';
                    status.classList.remove('text-bg-warning');
                    status.classList.add('signed', 'text-bg-success');
                }
                updateOnboardingSubmitState();
            });

            reapplyButton?.addEventListener('click', () => {
                markUnapplied();
            });

            associatedForm()?.addEventListener('submit', (event) => {
                if (pad.dataset.signatureApplied !== 'true') {
                    event.preventDefault();
                    alert('Please apply your electronic signature before submitting.');
                }
            });

            redraw();
            updateOnboardingSubmitState();
        });
    </script>

    @if (session('success') === 'Onboarding package submitted successfully.')
        <div id="onboardingSuccessToast" class="onboarding-toast" role="status" aria-live="polite">
            <div class="onboarding-toast-title">Onboarding Signature Submitted</div>
            <div class="onboarding-toast-copy">Your onboarding signatures have been submitted. Our admin team will review the signed documents and let you know if anything needs attention.</div>
        </div>
    @endif

    <style>
        .section-card-header {
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
            color: #fff;
            border: 0;
            padding: 1rem 1.15rem;
        }

        .section-card-header h1,
        .section-card-header h2,
        .section-card-header h3,
        .section-card-header h4,
        .section-card-header h5,
        .section-card-header h6,
        .section-card-header small,
        .section-card-header span,
        .section-card-header div,
        .section-card-header p {
            color: #fff !important;
        }

        .section-card-header h5 {
            font-size: 1.1rem;
            font-weight: 900;
        }

        main {
            padding: 18px 0;
        }

        .status-page {
            font-size: 0.9rem;
        }

        .status-shell-card,
        .status-side-card,
        #stage-action {
            border: 1px solid #e6ebf2;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(15, 36, 68, 0.07);
            overflow: hidden;
        }

        .status-hero {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.9rem;
            align-items: center;
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, var(--navy) 0%, #2d4a73 100%);
            color: #fff;
        }

        .status-hero h3 {
            font-size: 1.35rem;
        }

        .status-hero h3,
        .status-hero p {
            color: #fff;
            margin: 0;
        }

        .status-hero p {
            margin-top: 0.2rem;
            opacity: 0.92;
            font-weight: 700;
            font-size: 0.88rem;
        }

        .status-hero-link {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
            border: 0;
            background: transparent;
            color: #fff;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.52);
            padding: 0 0 0.06rem;
        }

        .status-hero-link:hover {
            color: #dbeafe;
            border-bottom-color: #dbeafe;
        }

        .status-hero-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.14);
            font-size: 1.25rem;
        }

        .status-hero-badges {
            display: grid;
            justify-items: end;
            gap: 0.45rem;
        }

        .status-hero-badges span,
        .status-hero-badges small {
            display: inline-flex;
            border-radius: 7px;
            padding: 0.25rem 0.55rem;
            color: #0b67df;
            background: #fff;
            font-weight: 800;
            font-size: 0.82rem;
        }

        .status-hero-badges small {
            color: #fff;
            background: rgba(255, 255, 255, 0.18);
        }

        .status-timeline .timeline-item {
            position: relative;
            align-items: center;
            gap: 0.85rem;
            padding: 0.7rem 0;
            margin: 0;
        }

        .status-timeline .timeline-item.post-filing-expanded {
            align-items: flex-start;
            border: 0;
            border-radius: 0;
            padding: 0.85rem 0.9rem 0.85rem 0;
            margin-top: 0.25rem;
            background: #ffffff;
            box-shadow: none;
        }

        .status-timeline .timeline-item.post-filing-expanded > .timeline-icon {
            margin-left: 0;
        }

        .status-timeline .timeline-item.post-filing-expanded::after {
            display: none;
        }

        .status-timeline .timeline-item:not(:last-child)::after {
            content: "";
            position: absolute;
            left: 19px;
            top: 48px;
            bottom: -12px;
            border-left: 2px dashed #d8e1ee;
        }

        .status-timeline .timeline-icon {
            position: relative;
            z-index: 1;
            width: 42px;
            height: 42px;
            font-size: 1rem;
            box-shadow: 0 8px 22px rgba(15, 36, 68, 0.08);
        }

        .timeline-stage-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2.3;
        }

        .timeline-check {
            position: absolute;
            right: -4px;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #13a268;
            color: #fff;
            font-size: 0.72rem;
            border: 2px solid #fff;
        }

        .status-timeline .timeline-content {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.65rem;
            align-items: center;
            width: 100%;
        }

        .status-timeline .timeline-content h5 {
            margin: 0 0 0.16rem;
            color: #14294b;
            font-size: 1rem;
        }

        .timeline-title-row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .timeline-documents-link {
            border: 0;
            background: transparent;
            color: #1464f6;
            padding: 0;
            font-size: 0.84rem;
            font-weight: 900;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .timeline-documents-link:hover {
            color: #0b5ed7;
        }

        .status-timeline .timeline-content p {
            margin: 0;
            color: #4d5b70;
            max-width: 520px;
            font-size: 0.84rem;
            line-height: 1.35;
        }

        .timeline-state {
            display: grid;
            justify-items: end;
            gap: 0.18rem;
            min-width: 128px;
            text-align: right;
        }

        .timeline-state-status {
            display: inline-flex;
            align-items: center;
            gap: 0.42rem;
        }

        .timeline-action-indicator {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1464f6;
            background: #e8f1ff;
            border: 1px solid #a9c8ff;
            text-decoration: none;
            font-size: 0.98rem;
            box-shadow: 0 8px 16px rgba(20, 100, 246, 0.14);
        }

        .timeline-action-indicator:hover,
        .timeline-action-indicator:focus {
            color: #ffffff;
            background: #1464f6;
            border-color: #1464f6;
        }

        .timeline-state small {
            color: #687386;
            font-weight: 600;
            font-size: 0.76rem;
        }

        .timeline-post-filing-tracker {
            grid-column: 1 / -1;
            position: relative;
            display: grid;
            gap: 0;
            margin-top: 0.35rem;
            margin-left: -51px;
            padding: 0.35rem 0 0.35rem 0;
        }

        .timeline-post-filing-tracker::before {
            content: "";
            position: absolute;
            left: 16px;
            top: -58px;
            bottom: 18px;
            border-left: 1px dashed #b8cff6;
        }

        .timeline-post-filing-stage {
            position: relative;
            display: grid;
            grid-template-columns: 86px minmax(0, 1fr) auto;
            gap: 0.75rem;
            align-items: center;
            padding: 0.45rem 0;
        }

        .timeline-post-filing-stage::before {
            content: "";
            position: absolute;
            left: 16px;
            top: 50%;
            width: 54px;
            border-top: 1px dashed #b8cff6;
        }

        .timeline-post-stage-number {
            position: relative;
            z-index: 1;
            justify-self: end;
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #ffffff;
            border: 1px solid #b7cffd;
            color: #1464f6;
            font-size: 0.8rem;
            font-weight: 900;
        }

        .timeline-post-filing-stage.completed .timeline-post-stage-number {
            background: #e7f8ef;
            border-color: #9fe4be;
            color: #15804e;
        }

        .timeline-post-filing-stage.processing .timeline-post-stage-number {
            background: #edf5ff;
            border-color: #9cc2ff;
            color: #0d62d6;
        }

        .timeline-post-filing-stage.opposed .timeline-post-stage-number {
            background: #fff1f2;
            border-color: #f5a3ab;
            color: #c2293d;
        }

        .timeline-post-filing-stage.case-victory .timeline-post-stage-number {
            background: #e7f8ef;
            border-color: #9fe4be;
            color: #15804e;
        }

        .timeline-post-filing-stage.case-lost .timeline-post-stage-number {
            background: #fff1f2;
            border-color: #f5a3ab;
            color: #c2293d;
        }

        .timeline-post-filing-stage.case-closed .timeline-post-stage-number {
            background: #f3f4f6;
            border-color: #d5d9e1;
            color: #556074;
        }

        .timeline-post-filing-stage.opposition_successful .timeline-post-stage-number,
        .timeline-post-filing-stage.application_withdrawn .timeline-post-stage-number {
            background: #fff1f2;
            border-color: #f5a3ab;
            color: #c2293d;
        }

        .timeline-post-filing-stage.defence_successful .timeline-post-stage-number,
        .timeline-post-filing-stage.opposition_withdrawn .timeline-post-stage-number {
            background: #e7f8ef;
            border-color: #9fe4be;
            color: #15804e;
        }

        .timeline-post-filing-stage.settlement_closed .timeline-post-stage-number,
        .timeline-post-filing-stage.other .timeline-post-stage-number {
            background: #f3f4f6;
            border-color: #d5d9e1;
            color: #556074;
        }

        .timeline-post-stage-title-row {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .timeline-post-stage-copy h6 {
            margin: 0;
            color: #14294b;
            font-size: 0.86rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .timeline-post-stage-copy p {
            margin: 0.15rem 0 0;
            color: #4d5b70;
            font-size: 0.8rem;
            line-height: 1.32;
            max-width: none;
        }

        .post-filing-stage-documents-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 0;
            background: transparent;
            color: #1464f6;
            padding: 0;
            font-size: 0.78rem;
            font-weight: 850;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .post-filing-stage-documents-link:hover {
            color: #0b5ed7;
        }

        .post-filing-documents-modal .modal-content {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 24px 70px rgba(15, 35, 70, 0.24);
        }

        .post-filing-documents-modal .modal-header {
            border-bottom: 1px solid #e7edf6;
            padding: 1rem 1.15rem;
        }

        .post-filing-documents-modal .modal-title {
            color: #14294b;
            font-weight: 850;
        }

        .post-filing-document-tabs {
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .post-filing-document-tabs .nav-link {
            border: 1px solid #d8e5f7;
            background: #ffffff;
            color: #2A9D8F;
            font-size: 0.86rem;
            font-weight: 800;
        }

        .post-filing-document-tabs .nav-link.active {
            border-color: #2A9D8F;
            background: #2A9D8F;
            color: #ffffff;
        }

        .post-filing-modal-document-list {
            display: grid;
            gap: 0.75rem;
        }

        .post-filing-modal-document-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e3ebf6;
            border-radius: 10px;
            padding: 0.85rem;
            background: #ffffff;
        }

        .post-filing-modal-document-row strong {
            display: block;
            color: #14294b;
            font-size: 0.92rem;
            word-break: break-word;
        }

        .post-filing-modal-document-row small {
            display: grid;
            gap: 0.2rem;
            margin-top: 0.25rem;
            color: #687386;
            font-size: 0.78rem;
            line-height: 1.35;
        }

        .post-filing-modal-document-row a {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex: 0 0 auto;
            border-radius: 8px;
            padding: 0.45rem 0.7rem;
            background: #edf5ff;
            color: #1464f6;
            font-size: 0.82rem;
            font-weight: 850;
            text-decoration: none;
        }

        .post-filing-modal-document-row a:hover {
            background: #1464f6;
            color: #ffffff;
        }

        .timeline-post-stage-state {
            display: grid;
            justify-items: end;
            gap: 0.15rem;
            min-width: 128px;
            text-align: right;
        }

        .timeline-post-stage-state small {
            color: #687386;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 7px;
            padding: 0.22rem 0.5rem;
            font-size: 0.74rem;
            font-weight: 800;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-pill-completed {
            color: #15804e;
            background: #e7f8ef;
            border-color: #9fe4be;
        }

        .status-pill-active {
            color: #0d62d6;
            background: #edf5ff;
            border-color: #9cc2ff;
        }

        .status-pill-pending {
            color: #815416;
            background: #fff4db;
            border-color: #ffd28a;
        }

        .submitted-data-header,
        .side-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 72px;
        }

        .submitted-data-header {
            padding: 1.15rem 1.35rem;
        }

        .submitted-data-header i,
        .side-card-header i {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.14);
            font-size: 1.35rem;
            opacity: 0.92;
        }

        .submitted-summary-block,
        .submitted-field {
            min-height: 82px;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            background: #f8fbff;
            border: 1px solid #e5edf7;
        }

        .submitted-summary-block span,
        .submitted-field span {
            display: block;
            margin-bottom: 0.35rem;
            color: #657184;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .submitted-summary-block strong,
        .submitted-field strong,
        .submitted-field a {
            color: #183153;
            font-weight: 800;
            word-break: break-word;
        }

        .stage-common-modal {
            z-index: 1085;
        }

        .stage-common-modal + .modal-backdrop {
            z-index: 1080;
        }

        .stage-common-modal .modal-content {
            overflow: hidden;
            border: 1px solid #cfe0f4;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 36, 68, 0.24);
        }

        .stage-common-modal .modal-header {
            align-items: center;
            background: linear-gradient(135deg, #071c3d 0%, #132f59 100%);
            border-bottom: 0;
            color: #fff;
            padding: 1rem 1.35rem 1.15rem;
        }

        .stage-common-modal .modal-title {
            margin: 0;
            color: #fff;
            font-size: 1.12rem;
            font-weight: 900;
        }

        .stage-common-modal .btn-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background-color: rgba(255, 255, 255, 0.14);
            filter: invert(1) grayscale(1) brightness(1.85);
            opacity: 1;
        }

        .stage-common-modal .modal-body {
            padding: 1.35rem;
            min-height: min(72vh, 760px);
        }

        .stage-section-badge,
        .stage-document-type,
        .stage-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            border-radius: 7px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .stage-section-badge {
            margin-bottom: 0.35rem;
            padding: 0.25rem 0.55rem;
            font-size: 0.66rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .stage-section-badge-blue {
            background: #1f67f2;
            color: #fff;
        }

        .stage-modal-tabs {
            gap: 0.85rem;
            border-bottom: 1px solid #e5edf7;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .stage-modal-tabs .nav-link {
            min-width: 142px;
            border: 1px solid #d7e3f2;
            border-radius: 9px;
            background: #ffffff;
            color: #2A9D8F !important;
            padding: 0.72rem 1rem;
            font-size: 0.86rem;
            font-weight: 900;
            box-shadow: none;
        }

        .stage-modal-tabs .nav-link.active {
            border-color: #2A9D8F;
            background: #2A9D8F;
            color: #fff !important;
            box-shadow: 0 10px 22px rgba(42, 157, 143, 0.24);
            transform: translateY(-1px);
        }

        .stage-modal-tabs .nav-link:not(.active) {
            opacity: 1;
        }

        .stage-modal-tabs .nav-link:hover {
            color: #2A9D8F !important;
            background: #eefaf8;
        }

        .stage-modal-tabs .nav-link.active:hover {
            color: #fff !important;
            background: #2A9D8F;
        }

        .stage-summary-grid,
        .stage-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.7rem;
        }

        .stage-summary-grid div,
        .stage-field {
            position: relative;
            border: 1px solid #dfe9f6;
            border-radius: 10px;
            background: linear-gradient(180deg, #fbfdff 0%, #f6faff 100%);
            padding: 0.78rem 0.9rem;
            min-height: 68px;
        }

        .stage-summary-grid span,
        .stage-field span {
            display: block;
            margin-bottom: 0.25rem;
            color: #6b7890;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .stage-summary-grid strong,
        .stage-field strong,
        .stage-field a {
            color: #183153;
            font-size: 0.9rem;
            font-weight: 800;
            word-break: break-word;
        }

        .summary-mini-badge {
            position: absolute;
            top: 0.55rem;
            right: 0.55rem;
            border-radius: 999px;
            background: #dcecff;
            color: #0d55dc;
            padding: 0.18rem 0.42rem;
            font-size: 0.62rem;
            font-style: normal;
            font-weight: 900;
        }

        .summary-mini-badge-teal {
            background: #ccfbf1;
            color: #0f766e;
        }

        .summary-mini-badge-indigo {
            background: #e0e7ff;
            color: #4338ca;
        }

        .stage-accordion .accordion-button {
            gap: 0.6rem;
            padding: 0.82rem 1rem;
            background: #fff;
            color: #183153;
            font-size: 0.92rem;
            font-weight: 900;
        }

        .stage-accordion .accordion-button:not(.collapsed) {
            background: linear-gradient(90deg, #dcedff 0%, #ecfbf8 100%);
            color: #14345b;
            box-shadow: none;
        }

        .accordion-title-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #2a9d8f;
            box-shadow: 0 0 0 4px rgba(42, 157, 143, 0.13);
        }

        .stage-accordion .accordion-body {
            padding: 0.8rem;
        }

        .stage-document-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #edf1f6;
        }

        .stage-document-main {
            min-width: 0;
        }

        .stage-document-row strong,
        .stage-document-row small {
            display: block;
        }

        .stage-document-row strong {
            margin-top: 0.35rem;
            color: #26384f;
            font-size: 0.98rem;
        }

        .stage-document-row small {
            color: #6a778b;
            font-size: 0.78rem;
            word-break: break-word;
        }

        .stage-document-type {
            padding: 0.25rem 0.5rem;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stage-document-type-blue {
            background: #e0f2fe;
            color: #0369a1;
        }

        .stage-document-type-green {
            background: #dcfce7;
            color: #047857;
        }

        .stage-document-type-purple {
            background: #ede9fe;
            color: #6d28d9;
        }

        .stage-document-type-amber {
            background: #fef3c7;
            color: #b45309;
        }

        .stage-document-type-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .stage-pill {
            padding: 0.42rem 0.7rem;
            font-size: 0.78rem;
        }

        .stage-pill-success {
            background: #dcfce7;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .stage-pill-warning {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .stage-pill-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .stage-pill-info {
            background: #dbeafe;
            color: #0d62d6;
            border: 1px solid #bfdbfe;
        }

        .stage-document-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .stage-document-row a {
            border: 1px solid #1262e8;
            border-radius: 8px;
            background: #1f67f2;
            color: #fff;
            padding: 0.36rem 0.65rem;
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(31, 103, 242, 0.18);
        }

        .stage-document-row a:hover {
            background: #0d55dc;
            color: #fff;
        }

        .stage-empty-state {
            min-height: calc(min(72vh, 760px) - 8.5rem);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.65rem;
            color: #4d5b70;
            text-align: center;
            font-size: 1rem;
        }

        .stage-empty-state i {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: #eef5ff;
            color: #2d4a73;
            font-size: 1.65rem;
        }

        .submitted-accordion .accordion-item {
            border-color: #e5edf7;
        }

        .submitted-accordion .accordion-button {
            color: #183153;
            font-weight: 900;
        }

        .submitted-documents {
            border-top: 1px solid #e5edf7;
            padding-top: 1.2rem;
        }

        .submitted-documents-title,
        .submitted-document-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 1rem;
        }

        .submitted-documents-title {
            margin-bottom: 0.7rem;
        }

        .submitted-documents-title h6 {
            margin: 0;
        }

        .submitted-documents-title span {
            color: #657184;
            font-weight: 800;
        }

        .submitted-document-row {
            padding: 0.85rem 0;
            border-bottom: 1px solid #edf1f6;
        }

        .submitted-document-row strong,
        .submitted-document-row small {
            display: block;
        }

        .submitted-document-row small {
            color: #657184;
            margin-top: 0.2rem;
        }

        .submitted-document-actions {
            display: flex;
            gap: 0.45rem;
        }

        .submitted-document-actions a {
            padding: 0.35rem 0.65rem;
            border: 1px solid #cbd7e6;
            border-radius: 7px;
            color: #0d62d6;
            text-decoration: none;
            font-weight: 800;
        }

        .side-list-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 0.7rem;
            align-items: center;
            padding: 0.65rem 0;
        }

        .side-list-item.with-border {
            border-bottom: 1px solid #edf1f6;
        }

        .side-list-icon,
        .payment-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #eef7ff;
            color: #0d62d6;
            font-size: 0.95rem;
        }

        .payment-status-row {
            border: 1px solid #e4ebf4;
            border-radius: 10px;
            padding: 0.7rem;
            margin-bottom: 0.7rem;
        }

        .payment-status-row.completed {
            border-left: 4px solid #20b26b;
        }

        .payment-status-row.pending {
            border-left: 4px solid #f7a90c;
        }

        .payment-status-row.completed .payment-icon {
            background: #e8f8ef;
            color: #159252;
        }

        .payment-status-row.pending .payment-icon {
            background: #fff4db;
            color: #d88900;
        }

        .payment-invoice-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.55rem;
            border: 1px solid #b8d6ff;
            border-radius: 7px;
            background: #f7fbff;
            color: #0d62d6;
            padding: 0.32rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
        }

        .payment-invoice-link:hover {
            color: #084cad;
            background: #edf6ff;
        }

        .timeline-item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1.25rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid #e9ecef;
        }

        .timeline-item:last-child {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef6ff;
            color: #0d6efd;
            flex-shrink: 0;
        }

        .timeline-item.completed .timeline-icon {
            background: #eaf7ee;
            color: #198754;
        }

        .timeline-item.pending .timeline-icon {
            background: #f1f3f5;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .status-hero,
            .status-timeline .timeline-content,
            .submitted-documents-title,
            .submitted-document-row,
            .stage-summary-grid,
            .stage-field-grid,
            .stage-document-row {
                grid-template-columns: 1fr;
            }

            .status-hero-badges {
                justify-items: start;
            }

            .stage-common-modal .modal-body {
                padding: 1rem;
                min-height: 70vh;
            }

            .stage-modal-tabs .nav-link {
                min-width: 0;
            }

            .stage-document-actions {
                width: 100%;
            }

            .side-list-item {
                grid-template-columns: auto 1fr;
            }
        }

        .onboarding-toast {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: min(380px, calc(100vw - 32px));
            background: #16302b;
            color: #fff;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.22);
            z-index: 1080;
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.24s ease, transform 0.24s ease;
        }

        .onboarding-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .onboarding-toast-title {
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .onboarding-toast-copy {
            font-size: 0.95rem;
            line-height: 1.45;
            color: rgba(255, 255, 255, 0.88);
        }

        .draft-review-panel {
            color: #16233b;
        }

        .draft-review-heading,
        .draft-document-head,
        .draft-section-heading {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .draft-review-heading {
            margin-bottom: 1.5rem;
        }

        .draft-review-heading h4,
        .draft-section-heading h5,
        .draft-document-head h5 {
            color: #16233b;
            margin: 0;
            font-weight: 800;
        }

        .draft-review-heading p,
        .draft-section-heading p,
        .draft-document-head p {
            color: #687386;
            margin: 0.2rem 0 0;
        }

        .draft-review-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            font-size: 1.6rem;
        }

        .draft-review-icon-sm {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            font-size: 1.45rem;
        }

        .draft-review-icon-blue {
            background: #eaf2ff;
            color: #1769f5;
        }

        .draft-review-icon-orange {
            background: #fff2df;
            color: #b86a17;
        }

        .draft-review-icon-green {
            background: #edf8ef;
            color: #24734a;
        }

        .draft-review-icon-purple {
            background: #f2ebff;
            color: #7048d6;
        }

        .draft-document-card {
            border: 1px solid #e1e6ef;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 14px rgba(20, 36, 64, 0.08);
            background: #fff;
            margin-bottom: 1.75rem;
        }

        .draft-document-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .draft-action-btn {
            min-width: 180px;
            height: 48px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            font-weight: 800;
            text-decoration: none;
        }

        .draft-action-primary {
            background: #1769f5;
            color: #fff;
            border: 1px solid #1769f5;
        }

        .draft-action-primary:hover {
            color: #fff;
            background: #0f58d6;
        }

        .draft-action-secondary {
            background: #fff;
            color: #15924a;
            border: 1px solid #d8dee8;
        }

        .draft-action-secondary:hover {
            color: #10783d;
            border-color: #b8c5d6;
        }

        .draft-admin-note {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin-top: 1.5rem;
            padding: 0.85rem 1rem;
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
            border-radius: 9px;
            background: #fffbeb;
            color: #78350f;
        }

        .draft-admin-note i {
            color: #d97706;
            margin-top: 0.15rem;
        }

        .draft-admin-note strong {
            color: #92400e;
            font-weight: 900;
        }

        .draft-admin-note p {
            margin: 0.25rem 0 0;
            color: #78350f;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .draft-review-divider {
            height: 1px;
            background: #e6eaf1;
            margin: 1.75rem 0;
        }

        .draft-section-heading h5 span {
            font-weight: 500;
        }

        .draft-textarea-wrap {
            position: relative;
            margin-top: 1.2rem;
        }

        .draft-review-textarea {
            min-height: 116px;
            border: 1px solid #dce2eb;
            border-radius: 8px;
            resize: vertical;
            padding: 1rem 1rem 2rem;
            font-weight: 600;
        }

        .draft-counter {
            position: absolute;
            right: 1rem;
            bottom: 0.7rem;
            color: #6f7b8d;
            font-weight: 700;
        }

        .draft-request-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .draft-request-btn {
            height: 48px;
            padding: 0 1.6rem;
            border-radius: 7px;
            border: 1px solid #8eb8ff;
            background: #fff;
            color: #1769f5;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
        }

        .draft-help-text,
        .draft-confirmation {
            color: #687386;
            margin: 0.8rem 0 0;
        }

        .user-action-note {
            border-left: 3px solid #2a9d8f;
            border-radius: 8px;
            background: #f0fbf8;
            color: #183153;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.4;
            padding: 0.65rem 0.8rem;
        }

        .stage-no-action-notice {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            border: 1px solid #f0b45a;
            border-left: 5px solid #d68600;
            border-radius: 8px;
            background: #fffaf0;
            color: #3b2a10;
            box-shadow: 0 6px 18px rgba(120, 72, 0, 0.08);
        }

        .stage-no-action-notice > span {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #fff0cf;
            color: #b76d00;
            font-size: 1rem;
        }

        .stage-no-action-notice strong {
            display: block;
            margin-bottom: 0.1rem;
            color: #16233b;
            font-size: 0.98rem;
            font-weight: 900;
        }

        .stage-no-action-notice p {
            margin: 0;
            color: #4b5870;
            font-size: 0.9rem;
            line-height: 1.4;
            font-weight: 700;
        }

        .stage-guide-card {
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #ffffff;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .stage-guide-head {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .stage-guide-head > span {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf2ff;
            color: #1769f5;
            font-size: 1rem;
        }

        .stage-guide-head h6 {
            margin: 0;
            color: #16233b;
            font-size: 1rem;
            font-weight: 900;
            line-height: 1.25;
        }

        .stage-guide-head p {
            margin: 0.2rem 0 0;
            color: #536176;
            font-size: 0.9rem;
            line-height: 1.4;
            font-weight: 600;
        }

        .stage-guide-steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            margin: 1rem 0 0;
            padding-left: 0;
            color: #25324a;
            font-size: 0.9rem;
            line-height: 1.35;
            font-weight: 700;
            list-style: none;
            counter-reset: stage-guide-step;
        }

        .stage-guide-steps li {
            display: flex;
            gap: 0.65rem;
            align-items: center;
            padding: 0 1rem;
            border-right: 1px solid #dbe6f3;
            counter-increment: stage-guide-step;
        }

        .stage-guide-steps li:first-child {
            padding-left: 0;
        }

        .stage-guide-steps li:last-child {
            border-right: 0;
            padding-right: 0;
        }

        .stage-guide-steps li::before {
            content: counter(stage-guide-step);
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1464f6;
            color: #fff;
            font-weight: 900;
            font-size: 0.86rem;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .action-empty-state {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border-left: 3px solid #2a9d8f;
            border-radius: 10px;
            background: #f0fbf8;
            padding: 1rem;
        }

        .action-empty-icon {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #0f9461;
            background: #d9f7ed;
            font-size: 1.15rem;
        }

        .action-empty-state strong {
            color: #0f2d64;
            font-size: 0.95rem;
            font-weight: 900;
        }

        .action-empty-state p {
            margin: 0.2rem 0 0;
            color: #183153;
            font-size: 0.86rem;
            font-weight: 800;
        }

        .strategy-active-highlight {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #1464f6;
            border-radius: 10px;
            background: #eff6ff;
            padding: 1rem;
        }

        .strategy-active-highlight > span {
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #1464f6;
            background: #dbeafe;
            font-size: 1.15rem;
        }

        .strategy-active-highlight strong {
            display: block;
            color: #0f2d64;
            font-size: 0.98rem;
            font-weight: 900;
            line-height: 1.3;
        }

        .strategy-active-highlight p {
            margin: 0.25rem 0 0;
            color: #1e3a8a;
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1.45;
        }

        .strategy-admin-note {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
            border-radius: 9px;
            background: #fffbeb;
            color: #78350f;
            padding: 0.85rem 1rem;
        }

        .strategy-admin-note i {
            color: #d97706;
            margin-top: 0.15rem;
        }

        .strategy-admin-note strong {
            color: #92400e;
            font-weight: 900;
        }

        .strategy-admin-note span {
            color: #78350f;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .post-filing-active-highlight {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            border: 1px solid #99f6e4;
            border-left: 4px solid #14b8a6;
            border-radius: 9px;
            background: #f0fdfa;
            color: #0f766e;
            padding: 0.9rem 1rem;
            margin-top: 0.75rem;
        }

        .post-filing-active-highlight i {
            color: #0d9488;
            margin-top: 0.15rem;
        }

        .post-filing-active-highlight strong {
            display: block;
            color: #0f766e;
            font-weight: 900;
            line-height: 1.35;
        }

        .post-filing-active-highlight p {
            margin: 0.25rem 0 0;
            color: #115e59;
            font-weight: 700;
            line-height: 1.45;
        }

        .action-center-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .action-center-title > span {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.14);
            box-shadow: none;
            font-size: 1.25rem;
        }

        .action-center-title p {
            font-weight: 700;
            opacity: 0.92;
        }

        .onboarding-action-shell {
            display: grid;
            gap: 1rem;
        }

        .onboarding-reupload-alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid #f5c85b;
            border-left: 4px solid #f5a400;
            border-radius: 8px;
            background: #fff8e8;
            color: #7a4b00;
            padding: 0.75rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .onboarding-reupload-alert i {
            color: #f5a400;
            font-size: 1.05rem;
        }

        .onboarding-package-title {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.9rem;
            align-items: center;
        }

        .onboarding-package-title > span {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #eaf3ff;
            color: #1464f6;
            font-size: 1.45rem;
        }

        .onboarding-package-title h4,
        .onboarding-section-copy h5 {
            margin: 0;
            color: #0f2d64;
            font-weight: 900;
        }

        .onboarding-package-title h4 {
            font-size: 1.18rem;
        }

        .onboarding-package-title p,
        .onboarding-section-copy p {
            margin: 0.25rem 0 0;
            color: #405476;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .onboarding-package-docs-btn {
            border: 1px solid #9bbcff;
            border-radius: 8px;
            background: #fff;
            color: #1464f6;
            padding: 0.55rem 0.8rem;
            font-size: 0.84rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .onboarding-package-docs-btn:hover {
            background: #f3f8ff;
        }

        .onboarding-documents-modal .modal-content {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 22px 60px rgba(15, 35, 70, 0.25);
        }

        .onboarding-documents-modal .modal-header {
            border-bottom: 1px solid #dfe6f1;
            background: #fff;
            padding: 1rem 1.2rem;
        }

        .onboarding-documents-modal .modal-title {
            color: #0f2d64;
            font-weight: 900;
        }

        .onboarding-documents-modal .modal-header p {
            color: #405476;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .onboarding-documents-modal .modal-body {
            background: #fff;
            padding: 1.2rem;
        }

        .onboarding-documents-switch {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            border: 1px solid #dbe6f3;
            border-radius: 9px;
            background: #f8fbff;
            padding: 0.35rem;
            margin-bottom: 1rem;
        }

        .onboarding-documents-switch button {
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #405476;
            padding: 0.5rem 0.8rem;
            font-size: 0.86rem;
            font-weight: 900;
        }

        .onboarding-documents-switch button.active {
            background: #1464f6;
            color: #fff;
            box-shadow: 0 8px 18px rgba(20, 100, 246, 0.18);
        }

        .onboarding-documents-tab-content {
            min-height: 260px;
        }

        .onboarding-section-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            padding: 1rem;
        }

        .onboarding-section-title-row {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            align-items: center;
        }

        .onboarding-section-title-row > span {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            background: #eaf3ff;
            color: #1464f6;
            font-size: 1.2rem;
        }

        .onboarding-section-heading {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.85rem;
            align-items: center;
            margin-bottom: 0.9rem;
        }

        .onboarding-section-heading > span {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            color: #0f9461;
            font-size: 1.35rem;
        }

        .action-info-card,
        .signature-help-card,
        .action-submit-card {
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            padding: 1rem;
        }

        .action-info-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.9rem;
            border-left: 3px solid #1464f6;
        }

        .action-info-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #fff;
            background: #1464f6;
            font-weight: 900;
        }

        .action-info-card strong {
            color: #0f2d64;
            font-size: 1rem;
        }

        .action-info-card p {
            margin: 0.3rem 0 0;
            color: #1f3558;
            line-height: 1.45;
        }

        .onboarding-documents-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.85rem;
            margin-top: 1rem;
        }

        .onboarding-documents-card-modal {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .onboarding-document-tile {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.85rem;
            align-content: start;
            min-height: 214px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 35, 70, 0.035);
            padding: 1.1rem;
        }

        .onboarding-document-tile:not(:last-child) {
            border-right: 0;
        }

        .document-tile-icon,
        .signature-card-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.55rem;
        }

        .document-tile-icon.blue,
        .signature-card-icon.blue {
            background: #eaf3ff;
            color: #1464f6;
        }

        .document-tile-icon.green,
        .signature-card-icon.green {
            background: #e8f8f0;
            color: #0f9461;
        }

        .document-tile-icon.purple {
            background: #f0e8ff;
            color: #7c3aed;
        }

        .signature-card-icon.purple {
            background: #f0e8ff;
            color: #7c3aed;
        }

        .document-tile-icon.orange,
        .signature-card-icon.orange {
            background: #fff4db;
            color: #f59e0b;
        }

        .onboarding-document-tile h6 {
            margin: 0;
            color: #0f2d64;
            font-weight: 900;
        }

        .onboarding-document-tile p,
        .onboarding-document-tile small {
            margin: 0.25rem 0 0;
            color: #475569;
            font-weight: 700;
        }

        .document-tile-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin-top: 0.8rem;
        }

        .document-tile-actions a,
        .document-tile-actions span {
            border: 1px solid #9bbcff;
            border-radius: 7px;
            color: #1464f6;
            background: #fff;
            padding: 0.35rem 0.7rem;
            font-weight: 900;
            font-size: 0.82rem;
            text-decoration: none;
        }

        .signed-version-actions {
            align-items: center;
            margin-top: 0.55rem;
        }

        .signed-version-actions span {
            border: 0;
            background: transparent;
            color: #0f9461;
            padding: 0;
        }

        .signed-version-actions a {
            border-color: #8ed9b5;
            color: #0f9461;
            background: #f1fff8;
        }

        .signed-documents-ready {
            border: 1px solid #dbeafe;
            border-radius: 10px;
            background: #f8fbff;
            padding: 1rem;
        }

        .signed-documents-ready > div:first-child strong {
            color: #0f2d64;
            font-size: 1rem;
        }

        .signed-documents-ready > div:first-child p {
            color: #475569;
            font-weight: 700;
            margin: 0.25rem 0 0.85rem;
            line-height: 1.45;
        }

        .signed-documents-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
        }

        .signed-document-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.85rem;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            padding: 0.9rem;
        }

        .signed-document-card h6 {
            margin: 0;
            color: #0f2d64;
            font-weight: 900;
        }

        .signed-document-card p {
            margin: 0.2rem 0 0;
            color: #0f9461;
            font-size: 0.8rem;
            font-weight: 900;
        }

        .signed-document-actions {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            flex-wrap: wrap;
            border: 1px solid #c8f1dc;
            border-radius: 8px;
            background: #f4fff9;
            padding: 0.65rem 0.75rem;
            margin-top: 0.75rem;
        }

        .signed-document-actions > span {
            flex: 1 1 260px;
            color: #0f6b45;
            font-size: 0.84rem;
            font-weight: 900;
        }

        .signed-document-action-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.7rem;
            flex: 0 0 auto;
            flex-wrap: nowrap;
        }

        .signed-document-actions a,
        .signed-document-actions button,
        .signed-document-actions label {
            border: 1px solid #8ed9b5;
            border-radius: 7px;
            color: #0f9461;
            background: #fff;
            padding: 0.35rem 0.7rem;
            font-size: 0.82rem;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            margin: 0;
        }

        .signed-document-actions .signed-document-resign {
            border-color: #f5c85b;
            background: #ffc107;
            color: #3d2d00;
        }

        .signed-document-actions .signed-document-resign input {
            display: none;
        }

        .signature-help-card {
            display: flex;
            gap: 0.85rem;
            align-items: center;
            background: #fff8e8;
            border-color: #ffe0a3;
            color: #4a3820;
        }

        .signature-help-card i {
            color: #f5aa13;
            font-size: 1.4rem;
        }

        .onboarding-signature-card {
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            padding: 1.15rem;
            margin-bottom: 0.9rem;
            box-shadow: 0 8px 22px rgba(15, 45, 100, 0.06);
        }

        .onboarding-upload-section .onboarding-signature-card {
            border: 1px solid #e2e8f0;
        }

        .signature-card-heading {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.9rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .engagement-signature-heading {
            grid-template-columns: auto 1fr auto;
        }

        .signature-card-heading h5 {
            margin: 0;
            color: #0f2d64;
            font-size: 1.05rem;
        }

        .signature-card-heading h5 span {
            display: inline-flex;
            margin-left: 0.55rem;
            border-radius: 999px;
            background: #eaf3ff;
            color: #1464f6;
            padding: 0.18rem 0.5rem;
            font-size: 0.72rem;
            vertical-align: middle;
        }

        .signature-card-heading p {
            margin: 0.25rem 0 0;
            color: #64748b;
            font-weight: 700;
        }

        .signature-card-actions {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
            margin: 0.65rem 0 0.8rem 4.25rem;
        }

        .signature-outline-action {
            border: 1px solid #9bbcff;
            border-radius: 7px;
            color: #1464f6;
            background: #fff;
            padding: 0.4rem 0.9rem;
            font-size: 0.84rem;
            font-weight: 900;
            text-decoration: none;
        }

        .signature-outline-action:hover {
            background: #f3f8ff;
            color: #0b5ed7;
        }

        .signature-card-heading > strong,
        .signature-heading-actions > strong {
            border: 1px solid #63c29b;
            border-radius: 7px;
            color: #0f9461;
            background: #f1fff8;
            padding: 0.35rem 0.65rem;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        .signature-heading-actions .badge,
        .engagement-signature-heading .badge {
            border-radius: 999px;
            padding: 0.38rem 0.62rem;
            font-size: 0.76rem;
            font-weight: 800;
            line-height: 1;
            justify-self: end;
        }

        .signature-card-heading > strong.signed,
        .signature-heading-actions > strong.signed {
            color: #fff;
            background: #0f9461;
            border-color: #0f9461;
        }

        .signature-heading-actions > strong.reupload {
            color: #8a4b00;
            background: #fff7e5;
            border-color: #f0b84f;
        }

        .reupload-reason {
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 8px;
            background: #fff1f2;
            color: #7f1d1d;
            font-size: 0.85rem;
            font-weight: 700;
            line-height: 1.4;
            padding: 0.65rem 0.8rem;
            margin-bottom: 0.8rem;
        }

        .reupload-reason strong {
            color: #b91c1c;
        }

        .physical-signature-note {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            border: 1px solid #ffd48a;
            border-left: 4px solid #f5a400;
            border-radius: 9px;
            background: #fff9ec;
            color: #6f4300;
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1.45;
            padding: 0.75rem 0.85rem;
            margin-bottom: 0.9rem;
        }

        .physical-signature-note i {
            color: #d88900;
            margin-top: 0.15rem;
        }

        .physical-upload-footer {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.85rem;
        }

        .physical-upload-box {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.9rem;
            border: 1px dashed #c9dcf6;
            border-radius: 9px;
            background: #fbfdff;
            padding: 0.8rem 0.9rem;
            cursor: pointer;
            margin: 0;
        }

        .physical-upload-box.has-file {
            border: 1px solid #c8f1dc;
            background: #f4fff9;
        }

        .physical-upload-box.has-file .physical-upload-icon {
            background: #dff8eb;
            color: #0f9461;
        }

        .physical-upload-box.has-file .physical-upload-copy strong {
            color: #0f6b45;
        }

        .physical-upload-box.has-file .physical-upload-copy small {
            color: #0f6b45;
        }

        .physical-upload-view {
            min-width: 120px;
            min-height: 38px;
            text-align: center;
            justify-content: center;
        }

        .physical-upload-copy {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }

        .physical-upload-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            background: #eaf3ff;
            color: #1464f6;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .physical-upload-copy strong {
            display: block;
            color: #111827;
            font-size: 0.86rem;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .physical-upload-copy small {
            display: block;
            margin-top: 0.18rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .physical-upload-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .physical-upload-choose {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            min-height: 38px;
            border: 1px solid #9bbcff;
            border-radius: 7px;
            background: #fff;
            color: #1464f6;
            cursor: pointer;
            font-size: 0.84rem;
            font-weight: 900;
            line-height: 1;
            text-decoration: none;
            margin: 0;
        }

        .physical-upload-box.has-file .physical-upload-choose {
            border-color: #8ed9b5;
            color: #0f9461;
            background: #fff;
        }

        .physical-upload-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0.01;
            pointer-events: none;
        }

        .physical-upload-box:focus-within {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.18rem rgba(13, 110, 253, 0.16);
        }

        .signature-heading-actions {
            display: grid;
            gap: 0.45rem;
            justify-items: end;
        }

        .engagement-signature-footer {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.55rem;
            margin-top: 0.35rem;
        }

        .engagement-signature-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .signature-heading-actions .signature-outline-action,
        .signature-heading-actions .signature-modal-trigger,
        .engagement-signature-actions .signature-outline-action,
        .engagement-signature-actions .signature-modal-trigger {
            min-width: 96px;
            text-align: center;
            justify-content: center;
        }

        .signature-modal-trigger {
            border: 1px solid #a9c8ff;
            border-radius: 7px;
            background: #fff;
            color: #1464f6;
            font-size: 0.78rem;
            font-weight: 900;
            padding: 0.35rem 0.65rem;
            white-space: nowrap;
        }

        .signature-card-actions .signature-modal-trigger {
            border-color: #8ed9b5;
            color: #0f9461;
            padding: 0.4rem 0.9rem;
        }

        .signature-modal-trigger-green {
            border-color: #8ed9b5;
            color: #0f9461;
        }

        .signature-document-modal .modal-content {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            max-height: calc(100vh - 1.5rem);
            box-shadow: 0 22px 60px rgba(15, 35, 70, 0.25);
        }

        .signature-document-modal {
            z-index: 30050 !important;
        }

        body.signature-modal-open .modal-backdrop {
            z-index: 30040 !important;
        }

        .signature-document-modal .modal-dialog,
        .signature-document-modal .modal-content {
            pointer-events: auto;
        }

        .signature-document-modal .modal-dialog-centered {
            align-items: flex-start;
            min-height: calc(100% - 1.5rem);
        }

        .signature-document-modal .modal-header {
            background: linear-gradient(135deg, #073b8f 0%, #1464f6 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.9rem 1rem;
        }

        .signature-document-modal .signature-modal-header-green {
            background: linear-gradient(135deg, #065f46 0%, #0f9461 100%);
        }

        .signature-document-modal .signature-modal-header-purple {
            background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%);
        }

        .signature-document-modal .modal-header h5 {
            color: #fff;
            font-weight: 900;
        }

        .signature-document-modal .modal-header p {
            color: rgba(255, 255, 255, 0.86);
            font-size: 0.84rem;
            font-weight: 700;
        }

        .signature-document-modal .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .signature-document-modal .modal-body {
            background: #f5f7fb;
            height: calc(100vh - 210px);
            min-height: 560px;
            overflow: hidden;
            padding: 0.85rem;
        }

        .signature-modal-footer {
            border-top: 1px solid #dfe6f1;
            background: #fff;
            padding: 0.75rem 1rem;
        }

        .signature-modal-footer .btn {
            min-width: 120px;
            border-radius: 7px;
            font-weight: 900;
        }

        .signature-document-modal .modal-dialog {
            margin-top: 0.75rem;
            margin-bottom: 0.75rem;
            max-height: calc(100vh - 1.5rem);
            transform: none !important;
            transition: none !important;
        }

        .signature-modal-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(360px, 0.65fr);
            gap: 1rem;
            height: 100%;
            min-height: 0;
        }

        .signature-document-viewer,
        .signature-modal-panel {
            border: 1px solid #dfe6f1;
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }

        .signature-document-viewer {
            position: relative;
            isolation: isolate;
            display: flex;
            flex-direction: column;
            min-height: 0;
            justify-content: flex-start;
            align-items: stretch;
            padding: 0;
            gap: 0;
        }

        .signature-document-toolbar {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid #e5ebf4;
            min-height: 0;
            padding: 0.1rem 0.7rem 0.35rem;
            margin: 0;
            background: #fff;
        }

        .signature-document-toolbar > div {
            margin: 0;
            min-width: 0;
        }

        .signature-document-toolbar strong {
            display: block;
            color: #0f2d64;
            font-size: 0.9rem;
            font-weight: 900;
            line-height: 1.15;
            margin: 0;
        }

        .signature-document-toolbar span {
            display: block;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            margin-top: 0.05rem;
            word-break: break-word;
            line-height: 1.2;
        }

        .signature-document-toolbar a {
            border: 1px solid #a9c8ff;
            border-radius: 7px;
            color: #1464f6;
            background: #fff;
            padding: 0.28rem 0.55rem;
            font-size: 0.76rem;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }

        .signature-document-viewer iframe {
            width: 100%;
            flex: 1 1 auto;
            min-height: 0;
            border: 0;
            background: #f8fafc;
            display: block;
            opacity: 1;
            visibility: visible;
            transform: translateZ(0);
            backface-visibility: hidden;
        }

        .signature-document-viewer iframe.is-visible,
        .signature-document-viewer iframe.is-loaded {
            opacity: 1;
            visibility: visible;
        }

        .signature-document-loading {
            position: absolute;
            inset: 52px 0 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            color: #536176;
            background: #f8fafc;
            font-weight: 800;
            font-size: 0.86rem;
        }

        .signature-document-empty {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            place-items: center;
            align-content: center;
            gap: 0.35rem;
            padding: 1.5rem;
            color: #64748b;
            text-align: center;
        }

        .signature-document-empty i {
            color: #1464f6;
            font-size: 2rem;
        }

        .signature-document-empty strong {
            color: #0f2d64;
            font-weight: 900;
        }

        .signature-document-empty p {
            margin: 0;
            font-weight: 700;
        }

        .signature-modal-panel {
            padding: 1rem;
            overflow-y: auto;
        }

        .signature-modal-panel-head {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 0.85rem;
        }

        .signature-modal-panel-head > span {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            color: #1464f6;
            background: #eaf3ff;
            font-size: 1.15rem;
        }

        .signature-modal-panel-head-green > span {
            color: #0f9461;
            background: #e8f8f0;
        }

        .signature-modal-panel-head-purple > span {
            color: #7c3aed;
            background: #f0e8ff;
        }

        .signature-modal-panel-head strong {
            color: #0f2d64;
            font-weight: 900;
        }

        .signature-modal-panel-head p {
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.35;
            margin: 0.15rem 0 0;
        }

        .action-submit-card {
            background: #f8fbff;
        }

        #onboardingPackageSubmitBtn {
            border: 0;
            background: linear-gradient(135deg, #0f9461 0%, #0b8b56 100%);
            box-shadow: 0 10px 22px rgba(15, 148, 97, 0.16);
            font-weight: 900;
        }

        #onboardingPackageSubmitBtn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .signature-pad {
            border: 1px solid #dfe5ef;
            border-radius: 8px;
            background: #fff;
            padding: 0.8rem;
            margin-top: 0.75rem;
        }

        .signature-pad.signature-is-applied .signature-tabs,
        .signature-pad.signature-is-applied .signature-panel,
        .signature-pad.signature-is-applied .signature-agreement,
        .signature-pad.signature-is-applied .signature-apply-btn {
            display: none;
        }

        .signature-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid #dfe5ef;
            margin-bottom: 0.75rem;
        }

        .signature-tabs button {
            border: 0;
            background: transparent;
            color: #28364d;
            font-weight: 800;
            padding: 0.45rem 0.4rem;
            border-bottom: 3px solid transparent;
        }

        .signature-tabs button.active {
            color: #1464f6;
            border-bottom-color: #1464f6;
        }

        .signature-pad-green .signature-tabs button.active {
            color: #0f9461;
            border-bottom-color: #0f9461;
        }

        .signature-panel {
            display: none;
        }

        .signature-panel.active {
            display: block;
        }

        .signature-panel p {
            margin: 0 0 0.45rem;
            color: #566176;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .signature-box-size {
            display: inline-flex;
            align-items: center;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            background: #eff6ff;
            color: #1459d9;
            font-size: 0.72rem;
            font-weight: 900;
            line-height: 1.2;
            padding: 0.24rem 0.55rem;
            margin-bottom: 0.55rem;
        }

        .signature-canvas {
            display: block;
            width: 100%;
            height: 150px;
            border: 1px solid #dfe5ef;
            border-radius: 7px 7px 0 0;
            background: #fff;
            cursor: crosshair;
            touch-action: none;
        }

        .signature-tools {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #dfe5ef;
            border-top: 0;
            border-radius: 0 0 7px 7px;
            overflow: hidden;
        }

        .signature-tools button {
            border: 0;
            background: #fff;
            color: #334155;
            font-weight: 800;
            padding: 0.45rem;
        }

        .signature-tools button + button {
            border-left: 1px solid #dfe5ef;
        }

        .signature-type-preview {
            min-height: 92px;
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            margin-top: 0.55rem;
            border: 1px solid #dfe5ef;
            border-radius: 7px;
            color: #111827;
            font-family: inherit;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.35;
            overflow: hidden;
        }

        .signature-upload-preview {
            min-height: 72px;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            align-items: center;
            justify-content: center;
            margin-top: 0.55rem;
            border: 1px dashed #b9c6d8;
            border-radius: 7px;
            color: #566176;
            font-weight: 800;
            font-size: 0.86rem;
            text-align: center;
            padding: 0.8rem;
        }

        .signature-upload-preview img {
            max-width: 100%;
            max-height: 110px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            padding: 0.35rem;
        }

        .signature-cropper {
            display: grid;
            gap: 0.65rem;
            margin-top: 0.65rem;
        }

        .signature-crop-stage {
            position: relative;
            width: 100%;
            min-height: 110px;
            overflow: hidden;
            border: 2px dashed #9bbcff;
            border-radius: 8px;
            background:
                linear-gradient(45deg, #f8fbff 25%, transparent 25%),
                linear-gradient(-45deg, #f8fbff 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f8fbff 75%),
                linear-gradient(-45deg, transparent 75%, #f8fbff 75%);
            background-color: #ffffff;
            background-position: 0 0, 0 8px, 8px -8px, -8px 0;
            background-size: 16px 16px;
            cursor: move;
            touch-action: none;
        }

        .signature-crop-stage::after {
            content: "";
            position: absolute;
            inset: 0;
            border: 2px solid rgba(20, 100, 246, 0.45);
            pointer-events: none;
        }

        .signature-crop-stage img {
            position: absolute;
            top: 0;
            left: 0;
            max-width: none;
            user-select: none;
            will-change: transform, width, height;
        }

        .signature-crop-controls {
            display: grid;
            gap: 0.55rem;
        }

        .signature-crop-controls label {
            display: grid;
            gap: 0.25rem;
            color: #536176;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .signature-crop-controls button {
            border: 1px solid #0f9461;
            border-radius: 7px;
            background: #0f9461;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 900;
            padding: 0.48rem 0.7rem;
        }

        .signature-agreement {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.6rem;
            align-items: start;
            margin: 0.8rem 0;
            color: #27364d;
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .signature-agreement input {
            width: 18px;
            height: 18px;
            margin-top: 0.08rem;
            accent-color: #1464f6;
        }

        .signature-apply-btn {
            width: 100%;
            border: 0;
            border-radius: 7px;
            background: #1464f6;
            color: #fff;
            font-weight: 900;
            padding: 0.65rem 1rem;
        }

        .signature-pad-green .signature-apply-btn {
            background: #0f9461;
        }

        .signature-applied {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: center;
            margin-top: 0.55rem;
            color: #148545;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .signature-applied button {
            border: 1px solid #f5c85b;
            border-radius: 7px;
            background: #ffc107;
            color: #3d2d00;
            font-weight: 900;
            padding: 0.35rem 0.6rem;
        }

        .draft-approve-btn {
            width: 100%;
            height: 58px;
            border: 0;
            border-radius: 7px;
            background: #148545;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            margin-top: 1.75rem;
            box-shadow: 0 12px 22px rgba(20, 133, 69, 0.2);
        }

        .draft-confirmation {
            text-align: center;
            font-size: 0.95rem;
        }

        .draft-compact-panel {
            display: grid;
            gap: 1rem;
        }

        .draft-compact-card {
            border: 1px solid #dde6f2;
            border-radius: 8px;
            padding: 1.25rem;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .draft-compact-main {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .draft-compact-copy h4 {
            margin: 0;
            color: #16233b;
            font-size: 1.25rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .draft-compact-copy p {
            margin: 0.25rem 0 0;
            color: #5f6b80;
            font-size: 0.95rem;
            line-height: 1.35;
        }

        .draft-compact-actions {
            margin-top: 1.15rem;
            gap: 0.75rem;
        }

        .draft-decision-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .draft-decision-btn {
            min-height: 48px;
            border-radius: 7px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            font-size: 0.98rem;
            font-weight: 900;
        }

        .draft-decision-approve {
            background: #148545;
            color: #ffffff;
        }

        .draft-decision-reject {
            background: #ffffff;
            color: #d0442e;
            border-color: #f0c4ba;
        }

        .draft-plain-help {
            border: 1px solid #dce6f2;
            border-radius: 8px;
            background: #f8fbff;
            color: #41506a;
            font-size: 0.9rem;
            line-height: 1.45;
            padding: 0.8rem 0.95rem;
        }

        .draft-compact-alert {
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .draft-action-modal {
            border: 0;
            border-radius: 10px;
            overflow: hidden;
        }

        .draft-action-modal .modal-header,
        .draft-action-modal .modal-footer {
            padding: 1rem 1.25rem;
        }

        .draft-action-modal .modal-body {
            padding: 1.1rem 1.25rem;
        }

        .draft-action-modal .draft-textarea-wrap {
            margin-top: 0;
        }

        .draft-action-modal .draft-approve-btn,
        .draft-action-modal .draft-request-btn {
            width: auto;
            height: 44px;
            margin-top: 0;
            padding: 0 1.2rem;
        }

        .draft-action-modal .draft-confirmation {
            text-align: left;
        }

        .post-filing-panel {
            display: grid;
            gap: 1rem;
        }

        .post-filing-heading {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .post-filing-heading > span {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #eaf2ff;
            color: #1464f6;
            font-size: 1.25rem;
            flex: 0 0 46px;
        }

        .post-filing-heading h4 {
            margin: 0;
            color: #14294b;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .post-filing-heading p,
        .post-filing-footnote {
            margin: 0.15rem 0 0;
            color: #536176;
            font-size: 0.9rem;
            line-height: 1.4;
            font-weight: 600;
        }

        .post-filing-meta {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #f8fbff;
            padding: 0.75rem 0.9rem;
            color: #16233b;
            font-size: 0.9rem;
        }

        .post-filing-steps {
            display: grid;
            gap: 0.8rem;
        }

        .post-filing-step {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.85rem;
            align-items: start;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #ffffff;
            padding: 0.9rem;
        }

        .post-filing-step.active {
            border-color: #9cc2ff;
            box-shadow: 0 10px 24px rgba(20, 100, 246, 0.1);
        }

        .post-filing-step-icon {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: #fff4db;
            color: #b56a00;
            font-size: 1rem;
        }

        .post-filing-step.completed .post-filing-step-icon {
            background: #e7f8ef;
            color: #15804e;
        }

        .post-filing-step.processing .post-filing-step-icon {
            background: #eaf2ff;
            color: #1464f6;
        }

        .post-filing-step-title {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .post-filing-step-title span {
            color: #1464f6;
            font-size: 0.76rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .post-filing-step-title h5 {
            margin: 0;
            color: #14294b;
            font-size: 0.98rem;
            font-weight: 900;
        }

        .post-filing-step-body p {
            margin: 0.25rem 0 0;
            color: #536176;
            font-size: 0.86rem;
            line-height: 1.35;
            font-weight: 600;
        }

        .post-filing-status-badge {
            border-radius: 999px;
            padding: 0.28rem 0.65rem;
            font-size: 0.76rem;
            font-weight: 900;
            white-space: nowrap;
        }

        .post-filing-status-badge.pending {
            background: #fff4db;
            color: #815416;
        }

        .post-filing-status-badge.processing {
            background: #edf5ff;
            color: #0d62d6;
        }

        .post-filing-status-badge.completed {
            background: #e7f8ef;
            color: #15804e;
        }

        .post-filing-status-badge.opposed {
            background: #fff1f2;
            color: #c2293d;
        }

        .post-filing-status-badge.case-victory {
            background: #e7f8ef;
            color: #15804e;
        }

        .post-filing-status-badge.case-lost {
            background: #fff1f2;
            color: #c2293d;
        }

        .post-filing-status-badge.case-closed {
            background: #f3f4f6;
            color: #556074;
        }

        .post-filing-status-badge.opposition_successful,
        .post-filing-status-badge.application_withdrawn {
            background: #fff1f2;
            color: #c2293d;
        }

        .post-filing-status-badge.defence_successful,
        .post-filing-status-badge.opposition_withdrawn {
            background: #e7f8ef;
            color: #15804e;
        }

        .post-filing-status-badge.settlement_closed,
        .post-filing-status-badge.other {
            background: #f3f4f6;
            color: #556074;
        }

        .post-filing-opposed-documents {
            overflow: hidden;
            border: 1px solid #e1e8f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(16, 42, 75, 0.07);
        }

        .post-filing-opposed-documents-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e7edf3;
            background: linear-gradient(135deg, #f5fbfa 0%, #f8fafc 100%);
            color: #123d66;
        }

        .post-filing-opposed-documents-title > span {
            display: grid;
            flex: 0 0 38px;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: #ddf5ef;
            color: #078575;
            font-size: 1rem;
        }

        .post-filing-opposed-documents-title strong,
        .post-filing-opposed-documents-title small {
            display: block;
        }

        .post-filing-opposed-documents-title strong {
            font-size: 0.9rem;
            line-height: 1.25;
        }

        .post-filing-opposed-documents-title small {
            margin-top: 0.15rem;
            color: #6b7c90;
            font-size: 0.72rem;
        }

        .post-filing-opposed-document-row {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            align-items: center;
            gap: 0.7rem 0.8rem;
            min-width: 0;
            padding: 1rem;
        }

        .post-filing-opposed-document-row + .post-filing-opposed-document-row {
            border-top: 1px solid #e7edf3;
        }

        .post-filing-opposed-document-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid #cce9e4;
            border-radius: 12px;
            background: #eefaf7;
            color: #078575;
            font-size: 1.05rem;
        }

        .post-filing-opposed-document-info {
            min-width: 0;
        }

        .post-filing-opposed-document-info strong {
            display: -webkit-box;
            overflow: hidden;
            color: #17385f;
            font-size: 0.86rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .post-filing-opposed-document-row small {
            display: block;
            margin-top: 0.2rem;
            color: #6b7280;
            font-size: 0.73rem;
        }

        .post-filing-opposed-document-actions {
            display: grid;
            grid-column: 1 / -1;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .post-filing-opposed-document-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-width: 0;
            min-height: 38px;
            padding: 0.5rem 0.65rem;
            border: 1px solid #cbdce8;
            border-radius: 10px;
            background: #ffffff;
            font-weight: 800;
            color: #123d66;
            font-size: 0.75rem;
            text-decoration: none;
            white-space: nowrap;
            transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .post-filing-opposed-document-actions a:hover {
            border-color: #078575;
            background: #eefaf7;
            color: #06766a;
            transform: translateY(-1px);
        }

        @media (max-width: 420px) {
            .post-filing-opposed-documents-title,
            .post-filing-opposed-document-row {
                padding: 0.8rem;
            }

            .post-filing-opposed-document-actions {
                grid-template-columns: 1fr;
            }
        }

        .post-filing-request-note {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.65rem;
            border: 1px solid #ffc868;
            border-left: 3px solid #f0a000;
            border-radius: 8px;
            background: #fff8e8;
            color: #6f4300;
            padding: 0.55rem 0.7rem;
            font-size: 0.84rem;
            font-weight: 800;
        }

        .post-filing-request-note.submitted {
            border-color: #9fe4be;
            border-left-color: #13a268;
            background: #f0fbf8;
            color: #0f6b45;
        }

        .post-filing-document-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.65rem;
        }

        .post-filing-document-list a {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border: 1px solid #b7cffd;
            border-radius: 7px;
            padding: 0.32rem 0.55rem;
            color: #1464f6;
            background: #f8fbff;
            font-size: 0.8rem;
            font-weight: 800;
            text-decoration: none;
        }

        .post-filing-upload-form {
            margin-top: 0.8rem;
            border: 1px solid #dbe6f3;
            border-radius: 8px;
            background: #fbfdff;
            padding: 0.85rem;
        }

        @media (max-width: 991.98px) {
            .status-page {
                max-width: 100%;
                padding-left: 0.85rem;
                padding-right: 0.85rem;
            }

            .status-page > .row {
                --bs-gutter-x: 0;
            }

            .status-page .col-lg-8,
            .status-page .col-lg-4,
            .status-page .col-lg-10 {
                width: 100%;
            }

            .status-side-card {
                margin-top: 1rem;
            }

            .status-hero {
                grid-template-columns: auto 1fr;
                align-items: start;
            }

            .status-hero-badges {
                grid-column: 1 / -1;
                justify-items: start;
                display: flex;
                flex-wrap: wrap;
            }

            .status-timeline .timeline-content {
                grid-template-columns: minmax(0, 1fr);
                gap: 0.5rem;
            }

            .timeline-state {
                justify-items: start;
                min-width: 0;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 0.65rem;
                flex-wrap: wrap;
            }

            .timeline-post-filing-tracker {
                margin-left: 0;
                padding-left: 0;
            }

            .timeline-post-filing-tracker::before,
            .timeline-post-filing-stage::before {
                display: none;
            }

            .timeline-post-filing-stage {
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
                gap: 0.65rem;
                border: 1px solid #e5edf7;
                border-radius: 10px;
                padding: 0.75rem;
                margin-top: 0.6rem;
                background: #fbfdff;
            }

            .timeline-post-stage-number {
                justify-self: start;
            }

            .timeline-post-stage-state {
                grid-column: 2;
                justify-items: start;
                min-width: 0;
                text-align: left;
                display: flex;
                gap: 0.55rem;
                align-items: center;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            main {
                padding: 10px 0;
            }

            .status-page {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .status-shell-card,
            .status-side-card,
            #stage-action {
                border-radius: 10px;
            }

            .status-hero {
                grid-template-columns: 1fr;
                gap: 0.75rem;
                padding: 1rem;
            }

            .status-hero-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
            }

            .status-hero h3 {
                font-size: 1.35rem;
                line-height: 1.15;
                overflow-wrap: anywhere;
            }

            .status-hero-link {
                align-items: flex-start;
                line-height: 1.25;
                white-space: normal;
                text-align: left;
            }

            .status-hero-badges span,
            .status-hero-badges small {
                font-size: 0.78rem;
                white-space: normal;
            }

            .status-timeline .timeline-item {
                gap: 0.7rem;
                align-items: flex-start;
                padding: 0.9rem 0;
            }

            .status-timeline .timeline-item:not(:last-child)::after {
                left: 20px;
                top: 48px;
                bottom: -10px;
            }

            .status-timeline .timeline-icon {
                width: 40px;
                height: 40px;
                flex: 0 0 40px;
            }

            .status-timeline .timeline-content h5 {
                font-size: 1rem;
                line-height: 1.2;
            }

            .status-timeline .timeline-content p {
                font-size: 0.84rem;
                line-height: 1.4;
            }

            .timeline-title-row {
                gap: 0.35rem;
                align-items: flex-start;
                flex-direction: column;
            }

            .timeline-documents-link,
            .post-filing-stage-documents-link {
                font-size: 0.82rem;
                line-height: 1.25;
                text-align: left;
            }

            .status-pill,
            .post-filing-status-badge {
                font-size: 0.72rem;
                padding: 0.22rem 0.48rem;
            }

            .timeline-state small,
            .timeline-post-stage-state small {
                font-size: 0.72rem;
            }

            .status-timeline .timeline-item.post-filing-expanded {
                padding: 0.85rem 0;
            }

            .timeline-post-filing-stage {
                grid-template-columns: 28px minmax(0, 1fr);
                gap: 0.55rem;
                padding: 0.65rem;
            }

            .timeline-post-stage-number {
                width: 24px;
                height: 24px;
                font-size: 0.74rem;
            }

            .timeline-post-stage-title-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.25rem;
            }

            .timeline-post-stage-copy h6 {
                font-size: 0.88rem;
            }

            .timeline-post-stage-copy p {
                font-size: 0.8rem;
                line-height: 1.38;
            }

            .post-filing-documents-modal .modal-dialog {
                margin: 0.75rem;
            }

            .post-filing-documents-modal .modal-header {
                align-items: flex-start;
                padding: 0.9rem;
            }

            .post-filing-document-tabs {
                display: grid;
                grid-template-columns: 1fr;
            }

            .post-filing-modal-document-row {
                align-items: stretch;
                flex-direction: column;
                gap: 0.65rem;
            }

            .post-filing-modal-document-row a {
                justify-content: center;
                width: 100%;
            }

            .stage-no-action-notice {
                align-items: flex-start;
                padding: 0.8rem;
            }

            .onboarding-documents-card,
            .signed-documents-grid,
            .signature-modal-grid,
            .signature-card-heading {
                grid-template-columns: 1fr;
            }

            .engagement-signature-heading,
            .physical-upload-card .signature-card-heading {
                grid-template-columns: auto minmax(0, 1fr) auto;
                align-items: flex-start;
            }

            .onboarding-document-tile:not(:last-child) {
                border-right: 0;
                border-bottom: 1px solid #e2e8f0;
            }

            .signature-heading-actions .badge {
                justify-self: start;
            }

            .signature-heading-actions {
                justify-items: start;
            }

            .physical-upload-card .signature-heading-actions {
                justify-items: end;
            }

            .physical-upload-card .signature-heading-actions .badge,
            .engagement-signature-heading .badge {
                justify-self: end;
            }

            .physical-upload-footer {
                grid-template-columns: 1fr;
                margin-top: 0.65rem;
            }

            .physical-upload-box {
                grid-template-columns: 1fr;
                gap: 0.7rem;
            }

            .physical-upload-actions {
                justify-content: stretch;
            }

            .physical-upload-choose,
            .physical-upload-view {
                flex: 1 1 130px;
                min-width: 0;
            }

            .physical-upload-view {
                width: 100%;
            }

            .signed-document-action-row {
                flex: 1 1 100%;
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.1rem;
            }

            .signature-card-actions {
                margin-left: 0;
            }

            .stage-guide-steps {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .stage-guide-steps li {
                padding: 0;
                border-right: 0;
            }

            .signature-document-modal .modal-body {
                height: min(72vh, 680px);
                min-height: 0;
                overflow: auto;
            }

            .signature-document-viewer iframe,
            .signature-document-empty {
                min-height: 52vh;
            }

            .signature-document-loading {
                min-height: 0;
            }

            .signature-modal-grid {
                height: auto;
                min-height: auto;
            }

            .draft-document-card {
                padding: 1rem;
            }

            .draft-action-btn,
            .draft-request-btn {
                width: 100%;
            }

            .draft-decision-row {
                grid-template-columns: 1fr;
            }

            .post-filing-step {
                grid-template-columns: auto 1fr;
            }

            .post-filing-status-badge {
                grid-column: 2;
                justify-self: start;
            }

            .onboarding-package-title {
                grid-template-columns: auto 1fr;
            }

            .onboarding-package-docs-btn {
                grid-column: 1 / -1;
                justify-self: start;
                white-space: normal;
                text-align: left;
            }

            .draft-request-actions,
            .draft-document-actions {
                display: grid;
            }

        }
    </style>
@endsection
