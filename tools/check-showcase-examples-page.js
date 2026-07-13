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
assert(html.includes('Full showcase (desktop)'), 'Expected a link back to the full desktop showcase.');
assert(!html.includes('Load selected example'), 'Selection changes should load their example without a separate load button.');
assert(/<button[^>]*\bid="previous-example"[^>]*\baria-label="Previous example"[^>]*>[\s\S]*?←/.test(html), 'Expected an accessible previous-example arrow.');
assert(/<button[^>]*\bid="next-example"[^>]*\baria-label="Next example"[^>]*>[\s\S]*?→/.test(html), 'Expected an accessible next-example arrow.');
assert(fullShowcase.includes('href="examples.html"'), 'The full showcase should link to the lightweight browser.');
assert(css.includes('@media (max-width: 620px)'), 'Expected dedicated mobile layout rules.');
assert(css.includes('min-height: 44px'), 'Expected touch-sized controls.');
assert(/\.examples-shell\s*\{\s*width:\s*100%;/.test(css), 'Expected the lightweight browser to use the full available width.');
assert(js.includes("const catalogUrl = 'examples-index.json';"), 'Expected the compact example catalogue.');
assert(!js.includes('manifest.json'), 'The lightweight page must not fetch the full manifest.');
assert(js.includes("frame.removeAttribute('src');"), 'Expected prior iframe documents to be unloaded.');
assert(js.includes("frame.src = 'about:blank';"), 'Expected the iframe to clear before each requested result.');
assert(js.includes('frame.src = view.path;'), 'Expected the viewer to load only the selected result.');
assert(js.includes('automaticViewMaxBytes'), 'Expected next/previous navigation to respect the mobile size limit.');
assert(/formatFilter\.addEventListener\('change',[\s\S]*?loadSelectedExample\(\);/.test(js), 'Changing the format should load the selected example.');
assert(/examplePicker\.addEventListener\('change',[\s\S]*?loadSelectedExample\(\);/.test(js), 'Changing the example should load it immediately.');
assert(/button\.addEventListener\('click',[\s\S]*?state\.view = nextView;[\s\S]*?loadSelectedExample\(\);/.test(js), 'Changing the rendered view should load it immediately.');

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
    assert(view.path === (fullView.path || ''), example.id + ' ' + viewName + ' path diverged from the full manifest.');
    assert(view.ok === (fullView.ok === true), example.id + ' ' + viewName + ' status diverged from the full manifest.');
    if (view.ok) {
      const outputPath = siteFile(view.path);
      assert(fs.existsSync(outputPath), example.id + ' ' + viewName + ' output file is missing.');
      if (fs.existsSync(outputPath)) {
        assert(view.bytes === fs.statSync(outputPath).size, example.id + ' ' + viewName + ' byte count is stale.');
      }
    }
  }

  const php = example.views && example.views.phpHtml;
  if (php && php.ok && php.bytes > 0 && php.bytes <= index.automaticViewMaxBytes) {
    automaticPhpExamples += 1;
  }
}

assert(automaticPhpExamples >= 80, 'Expected a broad set of small PHP examples for automatic mobile browsing.');
