<?php

namespace App\Http\Controllers;

use App\Mail\EventNotification;
use App\Models\Notification;
use App\Models\StuckTrademarkCase;
use App\Models\TrademarkExecutionAction;
use App\Models\TrademarkExecutionDocument;
use App\Models\TrademarkExecutionUpdate;
use App\Support\StuckTrademarkWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AdminTrademarkExecutionController extends Controller
{
    private const ACTIONS = [
        'RTI Filing',
        'Amendment / Correction',
        'Attorney Change',
        'Registry Follow-Up',
        'Status Monitoring',
        'Objection Reply',
        'Hearing Support',
        'Other',
    ];

    private const ACTION_STATUSES = ['Not Required', 'Pending', 'In Progress', 'Completed'];

    private const PROGRESS_STATUSES = ['Pending', 'In Progress', 'Completed'];

    private const MONITORING_STATUSES = ['Active', 'Completed'];

    private const RESOLUTION_STATUSES = ['Active', 'Completed'];

    public function showExecution(StuckTrademarkCase $case)
    {
        $this->ensureExecutionActions($case);

        return view('admin.stuck-trademark.execution', [
            'case' => $case->load([
                'documents',
                'executionActions',
                'executionUpdates',
                'executionDocuments',
            ]),
            'actions' => self::ACTIONS,
            'actionStatuses' => self::ACTION_STATUSES,
            'progressStatuses' => self::PROGRESS_STATUSES,
            'monitoringStatuses' => self::MONITORING_STATUSES,
        ]);
    }

    public function startExecution(StuckTrademarkCase $case)
    {
        if (!$this->canWorkExecution($case)) {
            return redirect()->back()->with('error', 'Execution can start only after the execution package is purchased.');
        }

        $from = $case->status;
        $case->update([
            'status' => StuckTrademarkWorkflow::EXECUTION_ACTIVE,
            'current_stage' => 'Execution In Progress',
            'execution_status' => 'Execution Started',
            'execution_sub_stage' => 'required_actions',
            'execution_started_at' => $case->execution_started_at ?: now(),
        ]);

        $this->createClientUpdate(
            $case,
            'Execution Started',
            'Your trademark recovery execution has started.',
            'Execution Started'
        );
        $this->logStatus($case, $from, StuckTrademarkWorkflow::EXECUTION_ACTIVE, 'Execution started', 'Admin started revival execution.');
        $this->notifyApplicant($case, 'Execution started', 'Your trademark recovery execution has started.');

        return redirect()->route('admin.trademark-execution.show', $case)
            ->with('success', 'Revival execution started.');
    }

    public function saveActions(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'actions' => 'required|array',
            'actions.*.action_name' => 'required|string|max:100',
            'actions.*.is_required' => 'nullable|boolean',
            'actions.*.status' => 'required|in:' . implode(',', self::ACTION_STATUSES),
            'actions.*.admin_note' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:5000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        foreach ($validated['actions'] as $actionData) {
            if (!in_array($actionData['action_name'], self::ACTIONS, true)) {
                continue;
            }

            TrademarkExecutionAction::updateOrCreate(
                [
                    'case_id' => $case->id,
                    'action_name' => $actionData['action_name'],
                ],
                [
                    'is_required' => (bool) ($actionData['is_required'] ?? false),
                    'status' => $actionData['status'],
                    'admin_note' => $actionData['admin_note'] ?? null,
                ]
            );
        }

        $this->storeOptionalDocuments($request, $case, 'Required Actions Confirmed');
        $case->update([
            'execution_status' => 'Required Actions Confirmed',
            'execution_sub_stage' => 'action_progress',
            'current_stage' => 'Execution In Progress',
        ]);

        $this->createClientUpdate(
            $case,
            'Required Actions Confirmed',
            $validated['note'] ?? 'Required recovery actions are confirmed for your case.',
            'Required Actions Confirmed'
        );
        $this->notifyApplicant($case, 'Required actions confirmed', $validated['note'] ?? 'Required recovery actions are confirmed for your case.');

        return redirect()->route('admin.trademark-execution.show', $case)
            ->with('success', 'Required actions confirmed.');
    }

    public function submitActionProgress(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:' . implode(',', self::PROGRESS_STATUSES),
            'note' => 'nullable|string|max:5000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $this->storeOptionalDocuments($request, $case, 'Action in Progress');
        $case->update([
            'execution_status' => 'Action in Progress',
            'execution_sub_stage' => 'status_monitoring',
            'current_stage' => 'Execution In Progress',
        ]);

        $this->createClientUpdate(
            $case,
            $validated['title'],
            $validated['note'] ?? 'We are working on the selected recovery actions.',
            'Action in Progress'
        );

        return redirect()->route('admin.trademark-execution.show', $case)
            ->with('success', 'Execution action progress saved.');
    }

    public function completeStatusMonitoring(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:5000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $this->storeOptionalDocuments($request, $case, 'Status Monitoring Active');
        $case->update([
            'execution_status' => 'Status Monitoring Active',
            'execution_sub_stage' => 'additional_action_review',
            'current_stage' => 'Execution In Progress',
        ]);

        $this->createClientUpdate(
            $case,
            'Status Monitoring Active',
            $validated['note'] ?? 'We are monitoring your trademark status for movement.',
            'Status Monitoring Active'
        );

        return redirect()->route('admin.trademark-execution.show', $case)
            ->with('success', 'Status monitoring completed.');
    }

    public function resolveAdditionalAction(Request $request, StuckTrademarkCase $case)
    {
        $validated = $request->validate([
            'additional_action_required' => 'nullable|boolean',
            'action_name' => 'required_if:additional_action_required,1|nullable|string|max:255',
            'action_description' => 'required_if:additional_action_required,1|nullable|string|max:5000',
            'status' => 'nullable|in:' . implode(',', self::PROGRESS_STATUSES),
            'note' => 'nullable|string|max:5000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $requiresAdditionalAction = (bool) ($validated['additional_action_required'] ?? false);

        if ($requiresAdditionalAction && $case->execution_sub_stage === 'additional_action_review') {
            $case->update([
                'execution_additional_action_required' => true,
                'execution_additional_action_name' => $validated['action_name'],
                'execution_additional_action_description' => $validated['action_description'],
                'execution_status' => 'Additional Action Required',
                'execution_sub_stage' => 'additional_action_progress',
                'current_stage' => 'Execution In Progress',
            ]);

            $this->createClientUpdate(
                $case,
                $validated['action_name'],
                $validated['action_description'],
                'Additional Action Required'
            );

            return redirect()->route('admin.trademark-execution.show', $case)
                ->with('success', 'Additional action added.');
        }

        $stage = $case->execution_additional_action_required
            ? ($case->execution_additional_action_name ?: 'Additional Action Completed')
            : 'No Additional Action Required';
        $note = $validated['note'] ?? (
            $case->execution_additional_action_required
                ? 'The additional recovery action has been completed.'
                : 'No additional action is required for this execution.'
        );

        $this->storeOptionalDocuments($request, $case, $stage);
        $this->createClientUpdate($case, $stage, $note, $stage);
        $this->createClientUpdate(
            $case,
            'Execution Completed',
            'Execution work is completed and the case has moved for resolution.',
            'Execution Completed'
        );

        if (! $case->execution_additional_action_required) {
            $case->execution_additional_action_name = 'No Additional Action Required';
            $case->execution_additional_action_description = 'No additional action is required for this execution.';
        }

        $updates = [
            'execution_additional_action_required' => $case->execution_additional_action_required,
            'execution_additional_action_name' => $case->execution_additional_action_name,
            'execution_additional_action_description' => $case->execution_additional_action_description,
            'status' => StuckTrademarkWorkflow::MONITORING,
            'execution_status' => 'Execution Completed',
            'execution_sub_stage' => 'execution_completed',
            'execution_completed_at' => now(),
            'current_stage' => 'Monitoring & Updates',
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'monitoring_status')) {
            $updates['monitoring_status'] = 'Active';
        }

        $case->update($updates);
        $this->logStatus($case, StuckTrademarkWorkflow::EXECUTION_ACTIVE, StuckTrademarkWorkflow::MONITORING, 'Monitoring started', 'Execution work was completed and monitoring updates are now active.');

        $this->notifyApplicant($case, 'Execution completed', 'Execution work is completed. Monitoring and registry updates are now active.');

        return redirect()->route('admin.stuck-trademark.show', $case)
            ->with('success', 'Execution completed. Monitoring and updates are now active.');
    }

    public function submitMonitoringUpdate(Request $request, StuckTrademarkCase $case)
    {
        $executionIsComplete = (bool) $case->execution_completed_at || $case->execution_sub_stage === 'execution_completed';

        if ($case->resolved_at || $case->closed_at || in_array($case->status, [StuckTrademarkWorkflow::RESOLVED, StuckTrademarkWorkflow::CLOSED], true)) {
            return redirect()->back()->with('error', 'A resolved or closed case cannot be moved back to monitoring.');
        }

        if (!$executionIsComplete && !in_array($case->status, [StuckTrademarkWorkflow::MONITORING, StuckTrademarkWorkflow::RESOLVED], true)) {
            return redirect()->back()->with('error', 'Monitoring updates are available only after execution is completed.');
        }

        $validated = $request->validate([
            'monitoring_status' => 'required|in:' . implode(',', self::MONITORING_STATUSES),
            'title' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:5000',
            'next_follow_up_at' => 'nullable|date',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $from = $case->status;
        $to = $validated['monitoring_status'] === 'Completed'
            ? StuckTrademarkWorkflow::RESOLVED
            : StuckTrademarkWorkflow::MONITORING;
        $title = $validated['title'] ?: 'Monitoring ' . $validated['monitoring_status'];
        $note = $validated['note'] ?: match ($validated['monitoring_status']) {
            'Completed' => 'Monitoring is completed and the case has moved for resolution.',
            default => 'Monitoring is active and registry movement is being tracked.',
        };

        $this->storeOptionalDocuments($request, $case, 'Monitoring & Updates');
        $updates = [
            'status' => $to,
            'current_stage' => $to === StuckTrademarkWorkflow::RESOLVED ? 'Resolved & Closed' : 'Monitoring & Updates',
            'next_follow_up_at' => filled($validated['next_follow_up_at'] ?? null)
                ? Carbon::parse($validated['next_follow_up_at'], config('app.timezone', 'Europe/London'))->utc()
                : $case->next_follow_up_at,
            'resolved_at' => $to === StuckTrademarkWorkflow::RESOLVED ? ($case->resolved_at ?: now()) : $case->resolved_at,
        ];

        if (Schema::hasColumn('stuck_trademark_cases', 'monitoring_status')) {
            $updates['monitoring_status'] = $validated['monitoring_status'];
        }

        $case->update($updates);

        $this->createClientUpdate($case, $title, $note, 'Monitoring & Updates');
        $this->logStatus($case, $from, $to, $title, $note);
        $this->notifyApplicant($case, $title, $note);

        return redirect()->route('admin.stuck-trademark.show', $case)
            ->with('success', 'Monitoring update saved.');
    }

    public function submitResolutionReport(Request $request, StuckTrademarkCase $case)
    {
        if (!in_array($case->status, [StuckTrademarkWorkflow::RESOLVED, StuckTrademarkWorkflow::CLOSED], true)) {
            return redirect()->back()->with('error', 'Resolved & Closed actions are available only after monitoring is completed.');
        }

        $validated = $request->validate([
            'resolution_status' => 'required|in:' . implode(',', self::RESOLUTION_STATUSES),
            'final_report' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
            'note' => 'nullable|string|max:5000',
            'optional_document_names' => 'nullable|array|max:10',
            'optional_document_names.*' => 'nullable|string|max:255',
            'optional_documents' => 'nullable|array|max:10',
            'optional_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:15360',
        ]);

        $from = $case->status;
        $finalReportPath = $request->file('final_report')->store('execution-documents/' . $case->id, 'public');

        TrademarkExecutionDocument::create([
            'case_id' => $case->id,
            'document_title' => 'Final Report',
            'document_type' => 'Final Report',
            'execution_stage' => 'Resolved & Closed',
            'file_path' => $finalReportPath,
            'visible_to_client' => true,
        ]);

        $this->storeOptionalDocuments($request, $case, 'Resolved & Closed');

        $to = $validated['resolution_status'] === 'Completed'
            ? StuckTrademarkWorkflow::CLOSED
            : StuckTrademarkWorkflow::RESOLVED;
        $note = $validated['note'] ?: (
            $to === StuckTrademarkWorkflow::CLOSED
                ? 'Final report has been shared and the case is closed.'
                : 'Final report has been shared for the resolved case.'
        );

        $case->update([
            'status' => $to,
            'current_stage' => 'Resolved & Closed',
            'monitoring_status' => 'Completed',
            'resolved_at' => $case->resolved_at ?: now(),
            'closed_at' => $to === StuckTrademarkWorkflow::CLOSED ? ($case->closed_at ?: now()) : $case->closed_at,
        ]);

        $this->createClientUpdate($case, 'Final Report Shared', $note, 'Resolved & Closed');
        $this->logStatus($case, $from, $to, 'Final report shared', $note);
        $this->notifyApplicant($case, 'Final report shared', $note);

        return redirect()->route('admin.stuck-trademark.show', $case)
            ->with('success', 'Final report saved and shared with the client.');
    }

    private function canWorkExecution(StuckTrademarkCase $case): bool
    {
        return $case->status === StuckTrademarkWorkflow::EXECUTION_ACTIVE
            && $case->execution_payment_status === 'paid';
    }

    private function ensureExecutionActions(StuckTrademarkCase $case): void
    {
        foreach (self::ACTIONS as $action) {
            TrademarkExecutionAction::firstOrCreate(
                [
                    'case_id' => $case->id,
                    'action_name' => $action,
                ],
                [
                    'status' => 'Not Required',
                ]
            );
        }
    }

    private function storeOptionalDocuments(Request $request, StuckTrademarkCase $case, string $stage): void
    {
        $files = $request->file('optional_documents', []);
        $names = $request->input('optional_document_names', []);

        foreach ($files as $index => $file) {
            if (!$file) {
                continue;
            }

            $path = $file->store('execution-documents/' . $case->id, 'public');

            TrademarkExecutionDocument::create([
                'case_id' => $case->id,
                'document_title' => $names[$index] ?? $file->getClientOriginalName(),
                'document_type' => 'Optional Document',
                'execution_stage' => $stage,
                'file_path' => $path,
                'visible_to_client' => true,
            ]);
        }
    }

    private function createClientUpdate(StuckTrademarkCase $case, string $title, ?string $note, string $stage): void
    {
        TrademarkExecutionUpdate::create([
            'case_id' => $case->id,
            'title' => $title,
            'note' => $note,
            'stage' => $stage,
            'visible_to_client' => true,
        ]);
    }

    private function notifyApplicant(StuckTrademarkCase $case, string $title, string $message): void
    {
        Notification::create([
            'user_id' => $case->user_id,
            'type' => 'stuck_trademark_execution_update',
            'title' => $title,
            'message' => $message,
            'data' => [
                'stuck_trademark_case_id' => $case->id,
                'case_number' => $case->case_number,
                'status' => $case->status,
            ],
        ]);

        try {
            Mail::to($case->email)->send(new EventNotification(
                $case->user,
                $title,
                $message,
                [],
                route('stuck-trademark.show', $case),
                'Open Recovery Case'
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function logStatus(StuckTrademarkCase $case, ?string $from, string $to, string $title, ?string $message = null): void
    {
        $case->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => 'admin',
            'actor_id' => Auth::guard('admin')->id(),
            'title' => $title,
            'message' => $message,
        ]);
    }
}
