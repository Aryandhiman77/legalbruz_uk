<?php

namespace App\Http\Controllers;

use App\Models\ConsultationBooking;
use App\Models\TrademarkSearchReportRequest;
use Illuminate\Http\Request;
use App\Support\TrademarkOppositionWorkflow;
use App\Support\TrademarkWorkflow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Show client dashboard
     */
    public function index()
    {
        $user = Auth::user();
        $applications = $user->applications()->with('payments')->latest()->get();
        $stuckTrademarkCases = $user->stuckTrademarkCases()->latest()->get();
        $trademarkOppositionCases = $user->trademarkOppositionCases()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_DEFEND)
            ->with('documents')
            ->latest()
            ->get();
        $trademarkOpposeCases = $user->trademarkOppositionCases()
            ->where('flow_type', TrademarkOppositionWorkflow::FLOW_OPPOSE)
            ->with('evidence')
            ->latest()
            ->get();
        $examinationReplyCases = $user->examinationReportReplyCases()
            ->latest()
            ->get();
        $consultationBookings = Schema::hasTable('consultation_bookings')
            ? ConsultationBooking::query()->visibleTo($user)->latest()->get()
            : collect();
        $trademarkSearchReportRequests = Schema::hasTable('trademark_search_report_requests')
            ? TrademarkSearchReportRequest::query()->visibleTo($user)->with('documents')->latest()->get()
            : collect();
        $pendingPayments = $applications->filter(fn ($application) => in_array($application->current_status, [
            TrademarkWorkflow::DRAFT,
            TrademarkWorkflow::PAYMENT_PENDING_FINAL,
        ]))->count()
            + $consultationBookings->where('payment_status', 'pending')->count()
            + $trademarkSearchReportRequests->where('payment_status', 'pending')->count();
        $underReview = $applications->where('current_status', TrademarkWorkflow::UNDER_REVIEW)->count();
        $registered = $applications->filter(function ($application) {
            if (Schema::hasColumn('applications', 'registry_status')) {
                return $application->registry_status === TrademarkWorkflow::REGISTRY_REGISTERED;
            }

            return filled($application->registered_at);
        })->count();

        return view('dashboard.index', [
            'applications' => $applications,
            'pendingPayments' => $pendingPayments,
            'underReview' => $underReview,
            'registered' => $registered,
            'stuckTrademarkCases' => $stuckTrademarkCases,
            'trademarkOppositionCases' => $trademarkOppositionCases,
            'trademarkOpposeCases' => $trademarkOpposeCases,
            'examinationReplyCases' => $examinationReplyCases,
            'consultationBookings' => $consultationBookings,
            'trademarkSearchReportRequests' => $trademarkSearchReportRequests,
        ]);
    }

    /**
     * Show application details
     */
    public function showApplication($applicationId)
    {
        $application = Auth::user()->applications()->findOrFail($applicationId);
        $documents = $application->documents()->get();
        $payments = $application->payments()->get();

        return view('dashboard.application-detail', [
            'application' => $application,
            'documents' => $documents,
            'payments' => $payments,
        ]);
    }
}
