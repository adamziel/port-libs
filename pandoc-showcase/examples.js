import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';

const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'HTML',
  wpBlocks: 'WordPress Block markup',
  haskell: 'Pandoc baseline',
};
const defaultView = 'wpBlocks';
const exampleUrlParameter = 'example';
const playgroundPluginBuild = 'pdf-page-checkpoints-20260715';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';
const playgroundUploadDirectory = '/tmp/port-libs-converter';
const playgroundPdfRasterByteLimit = 24_000_000;
const ownFileStatusPollIntervalMs = 1_000;
// The static example browser runs on the visitor's device, including phones.
// Keep Form-XObject enrichment deliberately smaller than the importer handoff:
// it is an optional preview, never a reason to exhaust the browser.
const staticPdfPreviewMaxSourceBytes = 4_000_000;
const staticPdfPreviewMaxRequests = 8;
const staticPdfPreviewMaxPixels = 2_000_000;
const staticPdfPreviewMaxTotalPixels = 8_000_000;
const staticPdfPreviewMaxImageBytes = 8_000_000;

const examplePicker = document.getElementById('example-picker');
const previousButton = document.getElementById('previous-example');
const nextButton = document.getElementById('next-example');
const viewButtons = Array.from(document.querySelectorAll('[data-example-view]'));
const viewerStatus = document.getElementById('viewer-status');
const downloadSource = document.getElementById('download-source');
const tryOwnFileButton = document.getElementById('try-own-file');
const ownFileInput = document.getElementById('own-file-input');
const frame = document.getElementById('example-frame');

const state = {
  examples: [],
  selectedId: '',
  defaultExampleId: '',
  view: defaultView,
  automaticViewMaxBytes: 0,
  loadToken: 0,
  ownFileToken: 0,
  ownFileBusy: false,
  frameMode: 'example',
  playgroundClient: null,
  playgroundReady: false,
  playgroundBootPromise: null,
  startPlaygroundWeb: null,
  decodePdfJbig2Rasters: null,
  decodePdfJpxRasters: null,
  staticPdfPreviewCache: new Map(),
  staticPdfPreviewAbortController: null,
};

function selectedExample() {
  return state.examples.find((example) => example.id === state.selectedId) || null;
}

function selectedView(example = selectedExample()) {
  return example && example.views ? example.views[state.view] || null : null;
}

function isBrowsableView(view) {
  return Boolean(view && view.ok && view.path && view.bytes > 0
    && view.bytes <= state.automaticViewMaxBytes);
}

function browsableExamples() {
  return state.examples.filter((example) => isBrowsableView(example.views && example.views.phpHtml));
}

function setStatus(message, { visible = false, tone = 'info' } = {}) {
  viewerStatus.textContent = message;
  viewerStatus.hidden = !visible;
  if (visible) {
    viewerStatus.dataset.tone = tone;
  } else {
    delete viewerStatus.dataset.tone;
  }
}

function createOption(value, label) {
  const option = document.createElement('option');
  option.value = value;
  option.textContent = label;
  return option;
}

function exampleIdFromUrl() {
  return new URL(window.location.href).searchParams.get(exampleUrlParameter);
}

function syncExampleUrl() {
  const url = new URL(window.location.href);
  const currentExampleId = url.searchParams.get(exampleUrlParameter);
  if (state.selectedId) {
    if (currentExampleId === state.selectedId) {
      return;
    }
    url.searchParams.set(exampleUrlParameter, state.selectedId);
  } else {
    if (currentExampleId === null) {
      return;
    }
    url.searchParams.delete(exampleUrlParameter);
  }

  window.history.replaceState(null, '', url);
}

function ensureBrowsableView() {
  if (isBrowsableView(selectedView())) {
    return;
  }

  const example = selectedExample();
  for (const fallbackView of [defaultView, 'phpHtml', 'haskell']) {
    const view = example && example.views ? example.views[fallbackView] : null;
    if (isBrowsableView(view)) {
      state.view = fallbackView;
      return;
    }
  }
}

function browsableExampleId(preferredId) {
  const examples = browsableExamples();
  if (examples.some((example) => example.id === preferredId)) {
    return preferredId;
  }

  const defaultExample = examples.find((example) => example.id === state.defaultExampleId);
  return defaultExample ? defaultExample.id : (examples[0] ? examples[0].id : '');
}

function applySelectedExample(preferredId, { load = true } = {}) {
  state.selectedId = browsableExampleId(preferredId);
  ensureBrowsableView();
  examplePicker.value = state.selectedId;
  updateDownloadSource();
  updateControls();
  if (load) {
    loadSelectedExample();
  }
}

function populateExamples(preferredId = state.selectedId) {
  const examples = browsableExamples();
  examplePicker.replaceChildren();
  examples.forEach((example) => {
    examplePicker.append(createOption(example.id, example.format + ' · ' + example.label));
  });
  applySelectedExample(preferredId, { load: false });
}

function updateViewButtons() {
  viewButtons.forEach((button) => {
    const active = button.dataset.exampleView === state.view;
    button.setAttribute('aria-pressed', String(active));
  });
}

function updateDownloadSource() {
  const example = selectedExample();
  if (!example || !example.samplePath) {
    downloadSource.hidden = true;
    downloadSource.removeAttribute('href');
    return;
  }
  downloadSource.href = example.samplePath;
  downloadSource.hidden = false;
}

