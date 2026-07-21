import {
  renderPdfFormRequestsIncrementally,
  renderPdfPageRasterRequestsIncrementally,
} from './pdfjs-form-rasterizer.mjs';
import {
  createImportJobSession,
  createPlaygroundPersistence,
  recoverImportMutation,
  resetPlaygroundIframeForRetry,
  startPlaygroundWithSnapshotRecovery,
} from './import-job-session.mjs?v=playground-retry-teardown-20260721';

const pluginBuild = 'playground-retry-teardown-20260721';
const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';
const playgroundUploadDirectory = '/tmp/port-libs-converter';
// Keep browser-produced PDF rasters within the exact decoded-byte limit that
// the Playground REST plugin accepts. Base64 expands this on the wire, but the
// server limit is deliberately applied to the decoded media bytes.
const pdfRasterPayloadByteLimit = 24_000_000;
const pdfRasterSourceByteLimit = 24 * 1024 * 1024;
const pdfFormRenderTotalPixelLimit = 48_000_000;
const pdfFormRenderTotalImageByteLimit = 24_000_000;
const pdfPageRenderTotalPixelLimit = 128_000_000;
const pdfPageRenderTotalImageByteLimit = 64 * 1024 * 1024;
const pdfPageRasterMethod = 'pdfjs-whole-page-raster';

const iframe = document.getElementById('wp-playground');
const playgroundPanel = document.getElementById('playground-panel');
const form = document.getElementById('converter-form');
const fileInput = document.getElementById('file-input');
const directoryInput = document.getElementById('directory-input');
const titleInput = document.getElementById('title-input');
const imageModeInputs = Array.from(document.querySelectorAll('input[name="image-mode"]'));
const pdfModeControl = document.getElementById('pdf-mode-control');
const pdfModeInputs = Array.from(document.querySelectorAll('input[name="pdf-mode"]'));
const pdfOutputModeControl = document.getElementById('pdf-output-mode-control');
const pdfOutputModeInputs = Array.from(document.querySelectorAll('input[name="pdf-output-mode"]'));
const convertButton = document.getElementById('convert-button');
const dropzone = document.getElementById('dropzone');
const fileName = document.getElementById('file-name');
const statusText = document.getElementById('status-text');
const statusDot = document.getElementById('playground-status');
const logOutput = document.getElementById('log-output');
const conversionProgressText = document.getElementById('conversion-progress-text');
const pageDropOverlay = document.getElementById('page-drop-overlay');
const qualityPanel = document.getElementById('quality-panel');
const qualityTitle = document.getElementById('quality-title');
const qualityMessage = document.getElementById('quality-message');
const qualityDetails = document.getElementById('quality-details');
const retryActions = document.getElementById('retry-actions');
const retryButtons = Array.from(document.querySelectorAll('[data-retry-image-mode], [data-retry-pdf-mode]'));
const resumePdfPagesButton = document.getElementById('resume-pdf-pages');
const resumeSavedImportButton = document.createElement('button');
resumeSavedImportButton.type = 'button';
resumeSavedImportButton.hidden = true;
resumeSavedImportButton.textContent = 'Resume saved import';
retryActions?.append(resumeSavedImportButton);

const importJobSession = createImportJobSession({
  storage: browserStorage(),
  storageKey: 'port-libs.playground-active-import.v1',
});
const playgroundPersistence = createPlaygroundPersistence({
  storage: browserStorage(),
  storageKey: 'port-libs.playground-import-site.v1',
  devicePath: `port-libs/${window.location.host}/playground-import-site-v1`,
});

let playgroundClient = null;
let playgroundReady = false;
let playgroundBootPromise = null;
let startPlaygroundWeb = null;
let decodePdfJbig2Rasters = null;
let decodePdfJpxRasters = null;
let selectedUpload = null;
let conversionActive = false;
let dragDepth = 0;
let progressHeartbeat = null;
let progressStartedAt = 0;
let activeProgressLabel = '';
let recoverableOutputJob = null;

fileInput.addEventListener('change', async () => {
  const files = fileInput.files ? Array.from(fileInput.files) : [];
  setSelectedUpload(uploadFromFiles(files));
});

directoryInput.addEventListener('change', async () => {
  const files = directoryInput.files ? Array.from(directoryInput.files) : [];
  setSelectedUpload(uploadFromFiles(files, { forceBatch: true }));
});

document.addEventListener('dragenter', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth += 1;
  setPageDragActive(true);
});

document.addEventListener('dragover', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = conversionActive ? 'none' : 'copy';
  }
  setPageDragActive(true);
});

document.addEventListener('dragleave', (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth = Math.max(0, dragDepth - 1);
  if (dragDepth === 0) {
    setPageDragActive(false);
  }
});

document.addEventListener('drop', async (event) => {
  if (!hasDraggedFiles(event)) {
    return;
  }
  event.preventDefault();
  dragDepth = 0;
  setPageDragActive(false);
  const upload = event.dataTransfer ? await uploadFromDataTransfer(event.dataTransfer) : null;
  if (!upload) {
    return;
  }
  setSelectedUpload(upload);
  await convertSelectedFile();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  await convertSelectedFile();
});

for (const button of retryButtons) {
  button.addEventListener('click', async () => {
    if (button.dataset.retryImageMode) {
      setSelectedImageMode(button.dataset.retryImageMode);
    }
    if (button.dataset.retryPdfMode) {
      setSelectedPdfMode(button.dataset.retryPdfMode);
    }
    await convertSelectedFile();
  });
}

resumePdfPagesButton?.addEventListener('click', async () => {
  await resumePdfAsPageTree();
});

resumeSavedImportButton.addEventListener('click', async () => {
  await resumeSavedImport();
});

