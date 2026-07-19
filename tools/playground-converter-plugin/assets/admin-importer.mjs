import {
  renderPdfFormRequestsIncrementally,
  renderPdfPageRasterRequestsIncrementally,
} from './pdfjs-form-rasterizer.mjs';

// This matches the server-side per-import cap. Keep the decoded image bytes
// in multipart fields, not JSON/base64, so a regular WordPress upload does
// not temporarily duplicate a large PDF and its raster fallbacks in memory.
const pdfRasterPayloadByteLimit = 24_000_000;
const pdfRasterSourceByteLimit = 24 * 1024 * 1024;
const pdfFormRenderTotalPixelLimit = 48_000_000;
const pdfFormRenderTotalImageByteLimit = 24_000_000;
const pdfPageRenderTotalPixelLimit = 128_000_000;
const pdfPageRenderTotalImageByteLimit = 64 * 1024 * 1024;
const pdfPageRasterMethod = 'pdfjs-whole-page-raster';
const maxAdvanceRecoveryAttempts = 2;
let decodePdfJbig2Rasters = null;
let decodePdfJpxRasters = null;

const root = document.getElementById('plpc-importer');
const config = window.PortLibsImporterConfig || {};

if (root) {
  const form = root.querySelector('form');
  const input = root.querySelector('#plpc-import-file');
  const selection = root.querySelector('[data-plpc-selection]');
  const submit = root.querySelector('[data-plpc-submit]');
  const pdfOptions = root.querySelector('[data-plpc-pdf-options]');
  const pdfOutputOptions = root.querySelector('[data-plpc-pdf-output-options]');
  const progress = root.querySelector('[data-plpc-progress]');
  const progressLabel = root.querySelector('[data-plpc-progress-label]');
  const progressBar = root.querySelector('[data-plpc-progress-bar]');
  const progressDetail = root.querySelector('[data-plpc-progress-detail]');
  const cancelButton = root.querySelector('[data-plpc-cancel]');
  const events = root.querySelector('[data-plpc-events]');
  const result = root.querySelector('[data-plpc-result]');
  let selected = null;
  let active = false;
  let elapsedTimer = null;
  let statusTimer = null;
  let activeJobId = '';
  let lastSnapshot = null;
  let cancellationRequested = false;
  const jobSession = createAdminImportJobSession(config);

  input.addEventListener('change', () => {
    selected = uploadFromFiles(Array.from(input.files || []));
    updateSelection({ clearResult: true });
  });
  form.addEventListener('dragover', (event) => {
    event.preventDefault();
    form.classList.add('is-dragging');
  });
  form.addEventListener('dragleave', () => form.classList.remove('is-dragging'));
  form.addEventListener('drop', (event) => {
    event.preventDefault();
    form.classList.remove('is-dragging');
    selected = uploadFromFiles(Array.from(event.dataTransfer?.files || []));
    updateSelection({ clearResult: true });
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!selected || active || jobSession.load()?.cancellationRequested) {
      if (jobSession.load()?.cancellationRequested) showSavedImportAction(lastSnapshot);
      return;
    }
    try {
      await runImport();
    } catch (error) {
      showError(errorMessage(error));
    }
  });
  cancelButton?.addEventListener('click', () => {
    if (!active || !activeJobId || cancellationRequested) return;
    cancellationRequested = true;
    jobSession.requestCancellation(activeJobId);
    cancelButton.disabled = true;
    cancelButton.textContent = 'Cancelling after this step…';
    appendEvent('cancelling', 'Cancellation requested. Finishing only the current bounded checkpoint.');
  });
  window.addEventListener('beforeunload', (event) => {
    if (!active) return;
    event.preventDefault();
    event.returnValue = '';
  });

  function updateSelection({ clearResult = false } = {}) {
    // Keep a completed import's Edit/View links visible after the finally
    // block re-enables this form. A new file selection is what invalidates a
    // previous result, not merely toggling the active state.
    if (clearResult) {
      result.hidden = true;
      result.replaceChildren();
    }
    submit.disabled = !selected || active || jobSession.load()?.cancellationRequested === true;
    if (!selected) {
      selection.textContent = 'No file selected.';
      pdfOptions.hidden = true;
      pdfOutputOptions.hidden = true;
      return;
    }
    selection.textContent = `${selected.displayName} (${formatBytes(selected.totalSize)})`;
    const hasPdf = selected.entries.some(({ file }) => isPdf(file));
    pdfOptions.hidden = !hasPdf;
    pdfOutputOptions.hidden = !hasPdf;
  }

  async function runImport() {
    active = true;
    cancellationRequested = false;
    updateSelection();
    events.replaceChildren();
    result.hidden = true;
    progress.hidden = false;
    const startedAt = Date.now();
    elapsedTimer = window.setInterval(() => {
      if (lastSnapshot?.progress?.label) {
        progressDetail.textContent = `${lastSnapshot.progress.label} Elapsed ${formatElapsed(Date.now() - startedAt)}.`;
      }
    }, 1_000);
    try {
      setProgress({ progress: { completed: 0, total: 1, label: 'Reading the selected file…' }, events: [] });
      const payload = await payloadFromUpload(selected, (label) => {
        setProgress({
          progress: { completed: 0, total: 1, label },
          events: lastSnapshot?.events || [],
        }, { replaceEvents: false });
      });
      let snapshot = await request('imports', { method: 'POST', body: payload });
      activeJobId = String(snapshot.jobId || '');
      startStatusPolling();
      setProgress(snapshot);
      const pdfFiles = new Map(selected.entries.filter(({ file }) => isPdf(file)).map(({ path, file }) => [path, file]));
      snapshot = await driveImportJob(snapshot, pdfFiles);
      if (snapshot.status === 'cancelled') {
        jobSession.forget(snapshot.jobId);
        showCancelled(snapshot);
        return;
      }
      if (snapshot.status === 'awaiting_output_mode') {
        showOutputModeRecovery(snapshot, pdfFiles);
        return;
      }
      if (snapshot.status === 'failed') {
        throw new Error(snapshot.message || 'Import failed.');
      }
      jobSession.forget(snapshot.jobId);
      showResult(snapshot.result || {});
    } finally {
      active = false;
      activeJobId = '';
      if (elapsedTimer !== null) {
        window.clearInterval(elapsedTimer);
        elapsedTimer = null;
      }
      if (statusTimer !== null) {
        window.clearInterval(statusTimer);
        statusTimer = null;
      }
      updateSelection();
      if (['complete', 'failed', 'cancelled'].includes(String(lastSnapshot?.status || ''))) {
        cancellationRequested = false;
      }
      if (cancelButton) {
        cancelButton.hidden = true;
        cancelButton.disabled = true;
        cancelButton.textContent = 'Cancel import';
      }
      if (lastSnapshot?.status !== 'awaiting_output_mode' && jobSession.load()) {
        showSavedImportAction(lastSnapshot);
      }
    }
  }

  async function driveImportJob(initialSnapshot, pdfFiles) {
    let snapshot = initialSnapshot;
    const formBudget = {
      remainingPixels: pdfFormRenderTotalPixelLimit,
      remainingImageBytes: pdfFormRenderTotalImageByteLimit,
    };
    const pageBudget = {
      remainingPixels: pdfPageRenderTotalPixelLimit,
      remainingImageBytes: pdfPageRenderTotalImageByteLimit,
    };
    while (!['complete', 'failed', 'cancelled'].includes(String(snapshot.status || ''))) {
        if (cancellationRequested) {
          snapshot = await cancelImportJob(snapshot);
          setProgress(snapshot);
          break;
        }
        if (snapshot.status === 'awaiting_output_mode') {
          break;
        }
        if (Array.isArray(snapshot.renderRequests) && snapshot.renderRequests.length > 0) {
          appendEvent('renderer', `Rendering ${snapshot.renderRequests.length} PDF visual${snapshot.renderRequests.length === 1 ? '' : 's'} (figures/page images) locally in this browser.`);
          for (const group of pdfRenderRequestGroups(snapshot.renderRequests)) {
            const requests = group.requests;
            const pageRaster = group.method === pdfPageRasterMethod;
            const budget = pageRaster ? pageBudget : formBudget;
            const filesByPath = budget.remainingPixels <= 0 || budget.remainingImageBytes <= 0
              ? new Map()
              : await pdfFilesForAdminRenderRequests(snapshot, pdfFiles, requests);
            const renderOptions = {
              ...(pageRaster
                ? {
                  source: pdfPageRasterSource(filesByPath, requests),
                  requests: requests.map(pdfPageRasterRequestForRenderer),
                }
                : { filesByPath, requests }),
              pdfjs: config,
              maxTotalPixels: budget.remainingPixels,
              maxTotalImageBytes: budget.remainingImageBytes,
              onProgress: ({ completed, total, label }) => {
                setProgress({
                  ...snapshot,
                  progress: { completed, total: Math.max(total, 1), label },
                }, { replaceEvents: false });
              },
            };
            const renderer = pageRaster
              ? renderPdfPageRasterRequestsIncrementally
              : renderPdfFormRequestsIncrementally;
            for await (const renderedItem of renderer(renderOptions)) {
              const item = pdfRenderedMediaItem(renderedItem);
              if (cancellationRequested) {
                snapshot = await cancelImportJob(snapshot);
                setProgress(snapshot);
                break;
              }
              if (!item.error && item.bytes instanceof Uint8Array) {
                const pixels = Math.max(0, Number(item.width) || 0) * Math.max(0, Number(item.height) || 0);
                budget.remainingPixels = Math.max(0, budget.remainingPixels - pixels);
                budget.remainingImageBytes = Math.max(0, budget.remainingImageBytes - item.bytes.byteLength);
              }
              if (item.budgetExhausted === 'pixels') budget.remainingPixels = 0;
              if (item.budgetExhausted === 'image-bytes') budget.remainingImageBytes = 0;
              snapshot = await submitRenderedMedia(snapshot, item);
              setProgress(snapshot);
              if (['complete', 'failed', 'cancelled'].includes(String(snapshot.status || ''))) break;
            }
            if (['complete', 'failed', 'cancelled'].includes(String(snapshot.status || ''))) break;
          }
          continue;
        }
        snapshot = await advanceImportJob(snapshot);
        setProgress(snapshot);
      }

    return snapshot;
  }

  async function cancelImportJob(snapshot) {
    const jobId = String(snapshot?.jobId || activeJobId || '');
    if (!jobId) throw new Error('The saved import job no longer has an identifier.');
    const encodedJobId = encodeURIComponent(jobId);
    let lastError = null;
    let attempt = 0;
    while (cancellationRequested && active && activeJobId === jobId) {
      attempt += 1;
      try {
        const cancelled = await request(`imports/${encodedJobId}/cancel`, { method: 'POST', body: {} });
        setProgress(cancelled);
        if (['complete', 'failed', 'cancelled'].includes(String(cancelled.status || ''))) return cancelled;
      } catch (error) {
        lastError = error;
        if (!cancellationErrorIsRetryable(error)) throw error;
      }
      if (!cancellationRequested || !active || activeJobId !== jobId) break;
      try {
        const recovered = await request(`imports/${encodedJobId}`, { method: 'GET' });
        setProgress(recovered);
        if (['complete', 'failed', 'cancelled'].includes(String(recovered.status || ''))) return recovered;
      } catch (error) {
        lastError = error;
        if (!cancellationErrorIsRetryable(error)) throw error;
      }
      if (!cancellationRequested || !active || activeJobId !== jobId) break;
      appendEvent('cancelling', `Cancellation is waiting for the current checkpoint (${attempt}). Checking again…`);
      await pause(Math.min(2_000, 400 * attempt));
    }
    throw new Error(`${errorMessage(lastError)} Cancellation remains saved and will be retried when this import is resumed.`);
  }

  async function submitRenderedMedia(snapshot, item) {
    const jobId = encodeURIComponent(String(snapshot?.jobId || ''));
    const requestId = String(item?.requestId || '');
    if (!jobId || !requestId) {
      throw new Error('WordPress returned an invalid PDF visual/page-image request.');
    }
    const body = () => (item.error
      ? { requestId, error: item.error }
      : renderedImageFormData(item));
    if (cancellationRequested) {
      return cancelImportJob(snapshot);
    }
    try {
      return await request(`imports/${jobId}/rendered-media`, { method: 'POST', body: body() });
    } catch (error) {
      if (cancellationRequested) {
        return cancelImportJob(snapshot);
      }
      appendEvent('recovery', 'The PDF visual/page-image upload ended unexpectedly. Checking the saved import before sending it again…');
      const recovered = await request(`imports/${jobId}`, { method: 'GET' });
      setProgress(recovered);
      const stillOutstanding = (recovered.renderRequests || [])
        .some((requestItem) => String(requestItem?.id || '') === requestId);
      if (!stillOutstanding || ['complete', 'failed', 'cancelled'].includes(String(recovered.status || ''))) {
        return recovered;
      }
      if (cancellationRequested) {
        return cancelImportJob(recovered);
      }
      try {
        return await request(`imports/${jobId}/rendered-media`, { method: 'POST', body: body() });
      } catch (retryError) {
        if (cancellationRequested) {
          return cancelImportJob(recovered);
        }
        throw new Error(`${errorMessage(retryError)} The rendered PDF visual/page image remains recoverable; use Resume saved import to re-check WordPress before rendering it again.`);
      }
    }
  }

  function startStatusPolling() {
    if (statusTimer !== null) {
      return;
    }
    statusTimer = window.setInterval(async () => {
      const jobId = activeJobId;
      if (!jobId) {
        return;
      }
      try {
        const snapshot = await request(`imports/${encodeURIComponent(jobId)}`, { method: 'GET' });
        if (active && activeJobId === jobId && !['complete', 'failed', 'cancelled'].includes(String(lastSnapshot?.status || ''))) {
          setProgress(snapshot);
        }
      } catch {
        // The foreground request reports actionable errors. A transient poll
        // failure must not interrupt an import that is still running.
      }
    }, 1_000);
  }

  async function advanceImportJob(snapshot) {
    const jobId = String(snapshot?.jobId || activeJobId || '');
    if (!jobId) {
      throw new Error('The saved import job no longer has an identifier. Please select the file again.');
    }
    let lastError = null;
    for (let recoveryAttempt = 0; recoveryAttempt <= maxAdvanceRecoveryAttempts; recoveryAttempt += 1) {
      if (cancellationRequested) {
        return cancelImportJob(snapshot);
      }
      try {
        return await request(`imports/${encodeURIComponent(jobId)}/advance`, { method: 'POST', body: {} });
      } catch (error) {
        lastError = error;
        if (cancellationRequested) {
          return cancelImportJob(snapshot);
        }
        if (recoveryAttempt >= maxAdvanceRecoveryAttempts) {
          break;
        }
        appendEvent(
          'recovery',
          `The server request ended unexpectedly. Checking the saved import state (${recoveryAttempt + 1} of ${maxAdvanceRecoveryAttempts})…`,
        );
        for (let statusAttempt = 1; statusAttempt <= 3; statusAttempt += 1) {
          if (cancellationRequested) {
            return cancelImportJob(lastSnapshot?.jobId === jobId ? lastSnapshot : snapshot);
          }
          await pause(400 * statusAttempt);
          try {
            const recovered = await request(`imports/${encodeURIComponent(jobId)}`, { method: 'GET' });
            setProgress(recovered);
            if (cancellationRequested) {
              return cancelImportJob(recovered);
            }
            // The mutation may have committed even when its response was
            // lost. While WordPress still reports the original worker as
            // converting, wait instead of retransmitting /advance.
            if (String(recovered.status || '') !== 'converting') {
              return recovered;
            }
          } catch (statusError) {
            lastError = statusError;
          }
        }
      }
    }
    throw new Error(`${errorMessage(lastError)} The importer stopped retrying automatically to avoid duplicating work. Refresh to inspect the saved import state, then start a new import if WordPress marked it failed.`);
  }

  function pdfRenderRequestGroups(requests) {
    const groups = new Map();
    for (const requestItem of Array.isArray(requests) ? requests : []) {
      const path = String(requestItem?.path || '');
      const sourceKey = String(requestItem?.sourceKey || path);
      const method = requestItem?.method === pdfPageRasterMethod
        ? pdfPageRasterMethod
        : 'pdf-form-xobject';
      const groupKey = `${method}\u001f${sourceKey}`;
      if (!groups.has(groupKey)) groups.set(groupKey, { method, requests: [] });
      groups.get(groupKey).requests.push(requestItem);
    }
    return Array.from(groups.values());
  }

  function pdfPageRasterSource(filesByPath, requests) {
    for (const requestItem of requests || []) {
      const path = String(requestItem?.path || '');
      if (path && filesByPath?.has(path)) return filesByPath.get(path);
    }
    return filesByPath instanceof Map ? filesByPath.values().next().value : undefined;
  }

  function pdfPageRasterRequestForRenderer(requestItem) {
    return {
      version: requestItem?.version,
      method: requestItem?.method,
      id: requestItem?.id,
      sourceSha256: requestItem?.sourceSha256,
      page: requestItem?.page,
      pageObject: requestItem?.pageObject,
      pageBox: requestItem?.pageBox,
      pageBoxSource: requestItem?.pageBoxSource,
      pageRotation: requestItem?.pageRotation,
      width: requestItem?.width,
      height: requestItem?.height,
      mimeType: requestItem?.mimeType,
      requestDigest: requestItem?.requestDigest,
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

  async function pdfFilesForAdminRenderRequests(snapshot, pdfFiles, renderRequests) {
    const filesByPath = new Map();
    for (const renderRequest of renderRequests || []) {
      const path = String(renderRequest?.path || '');
      if (!path || filesByPath.has(path)) {
        continue;
      }
      if (pdfFiles.has(path)) {
        filesByPath.set(path, pdfFiles.get(path));
        continue;
      }
      try {
        const source = await request(
          `imports/${encodeURIComponent(snapshot.jobId)}/render-source/${encodeURIComponent(String(renderRequest.id || ''))}`,
          { method: 'GET' },
        );
        const sourcePath = String(source.path || path);
        const sourceBytes = bytesFromBase64(String(source.bytes || ''));
        if (sourceBytes.length === 0) {
          throw new Error('The server returned an empty PDF renderer source.');
        }
        filesByPath.set(path, sourceBytes);
        filesByPath.set(sourcePath, sourceBytes);
      } catch (error) {
        // Keep the request outstanding: the shared renderer turns this into a
        // per-visual error, which lets PHP continue with its regular source
        // attachment/placeholder rather than abandoning the whole import.
        appendEvent('renderer', `One PDF visual/page-image source could not be loaded in this browser (${errorMessage(error)}). The import will continue with its available fallback.`);
      }
    }

    return filesByPath;
  }

  function setProgress(snapshot, { replaceEvents = true } = {}) {
    lastSnapshot = snapshot;
    jobSession.remember(snapshot);
    const state = snapshot?.progress || {};
    const total = Math.max(1, Number(state.total) || 1);
    const completed = Math.min(total, Math.max(0, Number(state.completed) || 0));
    progressLabel.textContent = String(state.label || 'Importing document…');
    progressBar.max = total;
    progressBar.value = completed;
    const details = [`${completed} of ${total} import steps complete`];
    const metrics = snapshot?.metrics || {};
    const pdfPagesTotal = Math.max(0, Number(metrics.pdfPagesTotal) || 0);
    const pdfPagesExtracted = Math.min(pdfPagesTotal, Math.max(0, Number(metrics.pdfPagesExtracted) || 0));
    if (pdfPagesTotal > 0) {
      details.push(`${pdfPagesExtracted} of ${pdfPagesTotal} PDF pages safely checkpointed`);
      const durationMs = Math.max(0, Number(metrics?.lastExtraction?.durationMs) || 0);
      if (durationMs > 0) {
        details.push(`last extraction request ${formatElapsed(durationMs)}`);
      }
    }
    const publication = snapshot?.publication || {};
    const publicationTotal = Math.max(0, Number(publication.total) || 0);
    if (publicationTotal > 0) {
      details.push(`${Math.max(0, Number(publication.completed) || 0)} of ${publicationTotal} verified pages published`);
    }
    const mediaMetadata = snapshot?.mediaMetadata || {};
    const mediaMetadataTotal = Math.max(0, Number(mediaMetadata.total) || 0);
    if (mediaMetadataTotal > 0) {
      details.push(`${Math.max(0, Number(mediaMetadata.completed) || 0)} of ${mediaMetadataTotal} media metadata records prepared`);
    }
    progressDetail.textContent = `${details.join('. ')}.`;
    if (cancelButton) {
      const cancellable = active
        && String(snapshot?.jobId || '') !== ''
        && !['complete', 'failed', 'cancelled'].includes(String(snapshot?.status || ''));
      cancelButton.hidden = !cancellable;
      cancelButton.disabled = !cancellable || cancellationRequested;
      cancelButton.textContent = cancellationRequested ? 'Cancelling after this step…' : 'Cancel import';
    }
    if (replaceEvents && Array.isArray(snapshot?.events)) {
      events.replaceChildren();
      for (const event of snapshot.events) {
        appendEvent(event.stage, event.message);
      }
    }
  }

  function appendEvent(stage, message) {
    const last = events.lastElementChild;
    if (last?.dataset.stage === String(stage) && last.textContent === String(message)) {
      return;
    }
    const item = document.createElement('li');
    item.dataset.stage = String(stage || 'progress');
    item.textContent = String(message || 'Import is continuing.');
    events.append(item);
  }

  function showResult(importResult) {
    progressLabel.textContent = 'Import complete.';
    progressDetail.textContent = 'Your WordPress page is ready.';
    result.hidden = false;
    result.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = 'Import complete';
    result.append(heading);
    const text = document.createElement('p');
    text.textContent = `Created ${importResult.postCount || 1} WordPress page${Number(importResult.postCount || 1) === 1 ? '' : 's'} and imported ${importResult.imagesImported || 0} media file${Number(importResult.imagesImported || 0) === 1 ? '' : 's'}.`;
    result.append(text);
    for (const [label, url] of [['Edit page', importResult.editUrl], ['View page', importResult.pageUrl]]) {
      if (!url) {
        continue;
      }
      const link = document.createElement('a');
      link.className = 'button button-secondary';
      link.href = String(url);
      link.textContent = label;
      result.append(link);
    }
  }

  function showCancelled(snapshot) {
    progressLabel.textContent = 'Import cancelled.';
    progressDetail.textContent = String(snapshot?.progress?.label || 'No further import work will run.');
    const drafts = Math.max(0, Number(snapshot?.cancellation?.postsRetainedAsDraft) || 0);
    const media = Math.max(0, Number(snapshot?.cancellation?.mediaAttachmentsRetained) || 0);
    appendEvent(
      'cancelled',
      `The private job checkpoint was cancelled. WordPress retained ${drafts} page draft${drafts === 1 ? '' : 's'} and ${media} media attachment${media === 1 ? '' : 's'} for review or reuse.`,
    );
  }

  function showOutputModeRecovery(snapshot, pdfFiles) {
    const failure = snapshot?.failure || {};
    const actual = Math.max(0, Number(failure.actualBytes) || Number(snapshot?.output?.assembledBytes) || 0);
    const allowed = Math.max(0, Number(failure.allowedBytes) || Number(snapshot?.output?.singlePageLimitBytes) || 0);
    result.hidden = false;
    result.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = 'The PDF is too large for one safe page';
    const explanation = document.createElement('p');
    explanation.textContent = `${formatBytes(actual)} of converted blocks exceeds this server’s ${formatBytes(allowed)} single-page limit. No partial page was created, and the completed PDF extraction is preserved.`;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary';
    button.textContent = 'Continue as one page per PDF page';
    button.addEventListener('click', async () => {
      if (active) return;
      active = true;
      cancellationRequested = jobSession.load()?.cancellationRequested === true;
      activeJobId = String(snapshot.jobId || '');
      button.disabled = true;
      result.hidden = true;
      try {
        let resumed = snapshot;
        startStatusPolling();
        if (!cancellationRequested) {
          resumed = await request(`imports/${encodeURIComponent(activeJobId)}/output-mode`, {
            method: 'POST',
            body: { pdfOutputMode: 'pages' },
          });
          setProgress(resumed);
        }
        resumed = await driveImportJob(resumed, pdfFiles);
        if (resumed.status === 'failed') {
          throw new Error(resumed.message || 'Import failed.');
        }
        if (resumed.status === 'awaiting_output_mode') {
          showOutputModeRecovery(resumed, pdfFiles);
          return;
        }
        if (resumed.status === 'cancelled') {
          jobSession.forget(resumed.jobId);
          showCancelled(resumed);
          return;
        }
        jobSession.forget(resumed.jobId);
        showResult(resumed.result || {});
      } catch (error) {
        showError(errorMessage(error));
      } finally {
        active = false;
        activeJobId = '';
        button.disabled = false;
        if (statusTimer !== null) {
          window.clearInterval(statusTimer);
          statusTimer = null;
        }
        updateSelection();
        if (lastSnapshot?.status !== 'awaiting_output_mode' && jobSession.load()) {
          showSavedImportAction(lastSnapshot);
        }
      }
    });
    result.append(heading, explanation, button);
  }

  function showError(message) {
    progress.hidden = false;
    progressLabel.textContent = 'Import stopped';
    progressDetail.textContent = message;
    appendEvent('failed', message);
    if (jobSession.load()) {
      showSavedImportAction(lastSnapshot);
    }
  }

  async function request(path, { method, body }) {
    const multipart = body instanceof FormData;
    const response = await fetch(restEndpoint(path), {
      method,
      credentials: 'same-origin',
      headers: {
        ...(multipart || body === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}),
      },
      body: body === undefined ? undefined : (multipart ? body : JSON.stringify(body)),
    });
    const data = await response.json().catch(() => null);
    const jobErrorSnapshot = data
      && typeof data === 'object'
      && String(data.jobId || '') !== ''
      && ['failed', 'retryable_failure'].includes(String(data.status || ''));
    if (!data || ((!response.ok || data.ok === false) && !jobErrorSnapshot)) {
      const error = new Error(data?.message || `Import request failed (${response.status}).`);
      error.status = response.status;
      throw error;
    }
    return data;
  }

  // WordPress uses ?rest_route=/namespace/... when pretty permalinks are
  // disabled. new URL('imports', thatRoot) would otherwise resolve to the
  // site's /imports page and silently return HTML rather than REST JSON.
  function restEndpoint(path) {
    const root = new URL(config.restRoot || '/wp-json/port-libs/v1/', window.location.href);
    const restRoute = root.searchParams.get('rest_route');
    if (restRoute === null) {
      return new URL(path, root);
    }
    const suffix = String(path || '').replace(/^\/+/, '');
    root.searchParams.set('rest_route', `${restRoute.replace(/\/?$/, '/')}${suffix}`);

    return root;
  }

  async function payloadFromUpload(upload, reportProgress = () => {}) {
    const imageMode = checkedValue('plpc-image-mode', 'important');
    const pdfMode = checkedValue('plpc-pdf-mode', 'layout');
    const pdfOutputMode = checkedValue('plpc-pdf-output-mode', 'single');
    const body = new FormData();
    const pdfRasterImages = [];
    const pdfBrowserFacts = {};
    let remainingRasterBytes = pdfRasterPayloadByteLimit;
    for (let index = 0; index < upload.entries.length; index += 1) {
      const entry = upload.entries[index];
      if (!isPdf(entry.file)) {
        continue;
      }
      if (entry.file.size > pdfRasterSourceByteLimit) {
        reportProgress(`Skipping optional browser PDF enhancements for ${entry.file.name}; it is over the 24 MiB browser safety limit. Native PHP extraction will continue.`);
        continue;
      }
      const bytes = new Uint8Array(await entry.file.arrayBuffer());
      // Browser text/structure facts are opt-in until PdfReader consumes the
      // handoff. Avoid a second eager parse of every PDF merely to store data
      // that cannot affect the import. When enabled by a future server, the
      // provider can request a bounded page range per handoff.
      if (config.enablePdfBrowserFacts === true) {
        reportProgress(`Reading requested PDF.js text and structure facts from ${entry.file.name} (${index + 1} of ${upload.entries.length})…`);
        try {
          const { collectPdfJsFacts } = await import(new URL('./pdfjs-facts-provider.mjs', import.meta.url).href);
          const facts = await collectPdfJsFacts({
            source: bytes,
            pdfjs: config,
            startPage: Math.max(1, Number(config.pdfBrowserFactsStartPage) || 1),
            maxPages: Math.max(1, Number(config.pdfBrowserFactsMaxPages) || Number.POSITIVE_INFINITY),
            onProgress({ label }) { reportProgress(label); },
          });
          if (facts.pages.length > 0) {
            pdfBrowserFacts[entry.path] = facts;
          }
        } catch (error) {
          reportProgress(`Optional PDF.js text and structure facts are unavailable for ${entry.file.name} (${errorMessage(error)}). Native PHP extraction will continue.`);
        }
      }
      if (imageMode === 'none' || remainingRasterBytes <= 0) {
        continue;
      }
      reportProgress(`Checking PDF images in ${entry.file.name} (${index + 1} of ${upload.entries.length})…`);
      const rasters = await browserPdfRasterImages(bytes, imageMode, remainingRasterBytes, reportProgress);
      for (const raster of rasters) {
        if (!(raster.bytes instanceof Uint8Array) || raster.bytes.length > remainingRasterBytes) {
          continue;
        }
        remainingRasterBytes -= raster.bytes.length;
        pdfRasterImages.push({
          path: entry.path,
          object: String(raster.object),
          bytes: raster.bytes,
          mimeType: String(raster.mimeType || 'image/png'),
          width: Number(raster.width) || 0,
          height: Number(raster.height) || 0,
        });
      }
    }
    if (remainingRasterBytes <= 0) {
      reportProgress('The browser PDF image safety limit was reached. Remaining PDF images will keep their original-file placeholders.');
    }

    const rasterDescriptors = [];
    for (const [index, raster] of pdfRasterImages.entries()) {
      const field = `plpc_raster_${index}`;
      rasterDescriptors.push({
        path: raster.path,
        object: raster.object,
        mimeType: raster.mimeType,
        width: raster.width,
        height: raster.height,
        field,
      });
      body.append(field, new Blob([raster.bytes], { type: raster.mimeType }), `pdf-raster-${raster.object}.png`);
    }
    body.append('metadata', JSON.stringify({
      filename: upload.kind === 'single' ? upload.entries[0].file.name : upload.displayName,
      title: titleFromFilename(upload.kind === 'single' ? upload.entries[0].file.name : upload.displayName),
      imageMode,
      pdfMode,
      pdfOutputMode,
      entries: upload.entries.map(({ path, file }) => ({ path, filename: file.name })),
      pdfBrowserFacts,
      pdfRasterImages: rasterDescriptors,
    }));
    upload.entries.forEach(({ file }, index) => {
      body.append(`plpc_file_${index}`, file, file.name);
    });

    return body;
  }

  function checkedValue(name, fallback) {
    return root.querySelector(`input[name="${name}"]:checked`)?.value || fallback;
  }

  function uploadFromFiles(files) {
    const entries = files.filter((file) => file && file.size > 0).map((file) => ({
      file,
      path: normalizePath(file.webkitRelativePath || file.name),
    })).filter(({ path }) => path);
    if (entries.length === 0) {
      return null;
    }
    const collection = entries.length > 1 || entries.some(({ path }) => path.includes('/'));
    return {
      kind: collection ? 'collection' : 'single',
      entries,
      displayName: collection ? `${entries.length} files` : entries[0].file.name,
      totalSize: entries.reduce((total, { file }) => total + file.size, 0),
    };
  }

  function showSavedImportAction(snapshot = null) {
    const saved = jobSession.load();
    if (!saved || active) return;
    result.hidden = false;
    result.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = saved.cancellationRequested
      ? 'An unfinished cancellation can continue'
      : 'An unfinished import can continue';
    const explanation = document.createElement('p');
    const completed = Math.max(0, Number(snapshot?.metrics?.pdfPagesExtracted) || 0);
    const total = Math.max(0, Number(snapshot?.metrics?.pdfPagesTotal) || 0);
    explanation.textContent = saved.cancellationRequested
      ? 'Cancellation remains saved. Continue without running another conversion or media mutation.'
      : (total > 0
        ? `${completed} of ${total} PDF pages are safely checkpointed in WordPress. Resume without uploading or re-reading the document.`
        : 'WordPress has durable checkpoints for this import. Resume without uploading the document again.');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary';
    button.textContent = saved.cancellationRequested ? 'Finish cancelling import' : 'Resume saved import';
    button.addEventListener('click', async () => {
      if (active) return;
      active = true;
      cancellationRequested = saved.cancellationRequested === true;
      activeJobId = saved.jobId;
      button.disabled = true;
      result.hidden = true;
      progress.hidden = false;
      startStatusPolling();
      try {
        let resumed = await request(`imports/${encodeURIComponent(saved.jobId)}`, { method: 'GET' });
        setProgress(resumed);
        if (resumed.status === 'awaiting_output_mode' && !cancellationRequested) {
          showOutputModeRecovery(resumed, new Map());
          return;
        }
        if (resumed.status === 'cancelled') {
          jobSession.forget(resumed.jobId);
          showCancelled(resumed);
          return;
        }
        resumed = await driveImportJob(resumed, new Map());
        if (resumed.status === 'failed') {
          throw new Error(resumed.message || 'Import failed.');
        }
        if (resumed.status === 'awaiting_output_mode') {
          showOutputModeRecovery(resumed, new Map());
          return;
        }
        if (resumed.status === 'cancelled') {
          jobSession.forget(resumed.jobId);
          showCancelled(resumed);
          return;
        }
        jobSession.forget(resumed.jobId);
        showResult(resumed.result || {});
      } catch (error) {
        showError(errorMessage(error));
      } finally {
        active = false;
        activeJobId = '';
        button.disabled = false;
        if (statusTimer !== null) {
          window.clearInterval(statusTimer);
          statusTimer = null;
        }
        updateSelection();
        if (lastSnapshot?.status !== 'awaiting_output_mode' && jobSession.load()) {
          showSavedImportAction(lastSnapshot);
        }
      }
    });
    result.append(heading, explanation, button);
  }

  async function restoreSavedImport() {
    const saved = jobSession.load();
    if (!saved) return;
    try {
      const snapshot = await request(`imports/${encodeURIComponent(saved.jobId)}`, { method: 'GET' });
      setProgress(snapshot);
      if (snapshot.status === 'complete' && snapshot.result) {
        jobSession.forget(snapshot.jobId);
        showResult(snapshot.result);
        return;
      }
      if (snapshot.status === 'failed') {
        jobSession.forget(snapshot.jobId);
        showError(snapshot.message || 'The saved import failed.');
        return;
      }
      if (snapshot.status === 'cancelled') {
        jobSession.forget(snapshot.jobId);
        showCancelled(snapshot);
        return;
      }
      if (snapshot.status === 'awaiting_output_mode' && !saved.cancellationRequested) {
        showOutputModeRecovery(snapshot, new Map());
        return;
      }
      showSavedImportAction(snapshot);
    } catch (error) {
      // Keep the pointer after a transient network failure. A definite 404 is
      // the only safe indication that WordPress no longer has this job.
      if (/404|not found|does not exist|unknown import/i.test(errorMessage(error))) {
        jobSession.forget(saved.jobId);
        return;
      }
      showError(`Could not inspect the saved import yet: ${errorMessage(error)}`);
    }
  }

  void restoreSavedImport();
}

