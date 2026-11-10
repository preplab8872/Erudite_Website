# Erudite Form Manager frontend fix

This package was rebuilt from the complete frontend ZIP uploaded in this conversation.

## Root cause fixed
The dropdown click handler called `displayFormManagerForm(...)`, but the function defined in this frontend is `openFormManagerViewer(...)`. The click handler now calls the function that actually exists.

## Cache prevention
`index.html` now references `script.js?v=20261107-fm4` and `form-manager-viewer.css?v=20261107-fm4`. The JavaScript logs `[Erudite Form Manager] build 2026-11-07-fm4 loaded` in the browser console so the active build can be confirmed.

## Viewer behaviour
The selected row's ID and section are used to open `/api/forms/{section}/{formId}/document` in the existing iframe placed immediately below the Erudite header. The homepage main content is hidden while the viewer is active and restored by the existing Back to Erudite button.

The iframe load handler now reports security redirects, non-HTML responses, and common Laravel error pages in the viewer status area.

## Required Laravel routes
- `GET /api/forms/{section}/list` returns JSON metadata (`id`, `form_name`).
- `GET /api/forms/{section}/{formId}/document` returns the raw `forms.forms` document as `text/html`.

No backend, database, middleware, security-score, or CRM changes are included. Existing `style.css` contents are retained.
