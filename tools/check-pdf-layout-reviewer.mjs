#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const site = path.join(root, 'pandoc-showcase');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'tools/pdf-layout-corpus-manifest.json'), 'utf8'));
const html = fs.readFileSync(path.join(site, 'pdf-layout-corpus.html'), 'utf8');
const css = fs.readFileSync(path.join(site, 'pdf-layout-review.css'), 'utf8');
const js = fs.readFileSync(path.join(site, 'pdf-layout-review.js'), 'utf8');
const errors = [];

function assert(condition, message) {
  if (!condition) errors.push(message);
}

function siteFile(relativePath) {
  const absolutePath = path.resolve(site, relativePath);
  assert(absolutePath === site || absolutePath.startsWith(`${site}${path.sep}`), `Reviewer path escapes the showcase directory: ${relativePath}`);
  return absolutePath;
}

assert(Array.isArray(manifest) && manifest.length >= 10, 'The visual reviewer needs at least 10 PDF corpus documents.');
const ids = new Set();
const allowedPublicHosts = new Set(['raw.githubusercontent.com', 'vdl.org.au']);
for (const entry of Array.isArray(manifest) ? manifest : []) {
  assert(typeof entry.id === 'string' && entry.id.length > 0, 'Every corpus entry needs an ID.');
  assert(!ids.has(entry.id), `Duplicate corpus ID: ${entry.id}`);
  ids.add(entry.id);
  assert(typeof entry.source === 'string' && entry.source.length > 0, `${entry.id} needs a human-readable public source.`);
  assert(typeof entry.url === 'string' && entry.url.startsWith('https://'), `${entry.id} needs an HTTPS public source URL.`);
  try {
    const sourceUrl = new URL(entry.url);
    assert(allowedPublicHosts.has(sourceUrl.hostname), `${entry.id} uses an unreviewed corpus host: ${sourceUrl.hostname}`);
    assert(!sourceUrl.username && !sourceUrl.password, `${entry.id} source URL must not contain credentials.`);
  } catch {
    assert(false, `${entry.id} has an invalid source URL.`);
  }
  assert(entry.success && typeof entry.success === 'object', `${entry.id} needs encoded visual success criteria.`);
}

assert((html.match(/<iframe\b/g) || []).length === 1, 'The reviewer must contain exactly one converted-preview iframe.');
assert(!/<iframe\b[^>]*\bsrc=/.test(html), 'The reviewer must not eagerly load its converted iframe in static HTML.');
assert(html.includes('id="original-viewer"') && html.includes('id="original-pages"'), 'The reviewer needs a lazy PDF.js original-document canvas host.');
assert(html.includes('id="review-picker"'), 'The reviewer needs its document picker.');
assert(html.includes('id="review-previous"') && html.includes('aria-label="Previous document"'), 'The reviewer needs an accessible previous control.');
assert(html.includes('id="review-next"') && html.includes('aria-label="Next document"'), 'The reviewer needs an accessible next control.');
assert(html.includes('id="review-download"') && html.includes('Download original'), 'The reviewer needs a Download original control.');
assert(html.includes('id="review-detail"') && html.includes('Open detail tabs'), 'The reviewer needs a link to the three detailed conversion tabs.');
assert(html.includes('data-review-view="compare"') && html.includes('data-review-view="converted"') && html.includes('data-review-view="original"'), 'The reviewer needs compare, converted, and original views.');
assert(html.includes('data-verdict="pass"') && html.includes('data-verdict="fail"'), 'The reviewer needs durable pass/fail review controls.');
assert(html.includes('id="quality-criteria"') && html.includes('id="quality-summary"'), 'The reviewer needs visible automatic success criteria.');
assert(html.includes('sandbox="allow-same-origin"'), 'The converted iframe must be inspectable without allowing scripts.');

const dataMatch = html.match(/<script id="pdf-layout-review-data" type="application\/json">([\s\S]*?)<\/script>/);
assert(Boolean(dataMatch), 'The reviewer is missing its embedded corpus catalogue.');
let data = null;
if (dataMatch) {
  try {
    data = JSON.parse(dataMatch[1]);
  } catch (error) {
    assert(false, `The embedded reviewer catalogue is invalid JSON: ${error.message}`);
  }
}
if (data) {
  assert(Array.isArray(data.examples) && data.examples.length === manifest.length, 'The embedded reviewer catalogue must cover the complete corpus.');
  for (let index = 0; index < (data.examples || []).length; index += 1) {
    const example = data.examples[index];
    const sourceEntry = manifest[index];
    assert(example.id === `pdf-layout-${sourceEntry.id}`, `${sourceEntry.id} reviewer ID diverged from the manifest.`);
    assert(example.sourceUrl === sourceEntry.url, `${sourceEntry.id} reviewer provenance diverged from the manifest.`);
    assert(/^samples\/[^/]+\.pdf$/i.test(example.samplePath), `${example.id} has an unexpected original path: ${example.samplePath}`);
    assert(/^outputs\/[^/]+\/wordpress-blocks-preview\.html$/.test(example.previewPath), `${example.id} has an unexpected converted preview path: ${example.previewPath}`);
    assert(fs.existsSync(siteFile(example.samplePath)), `${example.id} original PDF is missing.`);
    assert(fs.existsSync(siteFile(example.previewPath)), `${example.id} converted preview is missing.`);
  }
}