function updateControls() {
  const examples = browsableExamples();
  const ready = examples.length > 0;
  const example = selectedExample();
  const busy = state.ownFileBusy;
  examplePicker.disabled = !ready || busy;
  previousButton.disabled = examples.length < 2 || busy;
  nextButton.disabled = examples.length < 2 || busy;
  viewButtons.forEach((button) => {
    const view = example && example.views ? example.views[button.dataset.exampleView] : null;
    button.disabled = !ready || !isBrowsableView(view) || busy;
  });
  downloadSource.setAttribute('aria-disabled', String(busy));
  downloadSource.tabIndex = busy ? -1 : 0;
  tryOwnFileButton.disabled = busy;
  ownFileInput.disabled = busy;
  updateViewButtons();
}

function setOwnFileBusy(busy, label = '') {
  state.ownFileBusy = busy;
  tryOwnFileButton.textContent = busy ? label : 'Try your own file';
  updateControls();
}

function leavePlaygroundView() {
  if (state.frameMode !== 'playground') {
    frame.setAttribute('sandbox', '');
    return;
  }

  state.ownFileToken += 1;
  state.frameMode = 'example';
  state.playgroundClient = null;
  state.playgroundReady = false;
  state.playgroundBootPromise = null;
  delete frame.dataset.loadedPath;
  frame.removeAttribute('src');
  frame.removeAttribute('srcdoc');
  frame.setAttribute('sandbox', '');
}

function unloadCurrentExample() {
  abortStaticPdfPreview({ clearCache: true });
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.removeAttribute('src');
  frame.removeAttribute('srcdoc');
  frame.hidden = true;
}

function staticPdfPreviewAbortError(signal) {
  if (signal?.reason instanceof Error) {
    return signal.reason;
  }
  const error = new Error('PDF chart preview was cancelled.');
  error.name = 'AbortError';
  return error;
}

function throwIfStaticPdfPreviewAborted(signal) {
  if (signal?.aborted) {
    throw staticPdfPreviewAbortError(signal);
  }
}

function abortStaticPdfPreview({ clearCache = false } = {}) {
  const controller = state.staticPdfPreviewAbortController;
  state.staticPdfPreviewAbortController = null;
  if (controller && !controller.signal.aborted) {
    controller.abort();
  }
  for (const [key, entry] of state.staticPdfPreviewCache) {
    if (entry?.controller === controller && entry.pending) {
      state.staticPdfPreviewCache.delete(key);
    }
  }
  if (clearCache) {
    state.staticPdfPreviewCache.clear();
  }
}

function rememberStaticPdfPreview(key, entry) {
  state.staticPdfPreviewCache.delete(key);
  state.staticPdfPreviewCache.set(key, entry);
  // One completed srcdoc can already contain several data-URI PNGs. Keep a
  // single LRU entry so switching through the catalogue cannot retain a
  // growing gallery in JavaScript memory.
  while (state.staticPdfPreviewCache.size > 1) {
    const oldestKey = state.staticPdfPreviewCache.keys().next().value;
    const oldest = state.staticPdfPreviewCache.get(oldestKey);
    state.staticPdfPreviewCache.delete(oldestKey);
    if (oldest?.pending && !oldest.controller.signal.aborted) {
      oldest.controller.abort();
    }
  }
}

function isCurrentExampleLoad(token, example, view) {
  const currentView = selectedView();
  return token === state.loadToken
    && state.frameMode === 'example'
    && selectedExample()?.id === example.id
    && currentView?.path === view.path;
}

function staticPdfFormPreviewEnabled(example, viewName) {
  const forms = example && example.pdfFormRenders;
  return (viewName === 'phpHtml' || viewName === 'wpBlocks')
    && Boolean(forms && forms.ok && forms.path && Number(forms.bytes) > 0);
}

function staticPdfPreviewCacheKey(example, viewName, view) {
  return [example.id, viewName, view.path].join('\u001f');
}

function staticPreviewUrl(path) {
  return new URL(path, window.location.href).href;
}

async function fetchStaticPreviewText(path, label, signal) {
  throwIfStaticPdfPreviewAborted(signal);
  const response = await fetch(staticPreviewUrl(path), { cache: 'no-store', signal });
  throwIfStaticPdfPreviewAborted(signal);
  if (!response.ok) {
    throw new Error(label + ' could not be loaded (' + response.status + ').');
  }

  const text = await response.text();
  throwIfStaticPdfPreviewAborted(signal);
  return { text, url: response.url || staticPreviewUrl(path) };
}

function staticPdfSourceLimitError() {
  const error = new Error('This PDF exceeds the static preview size limit.');
  error.code = 'static-pdf-source-limit';
  return error;
}

function staticPdfSourceLimitExceeded(error) {
  return error && typeof error === 'object' && error.code === 'static-pdf-source-limit';
}

async function fetchStaticPdfSource(samplePath, manifestUrl, signal) {
  const candidates = [
    staticPreviewUrl(samplePath),
    new URL(samplePath, manifestUrl).href,
  ].filter((candidate, index, all) => candidate && all.indexOf(candidate) === index);
  let failure = null;
  for (const url of candidates) {
    try {
      throwIfStaticPdfPreviewAborted(signal);
      const response = await fetch(url, { cache: 'no-store', signal });
      throwIfStaticPdfPreviewAborted(signal);
      if (!response.ok) {
        throw new Error('The original PDF could not be loaded (' + response.status + ').');
      }
      const announcedBytes = Number(response.headers.get('content-length'));
      if (Number.isFinite(announcedBytes) && announcedBytes > staticPdfPreviewMaxSourceBytes) {
        throw staticPdfSourceLimitError();
      }

      const bytes = new Uint8Array(await response.arrayBuffer());
      throwIfStaticPdfPreviewAborted(signal);
      if (bytes.byteLength > staticPdfPreviewMaxSourceBytes) {
        throw staticPdfSourceLimitError();
      }
      return bytes;
    } catch (error) {
      if (signal?.aborted) {
        throw staticPdfPreviewAbortError(signal);
      }
      if (staticPdfSourceLimitExceeded(error)) {
        throw error;
      }
      failure = error;
    }
  }

  throw failure || new Error('The original PDF could not be loaded.');
}