window.addEventListener('beforeunload', (event) => {
  if (!conversionActive) return;
  event.preventDefault();
  event.returnValue = '';
});

async function convertSelectedFile() {
  if (!selectedUpload) {
    return;
  }
  if (conversionActive) {
    log('A conversion is already running.');
    return;
  }

  setBusy(true);
  setQualityPanel(null);
  conversionActive = true;
  progressStartedAt = Date.now();
  startProgressHeartbeat();
  setProgressStatus('Preparing document...');
  setStatus('loading', 'Preparing document for import...');
  setPlaygroundState(playgroundReady ? 'ready' : 'idle');
  let stagedUpload = null;
  try {
    log(`Reading ${selectedUpload.displayName} (${formatBytes(selectedUpload.totalSize)})`);

    setProgressStatus('Starting WordPress Playground...');
    setPlaygroundState(playgroundReady ? 'ready' : 'loading');
    await bootPlayground();
    if (!playgroundClient) {
      throw new Error('WordPress Playground was not ready to receive the selected file.');
    }
    setProgressStatus('Staging the selected file in WordPress Playground...');
    stagedUpload = await stageUploadInPlayground(playgroundClient, selectedUpload, setProgressStatus);
    setStatus('loading', 'Creating an import job in WordPress Playground...');
    setProgressStatus('Creating an import job in WordPress...');
    setPlaygroundState('ready');
    let job = await playgroundPluginRequest('/imports', stagedUpload.payload);
    const reportJob = createJobReporter();
    reportJob(job);
    job = await driveImportJob(job, reportJob);
    if (job.status === 'awaiting_output_mode') {
      recoverableOutputJob = job;
      showPdfOutputRecovery(job);
      return;
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'Conversion failed.');
    }
    importJobSession.forget(job.jobId);
    await openCompletedImport(job.result);
  } catch (error) {
    setPlaygroundState(playgroundReady ? 'ready' : 'idle');
    setStatus('error', error instanceof Error ? error.message : String(error));
    log(error instanceof Error ? error.stack || error.message : String(error));
  } finally {
    if (stagedUpload && playgroundClient) {
      await cleanupStagedUpload(playgroundClient, stagedUpload.paths);
    }
    conversionActive = false;
    stopProgressHeartbeat();
    setBusy(false);
    if (!recoverableOutputJob) showSavedImportAction();
  }
}

function createJobReporter() {
  const reportedEventKeys = new Set();

  return (snapshot) => {
    importJobSession.remember(snapshot);
    const state = snapshot?.progress || {};
    const completed = Number(state.completed || 0);
    const total = Math.max(1, Number(state.total || 1));
    const label = String(state.label || 'Import is continuing...');
    const metrics = snapshot?.metrics || {};
    const pdfTotal = Math.max(0, Number(metrics.pdfPagesTotal || 0));
    const pdfCompleted = Math.min(pdfTotal, Math.max(0, Number(metrics.pdfPagesExtracted || 0)));
    const pdfProgress = pdfTotal > 0 && pdfCompleted < pdfTotal
      ? ` — ${pdfCompleted} of ${pdfTotal} PDF pages safely saved`
      : '';
    setProgressStatus(`${label} (${completed} of ${total})${pdfProgress}`);
    setStatus('loading', label);
    for (const event of Array.isArray(snapshot?.events) ? snapshot.events : []) {
      const eventKey = `${event?.time || 0}:${event?.stage || ''}:${event?.message || ''}`;
      if (reportedEventKeys.has(eventKey)) continue;
      reportedEventKeys.add(eventKey);
      if (event?.message) log(String(event.message));
    }
    if (reportedEventKeys.size > 160) {
      const retained = Array.from(reportedEventKeys).slice(-80);
      reportedEventKeys.clear();
      retained.forEach((eventKey) => reportedEventKeys.add(eventKey));
    }
  };
}

async function driveImportJob(initialJob, reportJob) {
  let job = initialJob;
  const formBudget = {
    remainingPixels: pdfFormRenderTotalPixelLimit,
    remainingImageBytes: pdfFormRenderTotalImageByteLimit,
  };
  const pageBudget = {
    remainingPixels: pdfPageRenderTotalPixelLimit,
    remainingImageBytes: pdfPageRenderTotalImageByteLimit,
  };
  while (!['complete', 'failed', 'awaiting_output_mode'].includes(String(job.status || ''))) {
    if (Array.isArray(job.renderRequests) && job.renderRequests.length > 0) {
      log(`Rendering ${job.renderRequests.length} PDF visual${job.renderRequests.length === 1 ? '' : 's'} (figures/page images) locally with PDF.js.`);
      for (const group of pdfRenderRequestGroups(job.renderRequests)) {
        const requests = group.requests;
        const pageRaster = group.method === pdfPageRasterMethod;
        const budget = pageRaster ? pageBudget : formBudget;
        const filesByPath = budget.remainingPixels <= 0 || budget.remainingImageBytes <= 0
          ? new Map()
          : await pdfFilesForImportJob(job, selectedUpload, requests);
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
            setProgressStatus(`${label} (${completed} of ${total})`);
          },
        };
        const renderer = pageRaster
          ? renderPdfPageRasterRequestsIncrementally
          : renderPdfFormRequestsIncrementally;
        for await (const renderedItem of renderer(renderOptions)) {
          const item = pdfRenderedMediaItem(renderedItem);
          if (item.error) log(`PDF.js could not render one PDF visual/page image: ${item.error}`);
          if (!item.error && item.bytes instanceof Uint8Array) {
            const pixels = Math.max(0, Number(item.width) || 0) * Math.max(0, Number(item.height) || 0);
            budget.remainingPixels = Math.max(0, budget.remainingPixels - pixels);
            budget.remainingImageBytes = Math.max(0, budget.remainingImageBytes - item.bytes.byteLength);
          }
          if (item.budgetExhausted === 'pixels') budget.remainingPixels = 0;
          if (item.budgetExhausted === 'image-bytes') budget.remainingImageBytes = 0;
          const rendererPayload = item.error
            ? { requestId: item.requestId, error: item.error }
            : {
              requestId: item.requestId,
              bytes: base64FromBytes(item.bytes),
              mimeType: item.mimeType,
              width: item.width,
              height: item.height,
            };
          job = await submitPlaygroundRenderedMedia(job, rendererPayload, reportJob);
          reportJob(job);
          if (['complete', 'failed'].includes(String(job.status || ''))) break;
        }
        if (['complete', 'failed'].includes(String(job.status || ''))) break;
      }
      continue;
    }
    job = await advanceImportJob(job.jobId, reportJob);
    reportJob(job);
  }

  return job;
}

