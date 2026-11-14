/* =========================================================
   ERUDITE RESOURCE LIBRARY
   - Sections are discovered from resource_sections (the union of both tables).
   - Video and document records are fetched independently by section.
   - Every discovered section gets a table card for the selected resource type.
   - Empty or omitted data arrays still render the known table columns + empty row.
   - Video and document search states are completely independent.
   ========================================================= */
(() => {
  'use strict';

  const configuredBase = window.ERUDITE_RESOURCE_API_BASE || '/api';
  const API_BASE = String(configuredBase).replace(/\/+$/, '');
  const API_URL = new URL(API_BASE, window.location.href);
  const SECTION_ENDPOINT = `${API_BASE}/resources/sections`;

  const typeConfig = {
    videos: {
      table: 'video_resources',
      endpoint: (section) => `${API_BASE}/resources/video-resources/${encodeURIComponent(section)}`,
      panelId: 'video-resource-panel',
      groupsId: 'video-resource-groups',
      countsId: 'video-resource-counts',
      searchFormId: 'video-resource-search-form',
      searchInputId: 'video-resource-search-input',
      searchClearId: 'video-resource-search-clear',
      searchHintId: 'video-resource-search-hint',
      title: 'Video resources',
      itemLabel: 'video',
      emptyMessage: 'No video resources are available in this section yet.',
      loadMessage: 'Loading video resources…',
      // Mirrors video_resources. Section fields are included in the row as well
      // as the section card heading so the table reflects the actual DB schema.
      columns: [
        { key: 'id', label: 'ID' },
        { key: 'title', label: 'TITLE' },
        { key: 'title_description', label: 'TITLE DESCRIPTION' },
        { key: 'section', label: 'SECTION' },
        { key: 'section_description', label: 'SECTION DESCRIPTION' },
        { key: 'youtube_link', label: 'YOUTUBE LINK' },
        { key: 'created_at', label: 'CREATED AT' },
        { key: 'updated_at', label: 'UPDATED AT' }
      ]
    },
    documents: {
      table: 'document_resources',
      endpoint: (section) => `${API_BASE}/resources/document-resources/${encodeURIComponent(section)}`,
      panelId: 'document-resource-panel',
      groupsId: 'document-resource-groups',
      countsId: 'document-resource-counts',
      searchFormId: 'document-resource-search-form',
      searchInputId: 'document-resource-search-input',
      searchClearId: 'document-resource-search-clear',
      searchHintId: 'document-resource-search-hint',
      title: 'Document resources',
      itemLabel: 'document',
      emptyMessage: 'No documents are available in this section yet.',
      loadMessage: 'Select Document Resources to load documents.',
      // Mirrors document_resources. The LONGBLOB itself is never printed as
      // text; it is rendered as a download only when the API exposes file data
      // or a download URL.
      columns: [
        { key: 'id', label: 'ID' },
        { key: 'title', label: 'TITLE' },
        { key: 'title_description', label: 'TITLE DESCRIPTION' },
        { key: 'section', label: 'SECTION' },
        { key: 'section_description', label: 'SECTION DESCRIPTION' },
        { key: 'document', label: 'DOCUMENT' },
        { key: 'created_at', label: 'CREATED AT' },
        { key: 'updated_at', label: 'UPDATED AT' }
      ]
    }
  };

  const typeButtons = [...document.querySelectorAll('[data-resource-type]')];
  const sectionState = { loaded: false, promise: null, data: [] };
  const resourceCache = new Map();
  const states = {};

  for (const [type, config] of Object.entries(typeConfig)) {
    const panel = document.getElementById(config.panelId);
    const groups = document.getElementById(config.groupsId);
    const counts = document.getElementById(config.countsId);
    const searchForm = document.getElementById(config.searchFormId);
    const searchInput = document.getElementById(config.searchInputId);
    const searchClear = document.getElementById(config.searchClearId);
    const searchHint = document.getElementById(config.searchHintId);

    if (!panel || !groups || !counts || !searchForm || !searchInput || !searchClear || !searchHint) {
      console.error(`[Erudite Resources] Required ${type} panel elements are missing.`);
      return;
    }

    states[type] = {
      panel, groups, counts, searchForm, searchInput, searchClear, searchHint,
      searchTerm: '', loaded: false, loading: false, data: [], error: null
    };
  }

  async function requestJson(url) {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 15000);
    let response;

    try {
      response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
        signal: controller.signal
      });
    } catch (error) {
      if (error?.name === 'AbortError') {
        throw new Error(`Resource API timed out after 15 seconds: ${url}. Check that the Laravel backend is running and this route exists.`);
      }
      throw new Error(`Could not reach the Resource API at ${url}. ${error?.message || 'Check the backend server and route.'}`);
    } finally {
      window.clearTimeout(timeoutId);
    }

    const raw = await response.text();
    let payload = null;
    try {
      payload = raw ? JSON.parse(raw) : null;
    } catch (_) {
      payload = null;
    }

    if (response.status === 403 && payload?.verification_url) {
      // The page itself is protected by the same Laravel risk middleware.
      // If an API request is challenged again, complete the existing security
      // flow and return to this resource page (not to the raw JSON endpoint).
      const returnPath = `${window.location.pathname}${window.location.search}${window.location.hash}` || '/';
      const verificationUrl = new URL('/security-check', window.location.origin);
      verificationUrl.searchParams.set('return', returnPath);
      window.location.replace(verificationUrl.href);
      throw new Error('Security verification is required. Redirecting to the verification page…');
    }

    if (!response.ok) {
      const reason = payload?.message || payload?.error || `Request failed (${response.status}).`;
      const code = payload?.code ? ` [${payload.code}]` : '';
      throw new Error(`${reason}${code}`);
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
      throw new Error('The ResourceManager endpoint did not return a JSON object.');
    }

    if (payload.success !== true) {
      throw new Error(payload.message || 'The ResourceManager request was not successful.');
    }

    return payload;
  }

  function asArray(value) {
    // The response schema always contains data. If an older response omits it,
    // treat it as zero rows while still rendering the table structure.
    return Array.isArray(value) ? value : [];
  }

  async function loadSectionCatalogue() {
    if (sectionState.loaded) return sectionState.data;
    if (sectionState.promise) return sectionState.promise;

    sectionState.promise = (async () => {
      const payload = await requestJson(SECTION_ENDPOINT);
      if (payload.table !== 'resource_sections') {
        throw new Error(`Unexpected section table: ${String(payload.table || 'missing')}. Expected resource_sections.`);
      }

      const sections = asArray(payload.data)
        .map((entry) => ({
          section: String(entry?.section ?? '').trim(),
          section_description: String(entry?.section_description ?? '').trim()
        }))
        .filter((entry) => entry.section)
        .sort((a, b) => a.section.localeCompare(b.section, undefined, {
          sensitivity: 'base', numeric: true
        }));

      sectionState.data = sections;
      sectionState.loaded = true;
      return sections;
    })();

    try {
      return await sectionState.promise;
    } catch (error) {
      sectionState.promise = null;
      throw error;
    }
  }

  async function loadOneSection(section, type) {
    const config = typeConfig[type];
    const key = `${config.table}::${section}`;
    if (resourceCache.has(key)) return resourceCache.get(key);

    const payload = await requestJson(config.endpoint(section));

    if (payload.table !== config.table) {
      throw new Error(`Expected ${config.table} for ${section}, received ${String(payload.table || 'missing')}.`);
    }

    if (String(payload.section ?? '').toLocaleLowerCase() !== section.toLocaleLowerCase()) {
      throw new Error(`Section mismatch for ${section}.`);
    }

    const rows = asArray(payload.data);
    const catalogueEntry = sectionState.data.find(
      (item) => item.section.toLocaleLowerCase() === section.toLocaleLowerCase()
    );

    const result = {
      section: String(payload.section || section),
      section_description: String(payload.section_description ?? catalogueEntry?.section_description ?? ''),
      table: config.table,
      count: Number.isFinite(Number(payload.count)) ? Number(payload.count) : rows.length,
      items: rows
    };

    resourceCache.set(key, result);
    return result;
  }

  async function loadAllSectionsForType(type) {
    const sections = await loadSectionCatalogue();
    if (!sections.length) return [];

    // Fetch only the currently selected table. allSettled ensures a failure in
    // one section does not prevent the remaining section boxes from rendering.
    const requests = await Promise.allSettled(
      sections.map((entry) => loadOneSection(entry.section, type))
    );

    return sections.map((section, index) => {
      const outcome = requests[index];
      if (outcome.status === 'fulfilled') return outcome.value;

      console.error(
        `[Erudite Resources] Failed to load ${typeConfig[type].table} section ${section.section}:`,
        outcome.reason
      );

      return {
        section: section.section,
        section_description: section.section_description,
        table: typeConfig[type].table,
        count: 0,
        items: [],
        error: outcome.reason instanceof Error ? outcome.reason.message : 'This section could not be loaded.'
      };
    });
  }

  function makeTextCell(value, className = '') {
    const cell = document.createElement('td');
    if (className) cell.className = className;
    const text = String(value ?? '').trim();
    cell.textContent = text || '—';
    return cell;
  }

  function safeYouTubeUrl(value) {
    try {
      const url = new URL(String(value || ''));
      const hosts = new Set([
        'youtube.com', 'www.youtube.com', 'm.youtube.com',
        'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'
      ]);
      return ['http:', 'https:'].includes(url.protocol) && hosts.has(url.hostname.toLowerCase())
        ? url.href : null;
    } catch (_) {
      return null;
    }
  }

  function safeDocumentUrl(value) {
    if (!value) return null;
    try {
      // Resolve relative paths against the configured Laravel API base.
      const url = new URL(String(value), `${API_URL.href.replace(/\/+$/, '')}/`);
      if (!['http:', 'https:'].includes(url.protocol)) return null;

      const apiHost = API_URL.hostname.toLowerCase();
      const candidateHost = url.hostname.toLowerCase();
      const localHosts = new Set(['localhost', '127.0.0.1', '::1']);
      const sameHost = candidateHost === apiHost;
      const bothLocal = localHosts.has(candidateHost) && localHosts.has(apiHost);
      if (!sameHost && !bothLocal) return null;

      // Laravel's route() helper may generate the download URL using APP_URL,
      // whose port can differ from the configured API port. Keep the trusted
      // route path but send it to the configured backend origin.
      return new URL(`${url.pathname}${url.search}`, API_URL.origin).href;
    } catch (_) {
      return null;
    }
  }

  function formatDate(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-IN', {
      day: '2-digit', month: 'short', year: 'numeric'
    }).format(date);
  }

  function setPanelMessage(type, message, isError = false) {
    const state = states[type];
    const element = document.createElement('div');
    element.className = `resource-state${isError ? ' is-error' : ''}`;
    element.textContent = message;
    state.groups.replaceChildren(element);
    state.groups.setAttribute('aria-busy', 'false');
  }

  function appendEmptyRow(tbody, columnCount, message, className = 'resource-empty-row') {
    const row = document.createElement('tr');
    row.className = className;
    const cell = document.createElement('td');
    cell.className = 'resource-table-empty';
    cell.colSpan = columnCount;
    cell.textContent = message;
    row.append(cell);
    tbody.append(row);
  }

  function makeDownloadAnchor(href, label, fileName = '') {
    const link = document.createElement('a');
    link.href = href;
    link.textContent = label;
    link.rel = 'noopener noreferrer';
    if (fileName) link.setAttribute('download', fileName);
    return link;
  }

  function detectDocumentType(bytes) {
    const startsWith = (...signature) => signature.every((byte, index) => bytes[index] === byte);
    if (startsWith(0x25, 0x50, 0x44, 0x46, 0x2d)) return { mime: 'application/pdf', extension: '.pdf' };
    if (startsWith(0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a)) return { mime: 'image/png', extension: '.png' };
    if (startsWith(0xff, 0xd8, 0xff)) return { mime: 'image/jpeg', extension: '.jpg' };
    if (startsWith(0x47, 0x49, 0x46, 0x38)) return { mime: 'image/gif', extension: '.gif' };
    if (startsWith(0x50, 0x4b, 0x03, 0x04)) return { mime: 'application/zip', extension: '.zip' };
    if (startsWith(0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1)) return { mime: 'application/msword', extension: '.doc' };
    return { mime: 'application/octet-stream', extension: '.bin' };
  }

  function createDocumentBlobDownload(bytes, item, mimeHint = '') {
    if (!bytes || !bytes.length) return null;
    const detected = detectDocumentType(bytes);
    const mimeType = String(item?.document_mime_type || item?.mime_type || mimeHint || detected.mime);
    const suppliedName = String(item?.document_name || item?.file_name || '').trim();
    const fileName = suppliedName || `resource-${item?.id ?? 'document'}${detected.extension}`;
    return {
      href: URL.createObjectURL(new Blob([bytes], { type: mimeType })),
      fileName
    };
  }

  function blobUrlFromDocument(value, item) {
    if (value == null || value === '') return null;

    // Some API serializers expose a BLOB as a byte array (or Buffer-like object).
    if (Array.isArray(value) && value.length > 0
      && value.every((part) => Number.isInteger(Number(part)))) {
      return createDocumentBlobDownload(
        new Uint8Array(value.map((part) => Number(part) & 255)), item
      );
    }
    if (value && typeof value === 'object' && Array.isArray(value.data)
      && value.data.length > 0 && value.data.every((part) => Number.isInteger(Number(part)))) {
      return createDocumentBlobDownload(
        new Uint8Array(value.data.map((part) => Number(part) & 255)), item
      );
    }

    if (typeof value !== 'string') return null;
    const text = value.trim();
    if (!text) return null;

    // A backend may return a same-origin download URL in the document field.
    if (/^https?:\/\//i.test(text) || text.startsWith('/')) {
      const href = safeDocumentUrl(text);
      return href ? {
        href,
        fileName: String(item?.document_name || item?.file_name || `resource-${item?.id ?? 'document'}`)
      } : null;
    }

    // Support data URLs, explicitly marked base64, and a plain base64 BLOB
    // string. The document column is binary, so a long base64-looking value
    // is treated as file bytes rather than rendered as unreadable table text.
    let base64 = null;
    let detectedMime = '';
    const dataUrl = text.match(/^data:([^;,]+)?;base64,([\s\S]+)$/i);
    if (dataUrl) {
      detectedMime = dataUrl[1] || '';
      base64 = dataUrl[2].replace(/\s+/g, '');
    } else if (text.startsWith('base64:')) {
      base64 = text.slice(7).replace(/\s+/g, '');
    } else {
      const compact = text.replace(/\s+/g, '');
      if (compact.length >= 24 && /^[A-Za-z0-9+/]+={0,2}$/.test(compact)) base64 = compact;
    }

    if (base64 && /^[A-Za-z0-9+/]+={0,2}$/.test(base64)) {
      try {
        const binary = atob(base64);
        const data = new Uint8Array(binary.length);
        for (let index = 0; index < binary.length; index += 1) {
          data[index] = binary.charCodeAt(index);
        }
        const download = createDocumentBlobDownload(data, item, detectedMime);
        if (download) return download;
      } catch (_) {
        // Fall through to the UTF-8 text fallback below.
      }
    }

    // JSON can safely carry UTF-8 text documents as strings. Preserve those as
    // downloadable text files instead of exposing their contents in the table.
    const textBytes = new TextEncoder().encode(text);
    const suppliedName = String(item?.document_name || item?.file_name || '').trim();
    return {
      href: URL.createObjectURL(new Blob([textBytes], {
        type: String(item?.document_mime_type || item?.mime_type || 'text/plain;charset=utf-8')
      })),
      fileName: suppliedName || `resource-${item?.id ?? 'document'}.txt`
    };
  }

  function createVideoCell(item) {
    const cell = document.createElement('td');
    cell.className = 'resource-action-cell';
    const href = safeYouTubeUrl(item?.youtube_link);
    if (!href) {
      cell.textContent = item?.youtube_link ? 'Invalid YouTube link' : 'No video link supplied';
      cell.classList.add('is-unavailable');
      return cell;
    }

    const link = makeDownloadAnchor(href, '▶ Open YouTube video ↗');
    link.target = '_blank';
    cell.append(link);
    return cell;
  }

  function createDocumentCell(item) {
    const cell = document.createElement('td');
    cell.className = 'resource-action-cell';

    // download_url / has_document are optional API conveniences, not DB columns.
    // The actual persisted field is document (LONGBLOB), so use it as the
    // source of truth when those convenience fields are absent.
    const hasDocument = item?.has_document === true
      || Number(item?.has_document) === 1
      || (item?.document !== null && item?.document !== undefined && item?.document !== '');

    if (!hasDocument) {
      cell.textContent = 'No file uploaded';
      cell.classList.add('is-unavailable');
      return cell;
    }

    const downloadUrl = safeDocumentUrl(item?.download_url || item?.document_url);
    if (downloadUrl) {
      cell.append(makeDownloadAnchor(
        downloadUrl,
        '↓ Download document',
        String(item?.document_name || item?.file_name || `resource-${item?.id ?? 'document'}`)
      ));
      return cell;
    }

    const blobDownload = blobUrlFromDocument(item?.document, item);
    if (blobDownload) {
      const link = makeDownloadAnchor(blobDownload.href, '↓ Download document', blobDownload.fileName);
      link.addEventListener('click', () => {
        // Release the in-memory object URL after the browser has begun the download.
        window.setTimeout(() => URL.revokeObjectURL(blobDownload.href), 60000);
      }, { once: true });
      cell.append(link);
      return cell;
    }

    cell.textContent = 'File stored; download URL not supplied';
    cell.classList.add('is-unavailable');
    return cell;
  }

  function createResourceCell(item, column, sectionData) {
    const key = column.key;
    if (key === 'youtube_link') return createVideoCell(item);
    if (key === 'document') return createDocumentCell(item);

    let value = item?.[key];
    if (key === 'section') value = value || sectionData.section;
    if (key === 'section_description') value = value ?? sectionData.section_description;
    if (key === 'created_at' || key === 'updated_at') value = formatDate(value);

    const className = key === 'id'
      ? 'resource-id-cell'
      : key === 'title'
        ? 'resource-title-cell'
        : key === 'title_description' || key === 'section_description'
          ? 'resource-description-cell'
          : key === 'created_at' || key === 'updated_at'
            ? 'resource-updated-cell'
            : '';

    return makeTextCell(value, className);
  }

  function createSectionCard(sectionData, index, type) {
    const config = typeConfig[type];
    const card = document.createElement('article');
    card.className = 'resource-section-card';
    card.dataset.sectionSearch = [sectionData.section, sectionData.section_description]
      .join(' ').toLocaleLowerCase();

    const header = document.createElement('header');
    header.className = 'resource-section-header';

    const heading = document.createElement('div');
    heading.className = 'resource-section-heading';

    const kicker = document.createElement('p');
    kicker.className = 'resource-section-kicker';
    kicker.textContent = `SECTION ${String(index + 1).padStart(2, '0')}`;

    const title = document.createElement('h3');
    title.textContent = sectionData.section;

    const description = document.createElement('p');
    description.className = 'resource-section-description';
    description.textContent = sectionData.section_description || ' ';

    heading.append(kicker, title, description);

    const count = document.createElement('p');
    count.className = 'resource-section-count';
    count.textContent = `${sectionData.items.length} ${config.itemLabel}${sectionData.items.length === 1 ? '' : 's'}`;
    header.append(heading, count);

    const tableWrap = document.createElement('div');
    tableWrap.className = 'resource-table-wrap';

    const table = document.createElement('table');
    table.className = 'resource-table';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    config.columns.forEach((column) => {
      const th = document.createElement('th');
      th.scope = 'col';
      th.textContent = column.label;
      headerRow.append(th);
    });
    thead.append(headerRow);

    const tbody = document.createElement('tbody');

    if (sectionData.error) {
      appendEmptyRow(tbody, config.columns.length, `Could not load this section: ${sectionData.error}`, 'resource-error-row');
    } else if (!sectionData.items.length) {
      appendEmptyRow(tbody, config.columns.length, config.emptyMessage);
    } else {
      sectionData.items.forEach((item) => {
        const row = document.createElement('tr');
        config.columns.forEach((column) => {
          row.append(createResourceCell(item, column, sectionData));
        });
        row.dataset.searchText = [
          sectionData.section,
          sectionData.section_description,
          item?.id,
          item?.title,
          item?.title_description,
          item?.section,
          item?.section_description,
          type === 'videos' ? item?.youtube_link : item?.document_name
        ].join(' ').toLocaleLowerCase();
        tbody.append(row);
      });
    }

    table.append(thead, tbody);
    tableWrap.append(table);
    card.append(header, tableWrap);
    return card;
  }

  function applySearch(type) {
    const state = states[type];
    const query = state.searchTerm.trim().toLocaleLowerCase();
    const cards = [...state.groups.querySelectorAll('.resource-section-card')];
    let visibleSections = 0;
    let visibleRows = 0;

    cards.forEach((card) => {
      const matchesSection = !query || card.dataset.sectionSearch.includes(query);
      const rows = [...card.querySelectorAll('tbody tr')];
      let matchingRows = 0;

      rows.forEach((row) => {
        const isMessageRow = row.classList.contains('resource-empty-row') || row.classList.contains('resource-error-row');
        let visible;
        if (!query) {
          visible = true;
        } else if (isMessageRow) {
          visible = matchesSection;
        } else {
          visible = matchesSection || (row.dataset.searchText || '').includes(query);
        }

        row.hidden = !visible;
        if (visible && !isMessageRow) matchingRows += 1;
      });

      // A section-name match keeps the whole section table visible, including
      // its empty-state row. A resource match keeps only matching resource rows.
      const cardVisible = !query || matchesSection || matchingRows > 0;
      card.hidden = !cardVisible;
      if (cardVisible) visibleSections += 1;
      visibleRows += matchingRows;
    });

    state.groups.querySelector('.resource-no-results')?.remove();
    if (query && visibleSections === 0) {
      const message = document.createElement('div');
      message.className = 'resource-state resource-no-results';
      message.textContent = `No matching ${typeConfig[type].itemLabel} sections or records found.`;
      state.groups.append(message);
    }

    state.searchHint.textContent = query
      ? `${visibleSections} matching ${visibleSections === 1 ? 'section' : 'sections'} · ${visibleRows} matching ${typeConfig[type].itemLabel}${visibleRows === 1 ? '' : 's'}`
      : `Search ${typeConfig[type].itemLabel} sections, titles or descriptions.`;
    state.searchClear.hidden = !query;
  }

  function renderType(type) {
    const state = states[type];
    const config = typeConfig[type];
    state.groups.replaceChildren();

    if (!sectionState.data.length) {
      setPanelMessage(type, 'No resource sections are available yet.');
      state.counts.textContent = '0 sections';
      return;
    }

    // A section card is rendered for every section discovered across both
    // resource tables, even when this selected table has data: [].
    state.data.forEach((section, index) => {
      state.groups.append(createSectionCard(section, index, type));
    });

    const recordCount = state.data.reduce((sum, section) => sum + section.items.length, 0);
    state.counts.textContent = `${state.data.length} ${state.data.length === 1 ? 'section' : 'sections'} · ${recordCount} ${config.itemLabel}${recordCount === 1 ? '' : 's'}`;
    state.groups.setAttribute('aria-busy', 'false');
    applySearch(type);
  }

  async function loadType(type) {
    const state = states[type];
    if (state.loaded) {
      renderType(type);
      return;
    }
    if (state.loading) return;

    state.loading = true;
    state.counts.textContent = 'Loading sections…';
    state.groups.setAttribute('aria-busy', 'true');
    setPanelMessage(type, typeConfig[type].loadMessage);

    try {
      // Only this type's endpoint is called for every discovered section.
      state.data = await loadAllSectionsForType(type);
      state.loaded = true;
      state.error = null;
      renderType(type);
    } catch (error) {
      state.error = error;
      console.error(`[Erudite Resources] ${typeConfig[type].table} failed:`, error);
      state.counts.textContent = 'Resources unavailable';
      setPanelMessage(type, error.message || `${typeConfig[type].title} could not be loaded.`, true);
    } finally {
      state.loading = false;
    }
  }

  function showType(type) {
    if (!states[type]) return;
    for (const [candidate, state] of Object.entries(states)) {
      state.panel.hidden = candidate !== type;
    }

    typeButtons.forEach((button) => {
      const active = button.dataset.resourceType === type;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });

    loadType(type);
  }

  // Each search module owns its own form, input, clear button and query state.
  for (const [type, state] of Object.entries(states)) {
    state.searchForm.addEventListener('submit', (event) => {
      event.preventDefault();
      state.searchTerm = state.searchInput.value;
      if (state.loaded) applySearch(type);
    });

    state.searchInput.addEventListener('input', () => {
      state.searchTerm = state.searchInput.value;
      if (state.loaded) applySearch(type);
    });

    state.searchClear.addEventListener('click', () => {
      state.searchInput.value = '';
      state.searchTerm = '';
      if (state.loaded) applySearch(type);
      state.searchInput.focus();
    });
  }

  typeButtons.forEach((button) => {
    button.addEventListener('click', () => showType(button.dataset.resourceType));
  });

  // Start with Video Resources. Documents are not requested until selected.
  showType('videos');
})();
