#!/usr/bin/env node

import assert from 'node:assert/strict';
import {
  renderPdfFormRequests,
  renderPdfFormRequestsIncrementally,
} from '../pandoc-showcase/pdfjs-form-rasterizer.mjs';

const canvases = [];
globalThis.window = {
  document: {
    createElement(name) {
      assert.equal(name, 'canvas');
      const context = {
        fillStyle: '',
        fillRect() {},
        clearRect() {},
      };
      const canvas = {
        width: 0,
        height: 0,
        getContext() { return context; },
        toBlob(callback) { callback(new Blob([new Uint8Array([1, 2, 3])], { type: 'image/png' })); },
      };
      canvases.push(canvas);
      return canvas;
    },
  },
};

let renderCalls = 0;
let cleanupCalls = 0;
let destroyedDocuments = 0;
const pdfjsModule = {
  AnnotationMode: { DISABLE: 0 },
  getDocument({ data }) {
    assert(data instanceof Uint8Array);
    return {
      promise: Promise.resolve({
        numPages: 1,
        async getPage() {
          return {
            getViewport() {
              return {
                convertToViewportRectangle([x1, y1, x2, y2]) { return [x1, y1, x2, y2]; },
              };
            },
            render() {
              renderCalls += 1;
              return { promise: Promise.resolve() };
            },
            cleanup() { cleanupCalls += 1; },
          };
        },
        async destroy() { destroyedDocuments += 1; },
      }),
    };
  },
};

const requests = [1, 2].map((number) => ({
  id: `form-${number}`,
  path: 'fixture.pdf',
  page: 1,
  bbox: { x1: 10, y1: 10, x2: 110, y2: 70 },
}));
const options = {
  filesByPath: new Map([['fixture.pdf', new TextEncoder().encode('%PDF fixture')]]),
  requests,
  pdfjs: {},
  pdfjsModule,
};

const iterator = renderPdfFormRequestsIncrementally(options);
const first = await iterator.next();
assert.equal(first.value.requestId, 'form-1');
assert.equal(renderCalls, 1, 'The second crop must not render before the first can be acknowledged.');
assert.equal(canvases[0].width, 0, 'The first canvas backing store must be released before yielding its PNG.');
assert.equal(canvases[0].height, 0);

const second = await iterator.next();
assert.equal(second.value.requestId, 'form-2');
assert.equal(renderCalls, 2);
assert.equal(canvases[1].width, 0);
assert.equal((await iterator.next()).done, true);
assert.equal(destroyedDocuments, 1);
assert.equal(cleanupCalls, 2);

const collected = await renderPdfFormRequests({ ...options, requests: requests.slice(0, 1) });
assert.equal(collected.length, 1, 'The compatibility wrapper must still collect results for bounded static previews.');
assert.equal(collected[0].bytes.length, 3);
assert.equal(destroyedDocuments, 2);

const renderCallsBeforeExhaustedBudget = renderCalls;
const exhausted = await renderPdfFormRequests({
  ...options,
  requests: requests.slice(0, 1),
  maxTotalImageBytes: 0,
});
assert.match(exhausted[0].error, /total image-byte budget/);
assert.equal(exhausted[0].budgetExhausted, 'image-bytes');
assert.equal(renderCalls, renderCallsBeforeExhaustedBudget, 'An exhausted image-byte budget must stop before painting and encoding another page.');

const renderCallsBeforeResidualMiss = renderCalls;
const residualMiss = await renderPdfFormRequests({
  ...options,
  maxTotalImageBytes: 2,
});
assert.equal(residualMiss.length, 2);
assert.match(residualMiss[0].error, /total image-byte budget/);
assert.match(residualMiss[1].error, /total image-byte budget/);
assert.equal(residualMiss[0].budgetExhausted, 'image-bytes');
assert.equal(residualMiss[1].budgetExhausted, 'image-bytes');
assert.equal(renderCalls, renderCallsBeforeResidualMiss + 1, 'One oversized encode must exhaust the residual budget before later requests paint.');

console.log('PDF Form rasterizer checks passed.');