function staticPdfFilesByPath(requests, samplePath, bytes) {
  const files = new Map();
  if (samplePath) {
    files.set(samplePath, bytes);
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (path) {
      files.set(path, bytes);
    }
  }

  return files;
}

function rewriteStaticPreviewMediaUrls(previewDocument, viewUrl) {
  for (const element of previewDocument.querySelectorAll('img[src], source[src], video[src], audio[src], track[src], object[data]')) {
    const attribute = element.hasAttribute('data') ? 'data' : 'src';
    const value = String(element.getAttribute(attribute) || '').trim();
    if (!value || /^(?:[a-z][a-z0-9+.-]*:|#|\/\/)/i.test(value)) {
      continue;
    }
    try {
      element.setAttribute(attribute, new URL(value, viewUrl).href);
    } catch {
      // Keep malformed output untouched; the static preview remains safer
      // than failing the entire enhanced view over one optional media URL.
    }
  }
}

function normalizedPreviewText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function normalizedPdfTextAnchor(value) {
  // Positioned PDF text can retain a line-ending hyphen even though the
  // reflowed preview joins the rest of that word.  Anchors are only used as
  // unique prefixes, so trim the terminal discretionary hyphen generically.
  return normalizedPreviewText(value).replace(/(?:-|\u00ad)$/u, '');
}

function staticPdfUniqueTextAnchor(candidates, text) {
  if (text.length < 3) {
    return null;
  }
  const matches = candidates.filter((element) => normalizedPreviewText(element.textContent).includes(text));
  return matches.length === 1 ? { element: matches[0], index: candidates.indexOf(matches[0]) } : null;
}

function staticPdfTextAnchor(previewDocument, request) {
  const candidates = Array.from(previewDocument.body?.querySelectorAll('p, li, figcaption, h1, h2, h3, h4, h5, h6, pre, td, th') || []);
  const preceding = normalizedPdfTextAnchor(request?.precedingText || request?.anchorBefore);
  const following = normalizedPdfTextAnchor(request?.followingText || request?.anchorAfter);
  const precedingAnchor = staticPdfUniqueTextAnchor(candidates, preceding);
  const followingAnchor = staticPdfUniqueTextAnchor(candidates, following);
  // Match the importer: a unique following anchor is safest when it follows
  // the preceding one, and must receive the figure before its text. Ambiguous
  // text never becomes a guessed placement in the static preview.
  if (followingAnchor && (!precedingAnchor || precedingAnchor.index < followingAnchor.index)) {
    return { ...followingAnchor, position: 'before' };
  }
  if (precedingAnchor) {
    return { ...precedingAnchor, position: 'after' };
  }

  return null;
}

function staticPdfFormFigure(previewDocument, request, rendered, ordinal) {
  const figure = previewDocument.createElement('figure');
  figure.className = 'pandoc-pdf-form-figure wp-block-image';
  figure.dataset.pdfFormRequest = String(request?.id || ordinal + 1);
  if (Number.isInteger(request?.object)) {
    figure.dataset.pdfFormObject = String(request.object);
  }
  const label = String(request?.alt || request?.label || request?.title || '').trim();
  if (rendered?.bytes instanceof Uint8Array) {
    const image = previewDocument.createElement('img');
    image.alt = label || 'PDF figure ' + (ordinal + 1);
    image.dataset.pandocPdfFormRendered = 'true';
    image.decoding = 'async';
    const mimeType = String(rendered.mimeType || 'image/png');
    image.src = 'data:' + mimeType + ';base64,' + base64FromBytes(rendered.bytes);
    figure.append(image);
  } else {
    figure.classList.add('pandoc-pdf-form-placeholder');
    const message = previewDocument.createElement('p');
    const detail = String(rendered?.error || '').replace(/\s+/g, ' ').trim().slice(0, 240);
    message.textContent = (label || 'PDF figure ' + (ordinal + 1)) + ' could not be rendered in this browser'
      + (detail ? ': ' + detail : '.');
    figure.append(message);
  }
  const caption = String(request?.caption || request?.label || '').trim();
  if (caption) {
    const figcaption = previewDocument.createElement('figcaption');
    figcaption.textContent = caption;
    figure.append(figcaption);
  }

  return figure;
}

function injectStaticPdfFormFigures(previewDocument, requests, rendered) {
  const requestsById = new Map(requests.map((request) => [String(request?.id || ''), request]));
  const insertionPoints = new Map();
  const body = previewDocument.body || previewDocument.documentElement;
  let successful = 0;
  let failed = 0;
  rendered.forEach((item, ordinal) => {
    const request = requestsById.get(String(item?.requestId || '')) || requests[ordinal] || {};
    const figure = staticPdfFormFigure(previewDocument, request, item, ordinal);
    const anchor = staticPdfTextAnchor(previewDocument, request);
    const insertionPoint = anchor?.position === 'after' ? insertionPoints.get(anchor.element) || anchor.element : null;
    if (anchor?.position === 'before' && anchor.element.parentNode) {
      anchor.element.before(figure);
    } else if (insertionPoint?.parentNode) {
      insertionPoint.after(figure);
      insertionPoints.set(anchor.element, figure);
    } else {
      body.append(figure);
    }
    if (item?.bytes instanceof Uint8Array) {
      successful += 1;
    } else {
      failed += 1;
    }
  });

  return { successful, failed };
}

function addStaticPdfFormStyles(previewDocument) {
  if (previewDocument.getElementById('pandoc-pdf-form-preview-styles')) {
    return;
  }
  const style = previewDocument.createElement('style');
  style.id = 'pandoc-pdf-form-preview-styles';
  style.textContent = '.pandoc-pdf-form-figure{margin:1.25em 0}.pandoc-pdf-form-figure img{display:block;max-width:100%;height:auto}.pandoc-pdf-form-figure figcaption{margin-top:.45em;color:#4b5563;font-size:.9em}.pandoc-pdf-form-placeholder{padding:1em;border:1px dashed #aeb9c7;color:#4b5563}.pandoc-pdf-form-placeholder p{margin:0}';
  (previewDocument.head || previewDocument.documentElement).append(style);
}

function staticPdfFormPlaceholderResults(requests, message) {
  return requests.map((request) => ({
    requestId: String(request?.id || ''),
    error: message,
  }));
}

function staticPdfFormRequestPlan(requests) {
  const renderable = requests.slice(0, staticPdfPreviewMaxRequests);
  const skipped = staticPdfFormPlaceholderResults(
    requests.slice(staticPdfPreviewMaxRequests),
    'This static preview renders at most ' + staticPdfPreviewMaxRequests + ' PDF charts to keep browser memory bounded.',
  );
  return { renderable, skipped };
}

function staticPdfSourceIsTooLarge(example) {
  const sourceBytes = Number(example?.sampleSize);
  return Number.isFinite(sourceBytes) && sourceBytes > staticPdfPreviewMaxSourceBytes;
}

async function buildStaticPdfFormPreview(example, view, reportProgress, signal) {
  const formMetadata = example.pdfFormRenders;
  const [staticOutput, manifestOutput] = await Promise.all([
    fetchStaticPreviewText(view.path, 'The static preview', signal),
    fetchStaticPreviewText(formMetadata.path, 'The PDF figure manifest', signal),
  ]);
  throwIfStaticPdfPreviewAborted(signal);
  let manifest;
  try {
    manifest = JSON.parse(manifestOutput.text);
  } catch {
    throw new Error('The PDF figure manifest is not valid JSON.');
  }
  const requests = Array.isArray(manifest?.requests) ? manifest.requests : [];
  const samplePath = String(manifest?.samplePath || example.samplePath || '').trim();
  if (requests.length === 0 || !samplePath) {
    throw new Error('The PDF figure manifest has no renderable source.');
  }

  const plan = staticPdfFormRequestPlan(requests);
  let rendered = plan.skipped;
  if (staticPdfSourceIsTooLarge(example)) {
    rendered = staticPdfFormPlaceholderResults(
      requests,
      'This PDF exceeds the static preview size limit; its chart is shown as a placeholder.',
    );
  } else {
    try {
      reportProgress('Opening the original PDF for its charts…');
      const sourceBytes = await fetchStaticPdfSource(samplePath, manifestOutput.url, signal);
      const renderedRequests = await renderPdfFormRequests({
        filesByPath: staticPdfFilesByPath(plan.renderable, samplePath, sourceBytes),
        requests: plan.renderable,
        pdfjs: playgroundPdfJsConfig(),
        maxPixels: staticPdfPreviewMaxPixels,
        maxTotalPixels: staticPdfPreviewMaxTotalPixels,
        maxTotalImageBytes: staticPdfPreviewMaxImageBytes,
        signal,
        onProgress({ completed, total, label }) {
          reportProgress(total > 0 ? label + ' (' + completed + ' of ' + total + ')' : label);
        },
      });
      throwIfStaticPdfPreviewAborted(signal);
      rendered = [...renderedRequests, ...plan.skipped];
    } catch (error) {
      if (signal?.aborted) {
        throw staticPdfPreviewAbortError(signal);
      }
      if (!staticPdfSourceLimitExceeded(error)) {
        throw error;
      }
      rendered = staticPdfFormPlaceholderResults(
        requests,
        'This PDF exceeds the static preview size limit; its chart is shown as a placeholder.',
      );
    }
  }
  throwIfStaticPdfPreviewAborted(signal);
  const previewDocument = new DOMParser().parseFromString(staticOutput.text, 'text/html');
  rewriteStaticPreviewMediaUrls(previewDocument, staticOutput.url);
  addStaticPdfFormStyles(previewDocument);
  const counts = injectStaticPdfFormFigures(previewDocument, requests, rendered);
  if (counts.successful === 0 && counts.failed === 0) {
    throw new Error('The PDF figure renderer returned no chart results.');
  }

  return {
    html: '<!doctype html>\n' + previewDocument.documentElement.outerHTML,
    ...counts,
  };
}

function staticPdfFormPreviewDocument(example, view, viewName, reportProgress) {
  const key = staticPdfPreviewCacheKey(example, viewName, view);
  const cached = state.staticPdfPreviewCache.get(key);
  if (cached && !cached.controller.signal.aborted) {
    rememberStaticPdfPreview(key, cached);
    return cached.promise;
  }
  if (cached) {
    state.staticPdfPreviewCache.delete(key);
  }

  const controller = new AbortController();
  const entry = {
    controller,
    pending: true,
    promise: null,
  };
  const preview = buildStaticPdfFormPreview(example, view, reportProgress, controller.signal);
  entry.promise = preview;
  state.staticPdfPreviewAbortController = controller;
  rememberStaticPdfPreview(key, entry);
  preview.then(
    () => {
      entry.pending = false;
      if (state.staticPdfPreviewAbortController === controller) {
        state.staticPdfPreviewAbortController = null;
      }
    },
    () => {
      entry.pending = false;
      if (state.staticPdfPreviewAbortController === controller) {
        state.staticPdfPreviewAbortController = null;
      }
      if (state.staticPdfPreviewCache.get(key) === entry) {
        state.staticPdfPreviewCache.delete(key);
      }
    },
  );

  return preview;
}

function loadStaticPreviewUrl(example, view, token, warning = '') {
  window.requestAnimationFrame(() => {
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    frame.removeAttribute('srcdoc');
    frame.dataset.previewMode = warning ? 'fallback' : 'url';
    if (warning) {
      frame.dataset.previewStatus = warning;
    } else {
      delete frame.dataset.previewStatus;
    }
    frame.src = view.path;
  });
}

async function loadStaticPdfFormPreview(example, view, viewName, token) {
  try {
    const preview = await staticPdfFormPreviewDocument(example, view, viewName, (message) => {
      if (isCurrentExampleLoad(token, example, view)) {
        setStatus(message, { visible: true });
      }
    });
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    frame.dataset.previewMode = 'pdf-forms';
    frame.dataset.previewStatus = 'Loaded ' + example.label + ' with ' + preview.successful
      + ' PDF chart' + (preview.successful === 1 ? '' : 's')
      + (preview.failed > 0 ? '; ' + preview.failed + ' chart placeholder' + (preview.failed === 1 ? ' is' : 's are') + ' shown.' : '.');
    frame.removeAttribute('src');
    frame.srcdoc = preview.html;
  } catch (error) {
    if (!isCurrentExampleLoad(token, example, view)) {
      return;
    }
    const detail = error instanceof Error ? error.message : String(error);
    loadStaticPreviewUrl(
      example,
      view,
      token,
      'Could not render PDF charts here (' + detail + '). Showing the static preview instead.',
    );
  }
}

function loadSelectedExample() {
  if (state.ownFileBusy) {
    return;
  }
  abortStaticPdfPreview();
  leavePlaygroundView();
  const example = selectedExample();
  const view = selectedView(example);
  if (!example || !isBrowsableView(view)) {
    unloadCurrentExample();
    setStatus('No ' + viewLabels[state.view] + ' result is available for this example.');
    return;
  }

  const token = state.loadToken + 1;
  state.loadToken = token;
  const viewName = state.view;
  frame.hidden = false;
  frame.loading = 'eager';
  frame.dataset.loadedPath = view.path;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.setAttribute('sandbox', '');
  frame.removeAttribute('srcdoc');
  frame.removeAttribute('src');
  frame.src = 'about:blank';
  setStatus('Loading ' + example.label + '…', { visible: true });

  if (staticPdfFormPreviewEnabled(example, viewName)) {
    void loadStaticPdfFormPreview(example, view, viewName, token);
    return;
  }
  loadStaticPreviewUrl(example, view, token);
}

function moveExample(direction) {
  if (state.ownFileBusy) {
    return;
  }
  const examples = browsableExamples();
  if (examples.length === 0) {
    setStatus('No browsable example is available.');
    return;
  }

  const current = examples.findIndex((example) => example.id === state.selectedId);
  const nextIndex = current < 0
    ? (direction > 0 ? 0 : examples.length - 1)
    : (current + direction + examples.length) % examples.length;
  applySelectedExample(examples[nextIndex].id);
  syncExampleUrl();
}

function ownFileRequestIsCurrent(token) {
  return token === state.ownFileToken && state.frameMode === 'playground';
}

async function bootOwnFilePlayground() {
  if (state.playgroundReady) {
    return;
  }
  if (state.playgroundBootPromise) {
    await state.playgroundBootPromise;
    return;
  }

  state.playgroundBootPromise = startOwnFilePlayground();
  await state.playgroundBootPromise;
}

async function startOwnFilePlayground() {
  try {
    const pluginUrl = new URL(`playground/port-libs-playground-converter.zip?v=${playgroundPluginBuild}`, window.location.href).href;
    if (!state.startPlaygroundWeb) {
      const playgroundModule = await import(playgroundClientModuleUrl);
      state.startPlaygroundWeb = playgroundModule.startPlaygroundWeb;
    }
    state.playgroundClient = await state.startPlaygroundWeb({
      iframe: frame,
      remoteUrl: 'https://playground.wordpress.net/remote.html',
      blueprint: {
        preferredVersions: {
          php: '8.4',
          wp: 'latest',
        },
        landingPage: '/',
        features: {
          networking: true,
        },
        steps: [
          { step: 'login' },
          {
            step: 'installPlugin',
            pluginData: {
              resource: 'url',
              url: pluginUrl,
            },
            options: {
              activate: true,
            },
          },
        ],
      },
    });
    await state.playgroundClient.isReady();
    state.playgroundReady = true;
  } catch (error) {
    state.playgroundBootPromise = null;
    state.playgroundClient = null;
    state.playgroundReady = false;
    throw error;
  }
}

async function openOwnFile(file) {
  if (!file || file.size <= 0) {
    setStatus('Choose a non-empty file to open in WordPress Playground.');
    return;
  }

  abortStaticPdfPreview({ clearCache: true });
  const token = state.ownFileToken + 1;
  state.ownFileToken = token;
  const reusingPlayground = state.frameMode === 'playground'
    && state.playgroundReady
    && state.playgroundClient;
  state.frameMode = 'playground';
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  delete frame.dataset.previewMode;
  delete frame.dataset.previewStatus;
  frame.removeAttribute('srcdoc');
  if (!reusingPlayground) {
    frame.removeAttribute('src');
    frame.removeAttribute('sandbox');
  }
  frame.hidden = false;
  frame.loading = 'eager';
  setOwnFileBusy(true, state.playgroundReady ? 'Preparing file…' : 'Opening Playground…');
  setStatus(state.playgroundReady
    ? 'Preparing ' + file.name + ' for WordPress Playground…'
    : 'Opening WordPress Playground for ' + file.name + '…', { visible: true });

  let playgroundClient = null;
  let stagedPath = '';
  try {
    await bootOwnFilePlayground();
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    playgroundClient = state.playgroundClient;
    if (!playgroundClient) {
      throw new Error('WordPress Playground was not ready to receive the selected file.');
    }

    setOwnFileBusy(true, 'Preparing file…');
    setStatus('Preparing ' + file.name + ' for upload…', { visible: true });
    const prepared = await payloadFromOwnFile(file, (message) => {
      setOwnFileBusy(true, message);
      setStatus(message, { visible: true });
    });
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    setOwnFileBusy(true, 'Uploading…');
    setStatus('Uploading ' + file.name + ' to WordPress Playground…', { visible: true });
    stagedPath = await stageOwnFileInPlayground(playgroundClient, prepared.bytes, token);
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    setOwnFileBusy(true, 'Creating import…');
    setStatus('Creating an import job for ' + file.name + '…', { visible: true });
    let job = await ownFilePluginRequest(playgroundClient, '/imports', {
      ...prepared.payload,
      stagedPath,
    });
    const reportedEventKeys = new Set();
    const reportJob = (snapshot) => {
      if (!ownFileRequestIsCurrent(token)) {
        return;
      }
      const label = ownFileImportProgressLabel(snapshot);
      const latestEvent = ownFileImportLatestNewEvent(snapshot, reportedEventKeys);
      const message = latestEvent ? `${label} ${latestEvent}` : label;
      setOwnFileBusy(true, message);
      setStatus(message, { visible: true });
    };
    reportJob(job);

    while (!['complete', 'failed'].includes(String(job.status || ''))) {
      if (!ownFileRequestIsCurrent(token)) {
        return;
      }
      if (Array.isArray(job.renderRequests) && job.renderRequests.length > 0) {
        const rendered = await renderPdfFormRequests({
          filesByPath: await pdfFilesForOwnFile(playgroundClient, job, file, prepared.bytes),
          requests: job.renderRequests,
          pdfjs: playgroundPdfJsConfig(),
          onProgress({ completed, total, label }) {
            if (!ownFileRequestIsCurrent(token)) {
              return;
            }
            const progress = `${label} (${completed} of ${total})`;
            setOwnFileBusy(true, progress);
            setStatus(progress, { visible: true });
          },
        });
        if (!ownFileRequestIsCurrent(token)) {
          return;
        }
        for (const item of rendered) {
          const rendererPayload = item.error
            ? { requestId: item.requestId, error: item.error }
            : {
              requestId: item.requestId,
              bytes: base64FromBytes(item.bytes),
              mimeType: item.mimeType,
              width: item.width,
              height: item.height,
            };
          job = await ownFilePluginRequest(
            playgroundClient,
            `/imports/${encodeURIComponent(job.jobId)}/rendered-media`,
            rendererPayload,
          );
          reportJob(job);
          if (!ownFileRequestIsCurrent(token)) {
            return;
          }
        }
        continue;
      }
      if (job.status === 'awaiting_renderer') {
        throw new Error('WordPress requested a PDF figure, but did not provide a renderable crop. Please try the file again.');
      }
      job = await advanceOwnFileImport(playgroundClient, job, token, reportJob);
      reportJob(job);
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'Conversion failed.');
    }
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }

    const data = job.result;
    await playgroundClient.goTo(playgroundPath(data.pageUrl));
    if (ownFileRequestIsCurrent(token)) {
      setStatus('Opened a new WordPress page for ' + file.name + '.', { visible: true, tone: 'success' });
    }
  } catch (error) {
    if (ownFileRequestIsCurrent(token)) {
      const message = error instanceof Error ? error.message : String(error);
      setStatus('Could not open ' + file.name + ' in WordPress Playground: ' + message, { visible: true, tone: 'error' });
    }
  } finally {
    if (stagedPath && playgroundClient) {
      try {
        await playgroundClient.unlink(stagedPath);
      } catch {
        // The converter removes successfully read sources. A failed request
        // can still leave one behind, so cleanup remains best effort here.
      }
    }
    if (token === state.ownFileToken) {
      setOwnFileBusy(false);
    }
  }
}

