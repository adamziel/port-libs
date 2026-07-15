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
assert(js.includes('maxPngBytes: remainingBytes'), 'Expected browser PDF decoders to share one raster byte budget.');
assert(js.includes('pdfRasterImages'), 'Expected browser-decoded PDF rasters to be included in the import payload.');
assert(js.includes("import { renderPdfFormRequests } from './pdfjs-form-rasterizer.mjs';"), 'Expected the shared PDF.js Form renderer to be loaded by the Playground importer.');
assert(js.includes("import { collectPdfJsFacts } from './pdfjs-facts-provider.mjs';"), 'Expected bounded PDF.js text and structure facts in the Playground importer.');
assert(js.includes('pdfBrowserFacts[entry.path] = facts;'), 'Expected Playground PDF.js facts to be associated with their source path.');
assert(js.includes("playgroundPluginRequest('/imports', stagedUpload.payload)"), 'Expected imports to start through the persisted import-job endpoint.');
assert(js.includes('function stageUploadInPlayground(client, upload, reportProgress = () => {})'), 'Expected Playground sources to be staged instead of base64-encoded into the REST body.');
assert(js.includes('stagedFiles,'), 'Expected folder imports to send a staged-file manifest rather than a JSON byte collection.');
assert(!js.includes('function payloadFromUpload('), 'The Playground importer must not base64-encode every selected source before creating a job.');
assert(js.includes('const pdfRasterSourceByteLimit = 24 * 1024 * 1024;'), 'Expected optional PDF raster decoding to skip over-limit sources before allocating decoder state.');
assert(js.includes('/rendered-media'), 'Expected browser-rendered PDF figures to be sent back to the import job.');
assert(js.includes('/render-source/'), 'Expected an expanded ZIP PDF source to be fetched only for its outstanding renderer request.');
assert(js.includes('function advanceImportJob(jobId, reportJob)'), 'Expected imports to advance through bounded server work units.');
assert(js.includes("undefined, 'GET'"), 'Expected the UI to poll the persisted job while a conversion request is running.');
assert(js.includes("php: '8.4'"), 'Expected Playground to use PHP 8.4 for EPUB and HTML imports that need Dom\\HTMLDocument.');
assert(!html.includes('id="format-input"'), 'Document type must not be exposed as a client-side form field.');
assert(!js.includes('formatByExtension'), 'Document type inference must be authoritative on the server, not duplicated in the browser.');
assert(!js.includes('format: upload.format'), 'The browser must not send a user-controlled document type hint.');
assert(js.includes('function isLikelyPdfFile(file)'), 'Expected PDF-only browser preparation to remain available without choosing a document type.');
assert(!js.includes('unsupportedMessage'), 'Supported document formats must not carry a client-side blanket rejection.');

