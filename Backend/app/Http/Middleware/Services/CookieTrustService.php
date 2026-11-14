<?php

namespace App\Http\Middleware\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Throwable;

class CookieTrustService
{
    private const COOKIE_NAME = 'visitor_trust';
    private const COOKIE_VERSION = 1;

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 90;
    private const COOKIE_REFRESH_THRESHOLD_MINUTES = 60 * 24 * 14;

    private const IP_REPUTATION_CACHE_SECONDS = 60 * 60 * 24;
    private const HUMAN_VERIFICATION_CACHE_SECONDS = 60 * 60 * 24;

    private const NEW_VISITOR_SCORE = 50;

    public function __construct(
        private DeviceFingerprintService $fingerprintService,
        private ClientIpResolver $clientIpResolver
    ) {}

    /**
     * Recalculate cookie trust independently on every evaluation.
     * No previously calculated cookie score is ever read from the payload.
     */
    public function calculateScore(Request $request): int
    {
        $now = time();
        $payload = $this->readCookie($request, $now);

        if ($payload === null) {
            return self::NEW_VISITOR_SCORE;
        }

        $currentFingerprint = $this->fingerprintService->collect($request);
        $previousFingerprint = $payload['device_fingerprint'];

        // A regular protected-page GET does not carry the JavaScript
        // fingerprint fields posted by the security-check page. In that case
        // there is not enough evidence to call the fingerprint a mismatch.
        // Return the existing neutral new-visitor score while still validating
        // the encrypted cookie identity, version and timestamps above.
        if (!$this->fingerprintService->hasSufficientComparableData(
            $previousFingerprint,
            $currentFingerprint
        )) {
            return self::NEW_VISITOR_SCORE;
        }

        // Preserve the existing cookie-trust components and weights.
        $tokenScore = 20;
        $secureScore = $this->secureFlagsScore();
        $fingerprintScore = $this->fingerprintService->calculateMatchScore(
            $previousFingerprint,
            $currentFingerprint
        );
        $maturityScore = $this->calculateMaturityScore(
            $payload['issued_at'],
            $now
        );

        return $this->normalizeScore(
            $tokenScore
            + $secureScore
            + $fingerprintScore
            + $maturityScore
        );
    }

    /**
     * Return the cached IP reputation score only when its HMAC matches
     * the current visitor IP and its recorded cache lifetime is <= 24 hours.
     */
    public function getCachedIpReputationScore(Request $request): ?int
    {
        $now = time();
        $payload = $this->readCookie($request, $now);

        if ($payload === null) {
            return null;
        }

        $score = $payload['ip_reputation_score'] ?? null;
        $expiresAt = $payload['ip_cache_expires_at']
            ?? $payload['ip_reputation_expires_at']
            ?? null;
        $storedIpHash = $payload['ip_hash']
            ?? $payload['ip_reputation_ip_hash']
            ?? null;
        $checkedAt = $payload['ip_checked_at'] ?? null;

        // Migrate older payloads which stored only the expiry timestamp.
        if (!is_int($checkedAt) && is_int($expiresAt)) {
            $checkedAt = $expiresAt - self::IP_REPUTATION_CACHE_SECONDS;
        }

        $currentIpHash = $this->currentIpHash($request);

        if (
            !is_int($score)
            || $score < 0
            || $score > 100
            || !is_int($checkedAt)
            || $checkedAt > $now + 60
            || !is_int($expiresAt)
            || $expiresAt <= $now
            || $expiresAt <= $checkedAt
            || ($expiresAt - $checkedAt) > self::IP_REPUTATION_CACHE_SECONDS
            || !is_string($storedIpHash)
            || $currentIpHash === null
            || !hash_equals($storedIpHash, $currentIpHash)
        ) {
            return null;
        }

        return $score;
    }

    /**
     * Return the cached Turnstile result (0 or 1) while its 24-hour
     * cache remains valid. A newly submitted token is verified separately.
     */
    public function getCachedHumanVerificationScore(
        Request $request
    ): ?int {
        $now = time();
        $payload = $this->readCookie($request, $now);

        if ($payload === null) {
            return null;
        }

        $score = $payload['human_verification_score'] ?? null;
        $checkedAt = $payload['human_checked_at'] ?? null;
        $expiresAt = $payload['human_cache_expires_at'] ?? null;

        if (
            !is_int($score)
            || !in_array($score, [0, 1], true)
            || !is_int($checkedAt)
            || $checkedAt > $now + 60
            || !is_int($expiresAt)
            || $expiresAt <= $now
            || $expiresAt <= $checkedAt
            || ($expiresAt - $checkedAt)
                > self::HUMAN_VERIFICATION_CACHE_SECONDS
        ) {
            return null;
        }

        return $score;
    }

