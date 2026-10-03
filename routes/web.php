<?php

use App\Http\Controllers\AdminBlogController;
use App\Http\Controllers\AdminCareerApplicationController;
use App\Http\Controllers\AdminCareerJobController;
use App\Http\Controllers\AdminCmsPageController;
use App\Http\Controllers\AdminContactMessageController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDiscountCouponController;
use App\Http\Controllers\AdminFaqController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\AdminTrademarkExecutionController;
use App\Http\Controllers\AdminTrademarkPricingController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExaminationReportReplyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StuckTrademarkController;
use App\Http\Controllers\TrademarkController;
use App\Http\Controllers\TrademarkOppositionController;
use App\Http\Controllers\TrademarkProbabilityController;
use App\Http\Controllers\TrademarkScraperController;
use App\Http\Controllers\UkPostcodeLookupController;
use App\Http\Controllers\UserDocumentController;
use App\Http\Controllers\WorkflowController;
use App\Models\CustomerReview;
use App\Models\Faq;
use App\Models\TrademarkPricing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $faqs = Schema::hasTable('faqs')
        ? Faq::query()->published()->where('category', 'like', 'UK%')->orderBy('sort_order')->orderBy('id')->limit(6)->get()
        : collect();

    return view('home-uk', [
        'trademarkPricingPlans' => TrademarkPricing::activePlans(),
        'customerReviews' => CustomerReview::homepageReviews(),
        'faqs' => $faqs,
    ]);
})->name('landing');

Route::get('/trademark-search', function () {
    return view('home', [
        'searchPage' => true,
        'keyword' => request()->query('keyword', ''),
        'trademarkPricingPlans' => TrademarkPricing::activePlans(),
        'customerReviews' => CustomerReview::homepageReviews(),
    ]);
})->name('trademark.search-page');

Route::post('/trademark-search/ai-probability', TrademarkProbabilityController::class)
    ->middleware('throttle:20,1')
    ->name('trademark.ai-probability');

Route::post('/trademark-search/probability-report', [TrademarkProbabilityController::class, 'downloadReport'])
    ->middleware('throttle:20,1')
    ->name('trademark.probability-report');

Route::get('/scrape-trademark', [TrademarkScraperController::class, 'scrape']);

