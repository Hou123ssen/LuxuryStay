<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;

class CountryResolver
{
    public function resolve(Request $request): array
    {
        $regionName = $this->resolveLocationName($request, $this->regionHeaderNames());
        $cityName = $this->resolveLocationName($request, $this->cityHeaderNames());

        foreach ($this->headerNames() as $headerName) {
            $countryCode = $this->normalizeCountryCode($request->headers->get($headerName));

            if ($countryCode !== null) {
                return $this->payload($countryCode, $headerName, $regionName, $cityName);
            }
        }

        return array_merge(config('analytics.unknown'), [
            'region_name' => $regionName,
            'city_name' => $cityName,
        ]);
    }

    private function headerNames(): array
    {
        $headers = config('analytics.country_header_names', []);

        if (config('app.env') !== 'production') {
            array_unshift($headers, config('analytics.local_country_header_name'));
        }

        return array_filter($headers);
    }

    private function cityHeaderNames(): array
    {
        return $this->locationHeaderNames(
            config('analytics.city_header_names', []),
            config('analytics.local_city_header_name')
        );
    }

    private function regionHeaderNames(): array
    {
        return $this->locationHeaderNames(
            config('analytics.region_header_names', []),
            config('analytics.local_region_header_name')
        );
    }

    private function locationHeaderNames(array $headers, ?string $localHeaderName): array
    {
        if (config('app.env') !== 'production') {
            array_unshift($headers, $localHeaderName);
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

    private function resolveLocationName(Request $request, array $headerNames): string
    {
        foreach ($headerNames as $headerName) {
            $locationName = $this->sanitizeLocationName($request->headers->get($headerName));

            if ($locationName !== null) {
                return $locationName;
            }
        }

        return 'Unknown';
    }

    private function sanitizeLocationName(?string $locationName): ?string
    {
        if ($locationName === null) {
            return null;
        }

        $locationName = preg_replace('/[\p{C}]+/u', '', $locationName);
        $locationName = trim((string) $locationName);

        if ($locationName === '') {
            return null;
        }

        return mb_substr($locationName, 0, 100);
    }

    private function payload(string $countryCode, string $source, string $regionName, string $cityName): array
    {
        return [
            'country_code' => $countryCode,
            'country_name' => config("analytics.supported_country_names.$countryCode", 'Unknown'),
            'country_source' => $source,
            'region_name' => $regionName,
            'city_name' => $cityName,
        ];
    }
}
