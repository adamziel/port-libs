#!/usr/bin/env node

import assert from 'node:assert/strict';
import { createHash, webcrypto } from 'node:crypto';
import { collectPdfJsFacts } from '../pandoc-showcase/pdfjs-facts-provider.mjs';

if (!globalThis.crypto) globalThis.crypto = webcrypto;

const source = new TextEncoder().encode('%PDF-1.4\nPDF.js facts fixture');
let destroyed = false;
let cleanedPages = 0;
const progress = [];
const pdfjsModule = {
  getDocument(options) {
    assert(options.data instanceof Uint8Array);
    assert.equal(options.isEvalSupported, false);
    return {
      promise: Promise.resolve({
        numPages: 2,
        async getPage(pageNumber) {
          return {
            async getTextContent(options) {
              assert.equal(options.includeMarkedContent, true);
              return {
                items: [
                  { type: 'beginMarkedContent', id: `mcid-${pageNumber}` },
                  {
                    str: pageNumber === 1 ? 'Alpha' : 'Beta',
                    dir: 'ltr',
                    transform: [12, 0, 0, 12, 72, 500],
                    width: 34,
                    height: 12,
                    fontName: 'f1',
                    hasEOL: true,
                  },
                ],
                styles: { f1: { fontFamily: 'sans-serif', vertical: false, ascent: 0.8, descent: -0.2 } },
              };
            },
            async getStructTree() {
              return { role: 'Document', children: [{ role: 'P', id: `mcid-${pageNumber}` }] };
            },
            getViewport() {
              return { width: 400, height: 600, rotation: 0, viewBox: [0, 0, 400, 600] };
            },
            cleanup() { cleanedPages += 1; },
          };
        },
        async destroy() { destroyed = true; },
      }),
    };
  },
};

const facts = await collectPdfJsFacts({
  source,
  pdfjsModule,
  onProgress(item) { progress.push(item); },
});
assert.equal(facts.schemaVersion, 1);
assert.equal(facts.provider, 'pdfjs-v1');
assert.equal(facts.sourceSha256, createHash('sha256').update(source).digest('hex'));
assert.equal(facts.pageCount, 2);
assert.deepEqual(facts.pages.map((page) => page.spans[0].text), ['Alpha', 'Beta']);
assert.equal(facts.pages[0].spans[0].hasEol, true);
assert.equal(facts.pages[0].markedContent[0].id, 'mcid-1');
assert.equal(facts.pages[1].structure.children[0].role, 'P');
assert.equal(facts.failures.length, 0);
assert.equal(cleanedPages, 2);
assert.equal(destroyed, true);
assert(progress.some(({ label }) => label.includes('page 2 of 2')));

const secondPageOnly = await collectPdfJsFacts({
  source,
  pdfjsModule,
  startPage: 2,
  maxPages: 1,
});
assert.deepEqual(secondPageOnly.range, { startPage: 2, endPage: 2 });
assert.equal(secondPageOnly.pageCount, 2);
assert.deepEqual(secondPageOnly.pages.map((page) => page.spans[0].text), ['Beta']);

const bounded = await collectPdfJsFacts({
  source,
  pdfjsModule,
  maxTextSpans: 1,
});
assert.equal(bounded.pages.length, 1);
assert.equal(bounded.failures.length, 1);
assert(bounded.failures[0].reason.includes('safety budget'));

await assert.rejects(
  collectPdfJsFacts({ source, pdfjsModule, maxSourceBytes: 8 }),
  /browser facts safety limit/,
);

await assert.rejects(
  collectPdfJsFacts({ source, pdfjs: {} }),
  /provider assets are unavailable/,
);

console.log('PDF.js facts provider checks passed.');
