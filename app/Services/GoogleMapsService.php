<?php

declare(strict_types=1);

namespace PayTracker\Services;

use PayTracker\Logging\Logger;
use RuntimeException;

/**
 * Distance Matrix API client. Replaces the legacy `include/googleMapsAPI.php`
 * with an env-driven, dependency-injected service.
 *
 * Used as a fallback when `city_distances` has no recorded mileage for a
 * pair the driver entered. The resulting mile count is then cached back into
 * `city_distances` with source='google_maps' so future lookups hit the
 * local matrix instead of paying the API call (and the cents per request).
 *
 * Configuration:
 *   GOOGLE_MAPS_API_KEY in .env. The service is constructed with the key
 *   value (not the env name) so tests can inject a fixture.
 *
 * Hard rules:
 *   - Never log the API key.
 *   - Never throw for "API said no route" — that's a normal business
 *     outcome (driver typed two cities the API couldn't connect). Return
 *     null and let the caller decide.
 *   - DO throw for configuration errors (missing key) and transport errors
 *     (cURL fail, 5xx) — those are bugs, not data.
 */
final class GoogleMapsService
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    /**
     * @param string $apiKey  GOOGLE_MAPS_API_KEY from .env
     * @param int    $timeout HTTP timeout in seconds; DistanceMatrix is
     *                        typically sub-second but DreamHost's outbound
     *                        path can stall, so we cap at 10s by default.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly Logger $logger,
        private readonly int $timeout = 10,
    ) {
    }

    /**
     * Whether the service is configured. The load-entry write path checks
     * this before invoking; if false, an unknown city pair becomes a
     * user-facing validation error instead of a 500.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Look up road mileage between two human-readable city strings (e.g.
     * "Panama City, FL" → "Destin, FL"). Returns the integer mile count,
     * or null if Google returned NO_ROUTE / ZERO_RESULTS / unknown city.
     *
     * @throws RuntimeException for configuration or transport errors.
     */
    public function distanceMiles(string $from, string $to): ?int
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'GoogleMapsService called without GOOGLE_MAPS_API_KEY configured'
            );
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'origins'      => $from,
            'destinations' => $to,
            'units'        => 'imperial',
            'key'          => $this->apiKey,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed for GoogleMapsService');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'PayTracker/1.0 (+https://paytracker.xyz)',
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new RuntimeException("DistanceMatrix transport error: {$err}");
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("DistanceMatrix HTTP {$code}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('DistanceMatrix returned non-JSON body');
        }

        $topStatus = (string) ($decoded['status'] ?? '');
        if ($topStatus !== 'OK') {
            // REQUEST_DENIED / INVALID_REQUEST / OVER_QUERY_LIMIT — these are
            // configuration / quota problems, not "the cities aren't
            // connected". Surface them.
            throw new RuntimeException("DistanceMatrix top status: {$topStatus}");
        }

        /** @var array<int,array<string,mixed>>|null $rows */
        $rows = $decoded['rows'] ?? null;
        if (! is_array($rows) || ! isset($rows[0]['elements'][0])) {
            $this->logger->info('DistanceMatrix returned empty rows', [
                'from' => $from,
                'to'   => $to,
            ]);
            return null;
        }

        /** @var array<string,mixed> $element */
        $element = $rows[0]['elements'][0];
        $status  = (string) ($element['status'] ?? '');

        // NO_ROUTE / NOT_FOUND / ZERO_RESULTS — driver typed something the
        // routing graph can't connect. Normal business outcome.
        if ($status !== 'OK') {
            $this->logger->info('DistanceMatrix per-element non-OK', [
                'from'   => $from,
                'to'     => $to,
                'status' => $status,
            ]);
            return null;
        }

        // distance.value is meters. 1609.344 meters per mile.
        /** @var array<string,mixed>|null $distance */
        $distance = $element['distance'] ?? null;
        if (! is_array($distance) || ! isset($distance['value'])) {
            return null;
        }
        $meters = (int) $distance['value'];
        return (int) round($meters / 1609.344);
    }
}
