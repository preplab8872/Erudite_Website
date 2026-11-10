<?php

namespace App\Http\Middleware\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IpReputationService
{
    private const NEUTRAL_SCORE = 50;
    private const API_URL = 'https://api.ip2location.io/';

    public function __construct(
        private CookieTrustService $cookieTrustService,
        private ClientIpResolver $clientIpResolver
    ) {}

    public function getscore(Request $request): int
    {
        return $this->getAssessment($request)['score'];
    }

    public function getAssessment(Request $request): array
    {
        // Check device-side 24-hour cache first.
        $cachedScore = $this->cookieTrustService
            ->getCachedIpReputationScore($request);

        if ($cachedScore !== null) {
            return [
                'ip' => $this->clientIpResolver->resolve($request),
                'fraud_score' => null,
                'score' => $cachedScore,
                'classification' => $this->classify($cachedScore)['name'],
                'criteria' => 'Loaded from encrypted visitor cookie.',
                'signals' => [],
                'status' => 'device_cache',
            ];
        }

        // Resolve actual visitor IP, not Cloudflare's edge IP.
        $ip = $this->clientIpResolver->resolve($request);

        if ($ip === null) {
            return $this->unknownAssessment(
                null,
                'Could not resolve visitor IP.'
            );
        }

        $isPublicIp = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublicIp === false) {
            return $this->unknownAssessment(
                $ip,
                'Private or reserved IP address.'
            );
        }

        $apiKey = config('services.ip2location.key');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return $this->unknownAssessment(
                $ip,
                'IP2Location API key is not configured.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(4)
                ->timeout(8)
                ->get(self::API_URL, [
                    'key' => $apiKey,
                    'ip' => $ip,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                return $this->unknownAssessment(
                    $ip,
                    'IP2Location request failed.'
                );
            }

            $data = $response->json();

            if (!is_array($data) || isset($data['error'])) {
                return $this->unknownAssessment(
                    $ip,
                    'IP2Location returned an error.'
                );
            }

            $fraudScore = $data['fraud_score'] ?? null;

            // Quota exhausted or fraud_score unavailable.
            if (!is_numeric($fraudScore)) {
                return $this->unknownAssessment(
                    $ip,
                    'Fraud score unavailable; using neutral score.'
                );
            }

            $fraudScore = max(
                0,
                min(99, (int) round((float) $fraudScore))
            );

            // Convert fraud score into reputation score.
            $reputationScore = (int) round(
                100 - (($fraudScore / 99) * 100)
            );

            $classification = $this->classify($reputationScore);

            $proxy = is_array($data['proxy'] ?? null)
                ? $data['proxy']
                : [];

            return [
                'ip' => $ip,
                'fraud_score' => $fraudScore,
                'score' => $reputationScore,
                'classification' => $classification['name'],
                'criteria' => $classification['criteria'],

                'signals' => [
                    'is_proxy' => $data['is_proxy'] ?? false,
                    'proxy_type' => $proxy['proxy_type'] ?? null,
                    'threat' => $proxy['threat'] ?? null,
                    'is_vpn' => $proxy['is_vpn'] ?? false,
                    'is_tor' => $proxy['is_tor'] ?? false,
                    'is_data_center' => $proxy['is_data_center'] ?? false,
                    'is_public_proxy' => $proxy['is_public_proxy'] ?? false,
                    'is_web_proxy' => $proxy['is_web_proxy'] ?? false,
                    'is_residential_proxy' => $proxy['is_residential_proxy'] ?? false,
                    'is_ai_crawler' => $proxy['is_ai_crawler'] ?? false,
                    'is_spammer' => $proxy['is_spammer'] ?? false,
                    'is_scanner' => $proxy['is_scanner'] ?? false,
                    'is_botnet' => $proxy['is_botnet'] ?? false,
                    'is_bogon' => $proxy['is_bogon'] ?? false,
                ],

                'status' => 'available',
            ];

        } catch (Throwable $e) {
            Log::warning('IP2Location lookup failed.', [
                'message' => $e->getMessage(),
            ]);

            return $this->unknownAssessment(
                $ip,
                'IP2Location lookup failed; using neutral score.'
            );
        }
    }

    private function classify(int $score): array
    {
        if ($score >= 81) {
            return [
                'name' => 'GOOD',
                'criteria' => 'Low reported fraud risk (0-19).',
            ];
        }

        if ($score >= 50) {
            return [
                'name' => 'OKISH',
                'criteria' => 'Moderate or inconclusive reported fraud risk (20-49).',
            ];
        }

        if ($score >= 25) {
            return [
                'name' => 'BAD',
                'criteria' => 'Elevated reported fraud risk (50-74).',
            ];
        }

        return [
            'name' => 'VERY_BAD',
            'criteria' => 'Very high reported fraud risk (75-99).',
        ];
    }

    private function unknownAssessment(
        ?string $ip,
        string $reason
    ): array {
        return [
            'ip' => $ip,
            'fraud_score' => null,
            'score' => self::NEUTRAL_SCORE,
            'classification' => 'UNKNOWN',
            'criteria' => $reason,
            'signals' => [],
            'status' => 'unavailable',
        ];
    }
}