assert(adminImporter.includes("new URL('./pdf-jbig2-rasterizer.mjs', import.meta.url)"), 'Expected the WordPress admin importer to load the bundled JBIG2 rasterizer relative to its module.');
assert(adminImporter.includes("import { collectPdfJsFacts } from './pdfjs-facts-provider.mjs';"), 'Expected the WordPress admin importer to load the bundled PDF.js facts provider.');
assert(adminImporter.includes('pdfBrowserFacts[entry.path] = facts;'), 'Expected admin PDF.js facts to be associated with their source path.');
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
assert(adminImporter.includes('The importer stopped retrying automatically to avoid duplicating work.'), 'Expected advance recovery to stop with an actionable message instead of retrying forever.');
assert(adminImporter.includes('function updateSelection({ clearResult = false } = {})'), 'Expected the admin importer to distinguish a new selection from ending an active import.');
assert(adminImporter.includes('if (clearResult) {'), 'Expected completed admin import links to remain visible until the user chooses another file.');
assert(plugin.includes("wp_enqueue_script_module('port-libs-importer'"), 'Expected modern WordPress admin installs to enqueue the ESM importer through the native module API.');
assert(plugin.includes('function plpc_print_importer_configuration_script(): void'), 'Expected importer configuration to be emitted independently of a classic script handle.');
assert(plugin.includes('plpc_print_importer_configuration_script();'), 'Expected the configuration script to appear before the deferred importer module runs.');
assert(!plugin.includes("@ini_set('memory_limit', '512M')"), 'The plugin must not override a host memory limit while an import is running.');
assert(!plugin.includes('@set_time_limit(120)'), 'The plugin must not override a host execution-time limit while an import is running.');
assert(plugin.includes('function plpc_import_request_deadline(): ?float'), 'Expected the server to reserve time for a durable import checkpoint before PHP reaches its limit.');
assert(plugin.includes('function plpc_import_job_checkpoint_for_deadline('), 'Expected conversion phase progress to yield safely before the server execution deadline.');
assert(plugin.includes('PLPC_IMPORT_JOB_MAX_DEADLINE_YIELDS_PER_DOCUMENT'), 'Expected deadline handoffs to be capped instead of spinning forever.');
assert(plugin.includes('function plpc_import_job_store_browser_facts('), 'Expected browser PDF facts to be stored outside the WordPress options table.');
assert(plugin.includes('function plpc_import_job_load_browser_facts('), 'Expected durable browser PDF facts to be available to resumed imports.');
assert(plugin.includes('function plpc_import_job_store_pdf_chunk('), 'Expected every PDF page range to become a durable facts checkpoint.');
assert(plugin.includes('function plpc_import_job_load_pdf_chunk('), 'Expected resumed imports to verify and load durable page facts.');
assert(plugin.includes('function plpc_import_job_merge_pdf_document_facts('), 'Expected page facts to be integrity-checked into one complete document snapshot.');
assert(plugin.includes('function plpc_import_job_load_pdf_document_facts('), 'Expected global PDF semantics to consume the durable complete-document facts snapshot.');
assert(plugin.includes('function plpc_import_job_prepare_pdf_final_bundle('), 'Expected one whole-document semantic pass before WordPress publication.');
assert(plugin.includes('function plpc_import_job_store_pdf_final_bundle('), 'Expected the globally finalized private block and media bundle to be durable.');
assert(plugin.includes('function plpc_import_job_load_pdf_final_bundle('), 'Expected publication retries to reuse the durable final PDF bundle.');
assert(plugin.includes('function plpc_import_job_recover_interrupted_document('), 'Expected hard worker terminations to have a bounded durable recovery path.');
assert(pdfFormRasterizer.includes('const DEFAULT_MAX_SOURCE_BYTES = 24 * 1024 * 1024;'), 'Expected PDF.js figure rendering to cap source bytes before copying a large PDF in the browser.');
assert(pdfFormRasterizer.includes('pdfBytes(filesByPath.get(path), maxSourceBytes)'), 'Expected the PDF.js figure renderer to enforce its source-byte cap for every requested crop.');
assert(pdfFormRasterizer.includes('maxTotalPixels = Number.POSITIVE_INFINITY') && pdfFormRasterizer.includes('totalPixelsLimit - renderedPixels'), 'Expected callers to be able to bound aggregate PDF figure pixels without changing the importer default.');
assert(pdfFormRasterizer.includes('maxTotalImageBytes = Number.POSITIVE_INFINITY') && pdfFormRasterizer.includes('totalImageBytesLimit - renderedImageBytes'), 'Expected callers to be able to bound aggregate PDF figure bytes without changing the importer default.');
assert(pdfFormRasterizer.includes('throwIfAborted(signal)'), 'Expected static preview cancellation to release PDF.js work between figure crops.');
assert(pdfFormRasterizer.includes('The PDF is too large to render figures safely in this browser.'), 'Expected an over-limit PDF figure to fall back to the text import instead of crashing the browser.');
assert(pdfFormRasterizer.includes("typeof viewport.convertToViewportRectangle === 'function'"), 'Expected PDF.js Form cropping to retain compatibility with PDF.js versions that removed rectangle conversion.');
assert(pdfFormRasterizer.includes('viewport.convertToViewportPoint(bbox.x1, bbox.y1)'), 'Expected PDF.js Form cropping to fall back to point conversion on current PDF.js releases.');
assert(pdfFactsProvider.includes("provider: 'pdfjs-v1'"), 'Expected a versioned PDF.js facts handoff provider.');
assert(pdfFactsProvider.includes('sourceSha256 = await sha256Hex(bytes)'), 'Expected browser facts to be cryptographically tied to their source PDF.');
assert(pdfFactsProvider.includes('includeMarkedContent: true'), 'Expected marked-content boundaries to be retained alongside browser text spans.');
assert(pdfFactsProvider.includes('page.getStructTree()'), 'Expected PDF.js tagged structure observations when available.');
assert(pdfFactsProvider.includes('DEFAULT_MAX_HANDOFF_BYTES'), 'Expected browser PDF facts to have a bounded serialized handoff.');