    /**
     * Create/update the single encrypted visitor_trust cookie.
     * Cookie score and route decisions are deliberately never persisted.
     *
     * The cookie is used for essential security caching (IP/Human results).
     * Optional consent controls only the browser device-fingerprint fields.
     */
    public function generateCookie(
        Request $request,
        int $ipReputationScore,
        bool $cacheIpReputation,
        ?int $humanVerificationScore,
        bool $cacheHumanVerification
    ): void {
        $now = time();
        $hasFingerprintConsent = $this->hasVisitorCookieConsent($request);
        $payload = $this->readCookie($request, $now);
        $currentFingerprint = $this->fingerprintService->collect($request);
        $hasClientFingerprint = count($currentFingerprint) > 1;

        if ($payload === null) {
            $payload = [
                'token_id' => (string) Str::uuid(),
                'issued_at' => $now,
                'expires_at' => $now + (
                    self::COOKIE_LIFETIME_MINUTES * 60
                ),
                'device_fingerprint' => (
                    $hasFingerprintConsent ? $currentFingerprint : []
                ),
                'version' => self::COOKIE_VERSION,
            ];
        } elseif (!$hasFingerprintConsent) {
            // Declining optional fingerprinting removes those fields only.
            // The essential IP and human-verification caches are retained.
            $payload['device_fingerprint'] = [];
        } elseif ($hasClientFingerprint || empty($payload['device_fingerprint'])) {
            // A security-check POST carries screen/timezone/language fields.
            // Ordinary page GETs carry only User-Agent; do not overwrite a
            // richer previously collected fingerprint with that partial data.
            $payload['device_fingerprint'] = $currentFingerprint;
        }

        // Remove fields from the previous design; they must not be persisted.
        unset(
            $payload['cookie_score'],
            $payload['route_access'],
            $payload['route_checked_at']
        );

        // IP and human-verification caches are stored regardless of the
        // optional fingerprint preference; they are the security cache.
        $this->updateIpCache(
            $payload,
            $request,
            $ipReputationScore,
            $cacheIpReputation,
            $now
        );

        $this->updateHumanVerificationCache(
            $payload,
            $humanVerificationScore,
            $cacheHumanVerification,
            $now
        );

        // Keep the existing rolling 90-day cookie lifetime behaviour.
        if (
            ($payload['expires_at'] - $now)
            <= self::COOKIE_REFRESH_THRESHOLD_MINUTES * 60
        ) {
            $payload['expires_at'] = $now + (
                self::COOKIE_LIFETIME_MINUTES * 60
            );
            $payload['last_refreshed_at'] = $now;
        }

        $this->queueCookie($request, $payload);
    }

    private function updateIpCache(
        array &$payload,
        Request $request,
        int $score,
        bool $cacheScore,
        int $now
    ): void {
        $ipHash = $this->currentIpHash($request);

        $existingHash = $payload['ip_hash']
            ?? $payload['ip_reputation_ip_hash']
            ?? null;
        $existingExpiry = $payload['ip_cache_expires_at']
            ?? $payload['ip_reputation_expires_at']
            ?? null;
        $existingScore = $payload['ip_reputation_score'] ?? null;
        $existingCheckedAt = $payload['ip_checked_at'] ?? null;

        if (!is_int($existingCheckedAt) && is_int($existingExpiry)) {
            $existingCheckedAt = $existingExpiry
                - self::IP_REPUTATION_CACHE_SECONDS;
        }

        $existingCacheValid = (
            is_string($existingHash)
            && $ipHash !== null
            && hash_equals($existingHash, $ipHash)
            && is_int($existingExpiry)
            && $existingExpiry > $now
            && is_int($existingCheckedAt)
            && $existingCheckedAt <= $now + 60
            && $existingExpiry > $existingCheckedAt
            && ($existingExpiry - $existingCheckedAt)
                <= self::IP_REPUTATION_CACHE_SECONDS
            && is_int($existingScore)
            && $existingScore >= 0
            && $existingScore <= 100
        );

        if (
            $cacheScore
            && $ipHash !== null
            && $score >= 0
            && $score <= 100
        ) {
            $sameValidCache = (
                $existingCacheValid
                && $existingScore === $score
            );

            $payload['ip_reputation_score'] = $score;
            $payload['ip_hash'] = $ipHash;
            $payload['ip_checked_at'] = $sameValidCache
                ? $existingCheckedAt
                : $now;
            $payload['ip_cache_expires_at'] = $sameValidCache
                ? $existingExpiry
                : $now + self::IP_REPUTATION_CACHE_SECONDS;
        } elseif ($existingCacheValid) {
            // Preserve a still-valid cache without extending its lifetime.
            $payload['ip_reputation_score'] = $existingScore;
            $payload['ip_hash'] = $existingHash;
            $payload['ip_checked_at'] = $existingCheckedAt;
            $payload['ip_cache_expires_at'] = $existingExpiry;
        } else {
            // A quota/unavailable fallback (I=50) is never cached as success.
            unset(
                $payload['ip_reputation_score'],
                $payload['ip_hash'],
                $payload['ip_checked_at'],
                $payload['ip_cache_expires_at']
            );
        }

        // Migrate old field names to the agreed payload field names.
        unset(
            $payload['ip_reputation_ip_hash'],
            $payload['ip_reputation_expires_at']
        );
    }

