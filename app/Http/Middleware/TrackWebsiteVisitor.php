<?php

namespace App\Http\Middleware;

use App\Models\WebsiteVisitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackWebsiteVisitor
{
    private const COOKIE_NAME = 'legal_bruz_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            return $this->track($request, $response);
        } catch (\Throwable) {
            return $response;
        }
    }

    private function track(Request $request, Response $response): Response
    {
        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $visitorToken = $request->cookie(self::COOKIE_NAME)
            ?: $request->session()->get('website_visitor_token');
        $isNewToken = ! is_string($visitorToken) || ! Str::isUuid($visitorToken);

        if ($isNewToken) {
            $visitorToken = (string) Str::uuid();
        }

        $request->session()->put('website_visitor_token', $visitorToken);

        $routeName = (string) optional($request->route())->getName();
        $serviceKey = $this->serviceKey($request, $routeName);
        $now = now();
        $path = Str::limit('/'.ltrim($request->path(), '/'), 500, '');

        $visitor = WebsiteVisitor::firstOrCreate(
            ['visitor_token' => $visitorToken],
            [
                'first_path' => $path,
                'last_path' => $path,
                'page_views' => 0,
                'first_visited_at' => $now,
                'last_visited_at' => $now,
            ]
        );

        $updates = [
            'last_path' => $path,
            'last_visited_at' => $now,
            'page_views' => $visitor->page_views + 1,
        ];

        if ($serviceKey !== null && $visitor->service_first_visited_at === null) {
            $updates['service_first_visited_at'] = $now;
            $updates['first_service'] = $serviceKey;
        }

        $visitor->update($updates);

        if ($serviceKey !== null && Schema::hasTable('website_service_visits')) {
            $serviceVisit = $visitor->serviceVisits()->firstOrCreate(
                ['service_key' => $serviceKey],
                [
                    'first_path' => $path,
                    'last_path' => $path,
                    'page_views' => 0,
                    'first_visited_at' => $now,
                    'last_visited_at' => $now,
                ]
            );

            $serviceVisit->update([
                'last_path' => $path,
                'last_visited_at' => $now,
                'page_views' => $serviceVisit->page_views + 1,
            ]);
        }

        if ($isNewToken) {
            $response->headers->setCookie(cookie(
                self::COOKIE_NAME,
                $visitorToken,
                60 * 24 * 365,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! Schema::hasTable('website_visitors')
            || ! $request->isMethod('GET')
            || $request->expectsJson()
            || $request->ajax()
            || $response->getStatusCode() >= 400) {
            return false;
        }

        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'admin.')
            || in_array($routeName, ['storage.public.view', 'sitemap', 'robots'], true)) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function serviceKey(Request $request, string $routeName): ?string
    {
        if ($routeName === 'contact' && $request->query('service') === 'classification_specification') {
            return 'classification_specification';
        }

        if (str_starts_with($routeName, 'trademark-search-report.')) {
            return 'trademark_search_report';
        }

        if (str_starts_with($routeName, 'book-call.')) {
            return 'consultation_call';
        }

        if (in_array($routeName, [
            'trademark.search-page',
            'trademark.type-selection',
            'trademark.application-form',
        ], true)) {
            return 'trademark_registration';
        }

        if ($request->is('services/trademark/filed-and-stuck')
            || str_starts_with($routeName, 'stuck-trademark.')) {
            return 'filed_stuck_recovery';
        }

        if ($request->is('services/trademark/opposition-management')
            || str_starts_with($routeName, 'trademark-opposition.')) {
            return 'opposition_management';
        }

        if ($request->is('services/trademark/examination-report-reply')
            || str_starts_with($routeName, 'examination-reply.')) {
            return 'examination_report_reply';
        }

        return null;
    }
}
