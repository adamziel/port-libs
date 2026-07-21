import {
  PDF_STATIC_PREVIEW_RENDERER_SCHEMA,
  pdfFormRendererResourceSnapshot,
  renderPdfFormRequests,
  renderPdfFormRequestsIncrementally,
  renderPdfPageRasterRequests,
  renderPdfPageRasterRequestsIncrementally,
} from './pdfjs-form-rasterizer.mjs';
import {
  cancelImportMutationDurably,
  createImportJobSession,
  createPlaygroundPersistence,
  recoverImportMutation,
  resetPlaygroundIframeForRetry,
  startPlaygroundWithSnapshotRecovery,
} from './import-job-session.mjs?v=playground-retry-teardown-20260721';

const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'HTML',
  wpBlocks: 'WordPress Block markup',
  haskell: 'Pandoc baseline',
};
const defaultView = 'wpBlocks';
const exampleUrlParameter = 'example';
const playgroundPluginBuild = 'verified-pdf-prerender-20260720';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';
const playgroundUploadDirectory = '/tmp/port-libs-converter';
const playgroundPdfRasterByteLimit = 24_000_000;
const playgroundPdfFormTotalPixelLimit = 48_000_000;
const playgroundPdfFormTotalImageByteLimit = 24_000_000;
const playgroundPdfPageTotalPixelLimit = 128_000_000;
const playgroundPdfPageTotalImageByteLimit = 64 * 1024 * 1024;
const pdfPageRasterMethod = 'pdfjs-whole-page-raster';
const ownFileStatusPollIntervalMs = 1_000;
const ownFileAdvanceRecoveryAttempts = 3;

const examplePicker = document.getElementById('example-picker');
const previousButton = document.getElementById('previous-example');
const nextButton = document.getElementById('next-example');
const viewButtons = Array.from(document.querySelectorAll('[data-example-view]'));
const viewerStatus = document.getElementById('viewer-status');
const downloadSource = document.getElementById('download-source');
const tryOwnFileButton = document.getElementById('try-own-file');
const cancelOwnFileButton = document.getElementById('cancel-own-file');
const ownFileInput = document.getElementById('own-file-input');
const ownPdfOutputDialog = document.getElementById('own-pdf-output-dialog');
const ownPdfOutputMessage = document.getElementById('own-pdf-output-message');
const ownPdfOutputInputs = Array.from(document.querySelectorAll('input[name="own-pdf-output-mode"]'));
const frame = document.getElementById('example-frame');

const ownFileImportSession = createImportJobSession({
  storage: browserStorage(),
  storageKey: 'port-libs.playground-active-import.v1',
});
const ownFilePlaygroundPersistence = createPlaygroundPersistence({
  storage: browserStorage(),
  storageKey: 'port-libs.playground-import-site.v1',
  devicePath: `port-libs/${window.location.host}/playground-import-site-v1`,
});

const state = {
  examples: [],
  selectedId: '',
  defaultExampleId: '',
  view: defaultView,
  automaticViewMaxBytes: 0,
  loadToken: 0,
  ownFileToken: 0,
  ownFileBusy: false,
  ownFileCancelRequested: false,
  frameMode: 'example',
  playgroundClient: null,
  playgroundReady: false,
  playgroundBootPromise: null,
  startPlaygroundWeb: null,
  decodePdfJbig2Rasters: null,
  decodePdfJpxRasters: null,
  staticPdfPreviewCache: new Map(),
  staticPdfPreviewAbortController: null,
  lastOwnFileJob: null,
};

function selectedExample() {
  return state.examples.find((example) => example.id === state.selectedId) || null;
}

function selectedView(example = selectedExample()) {
  return example && example.views ? example.views[state.view] || null : null;
}

function isTypedPdfStatusPreview(view, viewName) {
  return Boolean(viewName === 'wpBlocks'
    && view
    && view.ok === false
    && /(?:^|\/)wordpress-blocks-preview\.html$/.test(String(view.path || ''))
    && (view.status === 'incomplete' || view.status === 'unsupported_no_text'));
}

function isBrowsableView(view, viewName) {
  return Boolean(view && (view.ok || isTypedPdfStatusPreview(view, viewName)) && view.path && view.bytes > 0
    && view.bytes <= state.automaticViewMaxBytes);
}

