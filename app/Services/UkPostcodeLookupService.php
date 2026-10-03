<?php

namespace App\Services;

use App\Models\UkPostcode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class UkPostcodeLookupService
{
    private const POSTCODE_REGEX = '/^(?:GIR0AA|[A-Z]{1,2}[0-9][0-9A-Z]?[0-9][A-Z]{2})$/';

    public function lookup(string $postcode): array
    {
        $compactPostcode = $this->compact($postcode);

        if (! preg_match(self::POSTCODE_REGEX, $compactPostcode)) {
            return $this->error('Enter a valid UK postcode.', 422);
        }

        $stored = UkPostcode::query()
            ->where('postcode_key', $compactPostcode)
            ->first();

        if ($stored) {
            return $this->fromStoredPostcode($stored);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(6)
                ->get('https://api.postcodes.io/postcodes/' . rawurlencode($compactPostcode));
        } catch (ConnectionException) {
            return $this->error('Postcode lookup is temporarily unavailable. Please try again.', 503);
        }

        if ($response->status() === 404) {
            return $this->error('We could not find that UK postcode.', 404);
        }

        if (! $response->successful() || ! is_array($response->json('result'))) {
            return $this->error('Postcode lookup is temporarily unavailable. Please try again.', 502);
        }

        $result = $response->json('result');
        $nation = (string) ($result['country'] ?? '');

        if (! in_array($nation, ['England', 'Scotland', 'Wales', 'Northern Ireland'], true)) {
            return $this->error('Only United Kingdom postcodes are accepted.', 422);
        }

        $stored = UkPostcode::query()->updateOrCreate(
            ['postcode_key' => $compactPostcode],
            [
                'postcode' => $result['postcode'] ?? $this->format($compactPostcode),
                'nation' => $nation,
                'region' => $result['region'] ?? $result['admin_county'] ?? $nation,
                'town_city' => $result['admin_district']
                    ?? $result['parish']
                    ?? $result['region']
                    ?? $nation,
                'county' => $result['admin_county'] ?? null,
                'raw_payload' => $result,
                'last_verified_at' => now(),
            ]
        );

        return $this->fromStoredPostcode($stored);
    }

    private function compact(string $postcode): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', rawurldecode(trim($postcode))));
    }

    private function format(string $postcode): string
    {
        return substr($postcode, 0, -3) . ' ' . substr($postcode, -3);
    }

    private function error(string $message, int $status): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'http_status' => $status,
        ];
    }

    private function fromStoredPostcode(UkPostcode $postcode): array
    {
        return [
            'status' => 'success',
            'postcode' => $postcode->postcode,
            'nation' => $postcode->nation,
            'region' => $postcode->region,
            'town_city' => $postcode->town_city,
            'county' => $postcode->county,
            'http_status' => 200,
        ];
    }
}
