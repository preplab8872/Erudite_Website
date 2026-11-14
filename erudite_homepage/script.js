/* ERUDITE MULTI-PAGE FRONT-END — VANILLA JAVASCRIPT */

(() => {

  'use strict';

  const menuToggle = document.querySelector('.menu-toggle');

  const navigation = document.querySelector('#primary-navigation');

  const navGroups = [...document.querySelectorAll('.nav-group')];

  function closeGroups(except = null) {

    navGroups.forEach((group) => {

      if (group !== except) {

        group.classList.remove('is-open');

        const trigger = group.querySelector('.nav-trigger');

        if (trigger) trigger.setAttribute('aria-expanded', 'false');

      }

    });

  }

  function setNavigationOpen(isOpen) {

    if (!menuToggle || !navigation) return;

    menuToggle.setAttribute('aria-expanded', String(isOpen));

    menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');

    navigation.classList.toggle('is-open', isOpen);

    if (!isOpen) closeGroups();

  }

  if (menuToggle && navigation) {

    menuToggle.addEventListener('click', () => setNavigationOpen(menuToggle.getAttribute('aria-expanded') !== 'true'));

    navigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNavigationOpen(false)));

    navGroups.forEach((group) => {

      const trigger = group.querySelector('.nav-trigger');

      if (!trigger) return;

      trigger.addEventListener('click', () => {

        const willOpen = !group.classList.contains('is-open');

        closeGroups(group);

        group.classList.toggle('is-open', willOpen);

        trigger.setAttribute('aria-expanded', String(willOpen));

      });

    });

    document.addEventListener('keydown', (event) => {

      if (event.key === 'Escape') {

        closeGroups();

        if (menuToggle.getAttribute('aria-expanded') === 'true') {

          setNavigationOpen(false);

          menuToggle.focus();

        }

      }

    });

    document.addEventListener('click', (event) => {

      if (!navigation.contains(event.target) && !menuToggle.contains(event.target)) closeGroups();

    });

    window.addEventListener('resize', () => {

      if (window.matchMedia('(min-width: 821px)').matches) setNavigationOpen(false);

    });

  }

  const year = document.querySelector('#current-year');

  if (year) year.textContent = String(new Date().getFullYear());

  // Daily practice sample. The question is prototype content, not from the live Erudite portal.

  const dailyForm = document.querySelector('#daily-question-form');

  const dailyFeedback = document.querySelector('#question-feedback');

  const dailyReset = document.querySelector('#question-reset');

  if (dailyForm && dailyFeedback) {

    dailyForm.addEventListener('submit', (event) => {

      event.preventDefault();

      const choice = new FormData(dailyForm).get('daily-answer');

      const correct = choice === 'b';

      dailyFeedback.hidden = false;

      dailyFeedback.classList.toggle('is-correct', correct);

      dailyFeedback.innerHTML = correct

        ? '<h3>Correct — the net change is a 4% decrease.</h3><p>₹100 increases by 20% to ₹120. A 20% decrease on ₹120 is ₹24, so the final value is ₹96: a 4% decrease from the original ₹100.</p>'

        : '<h3>Not quite. Work through the two percentage changes in order.</h3><p>Start at ₹100. After a 20% increase you have ₹120. The 20% decrease is ₹24, leaving ₹96. That is 4% lower than the original price. Select the correct option and check again.</p>';

    });

    if (dailyReset) dailyReset.addEventListener('click', () => { dailyForm.reset(); dailyFeedback.hidden = true; dailyFeedback.innerHTML = ''; dailyFeedback.classList.remove('is-correct'); });

  }

  // Helper: safely read and display values from the submitted front-end form only.

  const getFormNumber = (form, name) => {

    const raw = form.elements.namedItem(name)?.value;

    if (raw === undefined || raw === null || raw.trim?.() === '') return null;

    const n = Number(raw);

    return Number.isFinite(n) ? n : null;

  };

  const catForm = document.querySelector('#cat-predictor-form');

  const catResult = document.querySelector('#cat-predictor-result');

  if (catForm && catResult) {

    catForm.addEventListener('submit', (event) => {

      event.preventDefault();

      const overall = getFormNumber(catForm, 'overall');

      if (overall === null || overall < 0 || overall > 100) return;

      let title = 'Build consistency across sections';

      let body = 'Use the next few mocks to build fundamentals, accuracy and a repeatable attempt strategy.';

      let band = 'FOUNDATION BAND';

      if (overall >= 99) { band = 'VERY HIGH MOCK BAND'; title = 'Protect consistency and practise selection'; body = 'Review difficult questions, sectional stability and exam-day decision-making. Research current admission criteria rather than assuming an outcome.'; }

      else if (overall >= 95) { band = 'HIGH MOCK BAND'; title = 'Strengthen section balance'; body = 'Compare section-wise percentiles and accuracy over multiple mocks. Keep a balanced shortlist and confirm current eligibility rules.'; }

      else if (overall >= 90) { band = 'DEVELOPING COMPETITIVE BAND'; title = 'Turn weak topics into a plan'; body = 'Choose the two highest-impact topic gaps, then repeat timed sectional practice and review mistakes before the next mock.'; }

      else if (overall >= 80) { band = 'BUILDING BAND'; title = 'Prioritise accuracy before speed'; body = 'Revisit concepts, strengthen question selection, and use timed practice in short, reviewable blocks.'; }

      const focus = catForm.elements.namedItem('focus').value;

      const focusAdvice = { varc: 'VARC focus: review reading comprehension accuracy and why each option is supported or contradicted by the passage.', dilr: 'DILR focus: practise choosing sets by solvability and track time spent before committing.', qa: 'QA focus: revisit fundamentals in your lowest-performing topics and keep an error log.', mocks: 'Mock focus: review question selection, time allocation and the reasons behind skipped or incorrect attempts.', balanced: 'Balanced focus: keep reviewing all three sections; do not let one strong section hide a weaker one.' }[focus];

      const sections = ['varc', 'dilr', 'qa'].map((key) => [key.toUpperCase(), getFormNumber(catForm, key)]).filter(([, value]) => value !== null);

      catResult.hidden = false;

      catResult.innerHTML = `<span class="result-tag">${band}</span><h3>${title}</h3><p>${body}</p><p><strong>Selected focus:</strong> ${focusAdvice}</p>${sections.length ? `<p><strong>Section inputs:</strong> ${sections.map(([k, v]) => `${k} ${v.toFixed(2)}%ile`).join(' · ')}. These values are shown as entered, not independently evaluated.</p>` : ''}<p><strong>Your input:</strong> ${overall.toFixed(2)} mock percentile estimate.</p><p>Next: take another timed mock under similar conditions, compare the trend, and validate school cut-offs through official admissions pages.</p>`;

    });

  }

  const bschoolForm = document.querySelector('#bschool-predictor-form');

  const bschoolResult = document.querySelector('#bschool-predictor-result');

  if (bschoolForm && bschoolResult) {

    bschoolForm.addEventListener('submit', (event) => {

      event.preventDefault();

      const percentile = getFormNumber(bschoolForm, 'percentile');

      const class10 = getFormNumber(bschoolForm, 'class10');

      const class12 = getFormNumber(bschoolForm, 'class12');

      const grad = getFormNumber(bschoolForm, 'grad');

      const exp = getFormNumber(bschoolForm, 'experience') ?? 0;

      if ([percentile, class10, class12, grad].some(v => v === null || v < 0 || v > 100)) return;

      const category = bschoolForm.elements.namedItem('category').value;

      const pref = bschoolForm.elements.namedItem('preference').value;

      let range = 'Broaden the research set';

      let groups = ['Review programmes with transparent published eligibility criteria', 'Include multiple entrance routes where relevant', 'Compare location, fees, programme fit and placement information'];

      if (percentile >= 99) { range = 'Research a set of highly selective programmes'; groups = ['Check the newest IIM and FMS shortlisting formulas carefully', 'Build a balanced list beyond the most selective options', 'Verify sectional cut-offs, academic weighting and interview rules']; }

      else if (percentile >= 95) { range = 'Build a balanced shortlist'; groups = ['Research IIM programmes and other CAT-accepting schools individually', 'Compare academic-profile weighting and sectional requirements', 'Keep both aspirational and broader-fit programmes on the list']; }

      else if (percentile >= 90) { range = 'Combine reach options with a wider shortlist'; groups = ['Look beyond a single institution group', 'Check programmes with different shortlisting formulas', 'Review alternative management exams and application windows']; }

      const prefAdvice = { all: 'Compare IIMs, university departments, IIT MBA programmes and private B-schools only after checking their current criteria.', iim: 'Read each IIM’s current admission policy separately; the criteria are not uniform.', fms: 'Review university-specific criteria, eligibility and the admission process for FMS and other university departments.', iit: 'Check each IIT management department’s current entrance, academic and selection criteria.', private: 'Check whether each private B-school accepts CAT for the intake year and review its current selection process.' }[pref];

      const profileNote = (class10 < 60 || class12 < 60 || grad < 60) ? 'One or more academic marks entered are below 60%; verify minimum eligibility requirements and any programme-specific criteria before applying.' : 'Check each institution’s academic eligibility and weighting; the same profile can be evaluated differently by different programmes.';

      bschoolResult.hidden = false;

      bschoolResult.innerHTML = `<span class="result-tag">ILLUSTRATIVE RESEARCH GROUP</span><h3>${range}</h3><p>This is a starting-point research plan for a CAT percentile estimate of ${percentile.toFixed(2)} and category ${category} — not an admission probability.</p><ul>${groups.map(item => `<li>${item}</li>`).join('')}</ul><p><strong>Preference:</strong> ${prefAdvice}</p><p><strong>Profile snapshot:</strong> Class 10 ${class10}%, Class 12 ${class12}%, graduation ${grad}%, work experience ${exp} month(s).</p><p>${profileNote}</p><p>Next: record the current criteria and application deadline for each programme using its official admissions page.</p>`;

    });

  }

  // FORM MANAGER

  console.info('[Erudite Form Manager] build 2026-11-07-fm5 loaded');

  // The navigation lists form metadata. The selected full HTML document is

  // opened in the dedicated viewer directly below the sticky site header.

  const formManagerSections = {

    MBA_IN_INDIA: { label: 'MBA in India' },

    UG_COLLEGE_TEST: { label: 'UG College Test' },

    STUDY_ABROAD: { label: 'Study Abroad' }

  };

  const formManagerRequests = new Map();

  let formManagerRenderSequence = 0;

  const formManagerViewer = document.querySelector('#form-manager-viewer');

  const formManagerViewerTitle = document.querySelector('#form-manager-viewer-title');

  const formManagerViewerStatus = document.querySelector('#form-manager-viewer-status');

  const formManagerFrame = document.querySelector('#form-manager-page-frame');

  const mainContent = document.querySelector('#main-content');

  const formManagerCloseButton = document.querySelector('#form-manager-viewer-close');

  function setFormManagerMenuMessage(target, message, isError = false) {

    target.replaceChildren();

    const item = document.createElement('span');

    item.className = 'form-manager-menu-message';

    item.textContent = message;

    if (isError) item.classList.add('is-error');

    target.append(item);

  }

  function setFormManagerViewerStatus(message = '', isError = false) {

    if (!formManagerViewerStatus) return;

    formManagerViewerStatus.textContent = message;

    formManagerViewerStatus.hidden = !message;

    formManagerViewerStatus.classList.toggle('is-error', isError);

  }

  function normaliseFormManagerRows(payload) {

    const rows = Array.isArray(payload)

      ? payload

      : (Array.isArray(payload?.forms) ? payload.forms : []);

    return rows.map((row) => ({

      id: String(row?.id ?? ''),

      name: String(row?.form_name ?? '')

    })).filter((row) => row.id && row.name);

  }

  function renderFormManagerMenu(target, section, forms) {

    target.replaceChildren();

    if (forms.length === 0) {

      setFormManagerMenuMessage(target, 'No forms currently available.');

      return;

    }

    forms.forEach((form) => {

      const link = document.createElement('a');

      const params = new URLSearchParams({ section, form_id: form.id });

      link.href = `index.html?${params.toString()}#form-manager-viewer`;

      link.textContent = form.name;

      link.addEventListener('click', (event) => {

        event.preventDefault();

        if (menuToggle?.getAttribute('aria-expanded') === 'true') {

          setNavigationOpen(false);

        } else {

          closeGroups();

        }

        openFormManagerViewer(form, section, true);

      });

      target.append(link);

    });

  }

  async function loadFormManagerSection(section, target) {

    if (!formManagerSections[section] || !target) return [];

    // Always refresh this section from CRM when it is opened. The external app
    // owns the database, so a permanent browser cache could show stale names.
    // Requests already in progress are deduplicated immediately below.

if (formManagerRequests.has(section)) {

      return formManagerRequests.get(section);

    }

    setFormManagerMenuMessage(target, 'Loading available forms…');

    const request = (async () => {

      try {

        const response = await fetch(

          `/api/forms/${encodeURIComponent(section)}/list`,

          {

            method: 'GET',

            credentials: 'same-origin',

            headers: { 'Accept': 'application/json' },

            cache: 'no-store'

          }

        );

        const payloadText = await response.text();

        const contentType = response.headers.get('content-type') || '';

        if (!response.ok) {

          let message = `Forms request failed (${response.status}).`;

          try {

            const errorPayload = JSON.parse(payloadText);

            if (errorPayload.message) message = errorPayload.message;

            if (errorPayload.verification_url) {

              message = 'Complete the security verification in this browser, then reopen this menu.';

            }

          } catch (_) {

            // Keep the HTTP status message for non-JSON responses.

          }

          throw new Error(message);

        }

        if (!contentType.toLowerCase().includes('application/json')) {

          throw new Error('The forms list endpoint did not return JSON.');

        }

        const forms = normaliseFormManagerRows(JSON.parse(payloadText));

        renderFormManagerMenu(target, section, forms);

        return forms;

      } catch (error) {

        console.error(`Form Manager could not load ${section}:`, error);

        setFormManagerMenuMessage(

          target,

          error.message || 'Forms could not be loaded. Try again.',

          true

        );

        return [];

      } finally {

        formManagerRequests.delete(section);

      }

    })();

    formManagerRequests.set(section, request);

    return request;

  }

  function updateFormManagerUrl(section, formId, push = true) {

    const url = new URL(window.location.href);

    url.searchParams.set('section', section);

    url.searchParams.set('form_id', formId);

    url.searchParams.delete('open_section');

    url.hash = 'form-manager-viewer';

    if (push) {

      history.pushState({}, '', url);

    } else {

      history.replaceState({}, '', url);

    }

  }

  function openFormManagerViewer(form, section, updateUrl = false) {

    if (!formManagerViewer || !formManagerFrame || !mainContent) {

      console.error('Form Manager viewer markup is missing from index.html.');

      return;

    }

    formManagerRenderSequence++;

    formManagerViewerTitle.textContent = form.name;

    setFormManagerViewerStatus('Opening form…');

    // The homepage content is temporarily hidden so the selected page occupies

    // the area directly below the unchanged sticky navigation/header.

    mainContent.hidden = true;

    formManagerViewer.hidden = false;

    formManagerViewer.dataset.section = section;

    formManagerViewer.dataset.formId = form.id;

    if (updateUrl) updateFormManagerUrl(section, form.id, true);

    // The endpoint responds with the complete stored HTML document (text/html).

    // Load it as a real iframe navigation; do not parse or nest the HTML.

    const documentUrl =

      `/api/forms/${encodeURIComponent(section)}/` +

      `${encodeURIComponent(form.id)}/document`;

    formManagerFrame.onload = () => {

      // The iframe load event also fires for many HTTP error responses.

      // Inspect this same-origin document so failures are visible in the viewer.

      try {

        const frameWindow = formManagerFrame.contentWindow;

        const frameDocument = formManagerFrame.contentDocument;

        const finalUrl = frameWindow ? frameWindow.location.href : '';

        const frameType = frameDocument ? frameDocument.contentType : '';

        if (finalUrl && new URL(finalUrl).pathname.includes('/security-check')) {

          setFormManagerViewerStatus(

            'Security verification is required for this form request. Complete verification, return here, and select the form again.',

            true

          );

          return;

        }

        if (frameType && !frameType.toLowerCase().includes('text/html')) {

          const responseText = frameDocument?.body?.innerText?.trim() || '';

          setFormManagerViewerStatus(

            `The document endpoint returned ${frameType}. ${responseText.slice(0, 300)}`,

            true

          );

          return;

        }

        const bodyText = frameDocument?.body?.innerText?.trim() || '';

        const titleText = frameDocument?.title?.toLowerCase() || '';

        if (/whoops|404 not found|500 internal server error/.test(titleText) ||

            /whoops, looks like something went wrong|404 not found|500 internal server error/.test(bodyText.toLowerCase())) {

          setFormManagerViewerStatus(

            'Laravel returned an error page instead of the stored form. Check the document route and Laravel logs.',

            true

          );

          return;

        }

        const htmlText = frameDocument?.documentElement?.outerHTML || '';

        if (!bodyText && htmlText.length < 80) {

          setFormManagerViewerStatus(

            'The document endpoint returned an empty HTML page. Check the CRM forms.forms value and the document API response.',

            true

          );

          return;

        }

      } catch (inspectionError) {

        console.warn('Could not inspect the form iframe response:', inspectionError);

      }

      setFormManagerViewerStatus('');

    };

    formManagerFrame.onerror = () => {

      setFormManagerViewerStatus(

        'The form document could not be loaded. Check the document endpoint and its response.',

        true

      );

    };

    formManagerFrame.removeAttribute('srcdoc');

    formManagerFrame.src = documentUrl;

    formManagerViewer.scrollIntoView({

      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',

      block: 'start'

    });

  }

  function closeFormManagerViewer(updateUrl = true) {

    formManagerRenderSequence++;

    if (formManagerFrame) {

      formManagerFrame.onload = null;

      formManagerFrame.onerror = null;

      formManagerFrame.src = 'about:blank';

    }

    if (formManagerViewer) {

      formManagerViewer.hidden = true;

      delete formManagerViewer.dataset.section;

      delete formManagerViewer.dataset.formId;

    }

    if (mainContent) mainContent.hidden = false;

    setFormManagerViewerStatus('');

    if (updateUrl) {

      const url = new URL(window.location.href);

      url.searchParams.delete('section');

      url.searchParams.delete('form_id');

      url.searchParams.delete('open_section');

      url.hash = '';

      history.pushState({}, '', url);

    }

    window.scrollTo({ top: 0, behavior: 'smooth' });

  }

  formManagerCloseButton?.addEventListener('click', () => {

    closeFormManagerViewer(true);

  });

  const formManagerMenus = [...document.querySelectorAll('[data-form-manager-menu]')];

  formManagerMenus.forEach((target) => {

    const section = target.dataset.formManagerMenu;

    const group = target.closest('.nav-group');

    if (!formManagerSections[section] || !group) return;

    const trigger = group.querySelector('.nav-trigger');

    // The navigation handler toggles .is-open first. Refresh only when the
    // user opens this section, not when the user closes it.
    trigger?.addEventListener('click', () => {
      if (group.classList.contains('is-open')) {
        loadFormManagerSection(section, target);
      }
    });

  });

  const isHomePage = /(?:^|\/)index\.html$/.test(window.location.pathname)

    || window.location.pathname.endsWith('/');

  const formManagerQuery = new URLSearchParams(window.location.search);

  const requestedSection = formManagerQuery.get('section');

  const requestedFormId = formManagerQuery.get('form_id');

  const requestedOpenSection = formManagerQuery.get('open_section');

  if (requestedOpenSection && formManagerSections[requestedOpenSection]) {

    const target = formManagerMenus.find(

      (menu) => menu.dataset.formManagerMenu === requestedOpenSection

    );

    const group = target?.closest('.nav-group');

    if (group) {

      closeGroups(group);

      group.classList.add('is-open');

      group.querySelector('.nav-trigger')?.setAttribute('aria-expanded', 'true');

      if (window.matchMedia('(max-width: 820px)').matches) setNavigationOpen(true);

      loadFormManagerSection(requestedOpenSection, target);

    }

  }

  if (

    isHomePage && requestedSection && requestedFormId &&

    formManagerSections[requestedSection]

  ) {

    const target = formManagerMenus.find(

      (menu) => menu.dataset.formManagerMenu === requestedSection

    );

    loadFormManagerSection(requestedSection, target).then((forms) => {

      const selected = forms.find((form) => form.id === String(requestedFormId));

      if (selected) {

        openFormManagerViewer(selected, requestedSection, false);

      } else {

        if (formManagerViewer && mainContent) {

          formManagerViewer.hidden = false;

          mainContent.hidden = true;

          formManagerViewerTitle.textContent = 'Form unavailable';

          setFormManagerViewerStatus(

            'This form is not available in the selected section.',

            true

          );

        }

      }

    });

  }

  window.addEventListener('popstate', () => {

    const params = new URLSearchParams(window.location.search);

    const section = params.get('section');

    const formId = params.get('form_id');

    if (!section || !formId || !formManagerSections[section]) {

      closeFormManagerViewer(false);

      return;

    }

    const target = formManagerMenus.find(

      (menu) => menu.dataset.formManagerMenu === section

    );

    loadFormManagerSection(section, target).then((forms) => {

      const selected = forms.find((form) => form.id === String(formId));

      if (selected) openFormManagerViewer(selected, section, false);

    });

  });

  // HOMEPAGE COURSE CARDS
  // One card is rendered for each complete course record returned by FormManager.
  const homepageCourseGrid = document.querySelector('#homepage-course-grid');

  const homepageSectionLabels = {
    MBA_IN_INDIA: 'MBA IN INDIA',
    UG_COLLEGE_TEST: 'UG COLLEGE TEST',
    STUDY_ABROAD: 'STUDY ABROAD'
  };

  function setHomepageCourseState(message, isError = false) {
    if (!homepageCourseGrid) return;

    const state = document.createElement('p');
    state.className = 'homepage-course-state';
    state.textContent = message;
    if (isError) state.classList.add('is-error');

    homepageCourseGrid.replaceChildren(state);
    homepageCourseGrid.setAttribute('aria-busy', 'false');
  }

  function formatCoursePrice(price) {
    const amount = Number(price);
    if (!Number.isFinite(amount)) return '';

    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0
    }).format(amount);
  }

  function createHomepageCourseCard(course) {
    const card = document.createElement('article');
    card.className = 'homepage-course-card';
    card.dataset.formId = String(course.id);

    const media = document.createElement('div');
    media.className = 'homepage-course-card-media';

    const image = document.createElement('img');
    image.src = course.image_url;
    image.alt = `${course.title} course image`;
    image.loading = 'lazy';
    image.decoding = 'async';
    image.addEventListener('error', () => {
      image.remove();
      media.classList.add('is-image-missing');
      const fallback = document.createElement('span');
      fallback.className = 'homepage-course-image-fallback';
      fallback.textContent = 'Course image unavailable';
      media.append(fallback);
    }, { once: true });
    media.append(image);

    const body = document.createElement('div');
    body.className = 'homepage-course-card-body';

    const section = document.createElement('p');
    section.className = 'homepage-course-card-section';
    section.textContent = homepageSectionLabels[course.section] || 'ERUDITE PROGRAMME';

    const title = document.createElement('h3');
    title.className = 'homepage-course-card-title';
    title.textContent = course.title;

    const description = document.createElement('p');
    description.className = 'homepage-course-card-description';
    description.textContent = course.description;

    const footer = document.createElement('div');
    footer.className = 'homepage-course-card-footer';

    const priceGroup = document.createElement('div');
    priceGroup.className = 'homepage-course-card-price-group';

    const priceLabel = document.createElement('span');
    priceLabel.className = 'homepage-course-card-price-label';
    priceLabel.textContent = 'COURSE PRICE';

    const price = document.createElement('strong');
    price.className = 'homepage-course-card-price';
    price.textContent = formatCoursePrice(course.price);

    priceGroup.append(priceLabel, price);

    const bookLink = document.createElement('a');
    bookLink.className = 'button button-primary homepage-course-book-button';
    const bookingParams = new URLSearchParams({
      section: course.section,
      form_id: String(course.id)
    });
    bookLink.href = `booking.html?${bookingParams.toString()}`;
    bookLink.append(document.createTextNode('Book Now '));

    const arrow = document.createElement('span');
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '↗';
    bookLink.append(arrow);

    footer.append(priceGroup, bookLink);
    body.append(section, title, description, footer);
    card.append(media, body);

    return card;
  }

  function normaliseHomepageCourses(payload) {
    const rows = Array.isArray(payload?.data) ? payload.data : [];

    return rows.map((row) => {
      const id = Number(row?.id);
      const section = String(row?.section ?? '');
      const title = String(row?.title ?? '').trim();
      const description = String(row?.description ?? '').trim();
      const price = row?.price;
      const imageUrl = String(row?.image_url ?? '').trim();

      if (
        !Number.isSafeInteger(id) || id < 1 ||
        !homepageSectionLabels[section] ||
        !title || !description ||
        price === null || price === undefined || price === '' ||
        !imageUrl
      ) {
        return null;
      }

      let resolvedImageUrl;
      try {
        resolvedImageUrl = new URL(imageUrl, window.location.href);
      } catch (_) {
        return null;
      }

      if (!['http:', 'https:'].includes(resolvedImageUrl.protocol)) return null;

      return {
        id,
        section,
        title,
        description,
        price,
        image_url: resolvedImageUrl.href
      };
    }).filter(Boolean);
  }

  async function loadHomepageCourses() {
    if (!homepageCourseGrid) return;

    homepageCourseGrid.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch('/api/forms/homepage', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });

      const contentType = response.headers.get('content-type') || '';
      if (!response.ok) {
        throw new Error(`Course request failed (${response.status}).`);
      }
      if (!contentType.toLowerCase().includes('application/json')) {
        throw new Error('The homepage course endpoint did not return JSON.');
      }

      const payload = await response.json();
      if (payload?.success === false) {
        throw new Error(payload.message || 'Courses could not be loaded.');
      }

      const courses = normaliseHomepageCourses(payload);
      if (courses.length === 0) {
        setHomepageCourseState('No forms, stay tuned for new courses.');
        return;
      }

      const cards = courses.map(createHomepageCourseCard);
      homepageCourseGrid.replaceChildren(...cards);
      homepageCourseGrid.setAttribute('aria-busy', 'false');

    } catch (error) {
      console.error('[Erudite Homepage] Could not load course cards:', error);

      const state = document.createElement('div');
      state.className = 'homepage-course-state homepage-course-state-error';

      const message = document.createElement('p');
      message.textContent = error.message || 'Courses could not be loaded right now.';

      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'button button-primary';
      retry.textContent = 'Try again';
      retry.addEventListener('click', loadHomepageCourses);

      state.append(message, retry);
      homepageCourseGrid.replaceChildren(state);
      homepageCourseGrid.setAttribute('aria-busy', 'false');
    }
  }

  if (homepageCourseGrid) loadHomepageCourses();

  // Booking forms are read from the shared CRM database through the Laravel API.

  const bookingSection = document.querySelector('#booking-section');

  const bookingForms = document.querySelector('#booking-forms');

  const bookingLoadFeedback = document.querySelector('#booking-load-feedback');

  const bookingSections = ['MBA_IN_INDIA', 'UG_COLLEGE_TEST', 'STUDY_ABROAD'];

  const showBookingMessage = (element, message, isError = false) => {

    if (!element) return;

    element.hidden = false;

    element.textContent = message;

    element.classList.toggle('is-error', isError);

  };

  function readCookie(name) {

    const prefix = `${encodeURIComponent(name)}=`;

    const item = document.cookie

      .split('; ')

      .find(cookie => cookie.startsWith(prefix));

    if (!item) return '';

    try {

      return decodeURIComponent(item.slice(prefix.length));

    } catch (_) {

      return '';

    }

  }

  async function postBookingJson(url, data) {

    const headers = {

      'Content-Type': 'application/json',

      'Accept': 'application/json'

    };

    const xsrfToken = readCookie('XSRF-TOKEN');

    if (xsrfToken) {

      headers['X-XSRF-TOKEN'] = xsrfToken;

    }

    const response = await fetch(url, {

      method: 'POST',

      credentials: 'same-origin',

      headers,

      body: JSON.stringify(data)

    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok) {

      const validationMessage = result.errors

        ? Object.values(result.errors).flat().join(' ')

        : result.message;

      throw new Error(

        validationMessage || 'The request could not be completed.'

      );

    }

    return result;

  }

  function connectBookingForm(form) {

    const nameInput = form.elements.namedItem('name');

    const emailInput = form.elements.namedItem('email');

    const cityInput = form.elements.namedItem('city');

    const submitButton = form.querySelector('[type="submit"]');

    const feedback = document.createElement('p');

    feedback.className = 'form-message booking-form-feedback';

    feedback.setAttribute('role', 'status');

    feedback.setAttribute('aria-live', 'polite');

    form.append(feedback);

    if (!nameInput || !emailInput || !cityInput) {

      showBookingMessage(

        feedback,

        'This database form must include fields named name, email and city to submit a booking.',

        true

      );

      if (submitButton) submitButton.disabled = true;

      return;

    }

    const verification = document.createElement('div');

    verification.className = 'booking-verification';

    verification.innerHTML = `

      <label>Email verification code

        <input name="booking_otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="6-digit OTP">

      </label>

      <button class="button button-primary booking-send-otp" type="button">Send OTP</button>

    `;

    form.append(verification);

    const otpInput = verification.querySelector('[name="booking_otp"]');

    const sendButton = verification.querySelector('.booking-send-otp');

    sendButton.addEventListener('click', async () => {

      const email = String(emailInput.value || '').trim();

      if (!emailInput.checkValidity() || !email) {

        showBookingMessage(feedback, 'Enter a valid email address before requesting an OTP.', true);

        emailInput.focus();

        return;

      }

      sendButton.disabled = true;

      sendButton.textContent = 'Sending…';

      try {

        const result = await postBookingJson('/api/booking/otp/send', { recipient: email });

        showBookingMessage(feedback, result.message || 'OTP sent. Check your email.');

      } catch (error) {

        showBookingMessage(feedback, error.message, true);

      } finally {

        sendButton.disabled = false;

        sendButton.textContent = 'Send OTP';

      }

    });

    form.addEventListener('submit', async (event) => {

      event.preventDefault();

      if (!form.reportValidity()) return;

      const payload = {

        name: String(nameInput.value || '').trim(),

        email: String(emailInput.value || '').trim(),

        city: String(cityInput.value || '').trim(),

        otp: String(otpInput.value || '').trim()

      };

      if (!payload.name || !payload.email || !payload.city) {

        showBookingMessage(feedback, 'Complete your name, email and city.', true);

        return;

      }

      const button = submitButton || form.querySelector('button[type="submit"], input[type="submit"]');

      if (button) button.disabled = true;

      try {

        const result = await postBookingJson('/api/booking/confirm', payload);

        showBookingMessage(feedback, result.message || 'Booking request received successfully.');

        form.reset();

      } catch (error) {

        showBookingMessage(feedback, error.message, true);

      } finally {

        if (button) button.disabled = false;

      }

    });

  }

  async function loadBookingForms(section) {

    if (!bookingForms || !bookingLoadFeedback) return;

    if (!bookingSections.includes(section)) {

      bookingForms.replaceChildren();

      showBookingMessage(bookingLoadFeedback, 'Choose a section to view its available forms.');

      return;

    }

    bookingForms.innerHTML = '';

    showBookingMessage(bookingLoadFeedback, 'Loading the current forms…');

    try {

      const response = await fetch(`/api/forms/${encodeURIComponent(section)}`, {

        headers: { 'Accept': 'text/html' },

        cache: 'no-store'

      });

      if (!response.ok) throw new Error('Could not load forms for this section.');

      const html = await response.text();

      bookingForms.innerHTML = html;

      const dynamicForms = bookingForms.querySelectorAll('.booking-form-content form');

      dynamicForms.forEach(connectBookingForm);

      if (!html.trim()) {

        showBookingMessage(bookingLoadFeedback, 'There are currently no forms available in this section.');

      } else {

        showBookingMessage(

          bookingLoadFeedback,

          dynamicForms.length

            ? `${dynamicForms.length} form(s) loaded. Complete a form and verify your email to submit.`

            : 'Forms were loaded, but no HTML form element was found in the stored content.',

          dynamicForms.length === 0

        );

      }

    } catch (error) {

      bookingForms.replaceChildren();

      showBookingMessage(bookingLoadFeedback, error.message, true);

    }

  }

  // Database-backed forms belong only on the booking page. The shared script

  // is loaded by every page, so require both the page URL and its mount point.

  const isBookingPage = window.location.pathname.replace(/\/+$/, '').endsWith('/booking.html');

  if (isBookingPage && bookingSection && bookingForms) {

    const requestedSection = new URLSearchParams(window.location.search).get('section');

    if (bookingSections.includes(requestedSection)) {

      bookingSection.value = requestedSection;

      loadBookingForms(requestedSection);

    }

    bookingSection.addEventListener('change', () => loadBookingForms(bookingSection.value));

  }

})();

