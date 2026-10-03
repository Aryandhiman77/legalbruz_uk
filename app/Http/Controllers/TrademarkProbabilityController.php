<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrademarkProbabilityRequest;
use App\Services\GeminiTrademarkInsightService;
use App\Services\TrademarkProbabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TrademarkProbabilityController extends Controller
{
    public function __construct(
        private readonly TrademarkProbabilityService $probabilityService,
        private readonly GeminiTrademarkInsightService $insightService,
    ) {}

    public function __invoke(TrademarkProbabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $options = [
            'source_type' => $validated['source_type'] ?? 'third_party',
            'gemini_model' => (string) config('services.gemini.model'),
            'insight_schema_version' => GeminiTrademarkInsightService::SCHEMA_VERSION,
        ];
        $cacheKey = $this->probabilityService->cacheKey(
            $validated['keyword'],
            $validated['data'],
            $options,
        );

        try {
            $analysis = Cache::remember(
                $cacheKey,
                now()->addMinutes(30),
                fn (): array => $this->probabilityService->analyze(
                    $validated['keyword'],
                    $validated['data'],
                    $options,
                ),
            );
        } catch (\Throwable) {
            $analysis = $this->probabilityService->analyze(
                $validated['keyword'],
                $validated['data'],
                $options,
            );
        }
        $analysis['ai_insights'] = $this->insightService->generate($analysis, $validated['data']);

        return response()->json([
            'success' => true,
            'analysis' => $analysis,
        ]);
    }

    public function downloadReport(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'analysis' => ['required', 'array'],
            'analysis.keyword' => ['nullable', 'string', 'max:255'],
            'analysis.registration_probability' => ['required', 'numeric', 'min:0', 'max:100'],
            'analysis.conflict_risk' => ['required', 'numeric', 'min:0', 'max:100'],
            'analysis.risk_level' => ['required', 'string', 'max:100'],
            'analysis.analysis_quality' => ['nullable', 'string', 'max:100'],
            'analysis.exact_active_word_marks' => ['nullable', 'integer', 'min:0'],
            'analysis.exact_active_device_marks' => ['nullable', 'integer', 'min:0'],
            'analysis.similar_active_marks' => ['nullable', 'integer', 'min:0'],
            'analysis.unique_active_classes' => ['nullable', 'integer', 'min:0'],
            'analysis.factors' => ['nullable', 'array', 'max:20'],
            'analysis.factors.*.label' => ['nullable', 'string', 'max:120'],
            'analysis.factors.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'analysis.ai_insights' => ['nullable', 'array'],
            'analysis.ai_insights.summary' => ['nullable', 'string', 'max:3000'],
            'analysis.ai_insights.reasons' => ['nullable', 'array', 'max:20'],
            'analysis.ai_insights.reasons.*.title' => ['nullable', 'string', 'max:160'],
            'analysis.ai_insights.reasons.*.detail' => ['nullable', 'string', 'max:1000'],
            'analysis.ai_insights.reasons.*.impact' => ['nullable', 'string', 'max:50'],
            'analysis.ai_insights.warnings' => ['nullable', 'array', 'max:20'],
            'analysis.ai_insights.warnings.*.title' => ['nullable', 'string', 'max:160'],
            'analysis.ai_insights.warnings.*.detail' => ['nullable', 'string', 'max:1000'],
            'analysis.ai_insights.warnings.*.action' => ['nullable', 'string', 'max:1000'],
            'analysis.disclaimer' => ['nullable', 'string', 'max:2000'],
            'records' => ['nullable', 'array', 'max:25'],
            'records.*.application_id' => ['nullable', 'string', 'max:100'],
            'records.*.trademark_name' => ['nullable', 'string', 'max:255'],
            'records.*.status' => ['nullable', 'string', 'max:100'],
            'records.*.class' => ['nullable', 'string', 'max:100'],
            'records.*.type' => ['nullable', 'string', 'max:100'],
            'records.*.proprietor' => ['nullable', 'string', 'max:500'],
        ]);

        $analysis = $validated['analysis'];
        $analysis['keyword'] = $analysis['keyword'] ?? $validated['keyword'];
        $analysis['disclaimer'] = $analysis['disclaimer'] ?? TrademarkProbabilityService::DISCLAIMER;

        $pdf = Pdf::loadView('trademark.probability-report', [
            'keyword' => $validated['keyword'],
            'analysis' => $analysis,
            'records' => $validated['records'] ?? [],
            'generatedAt' => now(config('app.timezone', 'Europe/London'))->format('d M Y, h:i A T'),
            'formalDisclaimer' => TrademarkProbabilityService::DISCLAIMER,
        ])->setPaper('a4');

        $filename = Str::slug($validated['keyword']) ?: 'trademark';

        return $pdf->download($filename.'-probability-report.pdf');
    }
}
