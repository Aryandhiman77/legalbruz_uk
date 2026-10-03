<?php

namespace App\Http\Controllers;

use App\Services\UkPostcodeLookupService;
use Illuminate\Http\JsonResponse;

class UkPostcodeLookupController extends Controller
{
    /**
     * Resolve a UK postcode through Postcodes.io without exposing API details to the browser.
     */
    public function show(string $postcode, UkPostcodeLookupService $lookup): JsonResponse
    {
        $result = $lookup->lookup($postcode);
        $status = (int) ($result['http_status'] ?? 200);
        unset($result['http_status']);

        return response()->json($result, $status);
    }
}
