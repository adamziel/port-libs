#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const jsPath = path.join(root, 'pandoc-showcase', 'playground-converter.js');
const htmlPath = path.join(root, 'pandoc-showcase', 'playground-converter.html');
const js = fs.readFileSync(jsPath, 'utf8');
const html = fs.readFileSync(htmlPath, 'utf8');

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
assert(html.includes('playground-converter.js?v=jbig2-raster-20260709'), 'Expected cache-busted Playground converter script URL.');
assert(js.includes('pdf-jbig2-rasterizer.mjs'), 'Expected the browser JBIG2 rasterizer to be loaded for PDF imports.');
assert(js.includes('pdfRasterImages'), 'Expected browser-decoded PDF rasters to be included in the import payload.');