function browsableExamples() {
  return state.examples.filter((example) => (
    isBrowsableView(example.views && example.views.phpHtml, 'phpHtml')
    || isBrowsableView(example.views && example.views.wpBlocks, 'wpBlocks')
  ));
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
  if (isBrowsableView(selectedView(), state.view)) {
    return;
  }

  const example = selectedExample();
  for (const fallbackView of [defaultView, 'phpHtml', 'haskell']) {
    const view = example && example.views ? example.views[fallbackView] : null;
    if (isBrowsableView(view, fallbackView)) {
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
    button.disabled = !ready || !isBrowsableView(view, button.dataset.exampleView) || busy;
  });
  downloadSource.setAttribute('aria-disabled', String(busy));
  downloadSource.tabIndex = busy ? -1 : 0;
  tryOwnFileButton.disabled = busy;
  const cancellable = busy
    && String(state.lastOwnFileJob?.jobId || '') !== ''
    && !['complete', 'failed', 'cancelled'].includes(String(state.lastOwnFileJob?.status || ''));
  cancelOwnFileButton.hidden = !cancellable;
  cancelOwnFileButton.disabled = !cancellable || state.ownFileCancelRequested;
  cancelOwnFileButton.textContent = state.ownFileCancelRequested ? 'Cancelling after this step…' : 'Cancel import';
  ownFileInput.disabled = busy;
  updateViewButtons();
}