/**
 * Decode the narrow direct-JPX/JBIG2 PDF-image fallback supported by the
 * importer. Both decoders are optional: a missing WASM/module must leave the
 * PDF import usable, with the server retaining its normal original-media
 * placeholder instead.
 */
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

async function browserPdfRasterImages(bytes, imageMode, maxPngBytes, reportProgress = () => {}) {
  const decoderEntries = [
    {
      label: 'JBIG2',
      filterName: '/JBIG2Decode',
      load: async () => {
        if (!decodePdfJbig2Rasters) {
          const module = await import(new URL('./pdf-jbig2-rasterizer.mjs', import.meta.url).href);
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
          const module = await import(new URL('./pdf-jpx-rasterizer.mjs', import.meta.url).href);
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
  let remainingBytes = Math.max(0, Math.min(pdfRasterPayloadByteLimit, Number(maxPngBytes) || 0));
  for (const [index, result] of loaded.entries()) {
    if (result.status !== 'fulfilled') {
      reportProgress(`Browser ${decoderEntries[index]?.label || 'PDF'} image decoding is unavailable. The import will continue with original-file placeholders where needed.`);
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
            ? `Preparing ${entry.label} PDF images (${completed} of ${total})…`
            : `Preparing ${entry.label} PDF images…`);
        },
      });
      for (const raster of decoded.rasters || []) {
        const sourceObject = String(raster.object ?? '');
        const object = /^\d+$/.test(sourceObject) ? String(Number(sourceObject)) : '';
        if (!object || !/^\d+$/.test(object) || objects.has(object) || !(raster.bytes instanceof Uint8Array) || raster.bytes.length > remainingBytes
          || raster.mimeType !== 'image/png' || !Number.isInteger(Number(raster.width)) || !Number.isInteger(Number(raster.height))
          || Number(raster.width) <= 0 || Number(raster.height) <= 0) {
          continue;
        }
        objects.add(object);
        rasters.push(raster);
        remainingBytes -= raster.bytes.length;
      }
    } catch (error) {
      reportProgress(`Browser ${entry.label} image decoding is unavailable (${errorMessage(error)}). The import will continue with original-file placeholders where needed.`);
    }
  }

  return rasters;
}

