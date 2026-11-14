# Erudite security consent and deployment notes

## Consent and verification order

1. On a fresh session, the security page first displays the cookie/fingerprinting preference.
2. The essential encrypted `visitor_trust` cookie is explained as required for the security mechanism. Optional device fingerprinting can be accepted or declined.
3. The Cloudflare Turnstile script is not requested from the browser until the visitor submits one of those choices.
4. After the choice is saved, the Turnstile panel appears and the verification script loads.
5. The visitor can reopen the preference step using **Change cookie preferences**.

The consent session key was versioned to `visitor_fingerprint_consent_v2`, so a preference from the older screen does not silently skip the updated first-time prompt. The old localStorage consent auto-restore was removed; localStorage is used only for the site's light/dark theme preference.

## Security cookie behaviour retained

- `visitor_trust` remains Laravel-encrypted and HTTP-only.
- IP reputation and human-verification cache lifetimes remain 24 hours.
- Cookie trust is recalculated; the previous access-fix behaviour for incomplete fingerprint data is retained.
- The existing scoring expression and threshold are unchanged.
- The cookie still uses SameSite=Lax; production HTTPS should use the Secure flag.

## Production environment

For the production `.env`, use the actual HTTPS site URL and set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Do not copy production settings into local HTTP development; leave `SESSION_SECURE_COOKIE=false` for `http://localhost`. Keep `.env` and all API keys out of the ZIP and source control.

## Chrome warning clarification

A consent prompt does not control Chrome Safe Browsing or guarantee that Chrome will stop warning about a site. Chrome's security warnings are separate from cookie-consent UI. Use a valid HTTPS certificate, keep production cookies Secure/HttpOnly/SameSite, avoid mixed content, and check Google Safe Browsing status if Chrome displays an unsafe-site interstitial.
