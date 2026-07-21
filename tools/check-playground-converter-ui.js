#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const jsPath = path.join(root, 'pandoc-showcase', 'playground-converter.js');
const htmlPath = path.join(root, 'pandoc-showcase', 'playground-converter.html');
const adminImporterPath = path.join(root, 'tools', 'playground-converter-plugin', 'assets', 'admin-importer.mjs');
const pluginPath = path.join(root, 'tools', 'playground-converter-plugin', 'port-libs-playground-converter.php');
const pdfFormRasterizerPath = path.join(root, 'pandoc-showcase', 'pdfjs-form-rasterizer.mjs');
const pdfFactsProviderPath = path.join(root, 'pandoc-showcase', 'pdfjs-facts-provider.mjs');
const js = fs.readFileSync(jsPath, 'utf8');
const html = fs.readFileSync(htmlPath, 'utf8');
const adminImporter = fs.readFileSync(adminImporterPath, 'utf8');
const plugin = fs.readFileSync(pluginPath, 'utf8');
const pdfFormRasterizer = fs.readFileSync(pdfFormRasterizerPath, 'utf8');
const pdfFactsProvider = fs.readFileSync(pdfFactsProviderPath, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(message);
    process.exitCode = 1;
  }
}

function executableNamedFunction(source, name) {
  const start = source.indexOf(`function ${name}(`);
  if (start < 0) {
    throw new Error(`Could not find ${name} for its executable regression.`);
  }
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
  const start = source.indexOf(`function ${name}(`);
  if (start < 0) {
    throw new Error(`Could not find ${name} for its source regression.`);
  }
  const openingBrace = source.indexOf('{', start);
  let depth = 0;
  for (let index = openingBrace; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] !== '}') continue;
    depth -= 1;
    if (depth === 0) return source.slice(start, index + 1);
  }
  throw new Error(`Could not read the complete ${name} function source.`);
}

function checkMixedPdfRenderAdapters(source, clientLabel) {
  const requestForRenderer = executableNamedFunction(source, 'pdfPageRasterRequestForRenderer');
  const immutableRequest = {
    version: 1,
    method: 'pdfjs-whole-page-raster',
    id: 'pdf-page-raster-test',
    sourceSha256: 'a'.repeat(64),
    page: 2,
    pageObject: 7,
    pageBox: [0, 0, 612, 792],
    pageBoxSource: 'CropBox',
    pageRotation: 0,
    width: 1224,
    height: 1584,
    mimeType: 'image/png',
    requestDigest: 'b'.repeat(64),
  };
  const rendererRequest = requestForRenderer({
    ...immutableRequest,
    sourceKey: 'routing-only',
    path: 'routing-only.pdf',
    label: 'routing-only',
  });
  assert(JSON.stringify(Object.keys(rendererRequest).sort()) === JSON.stringify(Object.keys(immutableRequest).sort())
    && !('path' in rendererRequest)
    && !('sourceKey' in rendererRequest)
    && !('label' in rendererRequest), `${clientLabel} must remove all transport-only fields before exact page-request validation.`);

  const renderedMediaItem = executableNamedFunction(source, 'pdfRenderedMediaItem');
  const contents = new Uint8Array([1, 2, 3]);
  const mediaItem = renderedMediaItem({
    ...immutableRequest,
    requestId: immutableRequest.id,
    contents,
    byteLength: contents.byteLength,
    sha256: 'c'.repeat(64),
    proofDigest: 'd'.repeat(64),
  });
  assert(mediaItem.bytes === contents
    && JSON.stringify(Object.keys(mediaItem).sort()) === JSON.stringify(['bytes', 'height', 'mimeType', 'requestId', 'width']), `${clientLabel} must send page images through the existing requestId/bytes/mime/dimensions media shape without client-supplied immutable metadata.`);

  const groupingSource = namedFunctionSource(source, 'pdfRenderRequestGroups');
  const groupRequests = Function(
    'pdfPageRasterMethod',
    `"use strict"; return (${groupingSource});`,
  )('pdfjs-whole-page-raster');
  const groups = groupRequests([
    { id: 'form-a', sourceKey: 'source-a' },
    { id: 'page-a', sourceKey: 'source-a', method: 'pdfjs-whole-page-raster' },
    { id: 'page-b', sourceKey: 'source-b', method: 'pdfjs-whole-page-raster' },
  ]);
  assert(groups.length === 3
    && groups.map((group) => group.requests[0].id).join(',') === 'form-a,page-a,page-b', `${clientLabel} must keep source and render method as independent grouping dimensions without disturbing first-seen order.`);
}