function setOwnFileBusy(busy, label = '') {
  state.ownFileBusy = busy;
  const savedImport = ownFileImportSession.load();
  tryOwnFileButton.textContent = busy
    ? label
    : (savedImport?.cancellationRequested
      ? 'Finish cancelling import'
      : (savedImport ? 'Resume saved import' : 'Try your own file'));
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
  const error = new Error('PDF figure/page-image preview was cancelled.');
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

function publishedPdfPreviewGroups(manifest) {
  const requests = Array.isArray(manifest?.requests) ? manifest.requests : [];
  const assets = Array.isArray(manifest?.prerenderedAssets) ? manifest.prerenderedAssets : [];
  const coverage = Array.isArray(manifest?.prerenderedRequestCoverage)
    ? manifest.prerenderedRequestCoverage
    : [];
  if (manifest?.prerenderVersion !== 1
    || manifest?.prerenderRendererSchema !== PDF_STATIC_PREVIEW_RENDERER_SCHEMA
    || requests.length === 0 || assets.length === 0
    || coverage.length !== requests.length) {
    throw new Error('The PDF publication manifest has incomplete static asset coverage.');
  }
  const requestsById = new Map(requests.map((request) => [String(request?.id || ''), request]));
  const assetsByPath = new Map(assets.map((asset) => [String(asset?.path || ''), asset]));
  if (requestsById.has('') || requestsById.size !== requests.length
    || assetsByPath.has('') || assetsByPath.size !== assets.length) {
    throw new Error('The PDF publication manifest contains blank or duplicate identities.');
  }
  const groupsByPath = new Map(assets.map((asset) => [String(asset.path), { asset, coverage: [], requests: [] }]));
  const coveredIds = new Set();
  for (const item of coverage) {
    const requestId = String(item?.requestId || '');
    const assetPath = String(item?.assetPath || '');
    const request = requestsById.get(requestId);
    const group = groupsByPath.get(assetPath);
    if (!request || !group || coveredIds.has(requestId)
      || !['before-anchor', 'after-anchor', 'page-gallery', 'existing-page-image'].includes(String(item?.placement || ''))) {
      throw new Error('The PDF publication manifest has invalid request coverage.');
    }
    coveredIds.add(requestId);
    group.coverage.push(item);
    group.requests.push(request);
  }
  const groups = [...groupsByPath.values()];
  if (coveredIds.size !== requests.length || groups.some((group) => group.coverage.length === 0)) {
    throw new Error('The PDF publication manifest does not cover every request and asset exactly.');
  }
  return groups;
}

function decoratePublishedPdfFigure(figure, image, group) {
  const requestIds = group.coverage.map((item) => String(item.requestId));
  const request = group.requests[0] || {};
  figure.classList.add('pandoc-pdf-form-figure', 'wp-block-image');
  figure.dataset.pdfFormRequestIds = requestIds.join(' ');
  if (requestIds.length === 1) figure.dataset.pdfFormRequest = requestIds[0];
  if (Number.isInteger(request.object)) figure.dataset.pdfFormObject = String(request.object);
  image.dataset.pandocPdfFormRendered = 'true';
  image.decoding = 'async';
  image.loading = group.coverage.every((item) => item.placement === 'page-gallery') ? 'lazy' : 'eager';
  if (!image.alt) image.alt = String(request.alt || request.label || 'Published PDF preview image');
  if (Number.isInteger(group.asset.width) && group.asset.width > 0) image.width = group.asset.width;
  if (Number.isInteger(group.asset.height) && group.asset.height > 0) image.height = group.asset.height;
}

function existingPublishedPdfFigure(previewDocument, group) {
  const expectedUrl = staticPreviewUrl(group.asset.path);
  const image = Array.from(previewDocument.querySelectorAll('img[src]')).find((candidate) => {
    try {
      return new URL(candidate.src, window.location.href).href === expectedUrl;
    } catch {
      return false;
    }
  });
  if (!image) throw new Error('The PDF publication manifest references a missing existing page image.');
  let figure = image.closest('figure');
  if (!figure) {
    figure = previewDocument.createElement('figure');
    const wrapper = image.parentElement;
    if (wrapper && wrapper.childElementCount === 1 && wrapper.parentNode) {
      wrapper.replaceWith(figure);
    } else {
      image.before(figure);
    }
    figure.append(image);
  }
  decoratePublishedPdfFigure(figure, image, group);
  return figure;
}

function publishedPdfTextAnchor(previewDocument, request, placement) {
  const candidates = Array.from(previewDocument.body?.querySelectorAll('p, li, figcaption, h1, h2, h3, h4, h5, h6, pre, td, th') || []);
  const text = placement === 'before-anchor'
    ? normalizedPdfTextAnchor(request?.followingText || request?.anchorAfter)
    : normalizedPdfTextAnchor(request?.precedingText || request?.anchorBefore);
  const anchor = staticPdfUniqueTextAnchor(candidates, text);
  return anchor ? { ...anchor, position: placement === 'before-anchor' ? 'before' : 'after' } : null;
}

function publishedPdfFigure(previewDocument, group) {
  const figure = previewDocument.createElement('figure');
  const image = previewDocument.createElement('img');
  image.src = staticPreviewUrl(group.asset.path);
  decoratePublishedPdfFigure(figure, image, group);
  figure.append(image);
  return figure;
}

function injectPublishedPdfFigures(previewDocument, groups) {
  const insertionPoints = new Map();
  const body = previewDocument.body || previewDocument.documentElement;
  let successful = 0;
  for (const group of groups) {
    const existing = group.coverage.every((item) => item.placement === 'existing-page-image');
    if (existing) {
      existingPublishedPdfFigure(previewDocument, group);
      successful += 1;
      continue;
    }
    const figure = publishedPdfFigure(previewDocument, group);
    const placementItem = group.coverage.find((item) => item.placement === 'before-anchor')
      || group.coverage.find((item) => item.placement === 'after-anchor');
    const requestIndex = placementItem ? group.coverage.indexOf(placementItem) : -1;
    const request = requestIndex >= 0 ? group.requests[requestIndex] : null;
    const anchor = placementItem ? publishedPdfTextAnchor(previewDocument, request, placementItem.placement) : null;
    const insertionPoint = anchor?.position === 'after' ? insertionPoints.get(anchor.element) || anchor.element : null;
    if (anchor?.position === 'before' && anchor.element.parentNode) {
      anchor.element.before(figure);
    } else if (insertionPoint?.parentNode) {
      insertionPoint.after(figure);
      insertionPoints.set(anchor.element, figure);
    } else {
      figure.classList.add('pandoc-pdf-page-gallery-item');
      body.append(figure);
    }
    successful += 1;
  }
  return { successful, failed: 0 };
}

function addStaticPdfFormStyles(previewDocument) {
  if (previewDocument.getElementById('pandoc-pdf-form-preview-styles')) {
    return;
  }
  const style = previewDocument.createElement('style');
  style.id = 'pandoc-pdf-form-preview-styles';
  style.textContent = '.pandoc-pdf-form-figure{margin:1.25em 0}.pandoc-pdf-form-figure img{display:block;max-width:100%;height:auto}.pandoc-pdf-page-gallery-item{content-visibility:auto;contain-intrinsic-size:auto 800px}';
  (previewDocument.head || previewDocument.documentElement).append(style);
}

async function buildStaticPdfFormPreview(example, view, reportProgress, signal) {
  const formMetadata = example.pdfFormRenders;
  const [staticOutput, manifestOutput] = await Promise.all([
    fetchStaticPreviewText(view.path, 'The static preview', signal),
    fetchStaticPreviewText(formMetadata.path, 'The PDF visual/page-image manifest', signal),
  ]);
  throwIfStaticPdfPreviewAborted(signal);
  let manifest;
  try {
    manifest = JSON.parse(manifestOutput.text);
  } catch {
    throw new Error('The PDF visual/page-image manifest is not valid JSON.');
  }
  throwIfStaticPdfPreviewAborted(signal);
  reportProgress('Loading published PDF preview assets…');
  const groups = publishedPdfPreviewGroups(manifest);
  const previewDocument = new DOMParser().parseFromString(staticOutput.text, 'text/html');
  rewriteStaticPreviewMediaUrls(previewDocument, staticOutput.url);
  addStaticPdfFormStyles(previewDocument);
  const counts = injectPublishedPdfFigures(previewDocument, groups);

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
      + ' published PDF preview image' + (preview.successful === 1 ? '' : 's') + '.';
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
      'Could not render PDF figures/page images here (' + detail + '). Showing the static preview instead.',
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
  if (!example || !isBrowsableView(view, state.view)) {
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
    const startOptions = {
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
    };
    state.playgroundClient = await startPlaygroundWithSnapshotRecovery({
      persistence: ownFilePlaygroundPersistence,
      options: startOptions,
      start: state.startPlaygroundWeb,
      onRecovery() {
        const message = 'The saved Playground database could not be reopened. Starting a fresh private WordPress site; the previous browser snapshot is preserved.';
        setOwnFileBusy(true, message);
        setStatus(message, { visible: true });
      },
      beforeRetry: () => resetPlaygroundIframeForRetry(frame),
    });
    await state.playgroundClient.isReady();
    try {
      await ownFilePlaygroundPersistence.persist(state.playgroundClient, (message) => {
        setOwnFileBusy(true, message);
        setStatus(message, { visible: true });
      });
    } catch (error) {
      const detail = error instanceof Error ? error.message : String(error);
      setStatus('This Playground could not be saved in browser storage: ' + detail, { visible: true });
    }
    state.playgroundReady = true;
  } catch (error) {
    // A CDN or startup failure does not prove that the OPFS snapshot is
    // corrupt. Keep its pointer so retrying cannot replace a valid WordPress
    // tree (and hundreds of durable page checkpoints) with a fresh site.
    state.playgroundBootPromise = null;
    state.playgroundClient = null;
    state.playgroundReady = false;
    throw error;
  }
}

function chooseOwnPdfOutputMode({ recovery = false, job = null } = {}) {
  if (!ownPdfOutputDialog || typeof ownPdfOutputDialog.showModal !== 'function') {
    return Promise.resolve(recovery ? 'pages' : 'single');
  }
  const actual = Math.max(0, Number(job?.failure?.actualBytes) || Number(job?.output?.assembledBytes) || 0);
  const allowed = Math.max(0, Number(job?.failure?.allowedBytes) || Number(job?.output?.singlePageLimitBytes) || 0);
  ownPdfOutputMessage.textContent = recovery
    ? `${formatBytes(actual)} of converted blocks exceeds the safe ${formatBytes(allowed)} single-page limit. No partial page was created; continue with the saved conversion.`
    : 'Choose how this PDF should become WordPress pages.';
  for (const input of ownPdfOutputInputs) {
    input.disabled = recovery && input.value === 'single';
    input.checked = input.value === (recovery ? 'pages' : 'single');
  }

  return new Promise((resolve) => {
    const closed = () => {
      ownPdfOutputDialog.removeEventListener('close', closed);
      if (ownPdfOutputDialog.returnValue !== 'import') {
        resolve(null);
        return;
      }
      resolve(ownPdfOutputInputs.find((input) => input.checked)?.value || (recovery ? 'pages' : 'single'));
    };
    ownPdfOutputDialog.addEventListener('close', closed);
    ownPdfOutputDialog.showModal();
  });
}

function createOwnFileJobReporter(token) {
  const reportedEventKeys = new Set();

  return (snapshot) => {
    ownFileImportSession.remember(snapshot);
    state.lastOwnFileJob = snapshot;
    if (!ownFileRequestIsCurrent(token)) {
      return;
    }
    const label = ownFileImportProgressLabel(snapshot);
    const latestEvent = ownFileImportLatestNewEvent(snapshot, reportedEventKeys);
    const message = latestEvent ? `${label} ${latestEvent}` : label;
    setOwnFileBusy(true, message);
    setStatus(message, { visible: true });
  };
}

async function driveOwnFileImport(playgroundClient, initialJob, token, reportJob, file = null, bytes = null) {
  let job = initialJob;
  const formBudget = {
    remainingPixels: playgroundPdfFormTotalPixelLimit,
    remainingImageBytes: playgroundPdfFormTotalImageByteLimit,
  };
  const pageBudget = {
    remainingPixels: playgroundPdfPageTotalPixelLimit,
    remainingImageBytes: playgroundPdfPageTotalImageByteLimit,
  };
  while (!['complete', 'failed', 'cancelled'].includes(String(job.status || ''))) {
    if (!ownFileRequestIsCurrent(token)) {
      return job;
    }
    if (state.ownFileCancelRequested) {
      job = await cancelOwnFileImport(playgroundClient, job, reportJob, token);
      reportJob(job);
      break;
    }
    if (job.status === 'awaiting_output_mode') {
      const recoveredMode = await chooseOwnPdfOutputMode({ recovery: true, job });
      if (recoveredMode !== 'pages') {
        setStatus('The completed conversion remains saved in WordPress Playground.', { visible: true });
        return job;
      }
      job = await ownFilePluginRequest(
        playgroundClient,
        `/imports/${encodeURIComponent(job.jobId)}/output-mode`,
        { pdfOutputMode: 'pages' },
      );
      reportJob(job);
      continue;
    }
    if (Array.isArray(job.renderRequests) && job.renderRequests.length > 0) {
      for (const group of pdfRenderRequestGroups(job.renderRequests)) {
        const requests = group.requests;
        const pageRaster = group.method === pdfPageRasterMethod;
        const budget = pageRaster ? pageBudget : formBudget;
        const filesByPath = budget.remainingPixels <= 0 || budget.remainingImageBytes <= 0
          ? new Map()
          : await pdfFilesForOwnFile(playgroundClient, job, file, bytes, requests);
        const renderOptions = {
          ...(pageRaster
            ? {
              source: pdfPageRasterSource(filesByPath, requests),
              requests: requests.map(pdfPageRasterRequestForRenderer),
            }
            : { filesByPath, requests }),
          pdfjs: playgroundPdfJsConfig(),
          maxTotalPixels: budget.remainingPixels,
          maxTotalImageBytes: budget.remainingImageBytes,
          onProgress({ completed, total, label }) {
            if (!ownFileRequestIsCurrent(token)) {
              return;
            }
            const progress = `${label} (${completed} of ${total})`;
            setOwnFileBusy(true, progress);
            setStatus(progress, { visible: true });
          },
        };
        const renderer = pageRaster
          ? renderPdfPageRasterRequestsIncrementally
          : renderPdfFormRequestsIncrementally;
        for await (const renderedItem of renderer(renderOptions)) {
          const item = pdfRenderedMediaItem(renderedItem);
          if (!ownFileRequestIsCurrent(token)) {
            return job;
          }
          if (state.ownFileCancelRequested) {
            job = await cancelOwnFileImport(playgroundClient, job, reportJob, token);
            reportJob(job);
            break;
          }
          if (!item.error && item.bytes instanceof Uint8Array) {
            const pixels = Math.max(0, Number(item.width) || 0) * Math.max(0, Number(item.height) || 0);
            budget.remainingPixels = Math.max(0, budget.remainingPixels - pixels);
            budget.remainingImageBytes = Math.max(0, budget.remainingImageBytes - item.bytes.byteLength);
          }
          if (item.budgetExhausted === 'pixels') budget.remainingPixels = 0;
          if (item.budgetExhausted === 'image-bytes') budget.remainingImageBytes = 0;
          job = await submitOwnFileRenderedMedia(playgroundClient, job, item, reportJob, token);
          reportJob(job);
          if (['complete', 'failed', 'cancelled'].includes(String(job.status || ''))) {
            break;
          }
        }
        if (['complete', 'failed', 'cancelled'].includes(String(job.status || ''))) {
          break;
        }
      }
      continue;
    }
    if (job.status === 'awaiting_renderer') {
      throw new Error('WordPress requested a PDF figure/page image, but did not provide a renderable request. Please try the file again.');
    }
    job = await advanceOwnFileImport(playgroundClient, job, token, reportJob);
    reportJob(job);
  }

  return job;
}

async function cancelOwnFileImport(playgroundClient, job, reportJob = () => {}, token = state.ownFileToken) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  if (!jobId) {
    throw new Error('WordPress did not return an import job identifier to cancel.');
  }
  return cancelImportMutationDurably({
    cancel: () => ownFilePluginRequest(playgroundClient, `/imports/${jobId}/cancel`, {}),
    readStatus: () => ownFilePluginRequest(playgroundClient, `/imports/${jobId}`, undefined, 'GET'),
    onSnapshot: reportJob,
    isActive: () => ownFileRequestIsCurrent(token) && state.ownFileCancelRequested,
    onRetry({ attempt }) {
      const label = `Cancellation is waiting for the current checkpoint (${attempt}). Checking again…`;
      setOwnFileBusy(true, label);
      setStatus(label, { visible: true });
    },
  });
}

