#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const site = path.join(root, 'pandoc-showcase');
const html = fs.readFileSync(path.join(site, 'examples.html'), 'utf8');
const css = fs.readFileSync(path.join(site, 'examples.css'), 'utf8');
const js = fs.readFileSync(path.join(site, 'examples.js'), 'utf8');
const fullShowcase = fs.readFileSync(path.join(site, 'index.html'), 'utf8');
const indexPath = path.join(site, 'examples-index.json');
const manifestPath = path.join(site, 'manifest.json');
const indexBytes = fs.statSync(indexPath).size;
const manifestBytes = fs.statSync(manifestPath).size;
const index = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

function assert(condition, message) {
  if (!condition) {
    console.error(message);
    process.exitCode = 1;
  }
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
assert(!html.includes('<header'), 'The browser should not render a separate top section.');
assert((html.match(/<select\b/g) || []).length === 1, 'Expected one example selector.');
assert(!html.includes('format-filter'), 'The browser must not render a format selector.');
assert(/<a\b[^>]*\bid="download-source"[^>]*\bdownload\b[^>]*>Download original<\/a>/.test(html), 'Expected a Download original button.');
assert(/<button\b[^>]*\bid="try-own-file"[^>]*>Try your own file<\/button>/.test(html), 'Expected a Try your own file button.');
assert(/<input\b[^>]*\bid="own-file-input"[^>]*\btype="file"[^>]*\bhidden\b/.test(html), 'Expected the hidden own-file picker.');
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
assert(js.includes("import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';"), 'Expected own-file PDF figures to use the shared PDF.js renderer.');
assert(js.includes("const playgroundPluginBuild = 'pdf-memory-safe-finalization-20260715';"), 'Expected the own-file importer to use the current Playground plugin build.');
assert(js.includes("const playgroundClientModuleUrl = 'https://playground.wordpress.net/client/index.js';"), 'Expected Try your own file to use the Playground client.');
assert(js.includes("const playgroundUploadDirectory = '/tmp/port-libs-converter';"), 'Expected own files to use Playground temporary staging.');
assert(js.includes("php: '8.4'"), 'Expected own-file imports to use PHP 8.4 for EPUB and HTML documents.');
assert(js.includes("const tryOwnFileButton = document.getElementById('try-own-file');"), 'Expected Try your own file controls to be wired.');
assert(js.includes("const ownFileInput = document.getElementById('own-file-input');"), 'Expected the hidden own-file picker to be wired.');
assert(js.includes('ownFileInput.click();'), 'Expected the Try your own file button to open its file picker.');
assert(/ownFileInput\.addEventListener\('change',[\s\S]*?void openOwnFile\(file\);/.test(js), 'Selecting a file should open it immediately in the view area.');
assert(js.includes('async function bootOwnFilePlayground()'), 'Expected a reusable Playground boot path for own-file imports.');
assert(/async function bootOwnFilePlayground\(\)[\s\S]*?if \(state\.playgroundReady\)/.test(js), 'Expected a loaded Playground to be reused for another file.');
assert(js.includes("let job = await ownFilePluginRequest(playgroundClient, '/imports', {"), 'Expected own files to create persisted import jobs.');
assert(js.includes('`/imports/${jobId}/advance`'), 'Expected own files to advance their persisted import jobs.');
assert(js.includes('`/imports/${encodeURIComponent(job.jobId)}/rendered-media`'), 'Expected browser-rendered PDF figures to be returned to WordPress.');
assert(js.includes('async function advanceOwnFileImport'), 'Expected an explicit bounded import advance flow.');
assert(js.includes('startOwnFileImportStatusPolling'), 'Expected in-flight advances to poll persisted WordPress progress.');
assert(js.includes('const ownFileAdvanceRecoveryAttempts = 3;'), 'Expected bounded recovery after a Playground PHP worker ends unexpectedly.');
assert(js.includes('The completed page checkpoints remain saved in this Playground'), 'Expected an interrupted large import to explain that completed PDF work remains durable.');
assert(js.includes('The import completed and the WordPress page was saved, but Playground could not display it'), 'A failed result navigation must not be reported as a lost conversion.');
assert(js.includes('`/imports/${jobId}`') && js.includes("'GET'"), 'Expected the own-file importer to read persisted import status while WordPress works.');
assert(js.includes('ownFileImportLatestNewEvent') && js.includes('reportedEventKeys'), 'Expected status events to be deduplicated by event identity rather than array position.');
assert(js.includes('await renderPdfFormRequests({'), 'Expected WordPress to request PDF Form figure crops from the browser.');
assert(js.includes('staticPdfPreviewCache: new Map()')
  && js.includes('example.pdfFormRenders')
  && js.includes("image.dataset.pandocPdfFormRendered = 'true';")
  && js.includes('frame.srcdoc = preview.html;')
  && js.includes('staticPdfPreviewMaxRequests = 8')
  && js.includes('staticPdfPreviewMaxTotalPixels = 8_000_000')
  && js.includes('abortStaticPdfPreview()'), 'Expected static PDF previews to inject browser-rendered Form figures without retaining an unbounded mobile gallery.');
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
    const expectedPath = viewName === 'wpBlocks' && fullView.ok === true
      ? rawPath.replace(/wordpress-blocks\.html$/, 'wordpress-blocks-preview.html')
      : rawPath;
    assert(view.path === expectedPath, example.id + ' ' + viewName + ' path diverged from the full manifest.');
    assert(view.ok === (fullView.ok === true), example.id + ' ' + viewName + ' status diverged from the full manifest.');
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

assert(automaticPhpExamples >= 80, 'Expected a broad set of small PHP examples for automatic mobile browsing.');

const traceMonkey = index.examples.find((example) => example.id === 'pdf-tracemonkey');
const traceMonkeyRecord = recordsById.get('pdf-tracemonkey');
assert(traceMonkey && traceMonkeyRecord, 'Expected the bundled TraceMonkey PDF example.');
if (traceMonkey && traceMonkeyRecord) {
  const renderPlan = traceMonkey.pdfFormRenders;
  const recordPlan = traceMonkeyRecord.pdfFormRenders;
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
      assert(payload.samplePath === traceMonkey.samplePath, 'TraceMonkey PDF figure render plan must point at the bundled PDF.');
      assert(requests.length === 8, 'TraceMonkey PDF figure render plan must contain eight crop requests.');
      assert(requests.every((request) => request.path === traceMonkey.samplePath
        && /^form-[a-f0-9]{28}$/.test(String(request.id || ''))
        && Number.isInteger(request.page)
        && request.bbox && request.bbox.x2 > request.bbox.x1 && request.bbox.y2 > request.bbox.y1),
      'Every TraceMonkey PDF chart request must be bounded and address the bundled PDF.');
      assert(requests.map((request) => request.object).join(',') === '41,118,119,120,154,199,198,200',
        'TraceMonkey chart plan must retain the eight expected Form XObjects.');
      const captionsByObject = new Map([
        [41, 'Figure 2.'], [118, 'Figure 5.'], [119, 'Figure 6.'], [120, 'Figure 7.'],
        [154, 'Figure 8.'], [199, 'Figure 11.'], [198, 'Figure 10.'], [200, 'Figure 12.'],
      ]);
      assert(requests.every((request) => String(request.followingText || '').replace(/(?:-|\u00ad)$/u, '')
        .startsWith(captionsByObject.get(request.object) || '')),
      'Every TraceMonkey chart request must preserve its own following figure caption as a placement anchor.');
    }
  }
}
