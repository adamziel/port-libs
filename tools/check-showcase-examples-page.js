#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { createHash } = require('crypto');

const root = path.resolve(__dirname, '..');
const site = path.join(root, 'pandoc-showcase');
const html = fs.readFileSync(path.join(site, 'examples.html'), 'utf8');
const css = fs.readFileSync(path.join(site, 'examples.css'), 'utf8');
const js = fs.readFileSync(path.join(site, 'examples.js'), 'utf8');
const pdfFormRasterizer = fs.readFileSync(path.join(site, 'pdfjs-form-rasterizer.mjs'), 'utf8');
const fullShowcase = fs.readFileSync(path.join(site, 'index.html'), 'utf8');
const importE2e = fs.readFileSync(path.join(root, 'tools/e2e-playground-import.mjs'), 'utf8');
const showcaseBuilder = fs.readFileSync(path.join(root, 'tools/build-pandoc-showcase.php'), 'utf8');
const prerenderPublisher = fs.readFileSync(path.join(root, 'tools/prerender-showcase-pdf-assets.mjs'), 'utf8');
const playgroundWorkflow = fs.readFileSync(path.join(root, '.github/workflows/playground-converter.yml'), 'utf8');
const indexPath = path.join(site, 'examples-index.json');
const manifestPath = path.join(site, 'manifest.json');
const indexBytes = fs.statSync(indexPath).size;
const manifestBytes = fs.statSync(manifestPath).size;
const index = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const layoutManifest = JSON.parse(fs.readFileSync(path.join(root, 'tools/pdf-layout-corpus-manifest.json'), 'utf8'));
const layoutEntriesByExampleId = new Map(layoutManifest.map((entry) => [`pdf-layout-${entry.id}`, entry]));

function assert(condition, message) {
  if (!condition) {
    console.error(message);
    process.exitCode = 1;
  }
}

const pdfPreviewRendererSchemaMatch = pdfFormRasterizer.match(
  /export const PDF_STATIC_PREVIEW_RENDERER_SCHEMA = (['"])([^'"]+)\1;/,
);
const pdfPreviewRendererSchema = pdfPreviewRendererSchemaMatch?.[2] || '';
assert(pdfPreviewRendererSchema !== '',
  '[PDF prerender schema] The production rasterizer must export an explicit static-preview renderer schema.');
assert(prerenderPublisher.includes('const PDF_PRERENDER_BATCH_SIZE = 4;')
  && prerenderPublisher.includes('renderUnits.slice(batchStart, batchStart + PDF_PRERENDER_BATCH_SIZE)'),
  '[PDF prerender resources] Chrome publication must render in bounded four-asset target batches.');
assert(prerenderPublisher.includes("browser.call('Target.closeTarget', { targetId: activeTargetId })")
  && prerenderPublisher.includes('await activePage?.close().catch(() => {})')
  && prerenderPublisher.includes('cleanupUncommittedPlanAssets(plan)'),
  '[PDF prerender resources] Every batch target and every failed plan must release task-owned resources.');
assert(prerenderPublisher.includes('for (const plan of stale)')
  && prerenderPublisher.includes('finalizePlan(options.site, plan);'),
  '[PDF prerender resources] Each stale plan must finalize before the next plan can double disk usage.');

function executableNamedFunction(source, name) {
  const start = source.indexOf(`function ${name}(`);
  if (start < 0) throw new Error(`Could not find ${name} for its executable regression.`);
  const openingBrace = source.indexOf('{', start);
  let depth = 0;
  for (let index = openingBrace; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] !== '}') continue;
    depth -= 1;
    if (depth === 0) {
      return Function(`"use strict"; return (${source.slice(start, index + 1)});`)();
    }
  }
  throw new Error(`Could not read the complete ${name} function.`);
}

function namedFunctionSource(source, name) {
  const functionStart = source.indexOf(`function ${name}(`);
  if (functionStart < 0) throw new Error(`Could not find ${name} for its source regression.`);
  const start = source.slice(Math.max(0, functionStart - 6), functionStart) === 'async '
    ? functionStart - 6
    : functionStart;
  const openingBrace = source.indexOf('{', functionStart);
  let depth = 0;
  for (let index = openingBrace; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] !== '}') continue;
    depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }
  throw new Error(`Could not read the complete ${name} function source.`);
}

function siteFile(relativePath) {
  const file = path.resolve(site, relativePath);
  assert(file === site || file.startsWith(site + path.sep), 'Catalogue path escapes the showcase directory: ' + relativePath);
  return file;
}