async function submitOwnFileRenderedMedia(playgroundClient, job, item, reportJob, token) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  const requestId = String(item?.requestId || '');
  if (!jobId || !requestId) {
    throw new Error('WordPress returned an invalid PDF visual/page-image request.');
  }
  const rendererPayload = item.error
    ? { requestId, error: item.error }
    : {
      requestId,
      bytes: base64FromBytes(item.bytes),
      mimeType: item.mimeType,
      width: item.width,
      height: item.height,
    };
  if (state.ownFileCancelRequested) {
    return cancelOwnFileImport(playgroundClient, job, reportJob, token);
  }
  try {
    return await ownFilePluginRequest(
      playgroundClient,
      `/imports/${jobId}/rendered-media`,
      rendererPayload,
    );
  } catch (error) {
    if (state.ownFileCancelRequested) {
      return cancelOwnFileImport(playgroundClient, job, reportJob, token);
    }
    const recovered = await ownFilePluginRequest(playgroundClient, `/imports/${jobId}`, undefined, 'GET');
    reportJob(recovered);
    const stillOutstanding = (recovered.renderRequests || [])
      .some((request) => String(request?.id || '') === requestId);
    if (!stillOutstanding || ['complete', 'failed', 'cancelled'].includes(String(recovered.status || ''))) {
      return recovered;
    }
    if (state.ownFileCancelRequested) {
      return cancelOwnFileImport(playgroundClient, recovered, reportJob, token);
    }
    try {
      return await ownFilePluginRequest(
        playgroundClient,
        `/imports/${jobId}/rendered-media`,
        rendererPayload,
      );
    } catch (retryError) {
      if (state.ownFileCancelRequested) {
        return cancelOwnFileImport(playgroundClient, recovered, reportJob, token);
      }
      const detail = retryError instanceof Error ? retryError.message : String(retryError);
      throw new Error(`${detail} The rendered PDF visual/page image remains saved for a later Resume saved import attempt.`);
    }
  }
}