async function ownFilePluginRequest(playgroundClient, path, payload = {}, method = 'POST') {
  const request = {
    method,
    url: `/wp-json/port-libs/v1${path}`,
  };
  if (method !== 'GET') {
    request.headers = { 'Content-Type': 'application/json' };
    request.body = JSON.stringify(payload);
  }
  const response = await playgroundClient.request(request);
  const text = typeof response.text === 'function' ? await response.text() : response.text;
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error('WordPress Playground returned an unreadable import-job response. Please try the file again.');
  }
  if (!data.ok) {
    throw new Error(data.message || 'Conversion failed.');
  }

  return data;
}

async function advanceOwnFileImport(playgroundClient, job, token, reportJob) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  if (!jobId) {
    throw new Error('WordPress did not return an import job identifier. Please try the file again.');
  }
  const stopPolling = startOwnFileImportStatusPolling(playgroundClient, jobId, token, reportJob);
  try {
    return await ownFilePluginRequest(playgroundClient, `/imports/${jobId}/advance`, {});
  } finally {
    stopPolling();
  }
}

function startOwnFileImportStatusPolling(playgroundClient, jobId, token, reportJob) {
  let stopped = false;
  let timer = null;
  const poll = async () => {
    if (stopped || !ownFileRequestIsCurrent(token)) {
      return;
    }
    try {
      const snapshot = await ownFilePluginRequest(
        playgroundClient,
        `/imports/${jobId}`,
        undefined,
        'GET',
      );
      if (!stopped && ownFileRequestIsCurrent(token)) {
        reportJob(snapshot);
      }
    } catch {
      // The in-flight advance response remains authoritative. A transient
      // status poll failure should not abandon an otherwise healthy import.
    } finally {
      if (!stopped && ownFileRequestIsCurrent(token)) {
        timer = window.setTimeout(poll, ownFileStatusPollIntervalMs);
      }
    }
  };
  timer = window.setTimeout(poll, ownFileStatusPollIntervalMs);

  return () => {
    stopped = true;
    if (timer !== null) {
      window.clearTimeout(timer);
    }
  };
}

