<?php

namespace App\Http\Middleware;

use App\Mail\AdminWorkflowNotification;
use App\Mail\EventNotification;
use App\Models\Admin;
use App\Models\Application;
use App\Models\Document;
use App\Models\ExaminationReplyDocument;
use App\Models\ExaminationReportReplyCase;
use App\Models\Payment;
use App\Models\StuckTrademarkCase;
use App\Models\StuckTrademarkDocument;
use App\Models\TrademarkOppositionCase;
use App\Models\User;
use App\Support\TrademarkOppositionWorkflow;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkflowActionEmail
{
    private const CLIENT_ACTION_ROUTES = [
        'trademark.store',
        'trademark.store-details',
        'documents.store',
        'documents.save-edited',
        'user.document.upload-signed',
        'user.signature.submit',
        'user.affidavit.submit',
        'stuck-trademark.store',
        'stuck-trademark.onboarding.submit',
        'stuck-trademark.documents.store',
        'stuck-trademark.audit-report.approve',
        'stuck-trademark.audit-report.request-reupload',
        'stuck-trademark.approve-execution',
        'stuck-trademark.skip-execution',
        'examination-reply.store',
        'examination-reply.requested-documents',
        'examination-reply.evidence',
        'examination-reply.draft.approve',
        'examination-reply.draft.request-changes',
        'trademark-opposition.store',
        'trademark-opposition.documents',
        'trademark-opposition.evidence',
        'trademark-opposition.stage-documents',
        'trademark-opposition.draft.approve',
        'trademark-opposition.draft.request-changes',
        'trademark-opposition.oppose.store',
        'trademark-opposition.oppose.evidence',
        'trademark-opposition.oppose.draft.approve',
        'trademark-opposition.oppose.draft.request-changes',
        'trademark-opposition.oppose.third-party-evidence',
    ];

    private const ADMIN_APPLICATION_ACTION_ROUTES = [
        'admin.start-review',
        'admin.approve',
        'admin.resend-onboarding-package',
        'admin.manual-onboarding-package',
        'admin.request-changes',
        'admin.generate-affidavit',
        'admin.generate-poa',
        'admin.approve-document',
        'admin.reject-document',
        'admin.verify-document',
        'admin.documents.review',
        'admin.file',
        'admin.file-complete',
        'admin.update-status',
        'admin.kyc-verify',
        'admin.strategy-complete',
        'admin.upload-search-report',
        'admin.publish-draft',
        'admin.post-filing.update-stage',
        'admin.approve-payment',
        'admin.reject-payment',
    ];

    private const ADMIN_INTERNAL_ROUTES = [
        'admin.trademark-opposition.oppose.internal-note',
        'admin.examination-reply.stage-draft',
        'admin.examination-reply.internal-note',
        'admin.examination-reply.additional-documents',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = (string) $request->route()?->getName();

        if (! $this->wasSuccessful($response)) {
            return $response;
        }

        if ($this->isClientAction($routeName) && ! $request->attributes->get('workflow_admin_email_sent')) {
            $this->emailAdmins($request, $routeName);
        }

        if ($this->isAdminAction($request, $routeName) && ! $request->attributes->get('workflow_client_email_sent')) {
            $this->emailClient($request, $routeName);
        }

        return $response;
    }

    private function wasSuccessful(Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($response instanceof JsonResponse && (($response->getData(true)['status'] ?? null) === 'error')) {
            return false;
        }

        if ($response instanceof RedirectResponse) {
            $session = $response->getSession();
            if ($session?->has('errors') || $session?->has('error')) {
                return false;
            }
        }

        return true;
    }

    private function isClientAction(string $routeName): bool
    {
        return in_array($routeName, self::CLIENT_ACTION_ROUTES, true)
            || Str::startsWith($routeName, 'workflow.');
    }

    private function isAdminAction(Request $request, string $routeName): bool
    {
        if (! $request->isMethodSafe() && in_array($routeName, self::ADMIN_APPLICATION_ACTION_ROUTES, true)) {
            return true;
        }

        if ($request->isMethodSafe() || in_array($routeName, self::ADMIN_INTERNAL_ROUTES, true)) {
            return false;
        }

        return Str::startsWith($routeName, [
            'admin.trademark-opposition.',
            'admin.stuck-trademark.',
            'admin.trademark-execution.',
            'admin.examination-reply.',
        ]);
    }

    private function emailAdmins(Request $request, string $routeName): void
    {
        $subject = 'Client action submitted – '.$this->actionLabel($routeName);
        $context = $this->context($request);
        $message = implode(PHP_EOL, array_filter([
            'A client submitted an Action Center form.',
            'Action: '.$this->actionLabel($routeName),
            $context['label'] ?? null,
            'Submitted by: '.($request->user()?->name ?: 'Client'),
            'Submitted at: '.now()->timezone(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T'),
        ]));

        try {
            Admin::query()->pluck('email')->filter()->unique()->each(
                fn (string $email) => Mail::to($email)->send(new AdminWorkflowNotification(
                    $subject,
                    $message,
                    $context['admin_url'] ?? route('admin.dashboard'),
                ))
            );
            $request->attributes->set('workflow_admin_email_sent', true);
        } catch (\Throwable $exception) {
            Log::warning('Fallback admin Action Center email failed.', [
                'route' => $routeName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function emailClient(Request $request, string $routeName): void
    {
        $context = $this->context($request);
        $user = $context['user'] ?? null;

        if (! $user instanceof User || blank($user->email)) {
            return;
        }

        $action = $this->actionLabel($routeName);
        $message = 'The Legal Bruz team submitted an update for your matter: '.$action.'. Please open your Action Center to review the latest status and any required next step.';

        try {
            Mail::to($user->email)->send(new EventNotification(
                $user,
                'Your matter has been updated',
                $message,
                [],
                $context['client_url'] ?? route('dashboard'),
                'Open Action Center',
            ));
            $request->attributes->set('workflow_client_email_sent', true);
        } catch (\Throwable $exception) {
            Log::warning('Fallback client stage-action email failed.', [
                'route' => $routeName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function context(Request $request): array
    {
        $route = $request->route();
        $case = $route?->parameter('case');

        if ($case instanceof TrademarkOppositionCase) {
            return [
                'label' => 'Opposition case: '.$case->case_number,
                'user' => $case->user,
                'admin_url' => $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE
                    ? route('admin.trademark-opposition.oppose.show', $case)
                    : route('admin.trademark-opposition.show', $case),
                'client_url' => $case->flow_type === TrademarkOppositionWorkflow::FLOW_OPPOSE
                    ? route('trademark-opposition.oppose.show', $case)
                    : route('trademark-opposition.show', $case),
            ];
        }

        if ($case instanceof StuckTrademarkCase) {
            return [
                'label' => 'Recovery case: '.$case->case_number,
                'user' => $case->user,
                'admin_url' => route('admin.stuck-trademark.show', $case),
                'client_url' => route('stuck-trademark.show', $case),
            ];
        }

        if ($case instanceof ExaminationReportReplyCase) {
            return [
                'label' => 'Objection reply case: '.$case->case_number,
                'user' => $case->user,
                'admin_url' => route('admin.examination-reply.show', $case),
                'client_url' => route('examination-reply.show', $case),
            ];
        }

        $application = $this->applicationFromRequest($request);
        if ($application) {
            return [
                'label' => 'Application: '.($application->application_number ?: '#'.$application->id).' – '.($application->brand_name ?: 'Trade mark'),
                'user' => $application->user,
                'admin_url' => route('admin.view-application', $application),
                'client_url' => route('trademark.status', $application),
            ];
        }

        return [];
    }

    private function applicationFromRequest(Request $request): ?Application
    {
        $route = $request->route();
        $document = $route?->parameter('document');
        if ($document instanceof Document) {
            return $document->application;
        }
        if ($document instanceof StuckTrademarkDocument || $document instanceof ExaminationReplyDocument) {
            return null;
        }

        $payment = $route?->parameter('payment');
        if ($payment instanceof Payment) {
            return $payment->application;
        }

        $id = $route?->parameter('id') ?? $route?->parameter('applicationId');

        return is_numeric($id) ? Application::with('user')->find((int) $id) : null;
    }

    private function actionLabel(string $routeName): string
    {
        return Str::of($routeName)
            ->replace(['admin.', 'workflow.', 'trademark-opposition.', 'examination-reply.', 'stuck-trademark.'], '')
            ->replace(['.', '-', '_'], ' ')
            ->headline()
            ->toString();
    }
}