Route::get('/storage/{path}', function (string $path) {
    $path = rawurldecode(str_replace('\\', '/', $path));
    $path = preg_replace('#/+#', '/', $path);
    $path = ltrim($path, '/');

    if ($path === '' || str_contains($path, '..') || ! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*')->name('storage.public.view');

// Coming Soon (Public)
Route::get('/comming-soon', function () {
    return view('comming-soon');
})->name('comming-soon');

Route::get('/about-us', [PublicPageController::class, 'about'])->name('about');
Route::get('/terms-and-conditions', [PublicPageController::class, 'terms'])->name('terms');
Route::get('/privacy-policy', [PublicPageController::class, 'privacy'])->name('privacy');
Route::get('/refund-policy', [PublicPageController::class, 'refund'])->name('refund');
Route::get('/faq', [PublicPageController::class, 'faq'])->name('faq');
Route::get('/contact', [PublicPageController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicPageController::class, 'submitContact'])
    ->middleware('throttle:5,1')
    ->name('contact.submit');
Route::get('/careers', [CareerController::class, 'index'])->name('careers.index');
Route::get('/careers/{careerJob:slug}', [CareerController::class, 'show'])->name('careers.show');
Route::get('/careers/{careerJob:slug}/apply', [CareerController::class, 'apply'])->name('careers.apply');
Route::post('/careers/{careerJob:slug}/apply', [CareerController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('careers.submit');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{blog:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::middleware('legacy.services')->group(function () {
    Route::get('/services/trademark/filed-and-stuck', [StuckTrademarkController::class, 'landing'])->name('stuck-trademark.landing');
    Route::get('/services/trademark/opposition-management', function () {
        return view('trademark.opposition-management');
    })->name('trademark.opposition-management');
    Route::get('/services/trademark/examination-report-reply', [ExaminationReportReplyController::class, 'landing'])->name('examination-reply.landing');
});

// ADMIN LOGIN ROUTES (Public)
Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// User Login Route - Allow access if not authenticated as web user (admin can access)
Route::get('/login', function () {
    // If admin is logged in, redirect to admin dashboard
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    // If web user is logged in, redirect to home
    if (Auth::guard('web')->check()) {
        return redirect()->route('home');
    }

    // Otherwise show login form
    return view('auth.login');
})->name('login');

Auth::routes(['verify' => false]);

Route::middleware('guest')->group(function () {
    Route::get('/email-otp', [EmailOtpController::class, 'show'])->name('auth.otp.show');
    Route::post('/email-otp', [EmailOtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('auth.otp.verify');
    Route::post('/email-otp/resend', [EmailOtpController::class, 'resend'])
        ->middleware('throttle:5,10')
        ->name('auth.otp.resend');
    Route::post('/email-otp/cancel', [EmailOtpController::class, 'cancel'])->name('auth.otp.cancel');
});

Route::get('/home', [HomeController::class, 'index'])->name('home');

// CLIENT ROUTES (Protected by auth middleware)
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/application/{id}', [DashboardController::class, 'showApplication'])->name('dashboard.application');
    Route::get('/api/uk-postcodes/{postcode}', [UkPostcodeLookupController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('uk-postcodes.show');

    // Trademark Application Flow (Single Pre-Payment Form)
    Route::get('/trademark/type-selection', function () {
        return view('trademark.application-form-modern', [
            'trademarkPricingPlans' => TrademarkPricing::activePlans(),
        ]);
    })->name('trademark.type-selection');

    Route::get('/trademark/kyc/{type}', function () {
        return redirect()->route('trademark.type-selection');
    })->name('trademark.kyc');

    Route::get('/trademark/form/{type?}', function () {
        return view('trademark.application-form-modern', [
            'trademarkPricingPlans' => TrademarkPricing::activePlans(),
        ]);
    })->name('trademark.application-form');
    Route::post('/trademark/store', [TrademarkController::class, 'storeApplication'])->name('trademark.store');

    // Payment Flow (Razorpay Integration)
    Route::get('/payment/{id}', [PaymentController::class, 'showPayment'])->name('payment.show');
    Route::post('/payment/{id}/create-order', [PaymentController::class, 'createOrder'])->name('payment.create-order');
    Route::post('/payment/{id}/verify-signature', [PaymentController::class, 'verifySignature'])->name('payment.verify-signature');
    Route::get('/payment/{id}/check-status', [PaymentController::class, 'checkPaymentStatus'])->name('payment.check-status');
    Route::get('/payments/history', [PaymentController::class, 'paymentHistory'])->name('payment.history');
    Route::get('/payments/{payment}/invoice', [PaymentController::class, 'viewInvoice'])->name('payment.invoice');

    // Application Details (After Payment)
    Route::get('/trademark/{id}/details', [TrademarkController::class, 'showDetailedForm'])->name('trademark.detailed-form');
    Route::post('/trademark/{id}/details', [TrademarkController::class, 'storeDetailedForm'])->name('trademark.store-details');

    // Document Upload
    Route::get('/documents/{id}/upload', [TrademarkController::class, 'showDocumentUpload'])->name('documents.upload');
    Route::post('/documents/{id}/store', [TrademarkController::class, 'storeDocuments'])->name('documents.store');

    // Document Download - Affidavit & POA
    Route::get('/documents/{id}/download-page', [TrademarkController::class, 'showDocumentDownload'])->name('documents.download-page');
    Route::get('/documents/{id}/affidavit/download', [TrademarkController::class, 'downloadAffidavit'])->name('documents.affidavit.download');
    Route::get('/documents/{id}/poa/download', [TrademarkController::class, 'downloadPOA'])->name('documents.poa.download');
    Route::get('/trademark/{id}/image/view', [TrademarkController::class, 'viewTrademarkImage'])->name('trademark.image.view');
    Route::get('/trademark/{id}/proof-of-use/view', [TrademarkController::class, 'viewProofOfUse'])->name('trademark.proof-of-use.view');

    // Document Editing - Save edited document content
    Route::post('/documents/save-edited', [DocumentController::class, 'saveEdited'])->name('documents.save-edited');

    // Status Tracking
    Route::get('/trademark/{id}/status', [TrademarkController::class, 'showStatus'])->name('trademark.status');
    Route::get('/trademark/{id}/stage/{stage}', [TrademarkController::class, 'showStageDetails'])->name('trademark.stage-details');

    // User Documents Management
    Route::get('/my-documents', [UserDocumentController::class, 'index'])->name('user.documents');
    Route::get('/documents/{document}/view', [UserDocumentController::class, 'view'])->name('user.document.view');
    Route::get('/documents/{document}/download', [UserDocumentController::class, 'download'])->name('user.document.download');
    Route::post('/documents/upload-signed', [UserDocumentController::class, 'uploadSigned'])->name('user.document.upload-signed');
    Route::post('/application/{id}/signature', [UserDocumentController::class, 'submitSignature'])->name('user.signature.submit');
    Route::post('/application/{id}/affidavit/submit', [UserDocumentController::class, 'submitAffidavit'])->name('user.affidavit.submit');
    Route::get('/documents/list', [UserDocumentController::class, 'listDocuments'])->name('user.documents.list');
    Route::post('/application/{id}/tasks/{taskCode}/complete', [WorkflowController::class, 'completeTask'])->name('workflow.task.complete');
    Route::post('/application/{id}/onboarding-document/{documentType}/apply-signature', [WorkflowController::class, 'applyOnboardingSignature'])->name('workflow.onboarding.apply-signature');
    Route::post('/application/{id}/onboarding-document/{documentType}/draft-upload', [WorkflowController::class, 'saveOnboardingPhysicalDraft'])->name('workflow.onboarding.draft-upload');
    Route::post('/application/{id}/onboarding-package', [WorkflowController::class, 'submitOnboardingPackage'])->name('workflow.onboarding.submit');
    Route::post('/application/{id}/onboarding-document/{documentType}', [WorkflowController::class, 'resubmitOnboardingDocument'])->name('workflow.onboarding.resubmit-document');
    Route::post('/application/{id}/draft/request-changes', [WorkflowController::class, 'requestDraftChanges'])->name('workflow.draft.request-changes');
    Route::post('/application/{id}/draft/approve', [WorkflowController::class, 'approveDraft'])->name('workflow.draft.approve');
    Route::post('/application/{id}/post-filing/{stage}/documents', [WorkflowController::class, 'submitPostFilingDocuments'])->name('workflow.post-filing.documents');

    // Notifications
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notification.mark-read');

    Route::middleware('legacy.services')->group(function () {
    // Trademark Opposition Management - Flow A: Defend My Trademark
    Route::get('/trademark-opposition/defend/create', [TrademarkOppositionController::class, 'create'])->name('trademark-opposition.create');
    Route::post('/trademark-opposition/defend', [TrademarkOppositionController::class, 'storeBasic'])->name('trademark-opposition.store');
    Route::get('/trademark-opposition/defend/{case}', [TrademarkOppositionController::class, 'show'])->name('trademark-opposition.show');
    Route::get('/trademark-opposition/defend/{case}/action-center', [TrademarkOppositionController::class, 'actionCenter'])->name('trademark-opposition.action-center');
    Route::post('/trademark-opposition/defend/{case}/documents', [TrademarkOppositionController::class, 'uploadDocuments'])->name('trademark-opposition.documents');
    Route::post('/trademark-opposition/defend/{case}/evidence', [TrademarkOppositionController::class, 'uploadEvidence'])->name('trademark-opposition.evidence');
    Route::post('/trademark-opposition/defend/{case}/stage-documents', [TrademarkOppositionController::class, 'uploadOpposeThirdPartyEvidence'])->name('trademark-opposition.stage-documents');
    Route::post('/trademark-opposition/defend/{case}/payment', [TrademarkOppositionController::class, 'completePayment'])->name('trademark-opposition.payment');
    Route::post('/trademark-opposition/defend/{case}/payment/create-order', [TrademarkOppositionController::class, 'createPaymentOrder'])->name('trademark-opposition.payment.create-order');
    Route::post('/trademark-opposition/defend/{case}/payment/verify-signature', [TrademarkOppositionController::class, 'verifyPaymentSignature'])->name('trademark-opposition.payment.verify-signature');
    Route::get('/trademark-opposition/defend/{case}/payment/invoice', [TrademarkOppositionController::class, 'viewPaymentInvoice'])->name('trademark-opposition.payment.invoice');
    Route::post('/trademark-opposition/defend/{case}/draft/approve', [TrademarkOppositionController::class, 'approveDraft'])->name('trademark-opposition.draft.approve');
    Route::post('/trademark-opposition/defend/{case}/draft/request-changes', [TrademarkOppositionController::class, 'requestDraftChanges'])->name('trademark-opposition.draft.request-changes');
    Route::get('/trademark-opposition/defend/{case}/documents/{kind}/{id}', [TrademarkOppositionController::class, 'viewDocument'])->name('trademark-opposition.document.view');
    Route::get('/trademark-opposition/defend/{case}/file/{file}', [TrademarkOppositionController::class, 'viewCaseFile'])->name('trademark-opposition.file.view');
    Route::get('/trademark-opposition/oppose/create', [TrademarkOppositionController::class, 'createOppose'])->name('trademark-opposition.oppose.create');
    Route::post('/trademark-opposition/oppose', [TrademarkOppositionController::class, 'storeOpposeBasic'])->name('trademark-opposition.oppose.store');
    Route::get('/trademark-opposition/oppose/{case}', [TrademarkOppositionController::class, 'showOppose'])->name('trademark-opposition.oppose.show');
    Route::post('/trademark-opposition/oppose/{case}/evidence', [TrademarkOppositionController::class, 'uploadOpposeEvidence'])->name('trademark-opposition.oppose.evidence');
    Route::post('/trademark-opposition/oppose/{case}/payment', [TrademarkOppositionController::class, 'completeOpposePayment'])->name('trademark-opposition.oppose.payment');
    Route::post('/trademark-opposition/oppose/{case}/payment/create-order', [TrademarkOppositionController::class, 'createOpposePaymentOrder'])->name('trademark-opposition.oppose.payment.create-order');
    Route::post('/trademark-opposition/oppose/{case}/payment/verify-signature', [TrademarkOppositionController::class, 'verifyOpposePaymentSignature'])->name('trademark-opposition.oppose.payment.verify-signature');
    Route::get('/trademark-opposition/oppose/{case}/payment/invoice', [TrademarkOppositionController::class, 'viewOpposePaymentInvoice'])->name('trademark-opposition.oppose.payment.invoice');
    Route::post('/trademark-opposition/oppose/{case}/draft/approve', [TrademarkOppositionController::class, 'approveOpposeDraft'])->name('trademark-opposition.oppose.draft.approve');
    Route::post('/trademark-opposition/oppose/{case}/draft/request-changes', [TrademarkOppositionController::class, 'requestOpposeDraftChanges'])->name('trademark-opposition.oppose.draft.request-changes');
    Route::post('/trademark-opposition/oppose/{case}/third-party-evidence', [TrademarkOppositionController::class, 'uploadOpposeThirdPartyEvidence'])->name('trademark-opposition.oppose.third-party-evidence');
    Route::get('/trademark-opposition/oppose/{case}/documents/{kind}/{id}', [TrademarkOppositionController::class, 'viewDocument'])->name('trademark-opposition.oppose.document.view');
    Route::get('/trademark-opposition/oppose/{case}/file/{file}', [TrademarkOppositionController::class, 'viewCaseFile'])->name('trademark-opposition.oppose.file.view');

    // Stuck / Delayed Trademark Recovery
    Route::post('/services/trademark/filed-and-stuck', [StuckTrademarkController::class, 'store'])->name('stuck-trademark.store');
    Route::get('/stuck-trademark/{case}/onboarding', [StuckTrademarkController::class, 'showOnboarding'])->name('stuck-trademark.onboarding');
    Route::post('/stuck-trademark/{case}/onboarding', [StuckTrademarkController::class, 'submitOnboarding'])->name('stuck-trademark.onboarding.submit');
    Route::get('/stuck-trademark/{case}/documents', [StuckTrademarkController::class, 'showDocuments'])->name('stuck-trademark.documents');
    Route::get('/stuck-trademark/{case}', [StuckTrademarkController::class, 'show'])->name('stuck-trademark.show');
    Route::post('/stuck-trademark/{case}/documents', [StuckTrademarkController::class, 'uploadDocuments'])->name('stuck-trademark.documents.store');
    Route::get('/stuck-trademark/{case}/audit-package', [StuckTrademarkController::class, 'showAuditPackage'])->name('stuck-trademark.audit-package');
    Route::post('/stuck-trademark/{case}/audit-payment/create-order', [StuckTrademarkController::class, 'createAuditOrder'])->name('stuck-trademark.audit-payment.create-order');
    Route::post('/stuck-trademark/{case}/audit-payment/verify-signature', [StuckTrademarkController::class, 'verifyAuditPaymentSignature'])->name('stuck-trademark.audit-payment.verify-signature');
    Route::post('/stuck-trademark/{case}/audit-payment', [StuckTrademarkController::class, 'markAuditPaid'])->name('stuck-trademark.audit-payment');
    Route::get('/stuck-trademark/{case}/audit-payment/invoice', [StuckTrademarkController::class, 'viewAuditInvoice'])->name('stuck-trademark.audit-payment.invoice');
    Route::post('/stuck-trademark/{case}/audit-report/approve', [StuckTrademarkController::class, 'approveAuditReport'])->name('stuck-trademark.audit-report.approve');
    Route::post('/stuck-trademark/{case}/audit-report/request-reupload', [StuckTrademarkController::class, 'requestAuditReportReupload'])->name('stuck-trademark.audit-report.request-reupload');
    Route::post('/stuck-trademark/{case}/approve-execution', [StuckTrademarkController::class, 'approveExecution'])->name('stuck-trademark.approve-execution');
    Route::post('/stuck-trademark/{case}/execution-payment/create-order', [StuckTrademarkController::class, 'createExecutionOrder'])->name('stuck-trademark.execution-payment.create-order');
    Route::post('/stuck-trademark/{case}/execution-payment/verify-signature', [StuckTrademarkController::class, 'verifyExecutionPaymentSignature'])->name('stuck-trademark.execution-payment.verify-signature');
    Route::post('/stuck-trademark/{case}/skip-execution', [StuckTrademarkController::class, 'skipExecution'])->name('stuck-trademark.skip-execution');
    Route::get('/stuck-trademark/documents/{document}/view', [StuckTrademarkController::class, 'viewDocument'])->name('stuck-trademark.document.view');
    Route::get('/stuck-trademark/{case}/audit-report', [StuckTrademarkController::class, 'viewAuditReport'])->name('stuck-trademark.audit-report');

    // Examination Report Reply / Trademark Objection Reply
    Route::get('/trademark-objection-reply/create', [ExaminationReportReplyController::class, 'create'])->name('examination-reply.create');
    Route::post('/trademark-objection-reply', [ExaminationReportReplyController::class, 'store'])->name('examination-reply.store');
    Route::get('/trademark-objection-reply/{case}', [ExaminationReportReplyController::class, 'show'])->name('examination-reply.show');
    Route::post('/trademark-objection-reply/{case}/requested-documents', [ExaminationReportReplyController::class, 'uploadRequestedDocuments'])->name('examination-reply.requested-documents');
    Route::post('/trademark-objection-reply/{case}/evidence', [ExaminationReportReplyController::class, 'submitEvidence'])->name('examination-reply.evidence');
    Route::post('/trademark-objection-reply/{case}/draft/approve', [ExaminationReportReplyController::class, 'approveDraft'])->name('examination-reply.draft.approve');
    Route::post('/trademark-objection-reply/{case}/draft/request-changes', [ExaminationReportReplyController::class, 'requestDraftChanges'])->name('examination-reply.draft.request-changes');
    Route::post('/trademark-objection-reply/{case}/payment/create-order', [ExaminationReportReplyController::class, 'createPaymentOrder'])->name('examination-reply.payment.create-order');
    Route::post('/trademark-objection-reply/{case}/payment/verify-signature', [ExaminationReportReplyController::class, 'verifyPaymentSignature'])->name('examination-reply.payment.verify-signature');
    Route::get('/trademark-objection-reply/{case}/payment/invoice', [ExaminationReportReplyController::class, 'viewPaymentInvoice'])->name('examination-reply.payment.invoice');
    Route::get('/trademark-objection-reply/documents/{document}/view', [ExaminationReportReplyController::class, 'viewDocument'])->name('examination-reply.document.view');
    Route::get('/trademark-objection-reply/documents/{document}/download', [ExaminationReportReplyController::class, 'downloadDocument'])->name('examination-reply.document.download');
    });

});

// ADMIN ROUTES
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::resource('/blogs', AdminBlogController::class)
        ->except('show')
        ->names([
            'index' => 'admin.blogs.index',
            'create' => 'admin.blogs.create',
            'store' => 'admin.blogs.store',
            'edit' => 'admin.blogs.edit',
            'update' => 'admin.blogs.update',
            'destroy' => 'admin.blogs.destroy',
        ]);
    Route::get('/cms-pages', [AdminCmsPageController::class, 'index'])->name('admin.cms-pages.index');
    Route::get('/cms-pages/{key}/edit', [AdminCmsPageController::class, 'edit'])->name('admin.cms-pages.edit');
    Route::put('/cms-pages/{key}', [AdminCmsPageController::class, 'update'])->name('admin.cms-pages.update');
    Route::resource('/career-jobs', AdminCareerJobController::class)
        ->except('show')
        ->parameters(['career-jobs' => 'careerJob'])
        ->names([
            'index' => 'admin.career-jobs.index',
            'create' => 'admin.career-jobs.create',
            'store' => 'admin.career-jobs.store',
            'edit' => 'admin.career-jobs.edit',
            'update' => 'admin.career-jobs.update',
            'destroy' => 'admin.career-jobs.destroy',
        ]);
    Route::get('/career-applications', [AdminCareerApplicationController::class, 'index'])->name('admin.career-applications.index');
    Route::get('/career-applications/{careerApplication}', [AdminCareerApplicationController::class, 'show'])->name('admin.career-applications.show');
    Route::patch('/career-applications/{careerApplication}', [AdminCareerApplicationController::class, 'update'])->name('admin.career-applications.update');
    Route::get('/career-applications/{careerApplication}/resume', [AdminCareerApplicationController::class, 'downloadResume'])->name('admin.career-applications.resume');
    Route::resource('/faqs', AdminFaqController::class)
        ->except('show')
        ->names([
            'index' => 'admin.faqs.index',
            'create' => 'admin.faqs.create',
            'store' => 'admin.faqs.store',
            'edit' => 'admin.faqs.edit',
            'update' => 'admin.faqs.update',
            'destroy' => 'admin.faqs.destroy',
        ]);
    Route::resource('/reviews', AdminReviewController::class)
        ->except('show')
        ->names([
            'index' => 'admin.reviews.index',
            'create' => 'admin.reviews.create',
            'store' => 'admin.reviews.store',
            'edit' => 'admin.reviews.edit',
            'update' => 'admin.reviews.update',
            'destroy' => 'admin.reviews.destroy',
        ]);
    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('admin.contact-messages.index');
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('admin.contact-messages.show');
    Route::patch('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'update'])->name('admin.contact-messages.update');
    Route::get('/discount-coupons', [AdminDiscountCouponController::class, 'index'])->name('admin.discount-coupons.index');
    Route::get('/discount-coupons/create', [AdminDiscountCouponController::class, 'create'])->name('admin.discount-coupons.create');
    Route::post('/discount-coupons', [AdminDiscountCouponController::class, 'store'])->name('admin.discount-coupons.store');
    Route::get('/discount-coupons/{coupon}/edit', [AdminDiscountCouponController::class, 'edit'])->name('admin.discount-coupons.edit');
    Route::put('/discount-coupons/{coupon}', [AdminDiscountCouponController::class, 'update'])->name('admin.discount-coupons.update');
    Route::get('/trademark-pricing', [AdminTrademarkPricingController::class, 'edit'])->name('admin.trademark-pricing.edit');
    Route::put('/trademark-pricing', [AdminTrademarkPricingController::class, 'update'])->name('admin.trademark-pricing.update');
    Route::get('/applications', [AdminController::class, 'listPendingApplications'])->name('admin.applications');
    Route::get('/applications/all', [AdminController::class, 'listAllApplications'])->name('admin.all-applications');
    Route::get('/application/{id}', [AdminController::class, 'viewApplication'])->name('admin.view-application');
    Route::get('/application/{id}/review', [AdminController::class, 'viewApplication'])->name('admin.review-application');
    Route::get('/application/{id}/client-dashboard', [AdminController::class, 'viewClientDashboard'])->name('admin.application.client-dashboard');
    Route::get('/application/{id}/client-action-center', [AdminController::class, 'viewClientActionCenter'])->name('admin.application.client-action-center');

    // Approval/Rejection
    Route::post('/application/{id}/start-review', [AdminController::class, 'startApplicationReview'])->name('admin.start-review');
    Route::post('/application/{id}/approve', [AdminController::class, 'approveApplication'])->name('admin.approve');
    Route::post('/application/{id}/onboarding-package/resend', [AdminController::class, 'resendOnboardingPackage'])->name('admin.resend-onboarding-package');
    Route::post('/application/{id}/onboarding-package/manual-upload', [AdminController::class, 'uploadManualOnboardingPackage'])->name('admin.manual-onboarding-package');
    Route::post('/application/{id}/request-changes', [AdminController::class, 'requestApplicationChanges'])->name('admin.request-changes');

    // Manual Document Generation
    Route::post('/application/{id}/generate-affidavit', [AdminController::class, 'generateAffidavit'])->name('admin.generate-affidavit');
    Route::post('/application/{id}/generate-poa', [AdminController::class, 'generatePOA'])->name('admin.generate-poa');

    // Individual Document Approval/Rejection
    Route::post('/document/{id}/approve', [AdminController::class, 'approveDocument'])->name('admin.approve-document');
    Route::post('/document/{id}/reject', [AdminController::class, 'rejectDocument'])->name('admin.reject-document');
    Route::post('/document/{id}/verify', [AdminController::class, 'verifyDocument'])->name('admin.verify-document');
    Route::post('/application/{id}/documents/review', [AdminController::class, 'bulkReviewDocuments'])->name('admin.documents.review');

    // Filing
    Route::post('/application/{id}/file', [AdminController::class, 'fileApplication'])->name('admin.file');
    Route::post('/application/{id}/file/complete', [AdminController::class, 'completeFiledStage'])->name('admin.file-complete');
    Route::post('/application/{id}/status', [AdminController::class, 'updateStatus'])->name('admin.update-status');
    Route::post('/application/{id}/kyc-verify', [AdminController::class, 'verifyKyc'])->name('admin.kyc-verify');
    Route::post('/application/{id}/strategy-complete', [AdminController::class, 'completeStrategy'])->name('admin.strategy-complete');
    Route::post('/application/{id}/search-report', [AdminController::class, 'uploadSearchReport'])->name('admin.upload-search-report');
    Route::post('/application/{id}/publish-draft', [AdminController::class, 'publishDraft'])->name('admin.publish-draft');
    Route::post('/application/{id}/post-filing/{stage}', [AdminController::class, 'updatePostFilingStage'])->name('admin.post-filing.update-stage');

    // Payment Approval/Rejection
    Route::get('/payment/{payment}/invoice', [PaymentController::class, 'viewAdminInvoice'])->name('admin.payment.invoice');
    Route::post('/payment/{id}/approve', [AdminController::class, 'approvePayment'])->name('admin.approve-payment');
    Route::post('/payment/{id}/reject', [AdminController::class, 'rejectPayment'])->name('admin.reject-payment');
    Route::get('/trademark/{id}/image/view', [TrademarkController::class, 'viewTrademarkImage'])->name('admin.trademark.image.view');
    Route::get('/trademark/{id}/proof-of-use/view', [TrademarkController::class, 'viewProofOfUse'])->name('admin.trademark.proof-of-use.view');
    Route::get('/documents/{document}/view', [UserDocumentController::class, 'view'])->name('admin.document.view');
    Route::get('/documents/{document}/download', [UserDocumentController::class, 'download'])->name('admin.document.download');

    Route::middleware('legacy.services')->group(function () {
    // Stuck / Delayed Trademark Recovery
    Route::get('/stuck-trademark-cases', [StuckTrademarkController::class, 'adminIndex'])->name('admin.stuck-trademark.index');
    Route::get('/trademark-opposition-cases', [TrademarkOppositionController::class, 'adminIndex'])->name('admin.trademark-opposition.index');
    Route::get('/trademark-opposition-cases/{case}', [TrademarkOppositionController::class, 'adminShow'])->name('admin.trademark-opposition.show');
    Route::delete('/trademark-opposition-cases/{case}/additional-documents/{document}', [TrademarkOppositionController::class, 'destroyAdditionalDocument'])->name('admin.trademark-opposition.additional-document.destroy');
    Route::post('/trademark-opposition-cases/{case}/documents/review', [TrademarkOppositionController::class, 'adminReviewDocuments'])->name('admin.trademark-opposition.documents.review');
    Route::post('/trademark-opposition-cases/{case}/evidence/review', [TrademarkOppositionController::class, 'adminReviewEvidence'])->name('admin.trademark-opposition.evidence.review');
    Route::post('/trademark-opposition-cases/{case}/analysis', [TrademarkOppositionController::class, 'adminAnalyze'])->name('admin.trademark-opposition.analysis');
    Route::post('/trademark-opposition-cases/{case}/request-evidence', [TrademarkOppositionController::class, 'adminRequestEvidence'])->name('admin.trademark-opposition.request-evidence');
    Route::post('/trademark-opposition-cases/{case}/risk', [TrademarkOppositionController::class, 'adminRisk'])->name('admin.trademark-opposition.risk');
    Route::post('/trademark-opposition-cases/{case}/pricing', [TrademarkOppositionController::class, 'adminPricing'])->name('admin.trademark-opposition.pricing');
    Route::post('/trademark-opposition-cases/{case}/draft', [TrademarkOppositionController::class, 'adminUploadDraft'])->name('admin.trademark-opposition.draft');
    Route::post('/trademark-opposition-cases/{case}/file', [TrademarkOppositionController::class, 'adminFile'])->name('admin.trademark-opposition.file');
    Route::post('/trademark-opposition-cases/{case}/tracking', [TrademarkOppositionController::class, 'adminTracking'])->name('admin.trademark-opposition.tracking');
    Route::get('/trademark-opposition-cases/{case}/documents/{kind}/{id}', [TrademarkOppositionController::class, 'viewDocument'])->name('admin.trademark-opposition.document.view');
    Route::get('/trademark-opposition-cases/{case}/file/{file}', [TrademarkOppositionController::class, 'viewCaseFile'])->name('admin.trademark-opposition.file.view');
    Route::get('/trademark-opposition-oppose-cases', [TrademarkOppositionController::class, 'adminOpposeIndex'])->name('admin.trademark-opposition.oppose.index');
    Route::get('/trademark-opposition-oppose-cases/{case}', [TrademarkOppositionController::class, 'adminOpposeShow'])->name('admin.trademark-opposition.oppose.show');
    Route::post('/trademark-opposition-oppose-cases/{case}/review', [TrademarkOppositionController::class, 'adminOpposeReview'])->name('admin.trademark-opposition.oppose.review');
    Route::post('/trademark-opposition-oppose-cases/{case}/evidence/review', [TrademarkOppositionController::class, 'adminOpposeReviewEvidence'])->name('admin.trademark-opposition.oppose.evidence.review');
    Route::post('/trademark-opposition-oppose-cases/{case}/recommendation', [TrademarkOppositionController::class, 'adminOpposeRecommendation'])->name('admin.trademark-opposition.oppose.recommendation');
    Route::post('/trademark-opposition-oppose-cases/{case}/request-evidence', [TrademarkOppositionController::class, 'adminOpposeRequestEvidence'])->name('admin.trademark-opposition.oppose.request-evidence');
    Route::post('/trademark-opposition-oppose-cases/{case}/additional-documents', [TrademarkOppositionController::class, 'adminOpposeAdditionalDocuments'])->name('admin.trademark-opposition.oppose.additional-documents');
    Route::post('/trademark-opposition-oppose-cases/{case}/pricing', [TrademarkOppositionController::class, 'adminOpposePricing'])->name('admin.trademark-opposition.oppose.pricing');
    Route::post('/trademark-opposition-oppose-cases/{case}/draft-status', [TrademarkOppositionController::class, 'adminOpposeDraftStatus'])->name('admin.trademark-opposition.oppose.draft-status');
    Route::post('/trademark-opposition-oppose-cases/{case}/draft', [TrademarkOppositionController::class, 'adminOpposeUploadDraft'])->name('admin.trademark-opposition.oppose.draft');
    Route::post('/trademark-opposition-oppose-cases/{case}/file', [TrademarkOppositionController::class, 'adminOpposeFile'])->name('admin.trademark-opposition.oppose.file');
    Route::post('/trademark-opposition-oppose-cases/{case}/tracking', [TrademarkOppositionController::class, 'adminOpposeTracking'])->name('admin.trademark-opposition.oppose.tracking');
    Route::post('/trademark-opposition-oppose-cases/{case}/internal-note', [TrademarkOppositionController::class, 'adminOpposeInternalNote'])->name('admin.trademark-opposition.oppose.internal-note');
    Route::post('/trademark-opposition-oppose-cases/{case}/third-party-action', [TrademarkOppositionController::class, 'adminOpposeThirdPartyAction'])->name('admin.trademark-opposition.oppose.third-party-action');
    Route::post('/trademark-opposition-oppose-cases/{case}/third-party-evidence-request', [TrademarkOppositionController::class, 'adminOpposeThirdPartyEvidenceRequest'])->name('admin.trademark-opposition.oppose.third-party-evidence-request');
    Route::post('/trademark-opposition-oppose-cases/{case}/evidence-filed', [TrademarkOppositionController::class, 'adminOpposeEvidenceFiled'])->name('admin.trademark-opposition.oppose.evidence-filed');
    Route::post('/trademark-opposition-oppose-cases/{case}/registry-updates', [TrademarkOppositionController::class, 'adminOpposeRegistryUpdate'])->name('admin.trademark-opposition.oppose.registry-update');
    Route::get('/examination-report-replies', [ExaminationReportReplyController::class, 'adminIndex'])->name('admin.examination-reply.index');
    Route::get('/examination-report-replies/{case}', [ExaminationReportReplyController::class, 'adminShow'])->name('admin.examination-reply.show');
    Route::post('/examination-report-replies/{case}/stage-draft', [ExaminationReportReplyController::class, 'adminSaveStageDraft'])->name('admin.examination-reply.stage-draft');
    Route::post('/examination-report-replies/{case}/internal-note', [ExaminationReportReplyController::class, 'adminSaveInternalNote'])->name('admin.examination-reply.internal-note');
    Route::post('/examination-report-replies/{case}/additional-documents', [ExaminationReportReplyController::class, 'adminStoreAdditionalDocuments'])->name('admin.examination-reply.additional-documents');
    Route::post('/examination-report-replies/{case}/documents/review', [ExaminationReportReplyController::class, 'adminReviewDocuments'])->name('admin.examination-reply.documents.review');
    Route::post('/examination-report-replies/{case}/start-review', [ExaminationReportReplyController::class, 'adminStartReview'])->name('admin.examination-reply.start-review');
    Route::post('/examination-report-replies/{case}/objection', [ExaminationReportReplyController::class, 'adminSaveObjection'])->name('admin.examination-reply.objection');
    Route::post('/examination-report-replies/{case}/evidence-review', [ExaminationReportReplyController::class, 'adminReviewEvidence'])->name('admin.examination-reply.evidence-review');
    Route::post('/examination-report-replies/{case}/risk', [ExaminationReportReplyController::class, 'adminRisk'])->name('admin.examination-reply.risk');
    Route::post('/examination-report-replies/{case}/pricing', [ExaminationReportReplyController::class, 'adminPricing'])->name('admin.examination-reply.pricing');
    Route::post('/examination-report-replies/{case}/draft', [ExaminationReportReplyController::class, 'adminDraft'])->name('admin.examination-reply.draft');
    Route::post('/examination-report-replies/{case}/filing', [ExaminationReportReplyController::class, 'adminFiling'])->name('admin.examination-reply.filing');
    Route::post('/examination-report-replies/{case}/awaiting-registry', [ExaminationReportReplyController::class, 'adminAwaitingRegistry'])->name('admin.examination-reply.awaiting-registry');
    Route::post('/examination-report-replies/{case}/registry-update', [ExaminationReportReplyController::class, 'adminRegistryUpdate'])->name('admin.examination-reply.registry-update');
    Route::post('/examination-report-replies/{case}/hearing', [ExaminationReportReplyController::class, 'adminHearing'])->name('admin.examination-reply.hearing');
    Route::post('/examination-report-replies/{case}/close', [ExaminationReportReplyController::class, 'adminClose'])->name('admin.examination-reply.close');
    Route::get('/examination-report-reply-documents/{document}/view', [ExaminationReportReplyController::class, 'viewDocument'])->name('admin.examination-reply.document.view');
    Route::get('/examination-report-reply-documents/{document}/download', [ExaminationReportReplyController::class, 'downloadDocument'])->name('admin.examination-reply.document.download');
    Route::delete('/examination-report-reply-documents/{document}', [ExaminationReportReplyController::class, 'removeDocument'])->name('admin.examination-reply.document.destroy');
    Route::get('/stuck-trademark-cases/{case}', [StuckTrademarkController::class, 'adminShow'])->name('admin.stuck-trademark.show');
    Route::post('/stuck-trademark-cases/{case}', [StuckTrademarkController::class, 'adminUpdate'])->name('admin.stuck-trademark.update');
    Route::post('/stuck-trademark-cases/{case}/audit-report', [StuckTrademarkController::class, 'adminUploadAuditReport'])->name('admin.stuck-trademark.audit-report');
    Route::post('/stuck-trademark-cases/{case}/documents/bulk-verify', [StuckTrademarkController::class, 'adminBulkVerifyDocuments'])->name('admin.stuck-trademark.documents.bulk-verify');
    Route::post('/stuck-trademark-documents/{document}/verify', [StuckTrademarkController::class, 'adminVerifyDocument'])->name('admin.stuck-trademark.document.verify');
    Route::get('/stuck-trademark-documents/{document}/view', [StuckTrademarkController::class, 'viewDocument'])->name('admin.stuck-trademark.document.view');
    Route::get('/stuck-trademark-cases/{case}/audit-report/view', [StuckTrademarkController::class, 'viewAuditReport'])->name('admin.stuck-trademark.audit-report.view');
    Route::get('/stuck-trademark-cases/{case}/execution', [AdminTrademarkExecutionController::class, 'showExecution'])->name('admin.trademark-execution.show');
    Route::post('/stuck-trademark-cases/{case}/execution/start', [AdminTrademarkExecutionController::class, 'startExecution'])->name('admin.trademark-execution.start');
    Route::post('/stuck-trademark-cases/{case}/execution/required-actions', [AdminTrademarkExecutionController::class, 'saveActions'])->name('admin.trademark-execution.actions');
    Route::post('/stuck-trademark-cases/{case}/execution/action-progress', [AdminTrademarkExecutionController::class, 'submitActionProgress'])->name('admin.trademark-execution.action-progress');
    Route::post('/stuck-trademark-cases/{case}/execution/status-monitoring', [AdminTrademarkExecutionController::class, 'completeStatusMonitoring'])->name('admin.trademark-execution.status-monitoring');
    Route::post('/stuck-trademark-cases/{case}/execution/additional-action', [AdminTrademarkExecutionController::class, 'resolveAdditionalAction'])->name('admin.trademark-execution.additional-action');
    Route::post('/stuck-trademark-cases/{case}/monitoring-update', [AdminTrademarkExecutionController::class, 'submitMonitoringUpdate'])->name('admin.trademark-execution.monitoring-update');
    Route::post('/stuck-trademark-cases/{case}/resolution-report', [AdminTrademarkExecutionController::class, 'submitResolutionReport'])->name('admin.trademark-execution.resolution-report');
    });

});