function ownFileImportProgressLabel(job) {
  const progress = job && typeof job.progress === 'object' ? job.progress : {};
  const label = String(progress.label || 'Import is continuing…');
  const completed = Math.max(0, Number(progress.completed || 0));
  const total = Math.max(1, Number(progress.total || 1));

  return total > 1 ? `${label} (${completed} of ${total})` : label;
}

function ownFileImportLatestNewEvent(job, reportedEventKeys) {
  let latestMessage = '';
  for (const event of Array.isArray(job?.events) ? job.events : []) {
    const key = [event?.time ?? '', event?.stage ?? '', event?.message ?? ''].join('\u001f');
    if (reportedEventKeys.has(key)) {
      continue;
    }
    reportedEventKeys.add(key);
    const message = String(event?.message || '').trim();
    if (message) {
      latestMessage = message;
    }
  }

  return latestMessage;
}

async function pdfFilesForOwnFile(playgroundClient, job, file, bytes) {
  const files = new Map();
  const requests = Array.isArray(job?.renderRequests) ? job.renderRequests : [];
  if (isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
    files.set(file.name, bytes);
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (path && isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
      // The server sanitizes upload names before it persists the job. This is
      // a one-file import, so each requested source path refers to these
      // browser-held PDF bytes even when its sanitized name differs locally.
      files.set(path, bytes);
    }
  }

  for (const request of requests) {
    const path = String(request?.path || '');
    if (!path || files.has(path)) {
      continue;
    }
    const source = await ownFilePdfRenderSource(playgroundClient, job, request);
    if (source) {
      files.set(path, source);
    }
  }

  return files;
}

