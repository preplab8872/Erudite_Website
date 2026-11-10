# Erudite Multi-page Front-end Prototype

A responsive, editorial-style static website concept for Erudite, built with HTML5, CSS3 and vanilla JavaScript. The design uses warm ivory/yellow, institutional red, compact headings and square-cornered editorial layouts. No framework or build step is required.

## Run locally

1. Keep all `.html` files, `style.css` and `script.js` in the same folder.
2. Open `index.html` in a modern browser, or run a local server such as `python -m http.server 8000` from this directory and visit `http://localhost:8000`.
3. An internet connection is needed for remotely hosted camera photographs and the optional authoritative examination resources. Course pages are included locally in this prototype rather than linking visitors to Erudite’s course pages. The interactive demo tools themselves run locally in the browser.

## Pages

- `index.html` — home page, course pathways, offerings, resource-volume strip, published student outcomes and testimonial.
- `about.html` — institute background, vision, mission, values and public faculty biographies.
- MBA in India, UG College Test and Study Abroad are fixed navigation sections. Their dropdown entries are loaded from the CRM `forms` table; no predefined overview pages are used for these sections.
- `cat-preparation.html`, `mat-preparation.html`, `cmat-preparation.html` — individual management-entrance preparation pages.
- `gd-pi-wat.html` — group discussion, written ability and personal interview preparation.
- `bba-ipm.html`, `cuet-preparation.html`, `clat-preparation.html` — undergraduate and law entrance preparation pages.
- `gre-preparation.html`, `gmat-preparation.html`, `sat-preparation.html`, `ielts-preparation.html`, `toefl-preparation.html` — individual study-abroad examination pages.
- `results.html` — selected achievements reproduced from Erudite's published pages, with results presented as source-attributed examples rather than independently audited statistics.
- `rtp.html` — Recruitment Training Programme overview.
- `resources.html` — daily practice, mock tests, video-learning information and local prototype pathways.
- `daily-question.html` — original illustrative practice question with answer feedback; not a live daily question feed.
- `cat-predictor.html` — mock-percentile readiness band and next-step suggestions; not a percentile prediction model.
- `bschool-predictor.html` — broad shortlist research prompts; not an admission probability model.
- `login.html` — demo login UI that never authenticates and does not transmit or save entered values.
- `style.css` — shared design tokens, all page styling and responsive breakpoints.
- `script.js` — mobile/mega-navigation behaviour and local demo interactions.

## Content source notes

Erudite’s main website was used as the source of course descriptions, subject coverage and preparation-feature summaries. Course content is adapted into local pages in this prototype; course buttons do not send visitors to Erudite course pages. The following source URLs document the public material used during development:

- Main site: https://erudite.in/
- About Us, vision, mission and listed faculty: https://erudite.in/about-us/
- MBA / CAT overview: https://erudite.in/cat-coaching/
- CAT classroom preparation: https://erudite.in/cat-classroom-course-in-kolkata/
- Online CAT page and featured achievers: https://erudite.in/online-cat/
- CAT self-paced learning: https://erudite.in/cat-self-paced-learning-course/
- CAT mock test series: https://erudite.in/cat-mock-test-series-india/
- Institutes under CAT: https://erudite.in/institutes-under-cat/
- Institutes under XAT: https://erudite.in/institutes-under-xat/
- B-school placements: https://erudite.in/b-school-placements/
- CAT results archive: https://erudite.in/cat-our_results/
- MAT: https://erudite.in/mat/
- CMAT: https://erudite.in/cmat/
- GD-PI-WAT: https://erudite.in/gd-pi-wat-training-in-kolkata/
- BBA/IPM: https://erudite.in/bba-ipmat-coaching-in-kolkata/
- CUET: https://erudite.in/cuet-coaching-classes-india/
- CLAT: https://erudite.in/clat/
- Study abroad: https://erudite.in/study-abroad/
- GRE: https://erudite.in/gre-preparation-in-kolkata/
- GMAT: https://erudite.in/gmat-coaching-in-kolkata-3/
- SAT: https://erudite.in/sat-coaching-in-india/
- IELTS: https://erudite.in/ielts-coaching-in-kolkata/
- TOEFL: https://erudite.in/toefl-coaching-in-kolkata/
- RTP: https://erudite.in/rtp/
- Free CAT preparation: https://erudite.in/free-online-cat-coaching/
- CAT mock resources: https://erudite.in/free-cat-mock/
- Video section: https://erudite.in/videos/
- Erudite Online resource link supplied in the brief: https://eruditeonline.in/?page_id=262337
- Contact: https://erudite.in/contact/

The iQuanta site was consulted only as a visual reference for presenting outcomes and analytics in a scannable way. No iQuanta performance metric is presented as an Erudite result.

## Statistics and outcomes

The home-page resource strip uses figures from Erudite's public CAT preparation information (21,505+ questions, 250+ sectional tests and 20+ full-length mocks). These are represented as published resource volumes, not as proof of increased admission probability. Programme-specific inclusions can change and must be reconfirmed with the institute. Published individual percentile profiles on the home and results pages are attributed to Erudite's own pages and are not independently audited or a class-wide success rate.

## Assets

The site includes camera-shot educational photographs from Unsplash, hotlinked to the source images. They are photographs rather than illustrations; captions name the photographer and link to the photo page. These require an internet connection. The home and results page also include student-result portraits hotlinked from Erudite’s own media library:

