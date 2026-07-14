import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';

const catalogUrl = 'examples-index.json';
const viewLabels = {
  phpHtml: 'HTML',
  wpBlocks: 'WordPress Block markup',
  haskell: 'Pandoc baseline',
};
const defaultView = 'wpBlocks';
const exampleUrlParameter = 'example';
const playgroundPluginBuild = 'browser-import-jobs-staged-form-20260714';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';
const playgroundUploadDirectory = '/tmp/port-libs-converter';
const playgroundPdfRasterByteLimit = 24_000_000;
const ownFileStatusPollIntervalMs = 1_000;

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
  frame.setAttribute('sandbox', '');
}

function unloadCurrentExample() {
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
  frame.removeAttribute('src');
  frame.hidden = true;
}

function loadSelectedExample() {
  if (state.ownFileBusy) {
    return;
  }
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
  frame.hidden = false;
  frame.loading = 'eager';
  frame.dataset.loadedPath = view.path;
  frame.setAttribute('sandbox', '');
  frame.removeAttribute('src');
  frame.src = 'about:blank';
  setStatus('Loading ' + example.label + '…');

  window.requestAnimationFrame(() => {
    if (token !== state.loadToken) {
      return;
    }
    frame.src = view.path;
  });
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

  const token = state.ownFileToken + 1;
  state.ownFileToken = token;
  const reusingPlayground = state.frameMode === 'playground'
    && state.playgroundReady
    && state.playgroundClient;
  state.frameMode = 'playground';
  state.loadToken += 1;
  delete frame.dataset.loadedPath;
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
  if (!example || !path || frame.getAttribute('src') !== path) {
    return;
  }
  setStatus('Loaded ' + example.label + '.');
});

initialize();