async function ownFilePdfRenderSource(playgroundClient, job, request) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  const requestId = encodeURIComponent(String(request?.id || ''));
  if (!jobId || !requestId) {
    return null;
  }
  try {
    const source = await ownFilePluginRequest(
      playgroundClient,
      `/imports/${jobId}/render-source/${requestId}`,
      undefined,
      'GET',
    );
    const encoded = String(source.bytes || '');
    if (!encoded) {
      return null;
    }

    return bytesFromBase64(encoded);
  } catch {
    // Older plugin builds do not expose a stored ZIP member. PDF.js will
    // report the unavailable crop to WordPress, which leaves a visible
    // placeholder while the rest of the document is still imported.
    return null;
  }
}

function playgroundPdfJsConfig() {
  const base = new URL('vendor/pdfjs/', window.location.href).href;

  return {
    pdfjsModuleUrl: new URL('pdf.min.mjs', base).href,
    pdfjsWorkerUrl: new URL('pdf.worker.min.mjs', base).href,
    pdfjsWasmUrl: new URL('wasm/', base).href,
    pdfjsCMapUrl: new URL('cmaps/', base).href,
    pdfjsStandardFontDataUrl: new URL('standard_fonts/', base).href,
  };
}

async function payloadFromOwnFile(file, reportProgress) {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const payload = {
    filename: file.name,
    title: titleFromFilename(file.name),
    imageMode: 'important',
    pdfMode: 'layout',
  };
  if (!isLikelyPdfFile(file)) {
    return { payload, bytes };
  }

  const pdfRasterImages = await browserPdfRasterImages(bytes, reportProgress);
  return {
    bytes,
    payload: {
      ...payload,
      ...(pdfRasterImages.length > 0 ? { pdfRasterImages } : {}),
    },
  };
}