    private function updateHumanVerificationCache(
        array &$payload,
        ?int $score,
        bool $cacheScore,
        int $now
    ): void {
        $existingScore = $payload['human_verification_score'] ?? null;
        $existingCheckedAt = $payload['human_checked_at'] ?? null;
        $existingExpiry = $payload['human_cache_expires_at'] ?? null;

        $existingCacheValid = (
            is_int($existingScore)
            && in_array($existingScore, [0, 1], true)
            && is_int($existingCheckedAt)
            && $existingCheckedAt <= $now + 60
            && is_int($existingExpiry)
            && $existingExpiry > $now
            && $existingExpiry > $existingCheckedAt
            && ($existingExpiry - $existingCheckedAt)
                <= self::HUMAN_VERIFICATION_CACHE_SECONDS
        );

        if (
            $cacheScore
            && $score !== null
            && in_array($score, [0, 1], true)
        ) {
            $payload['human_verification_score'] = $score;
            $payload['human_checked_at'] = $now;
            $payload['human_cache_expires_at'] = $now
                + self::HUMAN_VERIFICATION_CACHE_SECONDS;
        } elseif (!$existingCacheValid) {
            unset(
                $payload['human_verification_score'],
                $payload['human_checked_at'],
                $payload['human_cache_expires_at']
            );
        }
    }

    /**
     * Read and validate the decrypted cookie payload.
     * Laravel EncryptCookies authenticates/decrypts it before this method.
     */
    private function readCookie(Request $request, int $now): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $payload = json_decode(
                $raw,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        if (
            ($payload['version'] ?? null) !== self::COOKIE_VERSION
            || !is_string($payload['token_id'] ?? null)
            || !Str::isUuid($payload['token_id'])
            || !is_int($payload['issued_at'] ?? null)
            || !is_int($payload['expires_at'] ?? null)
            || !is_array($payload['device_fingerprint'] ?? null)
        ) {
            return null;
        }

        $expiryAnchor = $payload['last_refreshed_at']
            ?? $payload['issued_at'];

        if (
            !is_int($expiryAnchor)
            || $payload['issued_at'] > $now + 60
            || $expiryAnchor > $now + 60
            || $payload['expires_at'] <= $now
            || $payload['expires_at'] <= $payload['issued_at']
            || $payload['expires_at'] <= $expiryAnchor
            || ($payload['expires_at'] - $expiryAnchor)
                > self::COOKIE_LIFETIME_MINUTES * 60
        ) {
            return null;
        }

        return $payload;
    }

    /**
     * Consent applies only to optional device fingerprint fields.
     * The visitor_trust cookie still carries essential security cache data.
     */
    private function hasVisitorCookieConsent(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get('visitor_fingerprint_consent_v2') === true;
    }

    /** Store only an HMAC of the resolved visitor IP, never the raw address. */
    private function currentIpHash(Request $request): ?string
    {
        $ip = $this->clientIpResolver->resolve($request);

        if ($ip === null) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function secureFlagsScore(): int
    {
        // Keep the existing secure-cookie scoring component unchanged.
        $secure = (bool) config('session.secure', false);
        $httpOnly = (bool) config('session.http_only', true);
        $sameSite = strtolower((string) config('session.same_site', 'lax'));

        $sameSiteValid = in_array($sameSite, ['lax', 'strict'], true)
            || ($sameSite === 'none' && $secure);

        return ($secure && $httpOnly && $sameSiteValid) ? 15 : 0;
    }

    private function calculateMaturityScore(int $issuedAt, int $now): int
    {
        $age = max(0, $now - $issuedAt);
        $day = 24 * 60 * 60;

        if ($age < 10 * 60) {
            return 0;
        }

        if ($age < $day) {
            return 2;
        }

        if ($age < 7 * $day) {
            return 6;
        }

        if ($age < 10 * $day) {
            return 12;
        }

        return 20;
    }

    private function queueCookie(Request $request, array $payload): void
    {
        $secureSetting = config('session.secure');
        $secure = $secureSetting === null
            ? $request->isSecure()
            : (bool) $secureSetting;

        $sameSite = strtolower((string) config('session.same_site', 'lax'));
        if (!in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            $sameSite = 'lax';
        }
        if ($sameSite === 'none' && !$secure) {
            $sameSite = 'lax';
        }

        Cookie::queue(
            cookie(
                self::COOKIE_NAME,
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                ),
                self::COOKIE_LIFETIME_MINUTES,
                '/',
                config('session.domain'),
                $secure,
                (bool) config('session.http_only', true),
                false,
                $sameSite
            )
        );
    }

    private function normalizeScore(int $score): int
    {
        return (int) round((min(80, max(0, $score)) / 80) * 100);
    }
}