assert((html.match(/<iframe\b/g) || []).length === 1, 'The lightweight page must contain exactly one reusable iframe.');
assert(/<iframe\b[^>]*\bid="example-frame"/.test(html), 'Expected the reusable example iframe.');
assert(!/<iframe\b[^>]*\bsrc=/.test(html), 'The lightweight page must not eagerly load an example iframe.');
assert(!html.includes('outputs/'), 'The lightweight page must not embed generated output paths.');
assert(html.includes('<main class="example-browser">'), 'Expected the standalone example browser.');
assert(html.includes('Adam&#039;s Pandoc → PHP Port'), 'Expected the minimal browser title.');
assert(html.includes('<link rel="icon" href="data:,">'), 'The standalone browser must not emit a failing implicit favicon request.');
assert(!html.includes('<header'), 'The browser should not render a separate top section.');
assert((html.match(/<select\b/g) || []).length === 1, 'Expected one example selector.');
assert(!html.includes('format-filter'), 'The browser must not render a format selector.');
assert(/<a\b[^>]*\bid="download-source"[^>]*\bdownload\b[^>]*>Download original<\/a>/.test(html), 'Expected a Download original button.');
assert(/<button\b[^>]*\bid="try-own-file"[^>]*>Try your own file<\/button>/.test(html), 'Expected a Try your own file button.');
assert(/<button\b[^>]*\bid="cancel-own-file"[^>]*hidden[^>]*disabled[^>]*>Cancel import<\/button>/.test(html), 'Expected an explicit own-file cancellation control.');
assert(/<input\b[^>]*\bid="own-file-input"[^>]*\btype="file"[^>]*\bhidden\b/.test(html), 'Expected the hidden own-file picker.');
assert(/<dialog\b[^>]*\bid="own-pdf-output-dialog"/.test(html), 'Expected a PDF-only publication-shape dialog for own files.');
assert(html.includes('name="own-pdf-output-mode" value="single" checked'), 'Expected one WordPress page to be the own-PDF default.');
assert(html.includes('name="own-pdf-output-mode" value="pages"'), 'Expected one child page per physical PDF page as the alternate choice.');
assert(!/<input\b[^>]*\bid="own-file-input"[^>]*\bmultiple\b/.test(html), 'Try your own file should open one selected file at a time.');
assert(/<div class="example-toolbar"><button[^>]*\bid="previous-example"[\s\S]*?<h1[^>]*class="example-title"[\s\S]*?<select[^>]*\bid="example-picker"[\s\S]*?<a[^>]*\bid="download-source"[\s\S]*?<button[^>]*\bid="try-own-file"[\s\S]*?<button[^>]*\bid="next-example"/.test(html), 'Expected the toolbar order to be previous, title, picker, download, try-own-file, next.');
assert(/<p[^>]*\bid="viewer-status"[^>]*\brole="status"[^>]*\baria-live="polite"[^>]*\bhidden/.test(html), 'Expected an initially hidden, accessible own-file status message.');
for (const removedControl of ['Upstream source', 'Open result', 'Open full comparison', 'Full showcase (desktop)', 'catalog-summary', 'current-example-title']) {
  assert(!html.includes(removedControl), 'The reduced browser must not include ' + removedControl + '.');
}
assert(/<button[^>]*\bid="previous-example"[^>]*\baria-label="Previous example"[^>]*>[\s\S]*?←/.test(html), 'Expected an accessible previous-example arrow.');
assert(/<button[^>]*\bid="next-example"[^>]*\baria-label="Next example"[^>]*>[\s\S]*?→/.test(html), 'Expected an accessible next-example arrow.');
assert(html.includes('<span class="arrow-label">Previous example</span>'), 'Expected a visible previous-example label.');
assert(html.includes('<span class="arrow-label">Next example</span>'), 'Expected a visible next-example label.');
for (const tab of ['HTML', 'WordPress Block markup', 'Pandoc baseline']) {
  assert(html.includes('>' + tab + '</button>'), 'Expected the ' + tab + ' preview tab.');
}
assert(/data-example-view="phpHtml" aria-pressed="false"/.test(html), 'Expected HTML to start inactive.');
assert(/data-example-view="wpBlocks" aria-pressed="true"/.test(html), 'Expected WordPress Block markup to be the default tab.');
assert(fullShowcase.includes('href="examples.html"'), 'The full showcase should link to the lightweight browser.');
assert(css.includes('min-height: 100dvh'), 'Expected the browser to fill the screen.');
assert(css.includes('grid-template-columns: var(--arrow-width) minmax(0, 1fr) auto auto var(--arrow-width);'), 'Expected a wide-arrow picker toolbar with the own-file action.');
assert(css.includes('grid-template-areas:\n    "previous title . . next"\n    "previous picker download own next";'), 'Expected desktop controls to share an explicit picker row.');
assert(css.includes('grid-template-rows: auto 48px;'), 'Expected desktop controls to align to a fixed 48px row.');
assert(css.includes('align-self: stretch;'), 'Expected desktop arrows to fill the height of the picker bar.');
assert(!css.includes('position: absolute; top: 10px; bottom: 8px;'), 'Mobile arrows must stay in the grid instead of clipping outside it.');
assert(css.includes('grid-template-areas:\n      "previous title next"\n      "previous picker next"\n      "previous download next"\n      "previous own next";'), 'Expected mobile arrows to occupy fixed left and right grid columns.');
assert(!css.includes('grid-row: 2 / 5'), 'Arrows must not span the preview area.');
assert(css.includes('font-size: clamp(15px, 1.8vw, 20px)'), 'Expected a compact picker title.');
assert(css.includes('border-radius: 8px 8px 0 0'), 'Expected view controls to look like tabs.');
assert(css.includes('box-shadow: 0 1px 0 var(--paper)'), 'Expected the selected tab to join the preview panel.');
assert(/#example-picker\s*\{[\s\S]*?height: 48px;/.test(css), 'Expected the picker to have a fixed 48px control height.');
assert(/\.download-source,\s*\.try-own-file\s*\{[\s\S]*?height: 48px;[\s\S]*?border: 1px solid #aeb9c7;[\s\S]*?background: #fff;[\s\S]*?color: var\(--ink\);/.test(css), 'Expected Download original and Try your own file to be neutral controls matching the picker height.');
assert(css.includes('.try-own-file {\n  grid-area: own;'), 'Expected Try your own file beside Download original on desktop.');
assert(css.includes('.try-own-file[hidden] {\n  display: none;\n}'), 'Hidden own-file controls must not overlap the visible Try your own file button.');
assert(css.includes('.download-source,\n  .try-own-file { min-width: 0; width: 100%;'), 'Expected Try your own file to remain usable in the narrow mobile toolbar.');
assert(css.includes('.viewer-status[data-tone="error"]'), 'Expected own-file errors to be visibly styled.');
assert(js.includes("const catalogUrl = 'examples-index.json';"), 'Expected the compact example catalogue.');
assert(js.includes("const defaultView = 'wpBlocks';"), 'Expected WordPress Block markup to be the initial view.');
assert(js.includes('view: defaultView'), 'Expected the state to initialize to the default WordPress view.');
assert(js.includes("const exampleUrlParameter = 'example';"), 'Expected a stable query parameter for linked examples.');
assert(js.includes('new URL(window.location.href)'), 'Expected example links to preserve other URL state safely.');
assert(js.includes('window.history.replaceState'), 'Expected picker navigation to keep the current URL shareable.');
assert(js.includes('renderPdfFormRequestsIncrementally,') && js.includes("from './pdfjs-form-rasterizer.mjs';"), 'Expected own-file PDF figures to use the incremental shared PDF.js renderer.');
assert(js.includes('PDF_STATIC_PREVIEW_RENDERER_SCHEMA,')
  && js.includes('manifest?.prerenderRendererSchema !== PDF_STATIC_PREVIEW_RENDERER_SCHEMA'),
'Published PDF previews must reject plans produced by a different renderer schema.');
assert(showcaseBuilder.includes('renderPdfPageRasterRequests,')
  && showcaseBuilder.includes('renderPdfPageRasterRequestsIncrementally,'), 'Expected the generated showcase client to import batch and incremental whole-page renderers alongside the Form APIs.');
assert(js.includes('startPlaygroundWithSnapshotRecovery,')
  && js.includes("from './import-job-session.mjs?v=playground-snapshot-recovery-20260721';"), 'Expected examples.php to load the cache-busted Playground snapshot recovery helper.');
assert(js.includes("const playgroundPluginBuild = 'verified-pdf-prerender-20260720';"), 'Expected the own-file importer to use the current Playground plugin build.');
assert(js.includes('const playgroundPdfFormTotalPixelLimit = 48_000_000;'), 'Expected own-file PDF figure rendering to have a total pixel budget.');
assert(js.includes('const playgroundPdfFormTotalImageByteLimit = 24_000_000;'), 'Expected own-file PDF figure rendering to match the server media budget.');
assert(showcaseBuilder.includes('const playgroundPdfPageTotalPixelLimit = 128_000_000;')
  && showcaseBuilder.includes('const playgroundPdfPageTotalImageByteLimit = 64 * 1024 * 1024;'), 'Expected generated own-file page rendering to have a separate cumulative 128M-pixel/64MiB budget.');
assert(showcaseBuilder.includes('remainingPixels: playgroundPdfFormTotalPixelLimit')
  && showcaseBuilder.includes('remainingImageBytes: playgroundPdfFormTotalImageByteLimit')
  && showcaseBuilder.includes('remainingPixels: playgroundPdfPageTotalPixelLimit')
  && showcaseBuilder.includes('remainingImageBytes: playgroundPdfPageTotalImageByteLimit')
  && showcaseBuilder.includes('const budget = pageRaster ? pageBudget : formBudget;')
  && showcaseBuilder.includes('maxTotalPixels: budget.remainingPixels')
  && showcaseBuilder.includes('maxTotalImageBytes: budget.remainingImageBytes'), 'Expected the generated own-file driver to retain independent cumulative Form and page budgets across source groups and status pages.');
assert(showcaseBuilder.includes('const groupKey = `${method}\\u001f${sourceKey}`;')
  && showcaseBuilder.includes('for (const group of pdfRenderRequestGroups(job.renderRequests))'), 'Expected generated own-file requests to be grouped by source and render method.');
assert(showcaseBuilder.includes('? renderPdfPageRasterRequestsIncrementally')
  && showcaseBuilder.includes(': renderPdfFormRequestsIncrementally;')
  && showcaseBuilder.includes('requests: requests.map(pdfPageRasterRequestForRenderer)')
  && showcaseBuilder.includes('bytes: item.contents,'), 'Expected generated own-file page requests to use the exact page API and then reuse the existing immutable media acknowledgement payload.');
assert(js.includes('window.__portLibsImportE2E'), 'Expected release E2E to inspect the real WordPress publication rows.');
assert(js.includes('rawDataProvenanceCount'), 'Expected release E2E to reject embedded data-URI provenance.');
assert(js.includes('Import complete. Converted pages were verified privately and published.'), 'Expected the browser to report verified publication before declaring a large import complete.');
assert(importE2e.includes("browser.call('Target.closeTarget', { targetId })"), 'Every E2E import must close its Playground target instead of leaking a browser VM.');
assert(importE2e.includes("'--max-browser-memory-mb'") && importE2e.includes("'--max-browser-rss-mb'"), 'Expected large-import E2E to expose the accurate browser-memory ceiling while retaining the old CLI alias.');
assert(importE2e.includes('maxBrowserMemoryMb: 1536'), 'Expected the whole-browser proportional-memory safety ceiling to be enabled by default.');
assert(importE2e.includes('Could not measure Chrome memory while the safety ceiling is enabled'), 'Expected an unavailable browser-memory sample to fail closed.');
assert(importE2e.includes('sampleBrowserMemory();') && importE2e.includes('Initial Chrome footprint'), 'Expected browser memory to be sampled before import and again before accepting success.');
assert(importE2e.includes('Chrome exceeded the ${options.maxBrowserMemoryMb} MiB browser-memory safety ceiling'), 'Expected the E2E memory ceiling to fail closed with a useful diagnostic.');
assert(importE2e.includes("['activeLoadingTasks', 'activeDocuments', 'activePages', 'activeCanvases', 'activeRenderTasks']"), 'Expected release E2E to reject live PDF.js resources after completion.');
assert(!importE2e.includes('Chrome did not release enough post-import memory'), 'Post-completion OS memory samples must remain telemetry rather than assuming that Chrome immediately decommits freed allocator arenas.');
assert(importE2e.includes('maxElapsedMs: 5 * 60 * 1000')
  && importE2e.includes('importDeadlineMs'), 'Expected dense release imports to have a default five-minute elapsed ceiling, not only an optional after-the-fact check.');
assert(importE2e.includes("['console errors', observations.consoleErrors]")
  && importE2e.includes("['network failures', observations.networkFailures]")
  && importE2e.includes("['browser log errors', observations.browserLogErrors]")
  && importE2e.includes('assertNoUnexpectedObservations(observations);'), 'Expected unexpected console, network, and browser-log observations to fail the release run.');
assert(importE2e.includes('postCompletionMemorySamples')
  && importE2e.includes('postCompletionSettleElapsedMs'), 'Expected release evidence to retain timestamped samples from the five-second post-completion settle window.');
assert(js.includes("const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';"), 'Expected Try your own file to use the Playground client.');
assert(js.includes("const playgroundUploadDirectory = '/tmp/port-libs-converter';"), 'Expected own files to use Playground temporary staging.');
assert(js.includes("php: '8.4'"), 'Expected own-file imports to use PHP 8.4 for EPUB and HTML documents.');
assert(js.includes("const tryOwnFileButton = document.getElementById('try-own-file');"), 'Expected Try your own file controls to be wired.');
assert(js.includes("const ownFileInput = document.getElementById('own-file-input');"), 'Expected the hidden own-file picker to be wired.');
assert(js.includes('ownFileInput.click();'), 'Expected the Try your own file button to open its file picker.');
assert(/ownFileInput\.addEventListener\('change',[\s\S]*?isLikelyPdfFile\(file\) \? await chooseOwnPdfOutputMode\(\) : 'single'[\s\S]*?void openOwnFile\(file, outputMode\);/.test(js), 'Selecting a file should infer PDF, ask only for its publication shape, and open it immediately.');
assert(js.includes('async function bootOwnFilePlayground()'), 'Expected a reusable Playground boot path for own-file imports.');
assert(/async function bootOwnFilePlayground\(\)[\s\S]*?if \(state\.playgroundReady\)/.test(js), 'Expected a loaded Playground to be reused for another file.');
assert(js.includes("let job = await ownFilePluginRequest(playgroundClient, '/imports', {"), 'Expected own files to create persisted import jobs.');
assert(js.includes("pdfOutputMode: pdfOutputMode === 'pages' ? 'pages' : 'single'"), 'Expected the compact importer to send its PDF publication choice.');
assert(js.includes("job.status === 'awaiting_output_mode'"), 'Expected the compact importer to surface the safe single-page limit.');
assert(js.includes('`/imports/${encodeURIComponent(job.jobId)}/output-mode`'), 'Expected compact recovery to reuse the same persisted import job.');
assert(js.includes('`/imports/${jobId}/advance`'), 'Expected own files to advance their persisted import jobs.');
assert(js.includes('async function cancelOwnFileImport(playgroundClient, job,') && js.includes('`/imports/${jobId}/cancel`'), 'Expected own-file imports to cancel at a durable server boundary.');
assert(js.includes('state.ownFileCancelRequested') && js.includes("'cancelled'"), 'Expected a cancellation request to remain pending until the current bounded request returns.');
assert(js.includes('cancelImportMutationDurably,')
  && js.includes('return cancelImportMutationDurably({')
  && js.includes('isActive: () => ownFileRequestIsCurrent(token) && state.ownFileCancelRequested'), 'Expected own-file cancellation to poll and retry the durable endpoint through a worker-lock collision.');
assert(js.includes('isActive: () => ownFileRequestIsCurrent(token) && !state.ownFileCancelRequested')
  && js.includes('shouldCancel: () => state.ownFileCancelRequested')
  && js.includes('cancel: () => cancelOwnFileImport(playgroundClient, job, reportJob, token)'), 'Expected cancellation during uncertain /advance recovery to cancel instead of replaying the mutation.');
assert(js.includes('if (state.ownFileCancelRequested) {\n      return cancelOwnFileImport(playgroundClient, recovered, reportJob, token);'), 'Expected an uncertain rendered-media response to cancel instead of replaying the upload.');
assert(js.includes('ownFileImportSession.requestCancellation(state.lastOwnFileJob.jobId);')
  && js.includes('state.ownFileCancelRequested = saved.cancellationRequested === true;')
  && js.includes('Finish cancelling import'), 'Expected cancellation intent to survive reload and resume only the cancellation.');
for (const durableCancellationSnippet of [
  'cancelImportMutationDurably,',
  'shouldCancel: () => state.ownFileCancelRequested',
  'ownFileImportSession.requestCancellation(state.lastOwnFileJob.jobId);',
]) {
  assert(showcaseBuilder.includes(durableCancellationSnippet), 'The canonical showcase builder must retain: ' + durableCancellationSnippet);
}
assert(js.includes('`/imports/${jobId}/rendered-media`'), 'Expected user-selected own-file PDF figures to be returned to WordPress one at a time.');
assert(js.includes('async function advanceOwnFileImport'), 'Expected an explicit bounded import advance flow.');
assert(js.includes('startOwnFileImportStatusPolling'), 'Expected in-flight advances to poll persisted WordPress progress.');
assert(js.includes('const ownFileAdvanceRecoveryAttempts = 3;'), 'Expected bounded recovery after a Playground PHP worker ends unexpectedly.');
assert(js.includes('The completed page checkpoints remain saved in this Playground'), 'Expected an interrupted large import to explain that completed PDF work remains durable.');
assert(js.includes('The import completed and the WordPress page was saved, but Playground could not display it'), 'A failed result navigation must not be reported as a lost conversion.');
assert(js.includes('`/imports/${jobId}`') && js.includes("'GET'"), 'Expected the own-file importer to read persisted import status while WordPress works.');
assert(js.includes('ownFileImportLatestNewEvent') && js.includes('reportedEventKeys'), 'Expected status events to be deduplicated by event identity rather than array position.');
assert(showcaseBuilder.includes('for await (const renderedItem of renderer(renderOptions))'), 'Expected user-selected own-file PDF figures/page images to be rendered and acknowledged incrementally.');
assert(js.includes('const sourceKey = String(request?.sourceKey || path);'), 'Expected source grouping to use the server digest instead of a truncated display path.');
assert(js.includes("storageKey: 'port-libs.playground-active-import.v1'"), 'Expected GitHub Pages to persist its active WordPress job pointer.');
assert(js.includes('async function resumeSavedOwnFileImport()'), 'Expected Try your own file to resume a saved import after interruption.');
assert(js.includes('state.playgroundClient = await startPlaygroundWithSnapshotRecovery({')
  && js.includes('persistence: ownFilePlaygroundPersistence,')
  && js.includes('options: startOptions,'), 'Expected the embedded WordPress filesystem to restore with bounded SQLite snapshot recovery.');
assert(js.includes('The saved Playground database could not be reopened. Starting a fresh private WordPress site; the previous browser snapshot is preserved.'), 'Expected invalid saved SQLite sites to explain the non-destructive fresh-site recovery.');
assert(!js.includes('ownFilePlaygroundPersistence.forget()'), 'A transient Playground boot failure must not discard or overwrite durable OPFS checkpoints.');
assert(js.includes('recoverImportMutation({'), 'Expected an uncertain /advance response to be reconciled with durable status before replay.');
assert(js.includes("['failed', 'retryable_failure'].includes(String(data.status || ''))"), 'Expected durable error snapshots to reach the own-file state machine instead of being mistaken for transport failures.');
assert(js.includes('example.pdfFormRenders'), 'Expected the compact catalogue to expose published PDF visual metadata.');
assert(!js.includes('staticPdfPreviewMaxRequests = 8')
  && !js.includes('This static preview renders at most ')
  && !js.includes("figure.classList.add('pandoc-pdf-form-placeholder')")
  && !js.includes('could not be rendered in this browser'),
'Published PDF visuals must not retain the visitor-side request cap or any runtime placeholder/warning path.');
assert(js.includes('`/imports/${jobId}/render-source/${requestId}`'), 'Expected ZIP-expanded PDF sources to be available to the browser renderer.');
assert(js.includes('await playgroundClient.goTo(playgroundPath(data.pageUrl));'), 'Expected each own file conversion to open its newly created WordPress page.');
assert(js.includes("frame.removeAttribute('sandbox');"), 'Expected Playground to use the preview iframe without the static-preview sandbox.');
assert(js.includes("frame.setAttribute('sandbox', '');"), 'Expected static previews to restore the iframe sandbox after leaving Playground.');
assert(js.includes('const reusingPlayground = state.frameMode === \'playground\''), 'Expected repeated own-file imports to preserve their loaded Playground iframe.');
assert(js.includes('const busy = state.ownFileBusy;'), 'Expected browser controls to be disabled while an own-file import is active.');
assert(js.includes("imageMode: 'important'"), 'Expected own-file imports to preserve the Playground image default.');
assert(js.includes("pdfMode: 'layout'"), 'Expected own-file imports to preserve the Playground PDF default.');
assert(js.includes('pdf-jpx-rasterizer.mjs'), 'Expected own-file PDF imports to load the JPEG 2000 rasterizer.');
assert(js.includes('decodePdfJpxRasters'), 'Expected own-file PDF imports to prepare JPEG 2000 images.');
assert(js.includes('Promise.allSettled'), 'A JPEG 2000 decoder failure must not discard usable JBIG2 rasters.');
assert(js.includes('const playgroundPdfRasterByteLimit = 24_000_000;'), 'Expected own-file rasters to honor the Playground decoded-byte limit.');
assert(js.includes('maxPngBytes: remainingBytes'), 'Expected own-file PDF decoders to share one raster byte budget.');
assert(js.includes('new Uint8Array(await file.arrayBuffer())'), 'Expected all own files to be read as transferable bytes.');
assert(js.includes('await playgroundClient.mkdirTree(playgroundUploadDirectory);'), 'Expected own files to create their Playground staging directory.');
assert(js.includes('await playgroundClient.writeFile(stagedPath, bytes);'), 'Expected own files to stage bytes before the REST request.');
assert(js.includes('...prepared.payload,\n      stagedPath,'), 'Expected import-job creation to use a compact staged-file payload.');
assert(js.includes('await playgroundClient.unlink(stagedPath);'), 'Expected failed or cancelled own-file uploads to be cleaned up.');
assert(!js.includes('readFileAsBase64'), 'Own-file imports must not use the fragile FileReader base64 path.');
assert(!js.includes('readAsDataURL'), 'Own-file imports must not build a data URL before conversion.');
assert(js.includes("visible: true, tone: 'error'"), 'Expected own-file failures to be visible instead of screen-reader-only.');
assert(!/\bformat\s*:/.test(js), 'Own-file imports must not send a user-controlled document format.');
assert(!js.includes('format-input'), 'Own-file imports must not ask the visitor for a document type.');
assert(!js.includes('manifest.json'), 'The lightweight page must not fetch the full manifest.');
assert(!js.includes('formatFilter'), 'The browser must not retain format-filter logic.');
assert(!js.includes('updateExampleDetails'), 'The browser must not retain metadata-panel logic.');
assert(js.includes("frame.removeAttribute('src');"), 'Expected prior iframe documents to be unloaded.');
assert(js.includes("frame.src = 'about:blank';"), 'Expected the iframe to clear before each requested result.');
assert(js.includes('frame.src = view.path;'), 'Expected the viewer to load only the selected result.');
assert(js.includes('function browsableExamples()'), 'Expected the one-at-a-time browser to retain its mobile safety limit.');
assert(js.includes('automaticViewMaxBytes'), 'Expected navigation to respect the mobile size limit.');
const typedPdfStatusPreview = executableNamedFunction(js, 'isTypedPdfStatusPreview');
assert(typedPdfStatusPreview({
  ok: false,
  path: 'outputs/fixture/wordpress-blocks-preview.html',
  status: 'unsupported_no_text',
}, 'wpBlocks'), 'Expected an explicit no-text WordPress status page to remain reviewable.');
assert(typedPdfStatusPreview({
  ok: false,
  path: 'outputs/fixture/wordpress-blocks-preview.html',
  status: 'incomplete',
}, 'wpBlocks'), 'Expected an explicit incomplete WordPress status page to remain reviewable.');
assert(!typedPdfStatusPreview({
  ok: false,
  path: 'outputs/fixture/wordpress-blocks.html.error.txt',
  status: 'unsupported_no_text',
}, 'wpBlocks'), 'A failed-conversion diagnostic must not become a browsable status page.');
assert(!typedPdfStatusPreview({
  ok: false,
  path: 'outputs/fixture/wordpress-blocks-preview.html',
  status: 'unexpected_failure',
}, 'wpBlocks'), 'An unrecognized failure status must remain unavailable.');
assert(!typedPdfStatusPreview({
  ok: false,
  path: 'outputs/fixture/wordpress-blocks-preview.html',
  status: 'unsupported_no_text',
}, 'phpHtml'), 'A typed status page must remain restricted to the WordPress preview view.');
assert(/function browsableExamples\(\)[\s\S]*?isBrowsableView\(example\.views && example\.views\.wpBlocks, 'wpBlocks'\)/.test(js), 'Expected typed WordPress status previews to enter the public picker.');
assert(/examplePicker\.addEventListener\('change',[\s\S]*?applySelectedExample\(examplePicker\.value\);[\s\S]*?syncExampleUrl\(\);/.test(js), 'Changing the example should update its shareable URL.');
assert(/function moveExample[\s\S]*?applySelectedExample\(examples\[nextIndex\]\.id\);[\s\S]*?syncExampleUrl\(\);/.test(js), 'Arrow navigation should update the shareable URL.');
assert(/button\.addEventListener\('click',[\s\S]*?state\.view = nextView;[\s\S]*?loadSelectedExample\(\);/.test(js), 'Changing the rendered view should load it immediately.');
assert(js.includes('downloadSource.href = example.samplePath'), 'Expected Download original to follow the selected example.');

const records = Array.isArray(manifest.records) ? manifest.records : [];
const recordsById = new Map(records.map((record) => [record.id, record]));
assert(Array.isArray(index.examples), 'Expected examples-index.json to provide examples.');
assert(index.examples.length === records.length, 'Compact catalogue must cover every showcase record.');
assert(indexBytes < manifestBytes / 8, 'Compact catalogue should remain much smaller than the full manifest.');
assert(Number.isFinite(index.automaticViewMaxBytes) && index.automaticViewMaxBytes > 0, 'Expected a positive automatic view size limit.');
assert(index.defaultExampleId && recordsById.has(index.defaultExampleId), 'Expected a default example present in the full manifest.');

let automaticPhpExamples = 0;
for (const example of index.examples || []) {
  const record = recordsById.get(example.id);
  assert(record, 'Catalogue example ' + example.id + ' is missing from the full manifest.');
  if (!record) {
    continue;
  }
  assert(!Object.hasOwn(example, 'preview'), example.id + ' must not duplicate a source preview into the compact catalogue.');
  for (const field of ['format', 'label', 'description', 'source', 'sourceUrl', 'samplePath', 'sampleSize', 'views']) {
    assert(Object.hasOwn(example, field), example.id + ' is missing ' + field + '.');
  }
  assert(example.format === record.format, example.id + ' format diverged from the full manifest.');
  assert(example.label === record.label, example.id + ' label diverged from the full manifest.');
  assert(fs.existsSync(siteFile(example.samplePath)), example.id + ' source file is missing.');

  for (const viewName of ['phpHtml', 'wpBlocks', 'haskell']) {
    const view = example.views && example.views[viewName];
    const fullView = record[viewName] || {};
    assert(view && typeof view === 'object', example.id + ' is missing ' + viewName + ' metadata.');
    if (!view || typeof view !== 'object') {
      continue;
    }
    const rawPath = fullView.path || '';
    const conversionExpectation = layoutEntriesByExampleId.get(example.id)?.conversionExpectation?.wpBlocks;
    const integrity = fullView.sourceIntegrity || {};
    const typedIncomplete = viewName === 'wpBlocks'
      && fullView.ok !== true
      && integrity.pdfTextLayerStatus === 'incomplete'
      && integrity.pdfNeedsOcr === false
      && integrity.pdfTextComplete === false
      && integrity.pdfNoTextClassificationComplete === false
      && integrity.pdfDocumentComplete === false
      && integrity.pdfPageRepresentationComplete === false
      && integrity.pdfPageCount > 0
      && Array.isArray(integrity.pdfRepresentedPageNumbers)
      && integrity.pdfRepresentedPageNumbers.length === 0
      && conversionExpectation?.ok === false
      && conversionExpectation?.status === 'incomplete';
    const typedUnsupportedNoText = viewName === 'wpBlocks'
      && fullView.ok !== true
      && integrity.pdfTextLayerStatus === 'unsupported_no_text'
      && integrity.pdfNeedsOcr === true
      && integrity.pdfTextComplete === true
      && integrity.pdfNoTextClassificationComplete === true
      && integrity.pdfDocumentComplete === true
      && integrity.pdfSemanticTextComplete === true
      && integrity.pdfPageRepresentationComplete === false
      && integrity.pdfPageCount > 0
      && Array.isArray(integrity.pdfPagesNeedingImageRepresentation)
      && integrity.pdfPagesNeedingImageRepresentation.join(',') === Array.from(
        { length: integrity.pdfPageCount },
        (_, index) => index + 1,
      ).join(',')
      && Array.isArray(integrity.pdfRepresentedPageNumbers)
      && integrity.pdfRepresentedPageNumbers.length === 0
      && conversionExpectation?.ok === false
      && conversionExpectation?.status === 'unsupported_no_text';
    const typedPdfFailure = typedIncomplete || typedUnsupportedNoText;
    const expectedPath = viewName === 'wpBlocks' && (fullView.ok === true || typedPdfFailure)
      ? rawPath.replace(/[^/]+$/, 'wordpress-blocks-preview.html')
      : rawPath;
    assert(view.path === expectedPath, example.id + ' ' + viewName + ' path diverged from the full manifest.');
    assert(view.ok === (fullView.ok === true), example.id + ' ' + viewName + ' status diverged from the full manifest.');
    if (typedPdfFailure) {
      const expectedStatus = typedIncomplete ? 'incomplete' : 'unsupported_no_text';
      assert(view.status === expectedStatus, example.id + ' typed failed preview status is missing.');
      const statusPath = siteFile(view.path);
      assert(fs.existsSync(statusPath), example.id + ' typed failed status preview is missing.');
      if (fs.existsSync(statusPath)) {
        const statusPreview = fs.readFileSync(statusPath, 'utf8');
        assert(statusPreview.includes(`data-pandoc-pdf-preview-status="${expectedStatus}"`), example.id + ' typed failed preview lacks its status marker.');
        assert(statusPreview.includes('No editable WordPress import was generated.'), example.id + ' typed incomplete preview must not imply conversion success.');
        if (typedIncomplete) {
          assert(statusPreview.includes('PDF extraction incomplete'), example.id + ' typed incomplete preview must identify extraction failure.');
          assert(!/OCR required|image-only/i.test(statusPreview), example.id + ' typed incomplete preview must not claim an OCR or image-only classification.');
        } else {
          assert(statusPreview.includes('PDF has no editable native text'), example.id + ' typed no-text preview must identify the native-text boundary.');
          assert(statusPreview.includes('OCR is outside this importer'), example.id + ' typed no-text preview must keep OCR explicitly out of scope.');
          assert(!statusPreview.includes('PDF extraction incomplete'), example.id + ' typed no-text preview must not claim unresolved extraction.');
        }
      }
    }
    if (view.ok) {
      const outputPath = siteFile(view.path);
      assert(fs.existsSync(outputPath), example.id + ' ' + viewName + ' output file is missing.');
      if (fs.existsSync(outputPath)) {
        assert(view.bytes === fs.statSync(outputPath).size, example.id + ' ' + viewName + ' byte count is stale.');
      }

      if (viewName === 'wpBlocks') {
        const rawOutputPath = siteFile(rawPath);
        assert(fs.existsSync(rawOutputPath), example.id + ' raw WordPress block output is missing.');
        if (fs.existsSync(rawOutputPath) && fs.existsSync(outputPath)) {
          const rawOutput = fs.readFileSync(rawOutputPath, 'utf8');
          const previewOutput = fs.readFileSync(outputPath, 'utf8');
          assert(!/^\s*<!doctype html/i.test(rawOutput), example.id + ' canonical WordPress output must remain a raw fragment.');
          assert(/^\s*<!doctype html/i.test(previewOutput), example.id + ' WordPress preview must be a standalone document.');
          assert(previewOutput.includes('Content-Security-Policy'), example.id + ' WordPress preview must isolate raw block HTML.');
          assert(previewOutput.includes('.wp-block-table'), example.id + ' WordPress preview must style core block markup.');
          assert(previewOutput.endsWith(rawOutput + '</body></html>'), example.id + ' WordPress preview must preserve the raw block fragment.');
        }
      }
    }
  }

  const php = example.views && example.views.phpHtml;
  if (php && php.ok && php.bytes > 0 && php.bytes <= index.automaticViewMaxBytes) {
    automaticPhpExamples += 1;
  }
}

const mineruRecord = recordsById.get('pdf-layout-mineru-small-ocr');
const mineruExpectation = layoutEntriesByExampleId.get('pdf-layout-mineru-small-ocr')?.conversionExpectation;
assert(mineruExpectation?.phpHtml?.ok === false
  && mineruExpectation?.phpHtml?.status === 'unsupported_no_text'
  && mineruExpectation?.wpBlocks?.ok === false
  && mineruExpectation?.wpBlocks?.status === 'unsupported_no_text', 'MinerU must declare the exact failed-conversion expectation.');
if (mineruRecord) {
  for (const viewName of ['phpHtml', 'wpBlocks']) {
    const view = mineruRecord[viewName] || {};
    assert(view.ok === false, `MinerU ${viewName} conversion must remain failed.`);
    assert(String(view.error || '').startsWith('unsupported_no_text:'), `MinerU ${viewName} failure must expose its typed no-text status.`);
    assert(view.sourceIntegrity?.pdfTextLayerStatus === 'unsupported_no_text', `MinerU ${viewName} source integrity must retain the exact no-text classification.`);
    assert(view.sourceIntegrity?.pdfNeedsOcr === true, `MinerU ${viewName} must retain the explicit OCR boundary.`);
    assert(view.sourceIntegrity?.pdfTextComplete === true, `MinerU ${viewName} must retain complete text extraction.`);
    assert(view.sourceIntegrity?.pdfNoTextClassificationComplete === true, `MinerU ${viewName} must retain its completed no-text classification.`);
    assert(view.sourceIntegrity?.pdfDocumentComplete === true, `MinerU ${viewName} must retain complete document coverage.`);
    assert(view.sourceIntegrity?.pdfSemanticTextComplete === true, `MinerU ${viewName} must retain complete semantic source disposition.`);
    assert(view.sourceIntegrity?.pdfPageCount === 8, `MinerU ${viewName} must retain its eight detected pages.`);
    assert(view.sourceIntegrity?.pdfPagesNeedingImageRepresentation?.join(',') === '1,2,3,4,5,6,7,8',
      `MinerU ${viewName} converter integrity must retain all eight pages needing a non-editable visual representation.`);
    assert(Array.isArray(view.sourceIntegrity?.pdfRepresentedPageNumbers)
      && view.sourceIntegrity.pdfRepresentedPageNumbers.length === 0,
    `MinerU ${viewName} converter output must not misreport publication-only assets as editable representations.`);
    assert(view.sourceIntegrity?.pdfPageRepresentationComplete === false,
      `MinerU ${viewName} converter integrity must remain distinct from the publication prerender coverage.`);
  }
  assert(mineruRecord.pdfFormRenders?.ok === true && mineruRecord.pdfFormRenders?.count === 8,
    'MinerU must retain eight publication raster request identities backed by static assets.');
  const mineruPreviewPath = index.examples.find((example) => example.id === 'pdf-layout-mineru-small-ocr')
    ?.views?.wpBlocks?.path;
  if (mineruPreviewPath && fs.existsSync(siteFile(mineruPreviewPath))) {
    const mineruPreview = fs.readFileSync(siteFile(mineruPreviewPath), 'utf8');
    assert(!/no static original-page representation was produced|browser-assisted path can render/i.test(mineruPreview),
      'MinerU status copy must acknowledge the eight publication-prerendered pages instead of promising a visitor-side renderer.');
  }
}

assert(automaticPhpExamples >= 80, 'Expected a broad set of small PHP examples for automatic mobile browsing.');

const traceMonkey = index.examples.find((example) => example.id === 'pdf-tracemonkey');
const traceMonkeyRecord = recordsById.get('pdf-tracemonkey');
assert(traceMonkey && traceMonkeyRecord, 'Expected the bundled TraceMonkey PDF example.');
if (traceMonkey && traceMonkeyRecord) {
  const renderPlan = traceMonkey.pdfFormRenders;
  const recordPlan = traceMonkeyRecord.pdfFormRenders;
  for (const viewName of ['phpHtml', 'wpBlocks']) {
    const sourceIntegrity = traceMonkeyRecord[viewName]?.sourceIntegrity || {};
    assert(Array.isArray(sourceIntegrity.pdfPagesNeedingImageRepresentation)
      && sourceIntegrity.pdfPagesNeedingImageRepresentation.length === 0,
    `TraceMonkey ${viewName} must not require whole-page image representation.`);
    assert(sourceIntegrity.pdfRepresentedPageNumbers?.join(',') === '1,2,3,4,5,6,7,8,9,10,11,12,13,14',
      `TraceMonkey ${viewName} must retain text representation for all fourteen source pages.`);
    assert(sourceIntegrity.pdfPageRepresentationComplete === true,
      `TraceMonkey ${viewName} must retain complete page representation without whole-page rasters.`);
  }
  assert(renderPlan && renderPlan.ok === true, 'TraceMonkey must expose its published PDF figure plan to the compact catalogue.');
  assert(recordPlan && recordPlan.ok === true, 'TraceMonkey must retain its published PDF figure plan in the full manifest.');
  if (renderPlan && recordPlan) {
    assert(renderPlan.path === recordPlan.path, 'TraceMonkey compact/full render-plan paths diverged.');
    assert(renderPlan.count === 8 && recordPlan.count === 8, 'TraceMonkey must retain all eight vector/Form chart placements.');
    const renderPlanPath = siteFile(renderPlan.path);
    assert(fs.existsSync(renderPlanPath), 'TraceMonkey PDF figure render plan is missing.');
    if (fs.existsSync(renderPlanPath)) {
      const payload = JSON.parse(fs.readFileSync(renderPlanPath, 'utf8'));
      const requests = Array.isArray(payload.requests) ? payload.requests : [];
      const pageRequests = requests.filter((request) => request.method === 'pdfjs-whole-page-raster');
      const formRequests = requests.filter((request) => request.method !== 'pdfjs-whole-page-raster');
      assert(payload.samplePath === traceMonkey.samplePath, 'TraceMonkey PDF figure render plan must point at the bundled PDF.');
      assert(requests.length === 8, 'TraceMonkey PDF render plan must contain eight crop requests.');
      assert(pageRequests.length === 0 && formRequests.length === 8,
        'TraceMonkey must retain eight Form requests without redundant whole-page rasters.');
      assert(formRequests.every((request) => request.path === traceMonkey.samplePath
        && /^form-[a-f0-9]{28}$/.test(String(request.id || ''))
        && Number.isInteger(request.page)
        && request.bbox && request.bbox.x2 > request.bbox.x1 && request.bbox.y2 > request.bbox.y1),
      'Every TraceMonkey PDF chart request must be bounded and address the bundled PDF.');
      assert(formRequests.map((request) => request.object).join(',') === '41,118,119,120,154,199,198,200',
        'TraceMonkey chart plan must retain the eight expected Form XObjects.');
      const captionsByObject = new Map([
        [41, 'Figure 2.'], [118, 'Figure 5.'], [119, 'Figure 6.'], [120, 'Figure 7.'],
        [154, 'Figure 8.'], [199, 'Figure 11.'], [198, 'Figure 10.'], [200, 'Figure 12.'],
      ]);
      assert(formRequests.every((request) => String(request.followingText || '').replace(/(?:-|\u00ad)$/u, '')
        .startsWith(captionsByObject.get(request.object) || '')),
      'Every TraceMonkey chart request must preserve its own following figure caption as a placement anchor.');
    }
  }
}

// Red-first publication contract: GitHub Pages must never ask a visitor's
// browser to open a source PDF and manufacture these images. Keep the request
// inventory as provenance, but publish deterministic image assets plus an
// exact request-to-asset/placement map alongside it.
const pdfPrerenderRequestCounts = new Map([
  ['pdf-layout-unstructured-ocr-overlay', 1],
  ['pdf-layout-docling-right-to-left', 1],
  ['pdf-layout-docling-aircraft-handbook', 9],
  ['pdf-layout-docling-table-picture-boundary', 2],
  ['pdf-layout-mineru-small-ocr', 8],
  ['pdf-layout-vdl-theatre-script', 1],
  ['pdf-tracemonkey', 8],
  ['pdf-grand-canyon-north-rim-map', 16],
  ['pdf-archive-motograph-book', 46],
  ['pdf-muir-beach-brochure', 6],
  ['pdf-quickbooks-invoice-template', 1],
]);
const indexedPdfRenderPlans = index.examples.filter((example) => example.pdfFormRenders?.ok === true);
assert(indexedPdfRenderPlans.length === pdfPrerenderRequestCounts.size,
  `[PDF prerender inventory] Expected exactly ${pdfPrerenderRequestCounts.size} PDF visual plans, not ${indexedPdfRenderPlans.length}.`);
assert(indexedPdfRenderPlans.reduce((total, example) => total + Number(example.pdfFormRenders?.count || 0), 0) === 99,
  '[PDF prerender inventory] The eleven audited plans must retain all 99 visual request identities as provenance.');

function pdfAssetSha256(file) {
  return createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

function pdfPrerenderSourceDigest(payload, sourceSha256) {
  return createHash('sha256').update(JSON.stringify({
    version: 1,
    rendererSchema: pdfPreviewRendererSchema,
    samplePath: payload.samplePath,
    sourceSha256,
    requests: payload.requests,
  })).digest('hex');
}

function requestAnchorText(request, direction) {
  const value = direction === 'before'
    ? (request?.followingText || request?.anchorAfter)
    : (request?.precedingText || request?.anchorBefore);
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function requestBBox(request) {
  const bbox = request?.bbox;
  return bbox && [bbox.x1, bbox.y1, bbox.x2, bbox.y2].every(Number.isFinite) ? bbox : null;
}

function bboxContains(outer, inner, tolerance = 0.01) {
  return outer && inner
    && outer.x1 <= inner.x1 + tolerance
    && outer.y1 <= inner.y1 + tolerance
    && outer.x2 + tolerance >= inner.x2
    && outer.y2 + tolerance >= inner.y2;
}

const pdfPrerenderPlans = new Map();
for (const [exampleId, expectedRequestCount] of pdfPrerenderRequestCounts) {
  const example = index.examples.find((candidate) => candidate.id === exampleId);
  const record = recordsById.get(exampleId);
  assert(example?.pdfFormRenders?.ok === true, `[PDF prerender: ${exampleId}] Compact catalogue plan is missing.`);
  assert(record?.pdfFormRenders?.ok === true, `[PDF prerender: ${exampleId}] Full manifest plan is missing.`);
  if (!example?.pdfFormRenders?.path) continue;

  const planPath = siteFile(example.pdfFormRenders.path);
  assert(fs.existsSync(planPath), `[PDF prerender: ${exampleId}] Render-plan JSON is missing.`);
  if (!fs.existsSync(planPath)) continue;
  const planBytes = fs.statSync(planPath).size;
  const payload = JSON.parse(fs.readFileSync(planPath, 'utf8'));
  const requests = Array.isArray(payload.requests) ? payload.requests : [];
  const assets = Array.isArray(payload.prerenderedAssets) ? payload.prerenderedAssets : [];
  const coverage = Array.isArray(payload.prerenderedRequestCoverage)
    ? payload.prerenderedRequestCoverage
    : [];
  pdfPrerenderPlans.set(exampleId, { example, record, payload, requests, assets, coverage });

  assert(requests.length === expectedRequestCount,
    `[PDF prerender: ${exampleId}] Expected ${expectedRequestCount} request identities, found ${requests.length}.`);
  assert(example.pdfFormRenders.count === expectedRequestCount
    && record.pdfFormRenders.count === expectedRequestCount,
  `[PDF prerender: ${exampleId}] Compact/full manifest request counts must both be ${expectedRequestCount}.`);
  assert(example.pdfFormRenders.path === record.pdfFormRenders.path,
    `[PDF prerender: ${exampleId}] Compact/full manifests must reference the same render plan.`);
  assert(example.pdfFormRenders.bytes === planBytes
    && record.pdfFormRenders.bytes === planBytes,
  `[PDF prerender: ${exampleId}] Compact/full manifest byte counts must match the ${planBytes}-byte render plan.`);
  const samplePath = String(payload.samplePath || '');
  const sampleFile = samplePath ? siteFile(samplePath) : '';
  assert(Boolean(sampleFile) && fs.existsSync(sampleFile),
    `[PDF prerender: ${exampleId}] Source sample is missing: ${samplePath || '(missing path)'}.`);
  if (sampleFile && fs.existsSync(sampleFile)) {
    const sourceSha256 = pdfAssetSha256(sampleFile);
    const expectedDigest = pdfPrerenderSourceDigest(payload, sourceSha256);
    assert(payload.prerenderVersion === 1
      && payload.prerenderRendererSchema === pdfPreviewRendererSchema,
    `[PDF prerender: ${exampleId}] Plan renderer schema is stale or missing.`);
    assert(payload.prerenderedSourceDigest === expectedDigest,
      `[PDF prerender: ${exampleId}] Plan is stale for its source PDF, requests, or renderer schema.`);
    assert(requests.every((request) => !request?.sourceSha256 || request.sourceSha256 === sourceSha256),
      `[PDF prerender: ${exampleId}] A render request carries a stale source SHA-256.`);
  }
  assert(assets.length > 0,
    `[PDF prerender: ${exampleId}] Missing prerenderedAssets; Pages publication must render deterministic files before deployment.`);
  assert(coverage.length === requests.length,
    `[PDF prerender: ${exampleId}] Expected exact asset coverage for all ${requests.length} requests, found ${coverage.length}.`);

  const requestIds = new Set(requests.map((request) => String(request?.id || '')));
  const coverageCounts = new Map();
  for (const item of coverage) {
    const requestId = String(item?.requestId || '');
    coverageCounts.set(requestId, (coverageCounts.get(requestId) || 0) + 1);
  }
  assert(requestIds.size === requests.length && !requestIds.has(''),
    `[PDF prerender: ${exampleId}] Render request IDs must be non-empty and unique.`);
  assert(requests.every((request) => coverageCounts.get(String(request.id)) === 1)
    && coverage.every((item) => requestIds.has(String(item?.requestId || ''))),
  `[PDF prerender: ${exampleId}] Every request must map to exactly one published asset, with no unknown coverage rows.`);

  const assetsByPath = new Map();
  for (const asset of assets) {
    const relativePath = String(asset?.path || '');
    const assetFile = relativePath ? siteFile(relativePath) : '';
    assert(relativePath.startsWith(`outputs/${exampleId}/`)
      && !/^(?:[a-z]+:|\/\/|\/|data:)/i.test(relativePath),
    `[PDF prerender: ${exampleId}] Asset paths must be deterministic same-origin files below the example output directory: ${relativePath || '(missing path)'}.`);
    assert(!assetsByPath.has(relativePath),
      `[PDF prerender: ${exampleId}] Prerender asset metadata contains duplicate path ${relativePath || '(missing path)'}.`);
    assetsByPath.set(relativePath, asset);
    assert(/^image\/(?:png|jpeg|webp|avif)$/.test(String(asset?.mimeType || '')),
      `[PDF prerender: ${exampleId}] ${relativePath || 'Asset'} must declare a browser image MIME type.`);
    assert(Number.isInteger(asset?.byteLength) && asset.byteLength > 0
      && Number.isInteger(asset?.width) && asset.width > 0
      && Number.isInteger(asset?.height) && asset.height > 0
      && /^[a-f0-9]{64}$/.test(String(asset?.sha256 || '')),
    `[PDF prerender: ${exampleId}] ${relativePath || 'Asset'} needs byteLength, dimensions, and SHA-256 publication metadata.`);
    assert(Boolean(assetFile) && fs.existsSync(assetFile),
      `[PDF prerender: ${exampleId}] Published prerender file is missing: ${relativePath || '(missing path)'}.`);
    if (assetFile && fs.existsSync(assetFile)) {
      assert(fs.statSync(assetFile).size === asset.byteLength,
        `[PDF prerender: ${exampleId}] ${relativePath} byteLength metadata is stale.`);
      assert(pdfAssetSha256(assetFile) === asset.sha256,
        `[PDF prerender: ${exampleId}] ${relativePath} SHA-256 metadata is stale.`);
    }
  }

  const requestById = new Map(requests.map((request) => [String(request.id), request]));
  for (const item of coverage) {
    const request = requestById.get(String(item?.requestId || ''));
    const assetPath = String(item?.assetPath || '');
    const placement = String(item?.placement || '');
    assert(assetsByPath.has(assetPath),
      `[PDF prerender: ${exampleId}] ${item?.requestId || 'Unknown request'} references unpublished asset ${assetPath || '(missing path)'}.`);
    assert(['before-anchor', 'after-anchor', 'page-gallery', 'existing-page-image'].includes(placement),
      `[PDF prerender: ${exampleId}] ${item?.requestId || 'Unknown request'} needs an explicit anchor, page-gallery, or existing-page-image placement.`);
    if (placement === 'before-anchor') {
      assert(requestAnchorText(request, 'before').length >= 3,
        `[PDF prerender: ${exampleId}] ${item.requestId} declares before-anchor without a usable following-text anchor.`);
    } else if (placement === 'after-anchor') {
      assert(requestAnchorText(request, 'after').length >= 3,
        `[PDF prerender: ${exampleId}] ${item.requestId} declares after-anchor without a usable preceding-text anchor.`);
    } else {
      assert(Number.isInteger(request?.page) && request.page > 0,
        `[PDF prerender: ${exampleId}] ${item?.requestId || 'Unknown request'} page placement must retain its source page.`);
    }
  }
}

assert(playgroundWorkflow.includes('git status --porcelain=v1 --untracked-files=all --'),
  '[PDF prerender CI] Generated-output cleanliness must include untracked files.');
for (const requiredGeneratedPath of [
  'pandoc-showcase/manifest.json',
  ':(glob)pandoc-showcase/outputs/*/pdf-form-renders.json',
  ':(glob)pandoc-showcase/outputs/*/pdf-preview-*.png',
]) {
  assert(playgroundWorkflow.includes(requiredGeneratedPath),
    `[PDF prerender CI] Generated-output cleanliness is missing ${requiredGeneratedPath}.`);
}

const staticPdfPreviewBuilderSource = showcaseBuilder.includes('function buildStaticPdfFormPreview(')
  ? namedFunctionSource(showcaseBuilder, 'buildStaticPdfFormPreview')
  : '';
assert(!staticPdfPreviewBuilderSource
  || (!staticPdfPreviewBuilderSource.includes('fetchStaticPdfSource(')
    && !staticPdfPreviewBuilderSource.includes('renderPdfFormRequests(')
    && !staticPdfPreviewBuilderSource.includes('renderPdfPageRasterRequests(')),
'[PDF prerender runtime] Static showcase previews must consume published image assets without fetching source PDFs or invoking PDF.js in a visitor browser.');

const aircraftPlan = pdfPrerenderPlans.get('pdf-layout-docling-aircraft-handbook');
if (aircraftPlan) {
  const coverageByRequest = new Map(aircraftPlan.coverage.map((item) => [String(item.requestId), item]));
  for (const request of aircraftPlan.requests) {
    const hasTextAnchor = requestAnchorText(request, 'before').length >= 3
      || requestAnchorText(request, 'after').length >= 3;
    if (!hasTextAnchor) {
      assert(coverageByRequest.get(String(request.id))?.placement === 'page-gallery',
        `[Aircraft prerender] Unanchored request ${request.id} must have explicit page-gallery placement instead of drifting to the document tail.`);
    }
  }
}

const motographPlan = pdfPrerenderPlans.get('pdf-archive-motograph-book');
if (motographPlan) {
  const expectedGalleryPages = Array.from({ length: 46 }, (_, index) => index + 2);
  const coverageByRequest = new Map(motographPlan.coverage.map((item) => [String(item.requestId), item]));
  assert(motographPlan.requests.map((request) => request.page).join(',') === expectedGalleryPages.join(','),
    '[Motograph prerender] The 46 requests intentionally cover physical pages 2 through 47; page 1 already has an extracted image.');
  const galleryCoverage = motographPlan.requests.map((request) => coverageByRequest.get(String(request.id)));
  assert(galleryCoverage.every((item) => item?.placement === 'page-gallery'),
    '[Motograph prerender] All 46 unanchored page images must use explicit page-gallery placement.');
  assert(new Set(galleryCoverage.map((item) => item?.assetPath).filter(Boolean)).size === 46,
    '[Motograph prerender] Each missing physical page needs its own published asset; optimize the gallery with lazy loading, not by collapsing pages.');
}

// Grand Canyon pages 1 and 2 each have a covering composite. Contained Form
// requests remain useful provenance, but they must reuse the covering asset
// rather than publishing redundant crops or falling through to placeholders.
const grandCanyonPlan = pdfPrerenderPlans.get('pdf-grand-canyon-north-rim-map');
if (grandCanyonPlan) {
  const coverageByRequest = new Map(grandCanyonPlan.coverage.map((item) => [String(item.requestId), item]));
  const wholePageRequests = grandCanyonPlan.requests.filter((request) => request.method === 'pdfjs-whole-page-raster');
  assert(wholePageRequests.map((request) => request.page).join(',') === '1,2',
    '[Grand Canyon prerender] Physical pages 1 and 2 must retain explicit whole-page publication requests.');
  const coveringAssets = [];
  for (const page of [1, 2]) {
    const pageRequests = grandCanyonPlan.requests.filter((request) => request.page === page && requestBBox(request));
    const coveringRequest = pageRequests.find((candidate) => pageRequests.every((request) => (
      bboxContains(requestBBox(candidate), requestBBox(request), 15)
    )));
    assert(Boolean(coveringRequest), `[Grand Canyon prerender] Page ${page} needs one covering composite request.`);
    if (!coveringRequest) continue;
    const coveringAsset = coverageByRequest.get(String(coveringRequest.id))?.assetPath;
    assert(Boolean(coveringAsset), `[Grand Canyon prerender] Page ${page} covering composite has no published asset.`);
    if (coveringAsset) coveringAssets.push(coveringAsset);
    const wholePageRequest = wholePageRequests.find((request) => request.page === page);
    const wholePageAsset = coverageByRequest.get(String(wholePageRequest?.id || ''))?.assetPath;
    assert(Boolean(wholePageAsset) && wholePageAsset === coveringAsset,
      `[Grand Canyon prerender] Page ${page} whole-page request must reuse its published covering composite.`);
    for (const request of pageRequests) {
      if (request === coveringRequest || !bboxContains(requestBBox(coveringRequest), requestBBox(request), 15)) continue;
      const containedAsset = coverageByRequest.get(String(request.id))?.assetPath;
      assert(Boolean(containedAsset) && containedAsset === coveringAsset,
        `[Grand Canyon prerender] Contained page-${page} request ${request.id} must reuse the covering composite instead of publishing a redundant crop.`);
    }
  }
  assert(new Set(coveringAssets).size === 2,
    '[Grand Canyon prerender] Pages 1 and 2 must publish two distinct covering composite assets.');
}

const tablePicturePlan = pdfPrerenderPlans.get('pdf-layout-docling-table-picture-boundary');
if (tablePicturePlan) {
  const coverageByRequest = new Map(tablePicturePlan.coverage.map((item) => [String(item.requestId), item]));
  const [firstRequest, secondRequest] = tablePicturePlan.requests;
  assert(firstRequest && secondRequest && JSON.stringify(firstRequest.bbox) === JSON.stringify(secondRequest.bbox),
    '[Table-picture prerender] Regression fixture must retain its two duplicate full-page request identities.');
  const firstAsset = coverageByRequest.get(String(firstRequest?.id))?.assetPath;
  const secondAsset = coverageByRequest.get(String(secondRequest?.id))?.assetPath;
  assert(Boolean(firstAsset) && firstAsset === secondAsset,
  '[Table-picture prerender] Duplicate full-page requests must be deduplicated to one published asset.');
}

// The OCR-overlay conversion already contains the physical page image. Its
// whole-page request must bind to that existing file and must not inject a
// second page representation into either final view.
const ocrOverlayPlan = pdfPrerenderPlans.get('pdf-layout-unstructured-ocr-overlay');
if (ocrOverlayPlan) {
  const request = ocrOverlayPlan.requests[0];
  const coverage = ocrOverlayPlan.coverage.find((item) => item.requestId === request?.id);
  assert(request?.method === 'pdfjs-whole-page-raster',
    '[OCR-overlay prerender] Fixture must retain its whole-page request provenance.');
  assert(coverage?.placement === 'existing-page-image',
    '[OCR-overlay prerender] Whole-page request must reuse the existing extracted page image instead of scheduling another raster.');
  for (const viewName of ['phpHtml', 'wpBlocks']) {
    const view = ocrOverlayPlan.record?.[viewName];
    if (!view?.ok || !fs.existsSync(siteFile(view.path))) continue;
    const output = fs.readFileSync(siteFile(view.path), 'utf8');
    const pageOneImages = (output.match(/<img\b[^>]*data-pandoc-pdf-page=["']1["'][^>]*>/gi) || [])
      .filter((tag) => /data-pandoc-pdf-image-placement=["']page["']/i.test(tag));
    const scheduledDuplicate = coverage?.placement === 'existing-page-image' ? 0 : 1;
    assert(pageOneImages.length + scheduledDuplicate === 1,
      `[OCR-overlay prerender] ${viewName} must finish with exactly one page-1 representation, not an extracted image plus a scheduled duplicate.`);
    assert(!coverage?.assetPath || output.includes(path.basename(coverage.assetPath)),
      `[OCR-overlay prerender] ${viewName} coverage must point to its already-published page image.`);
  }
}