function checkPdfDecoderSignatureGate(source, clientLabel) {
  const signatureSearch = executableNamedFunction(source, 'pdfBytesContainAscii');
  const bytes = (value) => new Uint8Array(Buffer.from(value, 'ascii'));
  const ordinary = bytes('%PDF-1.7\n5 0 obj <</Filter /FlateDecode>>\nendobj\n%%EOF');
  const jbig2 = bytes('%PDF-1.7\n<</Filter /JBIG2Decode>>\n%%EOF');
  const jpx = bytes('%PDF-1.7\n<</Filter[/ASCII85Decode /JPXDecode]>>\n%%EOF');
  const both = bytes('%PDF-1.7\n/JPXDecode\nstream\0binary\xff/JBIG2Decode\n%%EOF');

  assert(!signatureSearch(ordinary, '/JBIG2Decode') && !signatureSearch(ordinary, '/JPXDecode'), `${clientLabel} must skip both standalone image decoders when their PDF filter signatures are absent.`);
  assert(signatureSearch(jbig2, '/JBIG2Decode'), `${clientLabel} must enable JBIG2 decoding when /JBIG2Decode is present.`);
  assert(!signatureSearch(jbig2, '/JPXDecode'), `${clientLabel} must not enable JPEG 2000 decoding for a JBIG2-only PDF.`);
  assert(signatureSearch(jpx, '/JPXDecode'), `${clientLabel} must enable JPEG 2000 decoding when /JPXDecode is present.`);
  assert(!signatureSearch(jpx, '/JBIG2Decode'), `${clientLabel} must not enable JBIG2 decoding for a JPEG 2000-only PDF.`);
  assert(signatureSearch(both, '/JBIG2Decode') && signatureSearch(both, '/JPXDecode'), `${clientLabel} must retain both decoder fallbacks when both filter signatures are present.`);
  assert(!signatureSearch(bytes('/JBIG2Decod'), '/JBIG2Decode') && !signatureSearch(bytes('/JPXDecod'), '/JPXDecode'), `${clientLabel} must not accept partial PDF filter signatures.`);
  assert(!signatureSearch(both, 'A'.repeat(33)) && !signatureSearch(both, '/JPXDecodé'), `${clientLabel} must keep its byte signature search bounded to short ASCII needles.`);

  const implementation = signatureSearch.toString();
  assert(implementation.includes('bytes.indexOf(firstByte, offset)'), `${clientLabel} must search the existing byte view without copying the PDF.`);
  assert(!/TextDecoder|\.slice\(|\.subarray\(|String\.fromCharCode/.test(implementation), `${clientLabel} signature gating must not turn the full PDF into another byte or string copy.`);
  assert(source.includes("filterName: '/JBIG2Decode'") && source.includes("filterName: '/JPXDecode'"), `${clientLabel} must associate each optional decoder with its own exact PDF filter signature.`);
  const gate = source.indexOf('].filter(({ filterName }) => pdfBytesContainAscii(bytes, filterName));');
  const decoderLoad = source.indexOf('const loaded = await Promise.allSettled(decoderEntries.map');
  const decoderRun = source.indexOf('const decoded = await decode(bytes, {', decoderLoad);
  assert(gate >= 0 && decoderLoad > gate && decoderRun > decoderLoad, `${clientLabel} must apply the byte-signature gate before loading or running either decoder module.`);
}

assert(js.includes('function qualityLogMessage(quality)'), 'Expected a plain-language quality log formatter.');
assert(js.includes('log(qualityLogMessage(quality));'), 'Expected conversion logging to use the plain-language quality formatter.');
assert(js.includes('setStatus(\'ready\', quality ? `Page created and opened. ${qualityMessageForStatus(String(quality.status || \'complete\'))}`'), 'Expected final status text to include the plain-language quality message.');
assert(!js.includes('Import quality: ${data.quality.status}'), 'Raw import-quality status codes must not be shown in the visible log.');
assert(!js.includes('log(`Import quality: ${'), 'Visible quality logging should not interpolate raw status codes.');
const pluginBuildMatch = js.match(/^const pluginBuild = '([^']+)';/m);
assert(pluginBuildMatch, 'Expected a cache-busting Playground plugin build identifier.');
if (pluginBuildMatch) {
  assert(html.includes(`playground-converter.js?v=${pluginBuildMatch[1]}`), 'Expected the page script URL to use the current Playground plugin build identifier.');
}
assert(js.includes('pdf-jbig2-rasterizer.mjs'), 'Expected the browser JBIG2 rasterizer to be loaded for PDF imports.');
assert(js.includes('pdf-jpx-rasterizer.mjs'), 'Expected the browser JPEG 2000 rasterizer to be loaded for PDF imports.');
assert(js.includes('decodePdfJpxRasters'), 'Expected browser JPEG 2000 rasters to be prepared for PDF imports.');
assert(js.includes('Promise.allSettled'), 'A JPEG 2000 decoder failure must not discard usable JBIG2 rasters.');
assert(js.includes('const pdfRasterPayloadByteLimit = 24_000_000;'), 'Expected browser PDF rasters to honor the Playground decoded-byte limit.');
assert(js.includes('const pdfRasterBudget = { remainingBytes: pdfRasterPayloadByteLimit };') && js.includes('browserPdfRasterImages(bytes, imageMode, reportProgress, pdfRasterBudget)'), 'Expected a collection to share one browser PDF-raster byte budget across every source file.');
assert(js.includes('maxPngBytes: remainingBytes'), 'Expected browser PDF decoders to share one raster byte budget.');
assert(js.includes('pdfRasterImages'), 'Expected browser-decoded PDF rasters to be included in the import payload.');
assert(js.includes('renderPdfFormRequestsIncrementally,')
  && js.includes('renderPdfPageRasterRequestsIncrementally,')
  && js.includes("from './pdfjs-form-rasterizer.mjs';"), 'Expected incremental PDF.js renderers for both Form figures and whole-page images in the Playground importer.');
assert(js.includes('resetPlaygroundIframeForRetry,')
  && js.includes('startPlaygroundWithSnapshotRecovery,')
  && js.includes("from './import-job-session.mjs?v=playground-retry-teardown-20260721';"), 'Expected GitHub Pages imports to load the cache-busted Playground retry teardown helper.');
assert(!js.includes('playgroundPersistence.forget()'), 'A transient Playground boot failure must retain the saved OPFS WordPress tree for retry.');
assert(js.includes('playgroundClient = await startPlaygroundWithSnapshotRecovery({')
  && js.includes('persistence: playgroundPersistence,')
  && js.includes('options: startOptions,')
  && js.includes('beforeRetry: () => resetPlaygroundIframeForRetry(iframe),'), 'Expected the standalone importer to tear down the failed iframe before recovering once from an invalid persisted SQLite site.');
assert(js.includes('The saved Playground database could not be reopened. Starting a fresh private WordPress site; the previous browser snapshot is preserved.'), 'Expected standalone invalid-site recovery to be explicit and non-destructive.');
assert(!js.includes('collectPdfJsFacts'), 'Playground must not eagerly parse PDF.js text facts before a consumer exists.');
assert(!js.includes('pdfBrowserFacts'), 'Unused browser facts must not enlarge Playground import payloads.');
assert(js.includes("playgroundPluginRequest('/imports', stagedUpload.payload)"), 'Expected imports to start through the persisted import-job endpoint.');
assert(js.includes('function stageUploadInPlayground(client, upload, reportProgress = () => {})'), 'Expected Playground sources to be staged instead of base64-encoded into the REST body.');
assert(js.includes('stagedFiles,'), 'Expected folder imports to send a staged-file manifest rather than a JSON byte collection.');
assert(!js.includes('function payloadFromUpload('), 'The Playground importer must not base64-encode every selected source before creating a job.');
assert(js.includes('const pdfRasterSourceByteLimit = 24 * 1024 * 1024;'), 'Expected optional PDF raster decoding to skip over-limit sources before allocating decoder state.');
assert(js.includes('/rendered-media'), 'Expected browser-rendered PDF figures to be sent back to the import job.');
assert(js.includes('/render-source/'), 'Expected an expanded ZIP PDF source to be fetched only for its outstanding renderer request.');
assert(js.includes('for (const group of pdfRenderRequestGroups(job.renderRequests))'), 'Expected Playground to render one source-and-method PDF group at a time.');
assert(js.includes('budget.remainingPixels <= 0 || budget.remainingImageBytes <= 0\n          ? new Map()'), 'Expected Playground to skip later PDF source fetches after that rendering method exhausts its budget.');
assert(js.includes('function advanceImportJob(jobId, reportJob)'), 'Expected imports to advance through bounded server work units.');
assert(js.includes("['failed', 'retryable_failure'].includes(String(data.status || ''))"), 'Expected Playground to resume a retryable server checkpoint instead of treating it as a malformed response.');
assert(js.includes("undefined, 'GET'"), 'Expected the UI to poll the persisted job while a conversion request is running.');
assert(js.includes("php: '8.4'"), 'Expected Playground to use PHP 8.4 for EPUB and HTML imports that need Dom\\HTMLDocument.');
assert(!html.includes('id="format-input"'), 'Document type must not be exposed as a client-side form field.');
assert(!js.includes('formatByExtension'), 'Document type inference must be authoritative on the server, not duplicated in the browser.');
assert(!js.includes('format: upload.format'), 'The browser must not send a user-controlled document type hint.');
assert(js.includes('function isLikelyPdfFile(file)'), 'Expected PDF-only browser preparation to remain available without choosing a document type.');
assert(html.includes('id="pdf-output-mode-control"'), 'Expected the standalone importer to offer PDF publication shape only with its PDF controls.');
assert(js.includes('pdfOutputMode: selectedPdfOutputMode()'), 'Expected the standalone importer to send the selected PDF publication shape.');
assert(js.includes("'awaiting_output_mode'"), 'Expected the standalone importer to stop advancing while an output choice is required.');
assert(js.includes('/output-mode`'), 'Expected the standalone importer to resume an oversized job without re-uploading it.');
assert(!js.includes('unsupportedMessage'), 'Supported document formats must not carry a client-side blanket rejection.');
checkPdfDecoderSignatureGate(js, 'Playground');
checkMixedPdfRenderAdapters(js, 'Playground');

assert(adminImporter.includes("new URL('./pdf-jbig2-rasterizer.mjs', import.meta.url)"), 'Expected the WordPress admin importer to load the bundled JBIG2 rasterizer relative to its module.');
assert(!adminImporter.includes("import { collectPdfJsFacts } from './pdfjs-facts-provider.mjs';"), 'WordPress admin must not eagerly load the PDF.js facts provider.');
assert(adminImporter.includes('config.enablePdfBrowserFacts === true'), 'Expected browser PDF facts to require an explicit server-side opt in.');
assert(adminImporter.includes("await import(new URL('./pdfjs-facts-provider.mjs', import.meta.url).href)"), 'Expected opt-in browser facts to load only when requested.');
assert(adminImporter.includes('pdfBrowserFacts[entry.path] = facts;'), 'Expected an opted-in facts range to retain its source path.');
assert(adminImporter.includes("new URL('./pdf-jpx-rasterizer.mjs', import.meta.url)"), 'Expected the WordPress admin importer to load the bundled JPEG 2000 rasterizer relative to its module.');
assert(adminImporter.includes('const pdfRasterPayloadByteLimit = 24_000_000;'), 'Expected WordPress admin PDF rasters to share the server-side per-import byte limit.');
assert(adminImporter.includes('const pdfRasterSourceByteLimit = 24 * 1024 * 1024;'), 'Expected WordPress admin PDF decoding to skip over-limit sources before duplicating them in browser memory.');
assert(adminImporter.includes('Promise.allSettled'), 'A missing admin browser decoder must not discard usable rasters from the other decoder.');
assert(adminImporter.includes('pdfRasterImages: rasterDescriptors'), 'Expected the admin multipart metadata to describe browser-produced PDF rasters.');
assert(adminImporter.includes('const field = `plpc_raster_${index}`;'), 'Expected every admin browser raster to use a dedicated multipart field.');
assert(adminImporter.includes('new Blob([raster.bytes]'), 'Expected admin browser raster bytes to remain binary multipart uploads, not JSON/base64.');
assert(adminImporter.includes('function restEndpoint(path)'), 'Expected the admin importer to preserve query-string REST routes on non-pretty-permalink WordPress sites.');
assert(adminImporter.includes("root.searchParams.set('rest_route'"), 'Expected the admin importer to append import paths to WordPress plain-permalink REST routes.');
assert(adminImporter.includes('response.json().catch(() => null)'), 'Expected a non-JSON REST response to stop the UI rather than silently stalling an import.');
assert(adminImporter.includes('const maxAdvanceRecoveryAttempts = 2;'), 'Expected bounded recovery when a foreground advance request ends unexpectedly.');
assert(adminImporter.includes('async function advanceImportJob(snapshot)'), 'Expected the admin importer to re-check a durable job after an interrupted advance request.');
assert(adminImporter.includes('for (const group of pdfRenderRequestGroups(snapshot.renderRequests))'), 'Expected wp-admin to release each source-and-method PDF.js group before fetching another one.');
assert(adminImporter.includes('budget.remainingPixels <= 0 || budget.remainingImageBytes <= 0\n              ? new Map()'), 'Expected wp-admin to skip later PDF source fetches after that rendering method exhausts its budget.');
assert(adminImporter.includes("['failed', 'retryable_failure'].includes(String(data.status || ''))"), 'Expected wp-admin to pass retryable job snapshots back to its resumable driver.');
assert(adminImporter.includes('The importer stopped retrying automatically to avoid duplicating work.'), 'Expected advance recovery to stop with an actionable message instead of retrying forever.');
assert(adminImporter.includes('createAdminImportJobSession(config)'), 'Expected wp-admin to persist a pointer to the active server-owned job.');
assert(adminImporter.includes('Resume saved import'), 'Expected wp-admin to surface a durable resume action after reload or interruption.');
assert(adminImporter.includes('for (let statusAttempt = 1; statusAttempt <= 3; statusAttempt += 1)'), 'Expected wp-admin to re-check an uncertain mutation before retransmitting /advance.');
assert(adminImporter.includes('async function cancelImportJob(snapshot)') && adminImporter.includes('/cancel`'), 'Expected wp-admin to stop a persisted import at a durable unit boundary.');
assert(adminImporter.includes('cancellationRequested') && adminImporter.includes("'cancelled'"), 'Expected wp-admin to retain an explicit cancellation request until the current bounded step returns.');
const adminCancellation = namedFunctionSource(adminImporter, 'cancelImportJob');
const adminAdvance = namedFunctionSource(adminImporter, 'advanceImportJob');
const adminRenderedMedia = namedFunctionSource(adminImporter, 'submitRenderedMedia');
assert(adminCancellation.includes('while (cancellationRequested && active && activeJobId === jobId)')
  && adminCancellation.includes('method: \'POST\'')
  && adminCancellation.includes('method: \'GET\'')
  && adminCancellation.includes('await pause(Math.min(2_000, 400 * attempt))'), 'Expected wp-admin to poll and retry /cancel while another request owns the job lock.');
assert(adminAdvance.includes('if (cancellationRequested)')
  && (adminAdvance.match(/return cancelImportJob\(/g) || []).length >= 4, 'Expected every uncertain /advance replay boundary to yield to cancellation first.');
assert(adminRenderedMedia.includes('if (cancellationRequested)')
  && (adminRenderedMedia.match(/return cancelImportJob\(/g) || []).length >= 2, 'Expected wp-admin rendered-media recovery to cancel instead of replaying after cancellation intent.');
assert(adminImporter.includes('jobSession.requestCancellation(activeJobId);')
  && adminImporter.includes('cancellationRequested: record?.cancellationRequested === true')
  && adminImporter.includes('cancellationRequested = saved.cancellationRequested === true')
  && adminImporter.includes('submit.disabled = !selected || active || jobSession.load()?.cancellationRequested === true;'), 'Expected wp-admin to persist cancellation intent and restore it without starting another import.');
assert(adminImporter.includes('function updateSelection({ clearResult = false } = {})'), 'Expected the admin importer to distinguish a new selection from ending an active import.');
assert(adminImporter.includes('if (clearResult) {'), 'Expected completed admin import links to remain visible until the user chooses another file.');
checkPdfDecoderSignatureGate(adminImporter, 'WordPress admin');
checkMixedPdfRenderAdapters(adminImporter, 'WordPress admin');
assert(plugin.includes('data-plpc-pdf-output-options'), 'Expected wp-admin to expose the PDF publication shape beside its PDF-only controls.');
assert(plugin.includes('data-plpc-cancel') && plugin.includes("'/imports/(?P<jobId>[A-Za-z0-9_-]+)/cancel'"), 'Expected the admin UI and REST state machine to expose owner-scoped cancellation.');
assert(adminImporter.includes("checkedValue('plpc-pdf-output-mode', 'single')"), 'Expected wp-admin to default PDF publication to one page.');
assert(adminImporter.includes('showOutputModeRecovery(snapshot, pdfFiles)'), 'Expected wp-admin to offer a same-job page-tree recovery after the size guard.');
assert(adminImporter.includes('/output-mode`'), 'Expected wp-admin recovery to reuse the persisted job.');
assert(plugin.includes("wp_enqueue_script_module('port-libs-importer'"), 'Expected modern WordPress admin installs to enqueue the ESM importer through the native module API.');
assert(plugin.includes('function plpc_print_importer_configuration_script(): void'), 'Expected importer configuration to be emitted independently of a classic script handle.');
assert(plugin.includes('plpc_print_importer_configuration_script();'), 'Expected the configuration script to appear before the deferred importer module runs.');
assert(!plugin.includes("@ini_set('memory_limit', '512M')"), 'The plugin must not override a host memory limit while an import is running.');
assert(!plugin.includes('@set_time_limit(120)'), 'The plugin must not override a host execution-time limit while an import is running.');
assert(plugin.includes('function plpc_import_request_deadline(): ?float'), 'Expected the server to reserve time for a durable import checkpoint before PHP reaches its limit.');
assert(plugin.includes('function plpc_import_job_checkpoint_for_deadline('), 'Expected conversion phase progress to yield safely before the server execution deadline.');
assert(plugin.includes('function plpc_import_job_generate_next_media_metadata(') && plugin.includes("'ready_for_media_metadata'"), 'Expected WordPress media metadata to run as one resumable attachment per advance request.');
assert(adminImporter.includes('media metadata records prepared'), 'Expected wp-admin to report the durable media-metadata cursor.');
assert(plugin.includes('PLPC_IMPORT_JOB_MAX_DEADLINE_YIELDS_PER_DOCUMENT'), 'Expected deadline handoffs to be capped instead of spinning forever.');
assert(plugin.includes('function plpc_import_job_store_browser_facts('), 'Expected browser PDF facts to be stored outside the WordPress options table.');
assert(plugin.includes('plpc_pdf_raster_images_from_payload($records, $remainingBytes)') && plugin.includes('$totalBytes + strlen($contents) > PLPC_MAX_PDF_RASTER_BYTES'), 'Expected server raster decoding and storage to enforce one aggregate collection budget.');
assert(plugin.includes('function plpc_import_job_load_browser_facts('), 'Expected durable browser PDF facts to be available to resumed imports.');
assert(plugin.includes('function plpc_import_job_store_pdf_chunk('), 'Expected every PDF page range to become a durable facts checkpoint.');
assert(plugin.includes('function plpc_import_job_load_pdf_chunk('), 'Expected resumed imports to verify and load durable page facts.');
assert(plugin.includes('PLPC_IMPORT_JOB_PDF_SEGMENT_MAX_FACT_BYTES'), 'Expected dense PDF finalization to use an explicit serialized-facts memory budget.');
assert(plugin.includes('function plpc_import_job_plan_pdf_segments('), 'Expected finalization ranges to be planned from durable fact sizes.');
assert(plugin.includes('function plpc_import_job_merge_pdf_segment_facts('), 'Expected page facts to be integrity-checked into bounded contiguous ranges.');
assert(plugin.includes('->mergeRange($ranges, $startPage, $endPage)'), 'Expected dense facts to avoid rebuilding every PDF page in one worker.');
assert(plugin.includes('function plpc_import_job_prepare_pdf_final_bundle('), 'Expected each bounded semantic pass to become durable before WordPress publication.');
assert(plugin.includes('function plpc_import_job_store_pdf_final_bundle('), 'Expected private block and media bundles to be durable.');
assert(plugin.includes('function plpc_import_job_load_pdf_final_bundle('), 'Expected publication retries to reuse the durable final PDF bundle.');
assert(plugin.includes('PLPC_PDF_SINGLE_PAGE_HARD_LIMIT_BYTES = 8388608'), 'Expected one post_content value to have an explicit 8 MiB hard ceiling.');
assert(plugin.includes('function plpc_pdf_single_page_limit_bytes()'), 'Expected the single-page limit to account for PHP and database policy.');
assert(plugin.includes("'pdf_single_page_too_large'"), 'Expected an oversized assembled page to have a stable recoverable failure code.');
assert(plugin.includes('function plpc_import_job_store_pdf_publication_bundle('), 'Expected media-rewritten segment blocks to remain durable until safe assembly.');
assert(plugin.includes('function plpc_import_job_finalize_single_pdf_output('), 'Expected bounded internal segments to assemble into one continuous page.');
assert(plugin.includes('function plpc_import_job_finalize_pdf_page_tree('), 'Expected physical PDF pages to become a root and ordered children when selected.');
assert(plugin.includes("'_plpc_import_pdf_role' => 'root'"), 'Expected a stable private root identity for resumable page-tree publication.');
assert(plugin.includes("'post_parent' => $rootPostId"), 'Expected physical PDF pages to be WordPress children of their PDF root.');
assert(!/\$blockMarkup\s*=\s*plpc_prepend_(?:conversion_warning|import_quality)_blocks/.test(plugin), 'PDF publication must not prepend conversion or quality notes to imported content.');
assert(!/\$blocks\s*=\s*plpc_prepend_(?:conversion_warning|import_quality)_blocks/.test(plugin), 'Non-PDF publication must not prepend conversion or quality notes to imported content.');
assert(plugin.includes('function plpc_import_job_recover_interrupted_document('), 'Expected hard worker terminations to have a bounded durable recovery path.');
assert(plugin.includes('function plpc_pdf_form_placement_covers_page('), 'Expected page-sized Form wrappers to be distinguished from inline PDF figures.');
assert(plugin.includes('pdfPageSizedFormsSkipped'), 'Expected skipped page-layout wrappers to be visible in import metrics.');
assert(plugin.includes("'sourceKey' => substr(hash('sha256', (string) ($request['path'] ?? '')), 0, 32)"), 'Expected long collection paths to retain an unambiguous PDF.js source-group identity.');
assert(plugin.includes('Reaching an enhancement budget is not a document failure.'), 'Expected an exhausted optional figure budget to continue the text import.');
for (const [source, clientLabel] of [[js, 'Playground'], [adminImporter, 'wp-admin']]) {
  assert(source.includes('const pdfFormRenderTotalPixelLimit = 48_000_000;')
    && source.includes('const pdfFormRenderTotalImageByteLimit = 24_000_000;')
    && source.includes('remainingPixels: pdfFormRenderTotalPixelLimit')
    && source.includes('remainingImageBytes: pdfFormRenderTotalImageByteLimit'), `${clientLabel} must retain the cumulative 48M-pixel/24M-byte Form-figure budget.`);
  assert(source.includes('const pdfPageRenderTotalPixelLimit = 128_000_000;')
    && source.includes('const pdfPageRenderTotalImageByteLimit = 64 * 1024 * 1024;')
    && source.includes('remainingPixels: pdfPageRenderTotalPixelLimit')
    && source.includes('remainingImageBytes: pdfPageRenderTotalImageByteLimit'), `${clientLabel} must use a separate cumulative 128M-pixel/64MiB whole-page budget.`);
  assert(source.includes('const budget = pageRaster ? pageBudget : formBudget;')
    && source.includes('maxTotalPixels: budget.remainingPixels')
    && source.includes('maxTotalImageBytes: budget.remainingImageBytes')
    && source.includes('budget.remainingImageBytes = Math.max(0, budget.remainingImageBytes - item.bytes.byteLength);'), `${clientLabel} must debit only the selected render method's cumulative budget across source groups and status pages.`);
  assert(source.includes('const groupKey = `${method}\\u001f${sourceKey}`;')
    && source.includes("const pdfPageRasterMethod = 'pdfjs-whole-page-raster';"), `${clientLabel} must split PDF.js work by immutable source and rendering method.`);
  assert(source.includes('? renderPdfPageRasterRequestsIncrementally')
    && source.includes(': renderPdfFormRequestsIncrementally;'), `${clientLabel} must dispatch each render group to the matching incremental API.`);
  assert(source.includes('requests: requests.map(pdfPageRasterRequestForRenderer)')
    && source.includes('bytes: item.contents,'), `${clientLabel} must strip routing metadata before exact page validation and adapt page contents to the existing media upload shape.`);
}
assert(pdfFormRasterizer.includes('const DEFAULT_MAX_SOURCE_BYTES = 24 * 1024 * 1024;'), 'Expected PDF.js figure rendering to cap source bytes before copying a large PDF in the browser.');
assert(pdfFormRasterizer.includes('pdfBytes(filesByPath.get(path), maxSourceBytes)'), 'Expected the PDF.js figure renderer to enforce its source-byte cap for every requested crop.');
assert(pdfFormRasterizer.includes('maxTotalPixels = Number.POSITIVE_INFINITY') && pdfFormRasterizer.includes('totalPixelsLimit - renderedPixels'), 'Expected callers to be able to bound aggregate PDF figure pixels without changing the importer default.');
assert(pdfFormRasterizer.includes('maxTotalImageBytes = Number.POSITIVE_INFINITY') && pdfFormRasterizer.includes('totalImageBytesLimit - renderedImageBytes'), 'Expected callers to be able to bound aggregate PDF figure bytes without changing the importer default.');
assert(pdfFormRasterizer.includes('throwIfAborted(signal)'), 'Expected static preview cancellation to release PDF.js work between figure crops.');
assert(pdfFormRasterizer.includes('The PDF is too large to render figures safely in this browser.'), 'Expected an over-limit PDF figure to fall back to the text import instead of crashing the browser.');
assert(pdfFormRasterizer.includes("typeof viewport.convertToViewportRectangle === 'function'"), 'Expected PDF.js Form cropping to retain compatibility with PDF.js versions that removed rectangle conversion.');
assert(pdfFormRasterizer.includes('viewport.convertToViewportPoint(bbox.x1, bbox.y1)'), 'Expected PDF.js Form cropping to fall back to point conversion on current PDF.js releases.');
assert(pdfFormRasterizer.includes('export async function* renderPdfFormRequestsIncrementally'), 'Expected an acknowledgement-friendly one-crop-at-a-time renderer API.');
assert(pdfFormRasterizer.includes('canvas.width = 0;') && pdfFormRasterizer.includes('canvas.height = 0;'), 'Expected each rendered canvas backing store to be released before the next crop.');
assert(pdfFactsProvider.includes("provider: 'pdfjs-v1'"), 'Expected a versioned PDF.js facts handoff provider.');
assert(pdfFactsProvider.includes('sourceSha256 = await sha256Hex(bytes)'), 'Expected browser facts to be cryptographically tied to their source PDF.');
assert(pdfFactsProvider.includes('includeMarkedContent: true'), 'Expected marked-content boundaries to be retained alongside browser text spans.');
assert(pdfFactsProvider.includes('page.getStructTree()'), 'Expected PDF.js tagged structure observations when available.');
assert(pdfFactsProvider.includes('DEFAULT_MAX_HANDOFF_BYTES'), 'Expected browser PDF facts to have a bounded serialized handoff.');
assert(pdfFactsProvider.includes('startPage = 1') && pdfFactsProvider.includes('maxPages = Number.POSITIVE_INFINITY'), 'Expected consumers to request PDF.js facts incrementally by page range.');