async function stageOwnFileInPlayground(playgroundClient, bytes, token) {
  await playgroundClient.mkdirTree(playgroundUploadDirectory);
  const id = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : String(Date.now()) + '-' + Math.random().toString(36).slice(2);
  const stagedPath = playgroundUploadDirectory + '/' + token + '-' + id.replace(/[^A-Za-z0-9-]/g, '') + '.upload';
  await playgroundClient.writeFile(stagedPath, bytes);

  return stagedPath;
}

async function browserPdfRasterImages(bytes, reportProgress) {
  const decoderEntries = [
    {
      label: 'JBIG2',
      load: async () => {
        if (!state.decodePdfJbig2Rasters) {
          const module = await import(new URL('pdf-jbig2-rasterizer.mjs?v=jbig2-raster-20260709', window.location.href).href);
          state.decodePdfJbig2Rasters = module.decodePdfJbig2Rasters;
        }
        return state.decodePdfJbig2Rasters;
      },
    },
    {
      label: 'JPEG 2000',
      load: async () => {
        if (!state.decodePdfJpxRasters) {
          const module = await import(new URL('pdf-jpx-rasterizer.mjs?v=jpx-raster-20260714', window.location.href).href);
          state.decodePdfJpxRasters = module.decodePdfJpxRasters;
        }
        return state.decodePdfJpxRasters;
      },
    },
  ];
  const loaded = await Promise.allSettled(decoderEntries.map(async (entry) => ({
    entry,
    decode: await entry.load(),
  })));
  const rasters = [];
  const objects = new Set();
  let remainingBytes = playgroundPdfRasterByteLimit;
  for (const [index, result] of loaded.entries()) {
    if (result.status !== 'fulfilled') {
      reportProgress(`Continuing without ${decoderEntries[index]?.label || 'one'} PDF image decoder…`);
      continue;
    }
    const { decode } = result.value;
    if (typeof decode !== 'function' || remainingBytes <= 0) {
      continue;
    }
    try {
      const decoded = await decode(bytes, {
        imageMode: 'important',
        maxPngBytes: remainingBytes,
        onProgress({ completed, total }) {
          reportProgress(total > 0
            ? `Preparing PDF images (${completed} of ${total})…`
            : 'Preparing PDF images…');
        },
      });
      for (const raster of decoded.rasters || []) {
        const object = String(Number(raster.object));
        if (objects.has(object) || !(raster.bytes instanceof Uint8Array) || raster.bytes.length > remainingBytes) {
          continue;
        }
        objects.add(object);
        rasters.push(raster);
        remainingBytes -= raster.bytes.length;
      }
    } catch {
      reportProgress(`Continuing without ${result.value.entry.label} PDF image decoder…`);
    }
  }

  return rasters.map((raster) => ({
    object: raster.object,
    bytes: base64FromBytes(raster.bytes),
    mimeType: raster.mimeType,
    width: raster.width,
    height: raster.height,
  }));
}

