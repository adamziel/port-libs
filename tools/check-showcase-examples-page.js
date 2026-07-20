#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const site = path.join(root, 'pandoc-showcase');
const html = fs.readFileSync(path.join(site, 'examples.html'), 'utf8');
const css = fs.readFileSync(path.join(site, 'examples.css'), 'utf8');
const js = fs.readFileSync(path.join(site, 'examples.js'), 'utf8');
const fullShowcase = fs.readFileSync(path.join(site, 'index.html'), 'utf8');
const importE2e = fs.readFileSync(path.join(root, 'tools/e2e-playground-import.mjs'), 'utf8');
const showcaseBuilder = fs.readFileSync(path.join(root, 'tools/build-pandoc-showcase.php'), 'utf8');
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
assert(css.includes('.download-source,\n  .try-own-file { min-width: 0; width: 100%;'), 'Expected Try your own file to remain usable in the narrow mobile toolbar.');
assert(css.includes('.viewer-status[data-tone="error"]'), 'Expected own-file errors to be visibly styled.');
assert(js.includes("const catalogUrl = 'examples-index.json';"), 'Expected the compact example catalogue.');
assert(js.includes("const defaultView = 'wpBlocks';"), 'Expected WordPress Block markup to be the initial view.');
assert(js.includes('view: defaultView'), 'Expected the state to initialize to the default WordPress view.');
assert(js.includes("const exampleUrlParameter = 'example';"), 'Expected a stable query parameter for linked examples.');
assert(js.includes('new URL(window.location.href)'), 'Expected example links to preserve other URL state safely.');
assert(js.includes('window.history.replaceState'), 'Expected picker navigation to keep the current URL shareable.');
assert(js.includes('renderPdfFormRequestsIncrementally,') && js.includes("from './pdfjs-form-rasterizer.mjs';"), 'Expected own-file PDF figures to use the incremental shared PDF.js renderer.');
assert(showcaseBuilder.includes('renderPdfPageRasterRequests,')
  && showcaseBuilder.includes('renderPdfPageRasterRequestsIncrementally,'), 'Expected the generated showcase client to import batch and incremental whole-page renderers alongside the Form APIs.');
assert(js.includes("from './import-job-session.mjs';"), 'Expected examples.php to share the durable Playground import session helper.');
assert(js.includes("const playgroundPluginBuild = 'verified-pdf-import-20260716';"), 'Expected the own-file importer to use the current Playground plugin build.');
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
assert(importE2e.includes('Chrome did not release enough post-import memory'), 'Expected release E2E to enforce a bounded post-completion memory drop.');
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
assert(js.includes('`/imports/${jobId}/rendered-media`'), 'Expected browser-rendered PDF figures to be returned to WordPress one at a time.');
assert(js.includes('async function advanceOwnFileImport'), 'Expected an explicit bounded import advance flow.');
assert(js.includes('startOwnFileImportStatusPolling'), 'Expected in-flight advances to poll persisted WordPress progress.');
assert(js.includes('const ownFileAdvanceRecoveryAttempts = 3;'), 'Expected bounded recovery after a Playground PHP worker ends unexpectedly.');
assert(js.includes('The completed page checkpoints remain saved in this Playground'), 'Expected an interrupted large import to explain that completed PDF work remains durable.');
assert(js.includes('The import completed and the WordPress page was saved, but Playground could not display it'), 'A failed result navigation must not be reported as a lost conversion.');
assert(js.includes('`/imports/${jobId}`') && js.includes("'GET'"), 'Expected the own-file importer to read persisted import status while WordPress works.');
assert(js.includes('ownFileImportLatestNewEvent') && js.includes('reportedEventKeys'), 'Expected status events to be deduplicated by event identity rather than array position.');
assert(showcaseBuilder.includes('for await (const renderedItem of renderer(renderOptions))'), 'Expected generated PDF figures/page images to be rendered and acknowledged incrementally.');
assert(js.includes('const sourceKey = String(request?.sourceKey || path);'), 'Expected source grouping to use the server digest instead of a truncated display path.');
assert(js.includes("storageKey: 'port-libs.playground-active-import.v1'"), 'Expected GitHub Pages to persist its active WordPress job pointer.');
assert(js.includes('async function resumeSavedOwnFileImport()'), 'Expected Try your own file to resume a saved import after interruption.');
assert(js.includes('ownFilePlaygroundPersistence.startOptions(startOptions)'), 'Expected the embedded WordPress filesystem to restore from browser storage.');
assert(!js.includes('ownFilePlaygroundPersistence.forget()'), 'A transient Playground boot failure must not discard or overwrite durable OPFS checkpoints.');
assert(js.includes('recoverImportMutation({'), 'Expected an uncertain /advance response to be reconciled with durable status before replay.');
assert(js.includes("['failed', 'retryable_failure'].includes(String(data.status || ''))"), 'Expected durable error snapshots to reach the own-file state machine instead of being mistaken for transport failures.');
assert(js.includes('staticPdfPreviewCache: new Map()')
  && js.includes('example.pdfFormRenders')
  && js.includes("image.dataset.pandocPdfFormRendered = 'true';")
  && js.includes('frame.srcdoc = preview.html;')
  && js.includes('staticPdfPreviewMaxRequests = 8')
  && js.includes('abortStaticPdfPreview()'), 'Expected static PDF previews to inject browser-rendered Form figures without retaining an unbounded mobile gallery.');
