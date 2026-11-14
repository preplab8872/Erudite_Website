<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Services\RouteSensitivityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RiskCheckMiddleware
{
    public function __construct(
        private RouteSensitivityService $routeSensitivityService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // The security page must remain reachable so the visitor can complete
        // Turnstile and return to the originally requested protected route.
        if ($request->routeIs('security.check', 'security.verify')) {
            return $next($request);
        }

        // Re-evaluate C, I and H on every protected request.
        $evaluation = $this->routeSensitivityService->evaluate($request);

        if (($evaluation['route_access'] ?? 0) === 1) {
            return $next($request);
        }

        $verificationUrl = route('security.check', [
            'return' => $request->getRequestUri(),
        ]);

        // H=0 means verification is missing, expired, or failed. Send the
        // visitor to the challenge flow; a fresh submitted token bypasses
        // the cached H value and is verified by Siteverify.
        if (($evaluation['human_verification_score'] ?? 0) !== 1) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Security verification is required.',
                    'verification_url' => $verificationUrl,
                ], 403);
            }

            return redirect()->to($verificationUrl);
        }

        // Human verification passed, but the unchanged risk score threshold
        // was not met. Do not redirect back to the challenge page endlessly.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The security risk score does not permit this request.',
            ], 403);
        }

        return response(
            'Access denied by the security risk evaluation.',
            403
        );
    }
}