const combined = `${html}\n${JSON.stringify(data || {})}`;
for (const privateMarker of ['/Users/', '/private/tmp/', 'file://', 'localhost', '127.0.0.1', '\\\\Users\\']) {
  assert(!combined.includes(privateMarker), `The public reviewer leaks a local/private path marker: ${privateMarker}`);
}

assert(css.includes('height: 100svh'), 'The reviewer must fill the viewport.');
assert(css.includes('z-index: 10; isolation: isolate; transform: translateZ(0)')
  && css.includes('z-index: 0; isolation: isolate; contain: paint'), 'The opaque reviewer header and PDF workspace must stay in separate paint layers.');
assert(css.includes('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)'), 'The comparison workspace must split the full width evenly.');
assert(css.includes('@media (max-width: 820px)'), 'The reviewer needs a narrow-screen layout.');
assert(css.includes('.reviewer[data-active-view="converted"] [data-pane="original"]'), 'Converted-only view must remove the original pane from layout.');
assert(js.includes("activeView = matchMedia('(max-width: 820px)').matches ? 'converted' : 'compare'"), 'Mobile must default to the lightweight converted-only view.');
assert(js.includes("import('./vendor/pdfjs/pdf.min.mjs')") && js.includes('pdf.worker.min.mjs'), 'Original documents must use the bundled PDF.js build.');
assert(js.includes("cMapUrl: new URL('vendor/pdfjs/cmaps/', base).href") && js.includes("wasmUrl: new URL('vendor/pdfjs/wasm/', base).href"), 'The original renderer must reuse bundled PDF.js support assets.');
assert(js.includes('function cancelOriginalRender(clear = true)') && js.includes('originalPages.replaceChildren()'), 'Converted-only view must destroy and clear the original PDF renderer.');
assert(js.includes("originalFrame.dataset.loadedPath = path") && js.includes("canvas.className = 'original-page'"), 'Original view must expose completed PDF canvas rendering to the reviewer.');
assert(js.includes("url.searchParams.set('example', selectedExample().id)"), 'Reviewer selections must update a shareable example URL.');
assert(js.includes("url.searchParams.set('view', activeView)"), 'Reviewer views must update a shareable URL.');
assert(js.includes('history.replaceState'), 'Reviewer navigation must update its URL without a reload.');
assert(js.includes("const storagePrefix = 'port-libs:pdf-layout-review:'"), 'Reviewer verdicts must use durable per-document storage.');
assert(js.includes('localStorage.setItem') && js.includes('localStorage.getItem'), 'Reviewer verdicts must persist across navigation.');
assert(js.includes("if (event.key === 'ArrowLeft')") && js.includes("if (event.key === 'ArrowRight')"), 'Reviewer needs keyboard previous/next navigation.');
assert(js.includes('requiredText') && js.includes('orderedText'), 'Reviewer must evaluate content-presence and reading-order criteria.');
assert(js.includes("(Array.isArray(expected) ? expected : [expected]).join(' · ')")
  && js.includes("(Array.isArray(expected) ? expected : [expected]).join(' → ')"), 'Criterion labels must not apply array methods to numeric thresholds.');
assert(js.includes('noSpacedGlyphRuns') && js.includes('readablePdfFills'), 'Reviewer must expose spacing and PDF-fill readability regressions.');

const multicolumn = manifest.find((entry) => entry.id === 'unstructured-multicolumn');
const formula = manifest.find((entry) => entry.id === 'docling-code-formula');
const theatre = manifest.find((entry) => entry.id === 'vdl-theatre-script');
assert(multicolumn?.success?.orderedText?.includes('1 Introduction'), 'Multicolumn review must pin front-matter-to-introduction reading order.');
assert(multicolumn?.success?.requiredText?.includes('Abstract'), 'Multicolumn review must require its abstract.');
assert(formula?.success?.requiredText?.includes('a2 + 8 = 12'), 'Formula review must require its real formula text.');
assert(theatre?.success?.minDialogueParagraphs >= 3, 'Theatre review must require editable dialogue paragraphs.');
assert(theatre?.success?.maxCodeBlocks === 0, 'Theatre review must reject code blocks.');
assert(theatre?.success?.maxLineOrientedBlocks === 0, 'Theatre review must reject preformatted verse blocks.');
if (theatre) {
  const theatrePreview = fs.readFileSync(siteFile(`outputs/pdf-layout-${theatre.id}/wordpress-blocks-preview.html`), 'utf8');
  assert(theatrePreview.includes('<strong>CHARACTER 1</strong><br/>'), 'Theatre preview must keep speaker cues above dialogue text.');
  assert(!theatrePreview.includes('<!-- wp:verse -->') && !theatrePreview.includes('<!-- wp:code -->') && !theatrePreview.includes('<pre'), 'Theatre preview must not contain code-like preformatted blocks.');
}

if (errors.length > 0) {
  for (const error of errors) console.error(`FAIL: ${error}`);
  process.exitCode = 1;
} else {
  console.log(`PDF layout reviewer check passed for ${manifest.length} public corpus documents.`);
}