async function openCompletedOwnFileImport(playgroundClient, job, token, label) {
  ownFileImportSession.forget(job.jobId);
  state.lastOwnFileJob = job;
  if (!ownFileRequestIsCurrent(token)) {
    return;
  }
  const data = job.result;
  try {
    await playgroundClient.goTo(playgroundPath(data.pageUrl));
  } catch (pageError) {
    // Conversion and publication have already committed at this point. A
    // very large front-end render must not be reported as if saved work was
    // lost; try the editor and retain success if neither view can render.
    try {
      await playgroundClient.goTo(playgroundPath(data.editUrl));
    } catch {
      const detail = pageError instanceof Error ? pageError.message : String(pageError);
      setStatus(
        'The import completed and the WordPress page was saved, but Playground could not display it: ' + detail,
        { visible: true, tone: 'success' },
      );
      return;
    }
  }
  if (ownFileRequestIsCurrent(token)) {
    setStatus('Import complete. Converted pages were verified privately and published. Opened a new WordPress page for ' + label + '.', { visible: true, tone: 'success' });
  }
}

async function resumeSavedOwnFileImport() {
  const saved = ownFileImportSession.load();
  if (!saved || state.ownFileBusy) {
    setOwnFileBusy(false);
    return;
  }

  abortStaticPdfPreview({ clearCache: true });
  state.ownFileCancelRequested = saved.cancellationRequested === true;
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
  setOwnFileBusy(true, state.playgroundReady ? 'Resuming saved import…' : 'Restoring saved Playground…');
  setStatus('Restoring the saved WordPress import…', { visible: true });

  try {
    await bootOwnFilePlayground();
    const playgroundClient = state.playgroundClient;
    if (!playgroundClient || !ownFileRequestIsCurrent(token)) {
      return;
    }
    let job = await ownFilePluginRequest(
      playgroundClient,
      `/imports/${encodeURIComponent(saved.jobId)}`,
      undefined,
      'GET',
    );
    const reportJob = createOwnFileJobReporter(token);
    reportJob(job);
    job = await driveOwnFileImport(playgroundClient, job, token, reportJob);
    if (job.status === 'awaiting_output_mode') {
      return;
    }
    if (job.status === 'cancelled') {
      ownFileImportSession.forget(job.jobId);
      setStatus('Import cancelled. No further WordPress page or media work will run.', { visible: true });
      return;
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'The saved conversion failed.');
    }
    await openCompletedOwnFileImport(playgroundClient, job, token, 'the saved document');
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    if (/not found|does not exist|unknown import|404/i.test(message)) {
      ownFileImportSession.forget(saved.jobId);
    }
    if (ownFileRequestIsCurrent(token)) {
      setStatus('Could not resume the saved import: ' + message, { visible: true, tone: 'error' });
    }
  } finally {
    if (token === state.ownFileToken) {
      setOwnFileBusy(false);
    }
  }
}