assert(showcaseBuilder.includes('for (const group of pdfRenderRequestGroups(plan.renderable))')
  && showcaseBuilder.includes('? await renderPdfPageRasterRequests(renderOptions)')
  && showcaseBuilder.includes(': await renderPdfFormRequests(renderOptions);')
  && showcaseBuilder.includes('let remainingPixels = staticPdfPreviewMaxTotalPixels;')
  && showcaseBuilder.includes('let remainingImageBytes = staticPdfPreviewMaxImageBytes;')
  && showcaseBuilder.includes('staticPdfResultsInManifestOrder('), 'Expected generated static previews to dispatch source-and-method groups with shared caps and restore manifest order before injection.');
assert(showcaseBuilder.includes('const staticPdfPreviewMaxPixels = 2_100_000;')
  && showcaseBuilder.includes('const staticPdfPreviewMaxTotalPixels = 8_400_000;'), 'Expected static preview caps to admit the 1191×1684 VDL page while keeping substantially larger catalogue pages as placeholders.');
const restoreStaticPdfOrder = executableNamedFunction(showcaseBuilder, 'staticPdfResultsInManifestOrder');
const reorderedStaticPdfResults = restoreStaticPdfOrder(
  [{ id: 'first' }, { id: 'second' }, { id: 'third' }],
  [{ requestId: 'second' }, { requestId: 'first' }, { requestId: 'third' }],
);
assert(reorderedStaticPdfResults.map((item) => item.requestId).join(',') === 'first,second,third', 'Expected mixed static PDF render groups to be restored to manifest order before anchor placement and injection.');
assert(showcaseBuilder.includes('PDF visual/page-image manifest')
  && showcaseBuilder.includes('PDF figures/page images'), 'Expected generated preview labels to cover both Form figures and whole-page images.');
assert(js.includes('function normalizedPdfTextAnchor(value)')
  && js.includes("replace(/(?:-|\\u00ad)$/u, '')")
  && js.includes('figure.dataset.pdfFormObject = String(request.object);'), 'Expected static PDF chart placement to normalize terminal line-break hyphens and expose Form object diagnostics.');
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
    assert(view.sourceIntegrity?.pdfPagesNeedingImageRepresentation?.join(',') === '1,2,3,4,5,6,7,8', `MinerU ${viewName} must request representation for all eight pages.`);
    assert(Array.isArray(view.sourceIntegrity?.pdfRepresentedPageNumbers)
      && view.sourceIntegrity.pdfRepresentedPageNumbers.length === 0, `MinerU ${viewName} must retain zero represented pages.`);
    assert(view.sourceIntegrity?.pdfPageRepresentationComplete === false, `MinerU ${viewName} must retain incomplete page representation.`);
  }
  assert(mineruRecord.pdfFormRenders?.ok === true && mineruRecord.pdfFormRenders?.count === 8, 'MinerU must expose eight browser page raster requests.');
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
  assert(renderPlan && renderPlan.ok === true, 'TraceMonkey must expose its browser PDF figure render plan to the compact catalogue.');
  assert(recordPlan && recordPlan.ok === true, 'TraceMonkey must retain its browser PDF figure render plan in the full manifest.');
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
