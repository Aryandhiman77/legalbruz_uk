<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyServicesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeService = $request->routeIs('trademark.opposition-management')
            || $request->routeIs('trademark-opposition.*')
            || $request->routeIs('examination-reply.*')
            || $request->routeIs('admin.trademark-opposition.*')
            || $request->routeIs('admin.examination-reply.*');

        abort_unless($activeService || config('uk_site.legacy_services_enabled'), 404);

        return $next($request);
    }
}