async function openOwnFile(file, pdfOutputMode = 'single') {
  if (!file || file.size <= 0) {
    setStatus('Choose a non-empty file to open in WordPress Playground.');
    return;
  }

  abortStaticPdfPreview({ clearCache: true });
  state.lastOwnFileJob = null;
  state.ownFileCancelRequested = false;
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
    const prepared = await payloadFromOwnFile(file, pdfOutputMode, (message) => {
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
    const reportJob = createOwnFileJobReporter(token);
    reportJob(job);
    job = await driveOwnFileImport(playgroundClient, job, token, reportJob, file, prepared.bytes);
    if (job.status === 'awaiting_output_mode') {
      return;
    }
    if (job.status === 'cancelled') {
      ownFileImportSession.forget(job.jobId);
      setStatus('Import cancelled. No further WordPress page or media work will run.', { visible: true });
      return;
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'Conversion failed.');
    }
    await openCompletedOwnFileImport(playgroundClient, job, token, file.name);
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

// The release E2E driver opts in with ?e2e=... and verifies the actual
// WordPress rows after the UI reports success. Keep the hook absent for
// ordinary visitors and return only integrity counts, never document text.
if (new URL(window.location.href).searchParams.has('e2e')) {
  window.__portLibsImportE2E = {
    async inspectLastImport() {
      const job = state.lastOwnFileJob;
      const client = state.playgroundClient;
      if (!job?.result || !client) {
        throw new Error('No completed Playground import is available for inspection.');
      }
      const children = Array.isArray(job.result.children)
        ? job.result.children
        : (Array.isArray(job.result.posts) ? job.result.posts : []);
      const ids = [];
      const resultsByPostId = new Map();
      const collectPostIds = (result) => {
        if (!result || typeof result !== 'object') return;
        const postId = Number(result.postId) || 0;
        if (postId > 0) {
          ids.push(postId);
          if (!resultsByPostId.has(postId)) resultsByPostId.set(postId, result);
        }
        for (const key of ['children', 'posts', 'documents']) {
          for (const child of Array.isArray(result[key]) ? result[key] : []) collectPostIds(child);
        }
      };
      collectPostIds(job.result);
      const uniqueIds = Array.from(new Set(ids));
      const posts = [];
      for (const postId of uniqueIds) {
        const response = await client.request({
          method: 'GET',
          url: `/wp-json/wp/v2/pages/${postId}?context=view`,
        });
        const body = typeof response.text === 'function' ? await response.text() : response.text;
        const page = JSON.parse(String(body || '{}'));
        const raw = String(page?.content?.rendered || '');
        const visible = new DOMParser().parseFromString(raw.replace(/<!--.*?-->/gs, ' '), 'text/html')
          .body.textContent.replace(/\s+/g, ' ').trim();
        const result = resultsByPostId.get(postId) || {};
        posts.push({
          postId,
          status: String(page?.status || ''),
          contentBytes: new TextEncoder().encode(raw).byteLength,
          visibleTextBytes: new TextEncoder().encode(visible).byteLength,
          imageCount: (raw.match(/<img\b/gi) || []).length,
          rawDataProvenanceCount: (raw.match(/data-pandoc-media-(?:canonical-)?source=["']data:/gi) || []).length,
          importNoticeCount: (raw.match(/port-libs-(?:conversion-notice|import-quality)/gi) || []).length,
          intentionalBlank: Boolean(result.intentionalBlank),
          restErrorCode: String(page?.code || ''),
        });
      }

      return {
        jobId: String(job.jobId || ''),
        resultPostId: Number(job.result.postId) || 0,
        resultKind: String(job.result.kind || ''),
        pdfOutputMode: String(job.output?.pdfOutputMode || job.pdfOutputMode || ''),
        pageCount: Math.max(0, Number(job.result.pageCount) || 0),
        childPostCount: children.length,
        rendererResources: pdfFormRendererResourceSnapshot(),
        posts,
      };
    },
  };
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
  const jobErrorSnapshot = data
    && typeof data === 'object'
    && String(data.jobId || '') !== ''
    && ['failed', 'retryable_failure'].includes(String(data.status || ''));
  if (!data.ok && !jobErrorSnapshot) {
    const error = new Error(data.message || 'Conversion failed.');
    error.status = Number(data?.data?.status || response?.httpStatusCode || response?.status || 0);
    throw error;
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
    return await recoverImportMutation({
      mutate: () => ownFilePluginRequest(playgroundClient, `/imports/${jobId}/advance`, {}),
      readStatus: () => ownFilePluginRequest(playgroundClient, `/imports/${jobId}`, undefined, 'GET'),
      onSnapshot: reportJob,
      isActive: () => ownFileRequestIsCurrent(token) && !state.ownFileCancelRequested,
      shouldCancel: () => state.ownFileCancelRequested,
      cancel: () => cancelOwnFileImport(playgroundClient, job, reportJob, token),
      maxMutationRetries: ownFileAdvanceRecoveryAttempts,
      statusChecksPerRetry: 3,
      onRecovery({ mutationAttempt, maxMutationRetries, statusAttempt, statusChecks }) {
        const recoveryLabel = `The server request ended unexpectedly. Checking saved progress (${statusAttempt} of ${statusChecks}) before retry ${mutationAttempt} of ${maxMutationRetries}…`;
        setOwnFileBusy(true, recoveryLabel);
        setStatus(recoveryLabel, { visible: true });
      },
    });
  } catch (error) {
    const detail = error instanceof Error ? error.message : String(error || 'Unknown server error');
    throw new Error(`${detail} The completed page checkpoints remain saved in this Playground, but automatic recovery stopped to avoid a retry loop.`);
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

  const details = [];
  const metrics = job && typeof job.metrics === 'object' ? job.metrics : {};
  const pdfTotal = Math.max(0, Number(metrics.pdfPagesTotal || 0));
  const pdfCompleted = Math.max(0, Number(metrics.pdfPagesExtracted || 0));
  if (pdfTotal > 0 && pdfCompleted < pdfTotal) {
    details.push(`${pdfCompleted} of ${pdfTotal} PDF pages saved`);
  }
  const publication = job && typeof job.publication === 'object' ? job.publication : {};
  const publicationTotal = Math.max(0, Number(publication.total || 0));
  if (publicationTotal > 0 && String(job?.status || '') === 'ready_to_publish') {
    details.push(`${Math.max(0, Number(publication.completed || 0))} of ${publicationTotal} pages published`);
  }
  const step = total > 1 ? `${label} (${completed} of ${total})` : label;

  return details.length > 0 ? `${step} ${details.join('; ')}.` : step;
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

function pdfRenderRequestGroups(requests) {
  const groups = new Map();
  for (const request of Array.isArray(requests) ? requests : []) {
    const path = String(request?.path || '');
    const sourceKey = String(request?.sourceKey || path);
    const method = request?.method === pdfPageRasterMethod
      ? pdfPageRasterMethod
      : 'pdf-form-xobject';
    const groupKey = `${method}\u001f${sourceKey}`;
    if (!groups.has(groupKey)) {
      groups.set(groupKey, { method, requests: [] });
    }
    groups.get(groupKey).requests.push(request);
  }

  return Array.from(groups.values());
}

function pdfPageRasterSource(filesByPath, requests) {
  for (const request of requests || []) {
    const path = String(request?.path || '');
    if (path && filesByPath?.has(path)) return filesByPath.get(path);
  }
  return filesByPath instanceof Map ? filesByPath.values().next().value : undefined;
}

function pdfPageRasterRequestForRenderer(request) {
  return {
    version: request?.version,
    method: request?.method,
    id: request?.id,
    sourceSha256: request?.sourceSha256,
    page: request?.page,
    pageObject: request?.pageObject,
    pageBox: request?.pageBox,
    pageBoxSource: request?.pageBoxSource,
    pageRotation: request?.pageRotation,
    width: request?.width,
    height: request?.height,
    mimeType: request?.mimeType,
    requestDigest: request?.requestDigest,
  };
}

function pdfRenderedMediaItem(item) {
  if (item?.error || item?.bytes instanceof Uint8Array) return item;
  if (!(item?.contents instanceof Uint8Array)) return item;
  return {
    requestId: String(item.requestId || ''),
    bytes: item.contents,
    mimeType: item.mimeType,
    width: item.width,
    height: item.height,
  };
}

async function pdfFilesForOwnFile(playgroundClient, job, file, bytes, renderRequests = null) {
  const files = new Map();
  const requests = Array.isArray(renderRequests)
    ? renderRequests
    : (Array.isArray(job?.renderRequests) ? job.renderRequests : []);
  if (file && isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
    files.set(file.name, bytes);
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (path && file && isLikelyPdfFile(file) && bytes instanceof Uint8Array) {
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
    // report the unavailable figure/page image to WordPress, which leaves the
    // available fallback in place while the rest of the document is imported.
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

async function payloadFromOwnFile(file, pdfOutputMode, reportProgress) {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const payload = {
    filename: file.name,
    title: titleFromFilename(file.name),
    imageMode: 'important',
    pdfMode: 'layout',
    pdfOutputMode: pdfOutputMode === 'pages' ? 'pages' : 'single',
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

function browserStorage() {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
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
  setOwnFileBusy(false);
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
  if (ownFileImportSession.load()) {
    void resumeSavedOwnFileImport();
    return;
  }
  ownFileInput.value = '';
  ownFileInput.click();
});

cancelOwnFileButton.addEventListener('click', () => {
  if (!state.ownFileBusy || !state.lastOwnFileJob?.jobId || state.ownFileCancelRequested) {
    return;
  }
  state.ownFileCancelRequested = true;
  ownFileImportSession.requestCancellation(state.lastOwnFileJob.jobId);
  updateControls();
  setStatus('Cancellation requested. Finishing only the current bounded checkpoint…', { visible: true });
});

ownFileInput.addEventListener('change', async () => {
  const file = ownFileInput.files && ownFileInput.files[0];
  ownFileInput.value = '';
  if (file) {
    const outputMode = isLikelyPdfFile(file) ? await chooseOwnPdfOutputMode() : 'single';
    if (outputMode) {
      void openOwnFile(file, outputMode);
    }
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