// Persistent, user-controlled colour theme shared across the static pages.

(() => {

  const body = document.body;

  const toggles = document.querySelectorAll('.theme-toggle');

  const applyTheme = (dark) => {

    body.classList.toggle('theme-dark', dark);

    toggles.forEach(button => {

      button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');

      const label = button.querySelector('.theme-toggle-label');

      if (label) label.textContent = dark ? 'Light' : 'Dark';

      const icon = button.querySelector('[aria-hidden="true"]');

      if (icon) icon.textContent = dark ? '☼' : '◐';

    });

  };

  let saved = null;

  try { saved = localStorage.getItem('erudite-theme'); } catch (_) { }

  applyTheme(saved === 'dark');

  toggles.forEach(button => button.addEventListener('click', () => {

    const dark = !body.classList.contains('theme-dark');

    applyTheme(dark);

    try { localStorage.setItem('erudite-theme', dark ? 'dark' : 'light'); } catch (_) { }

  }));

})();

// Three small, responsive SVG charts. Every value is deliberately mock data until Erudite supplies verified figures.

(() => {

  const NS = 'http://www.w3.org/2000/svg';

  const datasets = {

    outcomes: { labels: ['2021', '2022', '2023', '2024', '2025'], values: [42, 56, 68, 83, 97], max: 110, type: 'bar', suffix: ' students' },

    growth: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], values: [48, 53, 57, 63, 69, 76], max: 100, type: 'line', suffix: ' / 100' },

    resources: { labels: ['Practice', 'Mocks', 'Review', 'Classes', 'Reading'], values: [4.5, 2.2, 2.8, 3.5, 2.0], max: 5, type: 'bar', suffix: ' hrs' }

  };

  function node(tag, attrs = {}, text = '') { const n = document.createElementNS(NS, tag); Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v)); if (text) n.textContent = text; return n; }

  function render(id, key) {

    const target = document.getElementById(id); if (!target) return; const d = datasets[key]; const w = 760, h = 240, p = { l: 38, r: 12, t: 18, b: 34 }, iw = w - p.l - p.r, ih = h - p.t - p.b; const svg = node('svg', { viewBox: `0 0 ${w} ${h}`, role: 'presentation', 'aria-hidden': 'true' });

    for (let i = 0; i <= 4; i++) { const y = p.t + ih * i / 4; svg.append(node('line', { x1: p.l, y1: y, x2: w - p.r, y2: y, class: 'grid-line' })); svg.append(node('text', { x: p.l - 8, y: y + 4, 'text-anchor': 'end', class: 'axis-label' }, String(Math.round(d.max * (1 - i / 4))))); }

    const slot = iw / d.values.length;

    if (d.type === 'bar') { d.values.forEach((v, i) => { const bh = v / d.max * ih, x = p.l + i * slot + slot * .2, y = p.t + ih - bh; svg.append(node('rect', { x, y, width: slot * .6, height: bh, rx: 1, class: i % 2 ? 'bar-soft' : 'bar' })); svg.append(node('text', { x: x + slot * .3, y: y - 6, 'text-anchor': 'middle', class: 'data-label' }, String(v))); svg.append(node('text', { x: p.l + (i + .5) * slot, y: h - 10, 'text-anchor': 'middle', class: 'axis-label' }, d.labels[i])); }); }

    else { const pts = d.values.map((v, i) => [p.l + (i / (d.values.length - 1)) * iw, p.t + ih - v / d.max * ih]); svg.append(node('polyline', { points: pts.map(a => a.join(',')).join(' '), class: 'trend-line' })); pts.forEach(([x, y], i) => { svg.append(node('circle', { cx: x, cy: y, r: 5, class: 'trend-dot' })); svg.append(node('text', { x, y: y - 12, 'text-anchor': 'middle', class: 'data-label' }, String(d.values[i]))); svg.append(node('text', { x, y: h - 10, 'text-anchor': 'middle', class: 'axis-label' }, d.labels[i])); }); }

    target.replaceChildren(svg); target.setAttribute('aria-label', `${d.labels.map((label, i) => `${label}: ${d.values[i]}${d.suffix}`).join('; ')}. Illustrative mock data, not official results.`);

  }

  Object.keys(datasets).forEach(key => render('chart-' + key, key));

  document.querySelectorAll('[data-chart-view]').forEach(btn => btn.addEventListener('click', () => { document.querySelectorAll('[data-chart-view]').forEach(b => { b.classList.toggle('is-active', b === btn); b.setAttribute('aria-pressed', String(b === btn)); }); const key = btn.dataset.chartView; document.querySelectorAll('.growth-card').forEach(card => { card.hidden = false; }); const target = document.getElementById('chart-' + key); if (target) { document.querySelectorAll('.growth-card').forEach(card => card.classList.toggle('chart-highlight', card.contains(target))); target.closest('.growth-card')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' }); } }));

})();
