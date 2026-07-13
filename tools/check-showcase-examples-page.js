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
assert(html.includes('<div class="picker-controls"><h1 class="example-title">Adam&#039;s Pandoc → PHP Port</h1>'), 'Expected the compact title immediately above the picker.');
assert(/<div class="example-toolbar"><button[^>]*\bid="previous-example"[\s\S]*?<select[^>]*\bid="example-picker"[\s\S]*?<a[^>]*\bid="download-source"[\s\S]*?<button[^>]*\bid="next-example"/.test(html), 'Expected the toolbar order to be previous, picker, download, next.');
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
assert(css.includes('grid-template-columns: var(--arrow-width) minmax(0, 1fr) auto var(--arrow-width);'), 'Expected a wide-arrow picker toolbar.');
assert(css.includes('align-self: stretch;'), 'Expected desktop arrows to fill the height of the picker bar.');
assert(css.includes('position: absolute; top: 10px; bottom: 8px;'), 'Expected mobile arrows to fill the picker-and-download bar.');
assert(css.includes('.next-arrow { grid-column: auto; right: 10px; }'), 'Expected the mobile next arrow to remain inside the viewport.');
assert(!css.includes('grid-row: 2 / 5'), 'Arrows must not span the preview area.');
assert(css.includes('font-size: clamp(15px, 1.8vw, 20px)'), 'Expected a compact picker title.');
assert(css.includes('border-radius: 8px 8px 0 0'), 'Expected view controls to look like tabs.');
assert(css.includes('box-shadow: 0 1px 0 var(--paper)'), 'Expected the selected tab to join the preview panel.');
assert(/#example-picker\s*\{[\s\S]*?height: 48px;/.test(css), 'Expected the picker to have a fixed 48px control height.');
assert(/\.download-source\s*\{[\s\S]*?height: 48px;[\s\S]*?border: 1px solid #aeb9c7;[\s\S]*?background: #fff;[\s\S]*?color: var\(--ink\);/.test(css), 'Expected Download original to be a neutral control matching the picker height.');
assert(js.includes("const catalogUrl = 'examples-index.json';"), 'Expected the compact example catalogue.');
assert(js.includes("const defaultView = 'wpBlocks';"), 'Expected WordPress Block markup to be the initial view.');
assert(js.includes('view: defaultView'), 'Expected the state to initialize to the default WordPress view.');
assert(js.includes("const exampleUrlParameter = 'example';"), 'Expected a stable query parameter for linked examples.');
assert(js.includes('new URL(window.location.href)'), 'Expected example links to preserve other URL state safely.');
assert(js.includes('window.history.replaceState'), 'Expected picker navigation to keep the current URL shareable.');
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