async function submitPlaygroundRenderedMedia(job, payload, reportJob) {
  const jobId = encodeURIComponent(String(job?.jobId || ''));
  const requestId = String(payload?.requestId || '');
  if (!jobId || !requestId) {
    throw new Error('WordPress returned an invalid PDF visual/page-image request.');
  }
  try {
    return await playgroundPluginRequest(`/imports/${jobId}/rendered-media`, payload);
  } catch (error) {
    // The upload may have committed even when its response was lost. Read the
    // durable request list before sending the PNG again.
    log('The PDF visual/page-image acknowledgement ended unexpectedly. Checking the saved import before retrying…');
    const recovered = await playgroundPluginRequest(`/imports/${jobId}`, undefined, 'GET');
    reportJob(recovered);
    const stillOutstanding = (recovered.renderRequests || [])
      .some((request) => String(request?.id || '') === requestId);
    if (!stillOutstanding || ['complete', 'failed'].includes(String(recovered.status || ''))) {
      return recovered;
    }
    try {
      return await playgroundPluginRequest(`/imports/${jobId}/rendered-media`, payload);
    } catch (retryError) {
      throw new Error(`${errorMessage(retryError)} The rendered PDF visual/page image remains checkpointed in this browser session; use Resume saved import to re-check WordPress before rendering it again.`);
    }
  }
}

function showPdfOutputRecovery(job) {
  const failure = job?.failure || {};
  const actual = Math.max(0, Number(failure.actualBytes) || Number(job?.output?.assembledBytes) || 0);
  const allowed = Math.max(0, Number(failure.allowedBytes) || Number(job?.output?.singlePageLimitBytes) || 0);
  qualityPanel.hidden = false;
  qualityPanel.dataset.status = 'partial';
  qualityTitle.textContent = 'The PDF is too large for one safe page';
  qualityMessage.textContent = `${formatBytes(actual)} of converted blocks exceeds this server’s ${formatBytes(allowed)} single-page limit. No partial page was created, and the completed PDF extraction is preserved.`;
  qualityDetails.replaceChildren();
  retryActions.hidden = false;
  for (const button of retryButtons) button.hidden = true;
  resumePdfPagesButton.hidden = false;
  resumeSavedImportButton.hidden = true;
  setProgressStatus('Choose how to continue the preserved import.');
  setStatus('ready', 'The conversion is preserved and can continue as a PDF page tree.');
}

async function resumePdfAsPageTree() {
  if (!recoverableOutputJob || conversionActive) return;
  conversionActive = true;
  setBusy(true);
  startProgressHeartbeat();
  resumePdfPagesButton.disabled = true;
  try {
    const reportJob = createJobReporter();
    let job = await playgroundPluginRequest(
      `/imports/${encodeURIComponent(recoverableOutputJob.jobId)}/output-mode`,
      { pdfOutputMode: 'pages' },
    );
    reportJob(job);
    job = await driveImportJob(job, reportJob);
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'Conversion failed.');
    }
    importJobSession.forget(job.jobId);
    recoverableOutputJob = null;
    resumePdfPagesButton.hidden = true;
    await openCompletedImport(job.result);
  } catch (error) {
    setStatus('error', errorMessage(error));
    log(error instanceof Error ? error.stack || error.message : String(error));
  } finally {
    conversionActive = false;
    stopProgressHeartbeat();
    setBusy(false);
    resumePdfPagesButton.disabled = false;
  }
}

async function resumeSavedImport() {
  const saved = importJobSession.load();
  if (!saved || conversionActive) {
    showSavedImportAction();
    return;
  }
  conversionActive = true;
  progressStartedAt = Date.now();
  setBusy(true);
  startProgressHeartbeat();
  resumeSavedImportButton.disabled = true;
  setStatus('loading', 'Opening the saved import…');
  setProgressStatus('Opening the saved import…');
  try {
    await bootPlayground();
    const reportJob = createJobReporter();
    let job = await playgroundPluginRequest(`/imports/${encodeURIComponent(saved.jobId)}`, undefined, 'GET');
    reportJob(job);
    if (job.status === 'awaiting_output_mode') {
      recoverableOutputJob = job;
      showPdfOutputRecovery(job);
      return;
    }
    job = await driveImportJob(job, reportJob);
    if (job.status === 'awaiting_output_mode') {
      recoverableOutputJob = job;
      showPdfOutputRecovery(job);
      return;
    }
    if (job.status === 'failed' || !job.result) {
      throw new Error(job.message || 'The saved import failed.');
    }
    importJobSession.forget(job.jobId);
    resumeSavedImportButton.hidden = true;
    await openCompletedImport(job.result);
  } catch (error) {
    // A missing status after Playground boot normally means browser storage
    // was cleared or evicted. Do not leave a permanently broken Resume action.
    if (/not found|does not exist|unknown import|404/i.test(errorMessage(error))) {
      importJobSession.forget(saved.jobId);
    }
    setStatus('error', `Could not resume the saved import: ${errorMessage(error)}`);
    log(error instanceof Error ? error.stack || error.message : String(error));
  } finally {
    conversionActive = false;
    stopProgressHeartbeat();
    setBusy(false);
    resumeSavedImportButton.disabled = false;
    showSavedImportAction();
  }
}

