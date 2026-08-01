<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;

class CountryResolver
{
    public function resolve(Request $request): array
    {
        foreach ($this->headerNames() as $headerName) {
            $countryCode = $this->normalizeCountryCode($request->headers->get($headerName));

            if ($countryCode !== null) {
                return $this->payload($countryCode, $headerName);
            }
        }

        return config('analytics.unknown');
    }

    private function headerNames(): array
    {
        $headers = config('analytics.country_header_names', []);

        if (config('app.env') !== 'production') {
            array_unshift($headers, config('analytics.local_country_header_name'));
        }

        return array_filter($headers);
    }

    private function normalizeCountryCode(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return null;
        }

        if (in_array($countryCode, ['XX', 'ZZ', 'T1', 'A1', 'A2'], true)) {
            return null;
        }

        return $countryCode;
    }

    private function payload(string $countryCode, string $source): array
    {
        return [
            'country_code' => $countryCode,
            'country_name' => config("analytics.supported_country_names.$countryCode", 'Unknown'),
            'country_source' => $source,
        ];
    }
}
