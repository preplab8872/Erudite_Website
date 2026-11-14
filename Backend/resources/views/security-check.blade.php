<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#f7f0d9">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Security check | Erudite</title>
    <link rel="stylesheet" href="{{ url('/style.css') }}">
    <style>
        .security-main { width:min(100% - 2.25rem,900px); margin:0 auto; padding:clamp(3.5rem,8vw,7rem) 0; }
        .security-intro { max-width:700px; margin-bottom:2.25rem; }
        .security-intro h1 { max-width:760px; margin:0 0 1rem; color:var(--ink); font-family:var(--font-display); font-size:clamp(2.4rem,5.4vw,4.8rem); font-weight:500; letter-spacing:-.045em; line-height:.99; }
        .security-intro h1 span { color:var(--red); font-style:italic; }
        .security-intro>p:last-child { max-width:650px; color:var(--muted); }
        .security-card { position:relative; max-width:720px; padding:clamp(1.4rem,4vw,2.6rem); border:1px solid var(--line); border-top:4px solid var(--red); background:var(--surface); box-shadow:var(--shadow-soft); }
        .security-step { margin:0 0 .8rem; color:var(--red); font-size:.68rem; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
        .security-card h2 { margin:0 0 .8rem; color:var(--ink); font-family:var(--font-display); font-size:clamp(1.55rem,3vw,2.15rem); font-weight:500; letter-spacing:-.035em; line-height:1.12; }
        .security-copy { max-width:650px; margin:0 0 1.35rem; color:var(--muted); font-size:.91rem; line-height:1.75; }
        .security-copy strong { color:var(--ink); }
        .security-actions { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:1.5rem; }
        .security-actions .button { min-width:210px; cursor:pointer; }
        .security-secondary { border:1px solid var(--line); background:transparent; color:var(--ink); cursor:pointer; }
        .security-secondary:hover { border-color:var(--red); color:var(--red); }
        .security-status { margin:1rem 0 0; color:var(--muted); font-size:.75rem; }
        #verification-panel { margin-top:1.5rem; }
        #turnstile-widget { min-height:66px; margin:1.25rem 0; }
        #busy { display:flex; align-items:center; gap:.75rem; margin-top:1rem; color:var(--muted); font-size:.85rem; }
        .spinner { width:22px; height:22px; flex:0 0 auto; border:3px solid color-mix(in srgb,var(--red) 20%,transparent); border-top-color:var(--red); border-radius:50%; animation:security-spin .8s linear infinite; }
        #error, #consent-error { margin:1rem 0 0; padding:.85rem 1rem; border-left:3px solid var(--red); background:var(--surface-2); color:var(--ink); font-size:.85rem; }
        #change-consent { margin-top:1.25rem; padding:0; border:0; background:none; color:var(--red); font:inherit; font-size:.78rem; font-weight:800; text-decoration:underline; text-underline-offset:.2em; cursor:pointer; }
        [hidden] { display:none !important; }
        @keyframes security-spin { to { transform:rotate(360deg); } }
        body.theme-dark .security-intro h1, body.theme-dark .security-card h2, body.theme-dark .security-copy strong { color:var(--ink); }
        @media(max-width:600px) { .security-main { width:min(100% - 1.25rem,900px); padding:2.5rem 0; } .security-actions { flex-direction:column; } .security-actions .button { width:100%; } }
    </style>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="#security-start" aria-label="Erudite security check">
            <span class="brand-mark" aria-hidden="true"><span>E</span></span>
            <span class="brand-copy"><span class="brand-name">ERUDITE</span><span class="brand-descriptor">LEARN WITH INTENT</span></span>
        </a>
        <button class="theme-toggle" type="button" aria-label="Switch to dark mode" title="Toggle colour theme">
            <span aria-hidden="true">◐</span><span class="theme-toggle-label">Dark</span>
        </button>
    </div>
</header>

<main class="security-main" id="security-start">


    <section class="security-card" aria-live="polite">
        <section id="consent-panel" @if($consentChoice !== null) hidden @endif>
            <p class="security-step">Step 01 · Cookie preference</p>
            <h2>Choose how Erudite may use your browser information.</h2>
            <p class="security-copy">
                <strong>Essential security cookie:</strong> Erudite uses an encrypted, HTTP-only
                <code>visitor_trust</code> cookie to protect requests and remember IP reputation
                and human-verification results for up to 24 hours. It is required for the security
                check and is not used for advertising. After you choose, Cloudflare Turnstile
                loads to verify that the request is human.
            </p>
            <p class="security-copy">
                <strong>Optional device fingerprinting:</strong> if allowed, limited browser
                characteristics help calculate the independent cookie-trust score. You can
                decline this and continue with the essential security cookie only.
            </p>
            <div class="security-actions">
                <button id="allow-cookies" class="button button-primary" type="button">Allow optional fingerprinting <span aria-hidden="true">↗</span></button>
                <button id="deny-cookies" class="button security-secondary" type="button">Essential cookies only</button>
            </div>
            <p id="consent-error" role="alert" hidden></p>
            <p class="security-status">Your choice is saved in this browser session. You can change it later from this screen.</p>
        </section>

        <section id="verification-panel" @if($consentChoice === null) hidden @endif>
            <p class="security-step">Step 02 · Human verification</p>
            <h2>Confirm that you are a person.</h2>
            <p class="security-copy">Complete the Cloudflare check below. Once it is verified, Erudite will evaluate the request and return you to the page you originally opened.</p>
            <div id="turnstile-widget"></div>
            <div id="busy" hidden><span class="spinner" aria-hidden="true"></span><span>Checking your request…</span></div>
            <p id="error" role="alert" hidden></p>
            <button id="retry" class="button security-secondary" type="button" hidden>Try again</button>
            <button id="change-consent" type="button" @if($consentChoice === null) hidden @endif>Change cookie preferences</button>
        </section>
    </section>
</main>

<script>
(() => {
    'use strict';
    const siteKey = @json($siteKey);
    const returnTo = @json($returnTo);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const consentPanel = document.getElementById('consent-panel');
    const verificationPanel = document.getElementById('verification-panel');
    const allowButton = document.getElementById('allow-cookies');
    const denyButton = document.getElementById('deny-cookies');
    const changeConsentButton = document.getElementById('change-consent');
    const busy = document.getElementById('busy');
    const errorBox = document.getElementById('error');
    const retryButton = document.getElementById('retry');
    let consentChoice = @json($consentChoice);
    let turnstileToken = null;
    let submitting = false;
    let turnstileScriptRequested = false;

    function showError(message) {
        busy.hidden = true;
        errorBox.textContent = message;
        errorBox.hidden = false;
        retryButton.hidden = false;
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':csrfToken },
            body: JSON.stringify(payload), cache: 'no-store'
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = result.errors ? Object.values(result.errors).flat().join(' ') : null;
            throw new Error(validationMessage || result.message || 'Request failed.');
        }
        return result;
    }

    function loadTurnstileAfterChoice() {
        if (consentChoice === null || turnstileScriptRequested) return;
        verificationPanel.hidden = false;
        turnstileScriptRequested = true;
        if (!siteKey) {
            showError('Human verification is not configured. Please contact the website administrator.');
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad&render=explicit';
        script.async = true;
        script.defer = true;
        script.onerror = () => showError('The verification service could not be loaded. Please retry.');
        document.head.appendChild(script);
    }

    async function saveConsent(allowFingerprinting) {
        allowButton.disabled = true;
        denyButton.disabled = true;
        errorBox.hidden = true;
        retryButton.hidden = true;
        try {
            document.getElementById('consent-error').hidden = true;
            await postJson(@json(route('security.consent')), { allow_cookies:allowFingerprinting });
            consentChoice = allowFingerprinting;
            consentPanel.hidden = true;
            verificationPanel.hidden = false;
            changeConsentButton.hidden = false;
            loadTurnstileAfterChoice();
        } catch (error) {
            const consentError = document.getElementById('consent-error');
            consentError.textContent = error.message;
            consentError.hidden = false;
        } finally {
            allowButton.disabled = false;
            denyButton.disabled = false;
        }
    }

    function getDeviceFingerprint() {
        return {
            screen:`${screen.width}x${screen.height}x${screen.colorDepth}`,
            timezone:Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            language:navigator.language || ''
        };
    }

    async function submitIfReady() {
        if (consentChoice === null || !turnstileToken || submitting) return;
        submitting = true;
        busy.hidden = false;
        errorBox.hidden = true;
        retryButton.hidden = true;
        const payload = { 'cf-turnstile-response':turnstileToken };
        if (consentChoice === true) payload.device_fingerprint = getDeviceFingerprint();
        try {
            const result = await postJson(@json(route('security.evaluate')), payload);
            if (result.approved === true) {
                window.location.replace(returnTo);
                return;
            }
            showError(result.message || 'Verification was not approved. Please try again.');
        } catch (error) {
            showError(error.message);
        } finally {
            submitting = false;
            turnstileToken = null;
        }
    }

    window.onTurnstileLoad = function () {
        if (!window.turnstile || !siteKey) {
            showError('Verification is temporarily unavailable.');
            return;
        }
        window.turnstile.render('#turnstile-widget', {
            sitekey:siteKey, theme:'auto', size:'normal', appearance:'always',
            callback:token => { turnstileToken = token; submitIfReady(); },
            'expired-callback':() => { turnstileToken = null; },
            'error-callback':() => {
                turnstileToken = null;
                showError('Verification could not be completed. Please retry.');
            }
        });
    };

    allowButton?.addEventListener('click', () => saveConsent(true));
    denyButton?.addEventListener('click', () => saveConsent(false));
    retryButton.addEventListener('click', () => window.location.reload());
    changeConsentButton.addEventListener('click', () => {
        const url = new URL(@json(route('security.check')), window.location.origin);
        url.searchParams.set('return', returnTo);
        url.searchParams.set('manage', '1');
        window.location.assign(url.toString());
    });

    // Use the same persisted colour-theme preference as the public frontend.
    const themeToggle = document.querySelector('.theme-toggle');
    const applyTheme = dark => {
        document.body.classList.toggle('theme-dark', dark);
        themeToggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        themeToggle.querySelector('.theme-toggle-label').textContent = dark ? 'Light' : 'Dark';
        themeToggle.querySelector('[aria-hidden="true"]').textContent = dark ? '☼' : '◐';
    };
    let savedTheme = null;
    try { savedTheme = localStorage.getItem('erudite-theme'); } catch (_) {}
    applyTheme(savedTheme === 'dark');
    themeToggle.addEventListener('click', () => {
        const dark = !document.body.classList.contains('theme-dark');
        applyTheme(dark);
        try { localStorage.setItem('erudite-theme', dark ? 'dark' : 'light'); } catch (_) {}
    });

    // Do not contact Cloudflare or render Turnstile until the visitor has made
    // an explicit choice. Existing session choices are respected; the visitor
    // can reopen the preference step with the change-preferences control.
    if (consentChoice !== null) loadTurnstileAfterChoice();
})();
</script>
</body>
</html>