function showSavedImportAction() {
  const saved = importJobSession.load();
  resumeSavedImportButton.hidden = !saved;
  if (!saved || conversionActive) return;
  qualityPanel.hidden = false;
  qualityPanel.dataset.status = 'partial';
  qualityTitle.textContent = 'A saved import can continue';
  qualityMessage.textContent = 'WordPress has durable checkpoints from an unfinished import. Resume it without selecting or parsing the PDF again.';
  qualityDetails.replaceChildren();
  retryActions.hidden = false;
  for (const button of retryButtons) button.hidden = true;
  resumePdfPagesButton.hidden = true;
}

async function openCompletedImport(data) {
  if (data.batch && Array.isArray(data.posts)) {
    log(`Created ${data.posts.length} page${data.posts.length === 1 ? '' : 's'} from ${selectedUpload?.displayName || 'the saved import'}`);
    for (const post of data.posts) log(`Created page #${post.postId}: ${post.title} (${post.path})`);
  } else {
    log(`Created page #${data.postId}: ${data.title}`);
  }
  log(`Rendered image tags: ${data.imageTagCount}; imported media files: ${data.imagesImported}`);
  const quality = data.quality || null;
  setQualityPanel(quality);
  if (quality?.status) log(qualityLogMessage(quality));
  setProgressStatus('Opening converted page...');
  await playgroundClient.goTo(playgroundPath(data.pageUrl));
  setPlaygroundState('ready');
  setStatus('ready', quality ? `Page created and opened. ${qualityMessageForStatus(String(quality.status || 'complete'))}` : 'Page created and opened in Playground.');
}

async function advanceImportJob(jobId, reportJob) {
  let polling = false;
  let stopped = false;
  const encodedJobId = encodeURIComponent(jobId);
  // The import worker persists each phase before starting the next one. A
  // second, read-only request lets the UI surface that work while the advance
  // request is still running, instead of leaving someone staring at one
  // spinner for several minutes.
  const poll = async () => {
    if (stopped || polling || !conversionActive) {
      return;
    }
    polling = true;
    try {
      const snapshot = await playgroundPluginRequest(`/imports/${encodedJobId}`, undefined, 'GET');
      if (!stopped && conversionActive) reportJob(snapshot);
    } catch {
      // The advance request is authoritative. A transient status-poll failure
      // should never make a document import fail.
    } finally {
      polling = false;
    }
  };
  const timer = window.setInterval(() => {
    void poll();
  }, 1_250);
  try {
    await poll();
    return await recoverImportMutation({
      mutate: () => playgroundPluginRequest(`/imports/${encodedJobId}/advance`, {}),
      readStatus: () => playgroundPluginRequest(`/imports/${encodedJobId}`, undefined, 'GET'),
      onSnapshot: reportJob,
      isActive: () => conversionActive,
      maxMutationRetries: 2,
      statusChecksPerRetry: 3,
      onRecovery({ mutationAttempt, maxMutationRetries, statusAttempt, statusChecks }) {
        const message = `The server request ended unexpectedly. Checking saved progress (${statusAttempt} of ${statusChecks}) before retry ${mutationAttempt} of ${maxMutationRetries}…`;
        setProgressStatus(message);
        log(message);
      },
    });
  } finally {
    stopped = true;
    window.clearInterval(timer);
  }
}

async function playgroundPluginRequest(path, payload = undefined, method = 'POST') {
  const request = {
    method,
    url: `/wp-json/port-libs/v1${path}`,
    headers: { 'Content-Type': 'application/json' },
  };
  if (payload !== undefined) {
    request.body = JSON.stringify(payload);
  }
  const response = await playgroundClient.request(request);
  const text = typeof response.text === 'function' ? await response.text() : response.text;
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error('WordPress returned an invalid import-job response.');
  }
  const jobErrorSnapshot = data
    && typeof data === 'object'
    && String(data.jobId || '') !== ''
    && ['failed', 'retryable_failure'].includes(String(data.status || ''));
  if (!data.ok && !jobErrorSnapshot) {
    throw new Error(data.message || 'Conversion failed.');
  }

  return data;
}

