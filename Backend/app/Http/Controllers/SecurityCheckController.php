<?php

namespace App\Http\Controllers;

use App\Http\Middleware\Services\ClientIpResolver;
use App\Http\Middleware\Services\RouteSensitivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityCheckController extends Controller
{
    public function show(Request $request): Response
    {
        // Versioned key forces one fresh explicit choice after this consent-flow update.
        if ($request->boolean('manage')) {
            $request->session()->forget('visitor_fingerprint_consent_v2');
        }

        $consentChoice = $request->session()->has('visitor_fingerprint_consent_v2')
            ? (bool) $request->session()->get('visitor_fingerprint_consent_v2')
            : null;

        return response()->view('security-check', [
            'siteKey' => config('services.turnstile.key'),
            'consentChoice' => $consentChoice,
            'returnTo' => $this->safeReturnPath(
                $request->query('return', '/')
            ),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    // Diagnostic endpoint for checking the resolved visitor IP.
    public function context(
        Request $request,
        ClientIpResolver $clientIpResolver
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'ip' => $clientIpResolver->resolve($request),
        ])->header(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );
    }

    public function saveConsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'allow_cookies' => ['required', 'boolean'],
        ]);

        $allowCookies = (bool) $validated['allow_cookies'];

        $request->session()->put(
            'visitor_fingerprint_consent_v2',
            $allowCookies
        );

        // The essential encrypted visitor_trust cookie remains active either way.
        // This preference controls optional device-fingerprint collection only.

        return response()->json([
            'success' => true,
            'fingerprint_consent' => $allowCookies,
        ]);
    }

    public function evaluate(
        Request $request,
        RouteSensitivityService $routeSensitivityService
    ): JsonResponse {
        if (!$request->session()->has('visitor_fingerprint_consent_v2')) {
            return response()->json([
                'success' => false,
                'message' => 'Choose your cookie preference first.',
            ], 409);
        }

        $request->validate([
            'cf-turnstile-response' => [
                'required',
                'string',
                'max:2048',
            ],

            'device_fingerprint' => [
                'sometimes',
                'array',
            ],

            'device_fingerprint.canvas_hash' => [
                'nullable',
                'string',
                'max:128',
            ],

            'device_fingerprint.screen' => [
                'nullable',
                'string',
                'max:100',
            ],

            'device_fingerprint.timezone' => [
                'nullable',
                'string',
                'max:100',
            ],

            'device_fingerprint.language' => [
                'nullable',
                'string',
                'max:50',
            ],
        ]);

        $evaluation = $routeSensitivityService->evaluate($request);
        $decision = (int) ($evaluation['route_access'] ?? 0);
        $humanPassed = (int) ($evaluation['human_verification_score'] ?? 0) === 1;

        return response()->json([
            'success' => true,
            'approved' => $decision === 1,
            'route_access' => $decision,
            'message' => $decision === 1
                ? 'Verification successful.'
                : ($humanPassed
                    ? 'Human verification passed, but the risk score did not meet the access threshold.'
                    : 'Human verification could not be validated. Please complete the challenge again.'),
        ])->header(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );
    }

    private function safeReturnPath(mixed $path): string
    {
        if (
            !is_string($path)
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\r\n]/', $path)
        ) {
            return '/';
        }

        return $path;
    }
}