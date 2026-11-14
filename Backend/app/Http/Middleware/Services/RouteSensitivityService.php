<?php

namespace App\Http\Middleware\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RouteSensitivityService
{
    public function __construct(
        private CookieTrustService $cookieTrustService,
        private IpReputationService $ipReputationService,
        private HumanVerificationService $humanVerificationService
    ) {}

    /**
     * Evaluate all three inputs on every protected request.
     * Returns the existing 1 = Allow / 0 = Deny decision.
     */
    public function isSensitiveRoute(Request $request): int
    {
        return $this->evaluate($request)['route_access'];
    }

    /**
     * Detailed evaluation used by RiskCheckMiddleware to distinguish
     * a missing/failed human check from a score-based denial.
     */
    public function evaluate(Request $request): array
    {
        $cookieScore = 50;
        $ipScore = 50;
        $humanScore = 0;
        $humanSource = 'missing';

        try {
            // 1. C: always independently recalculate cookie trust.
            $cookieScore = max(0, min(
                100,
                $this->cookieTrustService->calculateScore($request)
            ));

            // 2. I: use the valid device-side 24-hour cache or look up IP2Location.
            $ipAssessment = $this->ipReputationService->getAssessment($request);
            $ipScore = max(0, min(
                100,
                (int) ($ipAssessment['score'] ?? 50)
            ));

            $cacheIpReputation = in_array(
                $ipAssessment['status'] ?? null,
                ['available', 'device_cache'],
                true
            );

            // 3. H: a newly submitted Turnstile token forces fresh validation.
            // Otherwise, reuse the cookie's result while its 24-hour cache is valid.
            $hasFreshToken = $this->humanVerificationService
                ->hasResponseToken($request);

            $cachedHumanScore = $this->cookieTrustService
                ->getCachedHumanVerificationScore($request);

            $cacheHumanVerification = false;

            if ($hasFreshToken) {
                $humanScore = $this->humanVerificationService
                    ->humanScore($request);
                $humanSource = 'fresh';
                $cacheHumanVerification = true;
            } elseif ($cachedHumanScore !== null) {
                $humanScore = $cachedHumanScore;
                $humanSource = 'cookie_cache';
            } else {
                // No token and no usable cache: require the security-check flow.
                // This 0 is used for the current decision but is NOT cached.
                $humanScore = 0;
                $humanSource = 'missing';
            }

            // 4. Existing final formula and threshold are unchanged.
            $finalScore = (
                ($cookieScore + ($humanScore * 100)) / 400
            ) + ($ipScore / 200);

            // Preserve the existing hard requirement that H must equal 1.
            $routeAccess = (
                $humanScore === 1
                && $finalScore >= 0.6
            ) ? 1 : 0;

            // Keep score diagnostics in the local Laravel log only. Do not
            // return the component scores to the browser.
            if (config('app.debug')) {
                Log::debug('Security risk evaluation completed.', [
                    'C' => $cookieScore,
                    'I' => $ipScore,
                    'H' => $humanScore,
                    'final_score' => $finalScore,
                    'route_access' => $routeAccess,
                    'human_source' => $humanSource,
                ]);
            }

            // 5. Update the same encrypted cookie; never store C or routeAccess.
            $this->cookieTrustService->generateCookie(
                $request,
                $ipScore,
                $cacheIpReputation,
                $humanScore,
                $cacheHumanVerification
            );

            return [
                'route_access' => $routeAccess,
                'cookie_score' => $cookieScore,
                'ip_reputation_score' => $ipScore,
                'human_verification_score' => $humanScore,
                'human_verification_source' => $humanSource,
                'final_score' => $finalScore,
            ];
        } catch (Throwable $e) {
            Log::error('Route sensitivity evaluation failed.', [
                'message' => $e->getMessage(),
            ]);

            return [
                'route_access' => 0,
                'cookie_score' => $cookieScore,
                'ip_reputation_score' => $ipScore,
                'human_verification_score' => 0,
                'human_verification_source' => 'error',
                'final_score' => 0.0,
            ];
        }
    }
}