function pdfFilesForUpload(upload) {
  const files = new Map();
  for (const entry of upload?.entries || []) {
    if (isLikelyPdfFile(entry.file)) {
      files.set(entry.path, entry.file);
    }
  }
  return files;
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
    if (!groups.has(groupKey)) groups.set(groupKey, { method, requests: [] });
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

async function pdfFilesForImportJob(job, upload, renderRequests = null) {
  const files = pdfFilesForUpload(upload);
  const requests = Array.isArray(renderRequests)
    ? renderRequests
    : (Array.isArray(job?.renderRequests) ? job.renderRequests : []);
  const localPdfEntries = (upload?.entries || []).filter((entry) => isLikelyPdfFile(entry?.file));
  // A direct upload is sanitized before the job persists it. When the
  // browser holds only one PDF, it remains safe to bind that selected source
  // to the opaque paths in its own job even if the sanitized filename differs.
  if (localPdfEntries.length === 1) {
    for (const request of requests) {
      const path = String(request?.path || '');
      if (path) {
        files.set(path, localPdfEntries[0].file);
      }
    }
  }
  for (const request of requests) {
    const path = String(request?.path || '');
    if (!path || files.has(path)) {
      continue;
    }
    const source = await playgroundPdfRenderSource(job, request);
    if (source) {
      files.set(path, source);
    }
  }

  return files;
}

async function playgroundPdfRenderSource(job, request) {
  const jobId = String(job?.jobId || '');
  const requestId = String(request?.id || '');
  if (!jobId || !requestId) {
    return null;
  }
  try {
    const source = await playgroundPluginRequest(
      `/imports/${encodeURIComponent(jobId)}/render-source/${encodeURIComponent(requestId)}`,
      undefined,
      'GET',
    );
    const encoded = String(source.bytes || '');
    return encoded ? bytesFromBase64(encoded) : null;
  } catch {
    // A direct browser-held source is normally enough. If an older plugin
    // cannot return an expanded ZIP member, PDF.js reports that one figure or
    // page image as unavailable and the import keeps its available fallback.
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

async function bootPlayground() {
  if (playgroundReady) {
    return;
  }
  if (playgroundBootPromise) {
    await playgroundBootPromise;
    return;
  }

  playgroundBootPromise = startPlayground();
  await playgroundBootPromise;
}

async function startPlayground() {
  try {
    const pluginUrl = new URL(`playground/port-libs-playground-converter.zip?v=${pluginBuild}`, window.location.href).href;
    setPlaygroundState('loading');
    setStatus('loading', 'Starting WordPress Playground...');
    setProgressStatus('Starting WordPress Playground...');
    log('Starting WordPress Playground');
    if (isLikelyIOS()) {
      log('iOS detected; using on-demand Playground startup to reduce memory pressure.');
    }
    if (!startPlaygroundWeb) {
      const playgroundModule = await import(playgroundClientModuleUrl);
      startPlaygroundWeb = playgroundModule.startPlaygroundWeb;
    }
    log(`Installing converter plugin from ${pluginUrl}`);

    const startOptions = {
      iframe,
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
    playgroundClient = await startPlaygroundWithSnapshotRecovery({
      persistence: playgroundPersistence,
      options: startOptions,
      start: startPlaygroundWeb,
      onRecovery() {
        const message = 'The saved Playground database could not be reopened. Starting a fresh private WordPress site; the previous browser snapshot is preserved.';
        setProgressStatus(message);
        log(message);
      },
      beforeRetry: () => resetPlaygroundIframeForRetry(iframe),
    });
    await playgroundClient.isReady();
    try {
      await playgroundPersistence.persist(playgroundClient, (message) => {
        setProgressStatus(message);
        log(message);
      });
    } catch (error) {
      log(`Browser storage could not preserve this Playground: ${errorMessage(error)}`);
    }
    playgroundReady = true;
    setPlaygroundState('ready');
    setStatus('ready', 'WordPress Playground is ready.');
    updateConvertAvailability();
  } catch (error) {
    // A transient CDN, worker, or plugin download failure does not prove that
    // the OPFS snapshot is corrupt. Retain it for an explicit retry instead
    // of risking replacement of a valid, checkpointed WordPress tree.
    playgroundBootPromise = null;
    setPlaygroundState('idle');
    setStatus('error', 'WordPress Playground failed to start.');
    log(error instanceof Error ? error.stack || error.message : String(error));
    throw error;
  }
}

function setSelectedUpload(upload) {
  selectedUpload = upload;
  recoverableOutputJob = null;
  if (resumePdfPagesButton) resumePdfPagesButton.hidden = true;
  setQualityPanel(null);
  resumeSavedImportButton.hidden = true;
  if (!upload) {
    fileName.textContent = 'No file selected';
    titleInput.value = '';
    updatePdfModeVisibility(null);
    updateConvertAvailability();
    return;
  }

  fileName.textContent = `${upload.displayName} (${formatBytes(upload.totalSize)})`;
  titleInput.value = upload.title;
  setStatus('idle', `Ready to detect and import ${upload.displayName}.`);
  updatePdfModeVisibility(upload);
  updateConvertAvailability();
}

function setBusy(busy) {
  form.dataset.busy = busy ? 'true' : 'false';
  convertButton.disabled = busy || !selectedUpload;
  fileInput.disabled = busy;
  directoryInput.disabled = busy;
  for (const input of imageModeInputs) {
    input.disabled = busy;
  }
  for (const input of pdfModeInputs) {
    input.disabled = busy;
  }
  for (const input of pdfOutputModeInputs) {
    input.disabled = busy;
  }
  dropzone.dataset.disabled = busy ? 'true' : 'false';
  titleInput.disabled = busy;
  convertButton.textContent = busy ? 'Converting...' : convertButtonLabel();
}

function updateConvertAvailability() {
  convertButton.disabled = !selectedUpload;
  convertButton.textContent = convertButtonLabel();
}

function convertButtonLabel() {
  return playgroundReady ? 'Convert and open page' : 'Start WordPress and convert';
}

function setStatus(state, text) {
  statusDot.dataset.state = state;
  statusText.textContent = text;
}

function setPlaygroundState(state) {
  playgroundPanel.dataset.state = state;
}

function setProgressStatus(text) {
  activeProgressLabel = text;
  conversionProgressText.textContent = text;
}

function startProgressHeartbeat() {
  stopProgressHeartbeat();
  progressHeartbeat = window.setInterval(() => {
    if (!conversionActive || !activeProgressLabel) {
      return;
    }
    const elapsed = Math.max(0, Math.floor((Date.now() - progressStartedAt) / 1000));
    conversionProgressText.textContent = `${activeProgressLabel} Still working (${formatElapsedSeconds(elapsed)} elapsed).`;
  }, 1_000);
}

function stopProgressHeartbeat() {
  if (progressHeartbeat !== null) {
    window.clearInterval(progressHeartbeat);
    progressHeartbeat = null;
  }
  activeProgressLabel = '';
}

function formatElapsedSeconds(seconds) {
  return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function setPageDragActive(active) {
  document.body.dataset.dragging = active ? 'true' : 'false';
  pageDropOverlay.setAttribute('aria-hidden', active ? 'false' : 'true');
}

function hasDraggedFiles(event) {
  const types = event.dataTransfer ? Array.from(event.dataTransfer.types || []) : [];
  return types.includes('Files');
}

function log(message) {
  const stamp = new Date().toISOString().slice(11, 19);
  logOutput.textContent += `[${stamp}] ${message}\n`;
  logOutput.scrollTop = logOutput.scrollHeight;
}

function uploadFromFiles(files, options = {}) {
  const entries = files
    .filter((file) => file && file.size > 0)
    .map((file) => ({
      file,
      path: normalizeRelativePath(file._plpcRelativePath || file.webkitRelativePath || file.name),
    }))
    .filter((entry) => entry.path);
  if (entries.length === 0) {
    return null;
  }

  const forceBatch = options.forceBatch || entries.length > 1 || entries.some((entry) => entry.path.includes('/'));
  if (!forceBatch && entries.length === 1) {
    const file = entries[0].file;

    return {
      kind: 'single',
      displayName: file.name,
      title: titleFromFilename(file.name),
      totalSize: file.size,
      entries,
    };
  }

  const root = commonRoot(entries.map((entry) => entry.path));
  const displayName = root || `${entries.length} files`;

  return {
    kind: 'collection',
    displayName,
    title: titleFromFilename(displayName),
    totalSize: entries.reduce((sum, entry) => sum + entry.file.size, 0),
    entries,
  };
}

async function uploadFromDataTransfer(dataTransfer) {
  const entries = Array.from(dataTransfer.items || [])
    .map((item) => (typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null))
    .filter(Boolean);
  if (entries.length > 0) {
    const files = [];
    for (const entry of entries) {
      await collectEntryFiles(entry, '', files);
    }
    return uploadFromFiles(files.map((item) => item.fileWithPath), {
      forceBatch: files.some((item) => item.path.includes('/')) || files.length > 1,
    });
  }

  return uploadFromFiles(Array.from(dataTransfer.files || []));
}

async function collectEntryFiles(entry, parentPath, files) {
  const path = normalizeRelativePath(parentPath ? `${parentPath}/${entry.name}` : entry.name);
  if (!path) {
    return;
  }
  if (entry.isFile) {
    const file = await fileFromEntry(entry);
    try {
      Object.defineProperty(file, '_plpcRelativePath', {
        value: path,
        configurable: true,
      });
    } catch {
      file._plpcRelativePath = path;
    }
    files.push({ path, fileWithPath: file });
    return;
  }
  if (!entry.isDirectory) {
    return;
  }

  const reader = entry.createReader();
  for (;;) {
    const children = await readDirectoryEntries(reader);
    if (children.length === 0) {
      break;
    }
    for (const child of children) {
      await collectEntryFiles(child, path, files);
    }
  }
}

function fileFromEntry(entry) {
  return new Promise((resolve, reject) => {
    entry.file(resolve, reject);
  });
}

function readDirectoryEntries(reader) {
  return new Promise((resolve, reject) => {
    reader.readEntries(resolve, reject);
  });
}

/**
 * Put each browser File into Playground's private filesystem before creating
 * the import job. Sending source bytes as JSON/base64 used several document-
 * sized browser allocations at once (and made a 90 MB file especially
 * dangerous on mobile). The REST payload is now only a small, validated path
 * manifest; PHP moves those files into persistent job storage.
 */
async function stageUploadInPlayground(client, upload, reportProgress = () => {}) {
  const token = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  const root = `${playgroundUploadDirectory}/${token.replace(/[^A-Za-z0-9-]/g, '')}`;
  const paths = [];
  const stagedFiles = [];
  const pdfRasterImages = {};
  const pdfRasterBudget = { remainingBytes: pdfRasterPayloadByteLimit };
  const imageMode = selectedImageMode();

  try {
    await client.mkdirTree(root);
    for (let index = 0; index < upload.entries.length; index += 1) {
      const entry = upload.entries[index];
      reportProgress(`Staging document ${index + 1} of ${upload.entries.length} in WordPress Playground...`);
      let bytes = new Uint8Array(await entry.file.arrayBuffer());
      const stagedPath = `${root}/source-${String(index).padStart(4, '0')}.upload`;
      await client.writeFile(stagedPath, bytes);
      paths.push(stagedPath);
      stagedFiles.push({ path: entry.path, stagedPath });

      if (isLikelyPdfFile(entry.file)) {
        if (bytes.length > pdfRasterSourceByteLimit) {
          reportProgress(`Skipping optional browser image decoding for ${entry.file.name}; it is over the 24 MiB browser safety limit. Native PHP extraction will continue.`);
        } else {
          // Browser text/structure facts are deliberately not collected here.
          // No reader consumes them yet, and parsing the whole PDF a second
          // time made large imports slower before useful work even began.
          if (imageMode !== 'none') {
            const rasters = await browserPdfRasterImages(bytes, imageMode, reportProgress, pdfRasterBudget);
            if (rasters.length > 0) {
              pdfRasterImages[entry.path] = rasters;
            }
          }
        }
      }
      // Do not retain a whole source in the browser while the next file is
      // staged. The File remains available for any later PDF.js Form crop.
      bytes = new Uint8Array(0);
    }

    return {
      paths,
      payload: {
        filename: upload.displayName,
        title: upload.title,
        imageMode,
        pdfMode: selectedPdfMode(),
        pdfOutputMode: selectedPdfOutputMode(),
        stagedFiles,
        ...(Object.keys(pdfRasterImages).length > 0 ? { pdfRasterImages } : {}),
      },
    };
  } catch (error) {
    await cleanupStagedUpload(client, paths);
    throw error;
  }
}

async function cleanupStagedUpload(client, paths) {
  for (const path of paths || []) {
    try {
      await client.unlink(path);
    } catch {
      // A successful job atomically moved its staging file into private job
      // storage. A failed cleanup must not hide the original import error.
    }
  }
}

// PDF filter names are ASCII tokens. Search the existing byte view directly
// so ordinary PDFs do not pay for a full-source string copy merely to decide
// whether an optional image decoder is relevant.
function pdfBytesContainAscii(bytes, ascii) {
  const needleLength = typeof ascii === 'string' ? ascii.length : 0;
  if (!(bytes instanceof Uint8Array) || needleLength < 1 || needleLength > 32 || bytes.length < needleLength) {
    return false;
  }
  for (let index = 0; index < needleLength; index += 1) {
    if (ascii.charCodeAt(index) > 0x7f) {
      return false;
    }
  }

  const firstByte = ascii.charCodeAt(0);
  const lastStart = bytes.length - needleLength;
  let offset = 0;
  while (offset <= lastStart) {
    const matchStart = bytes.indexOf(firstByte, offset);
    if (matchStart < 0 || matchStart > lastStart) {
      return false;
    }
    let needleIndex = 1;
    while (needleIndex < needleLength && bytes[matchStart + needleIndex] === ascii.charCodeAt(needleIndex)) {
      needleIndex += 1;
    }
    if (needleIndex === needleLength) {
      return true;
    }
    offset = matchStart + 1;
  }
  return false;
}

async function browserPdfRasterImages(bytes, imageMode, reportProgress, aggregateBudget = null) {
  const decoderEntries = [
    {
      label: 'JBIG2',
      filterName: '/JBIG2Decode',
      load: async () => {
        if (!decodePdfJbig2Rasters) {
          const module = await import(new URL('pdf-jbig2-rasterizer.mjs?v=jbig2-raster-20260709', window.location.href).href);
          decodePdfJbig2Rasters = module.decodePdfJbig2Rasters;
        }
        return decodePdfJbig2Rasters;
      },
    },
    {
      label: 'JPEG 2000',
      filterName: '/JPXDecode',
      load: async () => {
        if (!decodePdfJpxRasters) {
          const module = await import(new URL('pdf-jpx-rasterizer.mjs?v=jpx-raster-20260714', window.location.href).href);
          decodePdfJpxRasters = module.decodePdfJpxRasters;
        }
        return decodePdfJpxRasters;
      },
    },
  ].filter(({ filterName }) => pdfBytesContainAscii(bytes, filterName));
  const loaded = await Promise.allSettled(decoderEntries.map(async (entry) => ({
    entry,
    decode: await entry.load(),
  })));
  const rasters = [];
  const objects = new Set();
  let remainingBytes = Math.min(
    pdfRasterPayloadByteLimit,
    Math.max(0, Number(aggregateBudget?.remainingBytes ?? pdfRasterPayloadByteLimit) || 0),
  );
  for (const [index, result] of loaded.entries()) {
    if (result.status !== 'fulfilled') {
      log(`Browser ${decoderEntries[index]?.label || 'PDF'} image preparation was unavailable: ${errorMessage(result.reason)}`);
      continue;
    }
    const { entry, decode } = result.value;
    if (typeof decode !== 'function' || remainingBytes <= 0) {
      continue;
    }
    try {
      const decoded = await decode(bytes, {
        imageMode,
        maxPngBytes: remainingBytes,
        onProgress({ completed, total }) {
          reportProgress(total > 0
            ? `Preparing PDF images (${completed} of ${total})...`
            : 'Preparing PDF images...');
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
    } catch (error) {
      log(`Browser ${entry.label} image preparation was unavailable: ${errorMessage(error)}`);
    }
  }
  if (rasters.length > 0) {
    log(`Prepared ${rasters.length} browser-decoded PDF image${rasters.length === 1 ? '' : 's'}.`);
  }
  if (aggregateBudget && typeof aggregateBudget === 'object') {
    aggregateBudget.remainingBytes = remainingBytes;
  }

  return rasters.map((raster) => ({
    object: raster.object,
    bytes: base64FromBytes(raster.bytes),
    mimeType: raster.mimeType,
    width: raster.width,
    height: raster.height,
  }));
}

function errorMessage(error) {
  return error instanceof Error ? error.message : String(error);
}

function selectedImageMode() {
  return imageModeInputs.find((input) => input.checked)?.value || 'important';
}

function setSelectedImageMode(mode) {
  for (const input of imageModeInputs) {
    input.checked = input.value === mode;
  }
}

function selectedPdfMode() {
  return pdfModeInputs.find((input) => input.checked)?.value || 'layout';
}

function selectedPdfOutputMode() {
  return pdfOutputModeInputs.find((input) => input.checked)?.value || 'single';
}

function setSelectedPdfMode(mode) {
  const normalized = mode === 'text' ? 'text' : 'layout';
  for (const input of pdfModeInputs) {
    input.checked = input.value === normalized;
  }
}

function updatePdfModeVisibility(upload) {
  const hidden = !uploadContainsPdf(upload);
  if (pdfModeControl) {
    pdfModeControl.hidden = hidden;
  }
  if (pdfOutputModeControl) {
    pdfOutputModeControl.hidden = hidden;
  }
}

function uploadContainsPdf(upload) {
  if (!upload) {
    return false;
  }
  return upload.entries.some((entry) => isLikelyPdfFile(entry.file));
}

function isLikelyPdfFile(file) {
  return file.type === 'application/pdf' || extensionFromName(file.name) === 'pdf';
}

function setQualityPanel(quality) {
  if (!qualityPanel || !qualityTitle || !qualityMessage || !qualityDetails) {
    return;
  }
  if (!quality) {
    qualityPanel.hidden = true;
    qualityPanel.dataset.status = '';
    qualityTitle.textContent = 'Import quality';
    qualityMessage.textContent = '';
    qualityDetails.replaceChildren();
    if (retryActions) {
      retryActions.hidden = true;
    }
    if (resumePdfPagesButton) {
      resumePdfPagesButton.hidden = true;
    }
    return;
  }

  const status = String(quality.status || 'complete');
  const flags = Array.isArray(quality.flags) ? quality.flags.map(String) : [];
  const warnings = Array.isArray(quality.warnings) ? quality.warnings.map(String).filter(Boolean) : [];
  qualityPanel.hidden = false;
  qualityPanel.dataset.status = status;
  qualityTitle.textContent = qualityTitleForStatus(status);
  qualityMessage.textContent = qualityMessageForStatus(status);
  qualityDetails.replaceChildren(...qualityDetailItems(status, flags, warnings).map((detail) => {
    const item = document.createElement('li');
    item.textContent = detail;
    return item;
  }));

  updateRetryActions(status, flags);
}

function qualityTitleForStatus(status) {
  switch (status) {
    case 'truncated':
      return 'Import quality: partial import';
    case 'ocr_needed':
      return 'Import quality: OCR needed';
    case 'partial':
      return 'Import quality: review needed';
    case 'media_missing':
      return 'Import quality: missing media';
    case 'layout_uncertain':
      return 'Import quality: layout review needed';
    case 'best_effort':
      return 'Import quality: best effort';
    default:
      return 'Import quality: complete';
  }
}

function qualityMessageForStatus(status) {
  switch (status) {
    case 'truncated':
      return 'Only part of this document was imported.';
    case 'ocr_needed':
      return 'This PDF likely needs OCR before import.';
    case 'partial':
      return 'Some content could not be imported automatically.';
    case 'media_missing':
      return 'The content imported, but some images or media files are missing.';
    case 'layout_uncertain':
      return 'The content imported, but the layout needs review.';
    case 'best_effort':
      return 'The document was imported using best-effort reconstruction.';
    default:
      return 'The document imported successfully.';
  }
}

function qualityLogMessage(quality) {
  const status = String(quality?.status || 'complete');

  return `${qualityTitleForStatus(status)}: ${qualityMessageForStatus(status)}`;
}

function qualityDetailItems(status, flags, warnings) {
  const details = [];
  const flagSet = new Set(flags);
  if (flagSet.has('truncated')) {
    details.push('Later content may be missing because the browser safety limit was reached.');
  }
  if (flagSet.has('ocr_needed')) {
    details.push('This PDF appears to have little or no extractable text. Run OCR first, then import the searchable PDF.');
  }
  if (flagSet.has('partial')) {
    details.push('Review the page before publishing; at least one part of the import was incomplete.');
  }
  if (flagSet.has('media_missing')) {
    details.push('Try importing again with all images, or upload the source folder/ZIP that contains the missing media.');
  }
  if (flagSet.has('layout_uncertain')) {
    details.push('Check headings, reading order, columns, tables, and image placement.');
  }
  if (flagSet.has('best_effort')) {
    details.push('This format may not preserve the original document layout exactly.');
  }
  for (const warning of warnings.slice(0, 3)) {
    if (!details.includes(warning)) {
      details.push(warning);
    }
  }

  return details;
}

function updateRetryActions(status, flags) {
  if (!retryActions) {
    return;
  }
  const qualityStatus = String(status || 'complete');
  const flagSet = new Set(flags);
  const shouldOfferImageRetry = Boolean(selectedUpload && flagSet.has('media_missing'));
  const shouldOfferPdfRetry = Boolean(selectedUpload && uploadContainsPdf(selectedUpload) && (
    qualityStatus !== 'complete'
    || flagSet.has('truncated')
    || flagSet.has('partial')
    || flagSet.has('ocr_needed')
    || flagSet.has('layout_uncertain')
    || flagSet.has('best_effort')
  ));
  retryActions.hidden = !(shouldOfferImageRetry || shouldOfferPdfRetry);
  const currentImageMode = selectedImageMode();
  const currentPdfMode = selectedPdfMode();
  for (const button of retryButtons) {
    if (button.dataset.retryImageMode) {
      button.hidden = !shouldOfferImageRetry || button.dataset.retryImageMode === currentImageMode;
      continue;
    }
    if (button.dataset.retryPdfMode) {
      button.hidden = !shouldOfferPdfRetry || button.dataset.retryPdfMode === currentPdfMode;
      continue;
    }
    button.hidden = true;
  }
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

function isLikelyIOS() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function titleFromFilename(name) {
  const last = name.split('/').filter(Boolean).pop() || name;
  const stem = last.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem ? stem.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Converted document';
}

function extensionFromName(name) {
  return name.includes('.') ? name.split('.').pop().toLowerCase() : '';
}

function normalizeRelativePath(path) {
  const parts = String(path || '')
    .replaceAll('\\', '/')
    .split('/')
    .filter((part) => part && part !== '.');
  const normalized = [];
  for (const part of parts) {
    if (part === '..') {
      normalized.pop();
    } else {
      normalized.push(part);
    }
  }

  return normalized.join('/');
}

function commonRoot(paths) {
  const roots = paths
    .map((path) => path.split('/').filter(Boolean)[0] || '')
    .filter(Boolean);
  if (roots.length === 0) {
    return '';
  }
  const first = roots[0];

  return roots.every((root) => root === first) ? first : '';
}

function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function browserStorage() {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

function playgroundPath(url) {
  try {
    const parsed = new URL(url);
    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return url || '/';
  }
}

showSavedImportAction();