- https://erudite.in/wp-content/uploads/2022/03/Untitled-design-64.png
- https://erudite.in/wp-content/uploads/2024/06/GDPI-Students-300x300-1.jpg
- https://erudite.in/wp-content/uploads/2025/04/Untitled-design.jpg

These images are not bundled locally. Confirm image reuse permission and the current captions/results with Erudite before public deployment.

## Important implementation limits

- The page interactions remain front-end-only. Laravel serves the existing pages and exposes the existing OTP API routes, but there is no connected login/authentication, account creation, CRM integration, live question feed, live CAT score model, or live admissions data.
- The login form is a demo and explicitly tells the visitor that it does not authenticate. Do not use a real password on this page.
- The CAT predictor uses broad bands based on a user-entered mock percentile. It cannot infer CAT percentile from raw marks, because live year-/slot-specific normalisation data is not connected.
- The B-school predictor generates only broad research prompts. It does not calculate chances or guarantee interview calls/admission. Replace the demo rule logic with validated, current institution-specific data before treating this as a real tool.
- Student result statements and resource figures are copied from Erudite's public site and should be reviewed/approved before production use.
- Course pages and course-navigation links stay inside this prototype. Photographer credits link to the original image source pages, and selected non-course authoritative resources may be external.

## Accessibility and responsive behaviour

Semantic landmarks, skip links, keyboard-operable mobile navigation, accessible labels, focus states, reduced-motion support, lazy-loaded photos and responsive layouts are included. Main breakpoints address desktop, tablet and narrow mobile layouts.


## Course-specific pages and theme

Dedicated course pages are provided for CAT, MAT, CMAT, GD-PI-WAT, BBA/IPM, CUET, CLAT, GRE, GMAT, SAT, IELTS and TOEFL. All use the shared visual system and contain local course descriptions adapted from Erudite’s publicly available programme information. Course navigation stays within this prototype; the site does not link visitors to Erudite course pages. A Home link and a persistent light/dark theme toggle appear in navigation.

The homepage includes three interactive SVG charts for yearly top-B-school outcomes, practice score trend, and weekly study activity. Every plotted number is fictional mock data used to demonstrate the interface. Do not publish these as Erudite outcomes; replace them with approved, year-wise and cohort-defined data.


## Course-page content and local navigation

The twelve dedicated course pages contain adapted summaries of the published programme descriptions for CAT, MAT, CMAT, GD-PI-WAT, BBA/IPM, CUET, CLAT, GRE, GMAT, SAT, IELTS and TOEFL. Course-page cards, buttons and navigation now route to the corresponding pages inside this prototype, not to Erudite’s official course URLs. TOEFL programme specifics were sparse on the public page reviewed, so this page deliberately avoids inventing fixed programme hours, prices or mock counts. Course-specific resource counts are accompanied by notes that current batches may differ.

## Real educational photography

The revised pages use real photographic images from Unsplash, not generated art or illustrations. Images are remotely loaded and require an internet connection. Photos used with a visible credit: Jason Hu (students studying at library tables), Vitaly Gariev (classroom teaching and student discussion), Ashutosh Gupta (focused student studying), Kelly Sikkema (student notes), and Kris Tian (students in a modern library). Their source pages are linked in image captions and all are identified on Unsplash as free to use under the Unsplash License. Verify licensing and image loading in your production environment before launch.

## Laravel integration in this project

The static frontend remains in `erudite_homepage/`; it has not been moved into Laravel's `public` directory. The Laravel web routes serve the existing HTML pages at `/` and `/<page>.html`, plus the shared `/style.css` and `/script.js` assets. Run the Laravel application from the `Backend/` directory and open the Laravel base URL to view the homepage.

The existing OTP endpoints are registered under `/api/otp/send` and `/api/otp/verify`. The current login page is intentionally still a demonstration page and does not call these endpoints or authenticate an account. The predictor and daily-question forms also remain browser-only demonstrations. The booking page remains a separate consultation request with its own email OTP flow. The Form Manager dropdowns are active frontend features: each section calls `GET /api/forms/{section}`, uses `form_name` for the dropdown label, and displays the corresponding `forms` HTML when selected.


## Booking integration

The booking page is `booking.html` and remains a separate consultation request with email OTP verification. Database-managed HTML forms are not loaded on the booking page: they are listed in the three section dropdowns on the website navigation and rendered when a visitor selects a form name.

Configure the Laravel `CRM_DB_*` variables in `Backend/.env` for the MySQL database named `crm`. The database must contain `crm_example` (`id`, `name`, `email`, `city`, `created_at`, `update_at`) and `forms` (`id`, `section`, `form_name`, `forms`). Run a queue worker (`php artisan queue:work`) so queued booking records are copied into `crm_example`. Configure Laravel mail settings for OTP delivery.

## Database-driven Form Manager

The website has three fixed sections: `MBA_IN_INDIA`, `UG_COLLEGE_TEST` and `STUDY_ABROAD`. Each section dropdown is populated only from rows returned by the existing `GET /api/forms/{section}` endpoint. The endpoint reads `id`, `section`, `form_name` and `forms` from the CRM `forms` table. `form_name` is displayed as the menu item; selecting it displays the associated stored HTML from `forms`. If no matching rows exist, no form links are shown. The three former hard-coded section overview pages have been removed.
