# Erudite security middleware

## Applied behaviour

The backend uses one Laravel-encrypted cookie named `visitor_trust`. The cookie contains:

- `version`, `token_id`, `issued_at`, `expires_at`, and `device_fingerprint`
- `ip_reputation_score`, `ip_hash`, `ip_checked_at`, and `ip_cache_expires_at`
- `human_verification_score`, `human_checked_at`, and `human_cache_expires_at`

It does **not** persist `cookie_score`, `route_access`, or `route_checked_at`.

### Evaluation on each protected request

1. Calculate cookie trust (C) from the current request and valid cookie. A missing/invalid cookie starts at C = 50. If the current request does not contain enough comparable browser-fingerprint fields (normal page GETs generally carry only User-Agent), use the neutral C = 50 rather than treating unavailable fields as a mismatch. The encrypted cookie is still validated for version, token ID, issue time, and expiry. C is not read from or written to the cookie.
2. Resolve the visitor IP through `ClientIpResolver`. Reuse I only when the cookie's HMAC IP hash matches and the recorded cache is valid for no more than 24 hours. Otherwise query IP2Location. Unavailable results/quota exhaustion use I = 50 and are not cached as a successful lookup.
3. Reuse H only while the human-verification cache is valid for no more than 24 hours. A newly submitted Turnstile response always forces a fresh server-side Siteverify request. No token or no valid cache means H = 0 for that evaluation.
4. Run the existing formula and threshold without changing them:

   `F = ((C + (H * 100)) / 400) + (I / 200)`

   Access requires `H === 1` and `F >= 0.6`.
5. Update the same encrypted cookie with only the reusable IP and human-verification cache values. Never store C or the route decision.

### Consent behaviour

The consent prompt controls optional device-fingerprint collection only. The encrypted `visitor_trust` cookie is retained for the essential security cache, so declining optional fingerprint collection does not destroy the 24-hour IP or human-verification cache. When fingerprint collection is declined, the stored `device_fingerprint` is cleared and C uses the neutral fallback when there is insufficient fingerprint evidence. This prevents declining optional fingerprinting from automatically producing a low score.

### Security-check redirect

`/security-check` and its consent/evaluation endpoints are intentionally outside `RiskCheckMiddleware`. When a protected request needs human verification, the middleware redirects to the security page with a same-origin return path. The page submits a fresh Turnstile token to `security.evaluate`; after approval it returns to the original path, where middleware evaluates C, I, and H again. A previous route decision is never used as a bypass. If Turnstile succeeds but the risk threshold is not met, the page now says so explicitly. With `APP_DEBUG=true`, the component scores and final result are written to the Laravel debug log (not returned to the browser).

## Environment variables

Set these in the deployment `.env` (never commit or distribute that file):

```dotenv
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
IP2LOCATION_API_KEY=
```

The matching `config/services.php` entries read these names. The example environment file includes blank placeholders.

## Deployment notes

- Keep Laravel's `EncryptCookies` and `AddQueuedCookiesToResponse` middleware enabled for the `web` routes.
- Configure `APP_KEY` and HTTPS correctly. `visitor_trust` is `HttpOnly`; its `Secure` attribute follows `SESSION_SECURE_COOKIE` when set, otherwise the request scheme. The existing cookie-trust secure-flags score still reads `session.secure` exactly as before, so set `SESSION_SECURE_COOKIE=true` in the production `.env` when the site is served over HTTPS.
- The Cloudflare proxy allowlist currently matches the official list of 15 IPv4 and 7 IPv6 ranges. Recheck the official Cloudflare list when maintaining this configuration: https://developers.cloudflare.com/fundamentals/concepts/cloudflare-ip-addresses/
- After changing `.env` or config files on the server, clear/rebuild Laravel's config cache (`php artisan config:clear` then `php artisan config:cache`).
- IP2Location must return a numeric `fraud_score` for a provider score to be cached. If the account plan/quota does not provide it, the evaluation uses I = 50 without caching that fallback.

## Validation

PHP syntax was checked across the project source. Laravel route registration, direct Blade template compilation, and the homepage/Vite JavaScript syntax checks passed. PHPUnit and Laravel Pint could not run in this build environment because the PHP DOM, mbstring, and XML extensions are missing; run `php artisan test` and `php vendor/bin/pint --test` in your normal PHP environment after extracting the ZIP.