function isPdf(file) {
  return file?.type === 'application/pdf' || String(file?.name || '').toLowerCase().endsWith('.pdf');
}

function normalizePath(path) {
  const result = [];
  for (const part of String(path || '').replaceAll('\\', '/').split('/')) {
    if (!part || part === '.') continue;
    if (part === '..') result.pop(); else result.push(part);
  }
  return result.join('/');
}

function renderedImageFormData(item) {
  const body = new FormData();
  body.append('requestId', String(item.requestId || ''));
  body.append('mimeType', String(item.mimeType || 'image/png'));
  body.append('width', String(item.width || 0));
  body.append('height', String(item.height || 0));
  body.append('plpc_rendered', new Blob([item.bytes], { type: item.mimeType || 'image/png' }), 'pdf-visual.png');

  return body;
}

function createAdminImportJobSession(importerConfig) {
  const endpoint = String(importerConfig?.restRoot || '/wp-json/port-libs/v1/');
  const storageKey = `port-libs.admin-active-import.v1:${endpoint}`;
  const maxAgeMs = 7 * 24 * 60 * 60 * 1_000;
  const storage = (() => {
    try { return window.localStorage; } catch { return null; }
  })();

  const load = () => {
    let record;
    try { record = JSON.parse(storage?.getItem(storageKey) || 'null'); } catch { return null; }
    const jobId = String(record?.jobId || '');
    const updatedAt = Number(record?.updatedAt || 0);
    if (!/^[A-Za-z0-9_-]{12,128}$/.test(jobId)
      || !Number.isFinite(updatedAt)
      || updatedAt <= 0
      || Date.now() - updatedAt > maxAgeMs
    ) {
      try { storage?.removeItem(storageKey); } catch { /* Storage is optional. */ }
      return null;
    }
    return {
      jobId,
      status: String(record?.status || ''),
      cancellationRequested: record?.cancellationRequested === true,
      updatedAt,
    };
  };

  const forget = (expectedJobId = '') => {
    const current = load();
    if (expectedJobId && current && current.jobId !== String(expectedJobId)) return;
    try { storage?.removeItem(storageKey); } catch { /* Storage is optional. */ }
  };

  const remember = (snapshot) => {
    const jobId = String(snapshot?.jobId || '');
    const status = String(snapshot?.status || '');
    if (!/^[A-Za-z0-9_-]{12,128}$/.test(jobId)) return;
    if (['complete', 'failed', 'cancelled'].includes(status)) {
      forget(jobId);
      return;
    }
    const current = load();
    try {
      storage?.setItem(storageKey, JSON.stringify({
        jobId,
        status,
        cancellationRequested: current?.jobId === jobId && current.cancellationRequested === true,
        updatedAt: Date.now(),
      }));
    } catch {
      // The live import remains usable even when the browser blocks storage.
    }
  };

  const requestCancellation = (expectedJobId) => {
    const current = load();
    if (!current || current.jobId !== String(expectedJobId || '')) return null;
    const record = { ...current, cancellationRequested: true, updatedAt: Date.now() };
    try { storage?.setItem(storageKey, JSON.stringify(record)); } catch { /* The live cancellation can continue. */ }
    return record;
  };

  return { load, remember, forget, requestCancellation };
}

function bytesFromBase64(encoded) {
  const binary = atob(String(encoded || ''));
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }
  return bytes;
}

function titleFromFilename(filename) {
  const stem = String(filename || 'Converted document').replace(/^.*\//, '').replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
  return stem || 'Converted document';
}

function formatBytes(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatElapsed(milliseconds) {
  const seconds = Math.floor(milliseconds / 1000);
  return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function pause(milliseconds) {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function errorMessage(error) {
  return error instanceof Error ? error.message : String(error);
}

function cancellationErrorIsRetryable(error) {
  const status = Number(error?.status || 0);
  return !Number.isFinite(status)
    || status <= 0
    || [408, 409, 423, 425, 429].includes(status)
    || status >= 500;
}
