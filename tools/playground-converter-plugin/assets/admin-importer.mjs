import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';
import { collectPdfJsFacts } from './pdfjs-facts-provider.mjs';

// This matches the server-side per-import cap. Keep the decoded image bytes
// in multipart fields, not JSON/base64, so a regular WordPress upload does
// not temporarily duplicate a large PDF and its raster fallbacks in memory.
const pdfRasterPayloadByteLimit = 24_000_000;
const pdfRasterSourceByteLimit = 24 * 1024 * 1024;
const pdfFormRenderTotalPixelLimit = 48_000_000;
const pdfFormRenderTotalImageByteLimit = 24_000_000;
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
  const events = root.querySelector('[data-plpc-events]');
  const result = root.querySelector('[data-plpc-result]');
  let selected = null;
  let active = false;
  let elapsedTimer = null;
  let statusTimer = null;
  let activeJobId = '';
  let lastSnapshot = null;

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
    if (!selected || active) {
      return;
    }
    try {
      await runImport();
    } catch (error) {
      showError(errorMessage(error));
    }
  });

  function updateSelection({ clearResult = false } = {}) {
    // Keep a completed import's Edit/View links visible after the finally
    // block re-enables this form. A new file selection is what invalidates a
    // previous result, not merely toggling the active state.
    if (clearResult) {
      result.hidden = true;
      result.replaceChildren();
    }
    submit.disabled = !selected || active;
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
      if (snapshot.status === 'awaiting_output_mode') {
        showOutputModeRecovery(snapshot, pdfFiles);
        return;
      }
      if (snapshot.status === 'failed') {
        throw new Error(snapshot.message || 'Import failed.');
      }
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
    }
  }

  async function driveImportJob(initialSnapshot, pdfFiles) {
    let snapshot = initialSnapshot;
    while (!['complete', 'failed', 'awaiting_output_mode'].includes(String(snapshot.status || ''))) {
        if (Array.isArray(snapshot.renderRequests) && snapshot.renderRequests.length > 0) {
          await ensurePdfRenderSources(snapshot, pdfFiles);
          appendEvent('renderer', `Rendering ${snapshot.renderRequests.length} PDF figure${snapshot.renderRequests.length === 1 ? '' : 's'} locally in this browser.`);
          const rendered = await renderPdfFormRequests({
            filesByPath: pdfFiles,
            requests: snapshot.renderRequests,
            pdfjs: config,
            maxTotalPixels: pdfFormRenderTotalPixelLimit,
            maxTotalImageBytes: pdfFormRenderTotalImageByteLimit,
            onProgress: ({ completed, total, label }) => {
              setProgress({
                ...snapshot,
                progress: { completed, total: Math.max(total, 1), label },
              }, { replaceEvents: false });
            },
          });
          for (const item of rendered) {
            const body = item.error
              ? { requestId: item.requestId, error: item.error }
              : renderedImageFormData(item);
            snapshot = await request(`imports/${encodeURIComponent(snapshot.jobId)}/rendered-media`, { method: 'POST', body });
            setProgress(snapshot);
          }
          continue;
        }
        snapshot = await advanceImportJob(snapshot);
        setProgress(snapshot);
      }

    return snapshot;
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
        setProgress(await request(`imports/${encodeURIComponent(jobId)}`, { method: 'GET' }));
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
      try {
        return await request(`imports/${encodeURIComponent(jobId)}/advance`, { method: 'POST', body: {} });
      } catch (error) {
        lastError = error;
        if (recoveryAttempt >= maxAdvanceRecoveryAttempts) {
          break;
        }
        appendEvent(
          'recovery',
          `The server request ended unexpectedly. Checking the saved import state (${recoveryAttempt + 1} of ${maxAdvanceRecoveryAttempts})…`,
        );
        await pause(400 * (recoveryAttempt + 1));
        try {
          const recovered = await request(`imports/${encodeURIComponent(jobId)}`, { method: 'GET' });
          setProgress(recovered);
          // A completed checkpoint, browser-render request, or a fresh
          // ready-to-convert state is safe for the outer state machine to
          // handle. Only "converting" needs another bounded advance retry.
          if (String(recovered.status || '') !== 'converting') {
            return recovered;
          }
        } catch (statusError) {
          lastError = statusError;
        }
      }
    }
    throw new Error(`${errorMessage(lastError)} The importer stopped retrying automatically to avoid duplicating work. Refresh to inspect the saved import state, then start a new import if WordPress marked it failed.`);
  }

  async function ensurePdfRenderSources(snapshot, pdfFiles) {
    for (const renderRequest of snapshot.renderRequests || []) {
      const path = String(renderRequest?.path || '');
      if (!path || pdfFiles.has(path)) {
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
        pdfFiles.set(sourcePath, sourceBytes);
      } catch (error) {
        // Keep the request outstanding: the shared renderer turns this into a
        // per-figure error, which lets PHP continue with its regular source
        // attachment/placeholder rather than abandoning the whole import.
        appendEvent('renderer', `One PDF figure source could not be loaded in this browser (${errorMessage(error)}). The text import will continue.`);
      }
    }
  }

  function setProgress(snapshot, { replaceEvents = true } = {}) {
    lastSnapshot = snapshot;
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
    progressDetail.textContent = `${details.join('. ')}.`;
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
      activeJobId = String(snapshot.jobId || '');
      button.disabled = true;
      result.hidden = true;
      try {
        let resumed = await request(`imports/${encodeURIComponent(activeJobId)}/output-mode`, {
          method: 'POST',
          body: { pdfOutputMode: 'pages' },
        });
        setProgress(resumed);
        startStatusPolling();
        resumed = await driveImportJob(resumed, pdfFiles);
        if (resumed.status === 'failed') {
          throw new Error(resumed.message || 'Import failed.');
        }
        if (resumed.status === 'awaiting_output_mode') {
          showOutputModeRecovery(resumed, pdfFiles);
          return;
        }
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
      }
    });
    result.append(heading, explanation, button);
  }

  function showError(message) {
    progress.hidden = false;
    progressLabel.textContent = 'Import stopped';
    progressDetail.textContent = message;
    appendEvent('failed', message);
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
    if (!response.ok || !data || data.ok === false) {
      throw new Error(data?.message || `Import request failed (${response.status}).`);
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
        reportProgress(`Skipping optional PDF.js facts and image decoding for ${entry.file.name}; it is over the 24 MiB browser safety limit. Native PHP extraction will continue.`);
        continue;
      }
      reportProgress(`Reading PDF.js text and structure facts from ${entry.file.name} (${index + 1} of ${upload.entries.length})…`);
      const bytes = new Uint8Array(await entry.file.arrayBuffer());
      try {
        const facts = await collectPdfJsFacts({
          source: bytes,
          pdfjs: config,
          onProgress({ label }) { reportProgress(label); },
        });
        if (facts.pages.length > 0) {
          pdfBrowserFacts[entry.path] = facts;
        }
      } catch (error) {
        reportProgress(`Optional PDF.js text and structure facts are unavailable for ${entry.file.name} (${errorMessage(error)}). Native PHP extraction will continue.`);
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
}

/**
 * Decode the narrow direct-JPX/JBIG2 PDF-image fallback supported by the
 * importer. Both decoders are optional: a missing WASM/module must leave the
 * PDF import usable, with the server retaining its normal original-media
 * placeholder instead.
 */
async function browserPdfRasterImages(bytes, imageMode, maxPngBytes, reportProgress = () => {}) {
  const decoderEntries = [
    {
      label: 'JBIG2',
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
      load: async () => {
        if (!decodePdfJpxRasters) {
          const module = await import(new URL('./pdf-jpx-rasterizer.mjs', import.meta.url).href);
          decodePdfJpxRasters = module.decodePdfJpxRasters;
        }
        return decodePdfJpxRasters;
      },
    },
  ];
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
  body.append('plpc_rendered', new Blob([item.bytes], { type: item.mimeType || 'image/png' }), 'pdf-figure.png');

  return body;
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