function base64FromBytes(bytes) {
  let binary = '';
  const chunkSize = 0x8000;
  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(offset, Math.min(offset + chunkSize, bytes.length)));
  }

  return btoa(binary);
}

function bytesFromBase64(base64) {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes;
}

function isLikelyPdfFile(file) {
  return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
}

function titleFromFilename(name) {
  const last = name.split('/').filter(Boolean).pop() || name;
  const stem = last.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem ? stem.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Converted document';
}

function playgroundPath(url) {
  try {
    const parsed = new URL(url);
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return url || '/';
  }
}

async function initialize() {
  try {
    const response = await fetch(catalogUrl, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error('catalogue request failed (' + response.status + ')');
    }
    const catalog = await response.json();
    if (!Array.isArray(catalog.examples) || catalog.examples.length === 0 || !Number.isFinite(catalog.automaticViewMaxBytes)) {
      throw new Error('catalogue payload is incomplete');
    }
    state.automaticViewMaxBytes = catalog.automaticViewMaxBytes;
    state.examples = catalog.examples.filter((example) => example && example.id && example.views);
    state.defaultExampleId = catalog.defaultExampleId || state.examples[0].id;
    const linkedExampleId = exampleIdFromUrl();
    state.selectedId = linkedExampleId === null ? state.defaultExampleId : linkedExampleId;
    populateExamples(state.selectedId);
    if (linkedExampleId !== null && linkedExampleId !== state.selectedId) {
      syncExampleUrl();
    }
    loadSelectedExample();
  } catch (error) {
    setStatus('Try reloading this page.');
  }
}

examplePicker.addEventListener('change', () => {
  if (state.ownFileBusy) {
    return;
  }
  applySelectedExample(examplePicker.value);
  syncExampleUrl();
});

previousButton.addEventListener('click', () => moveExample(-1));
nextButton.addEventListener('click', () => moveExample(1));

downloadSource.addEventListener('click', (event) => {
  if (state.ownFileBusy) {
    event.preventDefault();
  }
});

tryOwnFileButton.addEventListener('click', () => {
  if (state.ownFileBusy) {
    return;
  }
  ownFileInput.value = '';
  ownFileInput.click();
});

ownFileInput.addEventListener('change', () => {
  const file = ownFileInput.files && ownFileInput.files[0];
  ownFileInput.value = '';
  if (file) {
    void openOwnFile(file);
  }
});

viewButtons.forEach((button) => {
  button.addEventListener('click', () => {
    if (state.ownFileBusy) {
      return;
    }
    const nextView = button.dataset.exampleView;
    if (!nextView || !viewLabels[nextView] || nextView === state.view) {
      return;
    }
    state.view = nextView;
    updateControls();
    loadSelectedExample();
  });
});

frame.addEventListener('load', () => {
  const example = selectedExample();
  const path = frame.dataset.loadedPath;
  if (!example || !path) {
    return;
  }
  if (frame.dataset.previewMode === 'pdf-forms' && frame.hasAttribute('srcdoc')) {
    setStatus(frame.dataset.previewStatus || 'Loaded ' + example.label + '.', { visible: true, tone: 'success' });
    return;
  }
  if (frame.getAttribute('src') !== path) {
    return;
  }
  if (frame.dataset.previewMode === 'fallback') {
    setStatus(frame.dataset.previewStatus || 'Showing the static preview instead.', { visible: true });
    return;
  }
  setStatus('Loaded ' + example.label + '.');
});

initialize();
