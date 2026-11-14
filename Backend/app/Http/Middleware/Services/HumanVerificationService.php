<?php

namespace App\Http\Middleware\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HumanVerificationService
{
    private const SITEVERIFY_URL =
        'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private ClientIpResolver $clientIpResolver
    ) {}

    /**
     * Whether this request contains a Turnstile response to verify.
     * A missing token is different from a submitted token that failed.
     */
    public function hasResponseToken(Request $request): bool
    {
        $token = $request->input('cf-turnstile-response');

        return is_string($token)
            && trim($token) !== ''
            && strlen($token) <= 2048;
    }

    /**
     * 1 = Turnstile passed; 0 = submitted token failed or could not be verified.
     * Call this only when a response token has been submitted.
     */
    public function humanScore(Request $request): int
    {
        $token = $request->input('cf-turnstile-response');
        $secret = config('services.turnstile.secret');

        if (!$this->hasResponseToken($request)) {
            Log::warning(
                'Turnstile verification skipped: response token missing or invalid.',
                [
                    'content_type' => $request->header('Content-Type'),
                    'request_is_json' => $request->isJson(),
                ]
            );

            return 0;
        }

        if (!is_string($secret) || trim($secret) === '') {
            Log::error(
                'Turnstile verification skipped: secret key is not configured.'
            );

            return 0;
        }

        $payload = [
            'secret' => trim($secret),
            'response' => trim($token),
        ];

        // Siteverify's remoteip field is optional. Do not send loopback,
        // private, or reserved IPs from local development to Cloudflare.
        $ip = $this->clientIpResolver->resolve($request);

        if (
            $ip !== null
            && filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )
        ) {
            $payload['remoteip'] = $ip;
        }

        try {
$caBundle = config('services.turnstile.ca_bundle');

if (
    is_string($caBundle)
    && $caBundle !== ''
    && !is_file($caBundle)
) {
    Log::error('Turnstile CA bundle file does not exist.', [
        'path' => $caBundle,
    ]);

    return 0;
}

$verifyOption = (
    is_string($caBundle) && $caBundle !== ''
) ? $caBundle : true;

$response = Http::asForm()
    ->acceptJson()
    ->withOptions([
        'verify' => $verifyOption,
    ])
    ->connectTimeout(5)
    ->timeout(10)
    ->post(self::SITEVERIFY_URL, $payload);

            if (!$response->successful()) {
                Log::warning(
                    'Turnstile Siteverify returned an HTTP error.',
                    [
                        'status' => $response->status(),
                        'response_body' => substr($response->body(), 0, 500),
                    ]
                );

                return 0;
            }

            $result = $response->json();

            if (!is_array($result)) {
                Log::warning(
                    'Turnstile Siteverify returned an unreadable response.',
                    [
                        'content_type' => $response->header('Content-Type'),
                        'response_body' => substr($response->body(), 0, 500),
                    ]
                );

                return 0;
            }

            if (($result['success'] ?? false) === true) {
                Log::debug(
                    'Turnstile Siteverify accepted the token.',
                    [
                        'hostname' => $result['hostname'] ?? null,
                        'challenge_ts' => $result['challenge_ts'] ?? null,
                        'remote_ip_sent' => array_key_exists(
                            'remoteip',
                            $payload
                        ),
                    ]
                );

                return 1;
            }

            // Never log the submitted token or secret.
            Log::warning(
                'Turnstile Siteverify rejected the token.',
                [
                    'error_codes' => $result['error-codes'] ?? [],
                    'hostname' => $result['hostname'] ?? null,
                    'token_length' => strlen(trim($token)),
                    'remote_ip_sent' => array_key_exists(
                        'remoteip',
                        $payload
                    ),
                ]
            );

            return 0;

        } catch (Throwable $e) {
            Log::error(
                'Turnstile Siteverify request failed before validation completed.',
                [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]
            );

            return 0;
        }
    }
}