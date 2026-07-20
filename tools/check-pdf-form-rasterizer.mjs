#!/usr/bin/env node

import assert from 'node:assert/strict';
import { createHash, webcrypto } from 'node:crypto';
import {
  pdfFormRendererResourceSnapshot,
  renderPdfFormRequests,
  renderPdfFormRequestsIncrementally,
  renderPdfPageRasterRequests,
  renderPdfPageRasterRequestsIncrementally,
} from '../pandoc-showcase/pdfjs-form-rasterizer.mjs';

if (!globalThis.crypto) {
  globalThis.crypto = webcrypto;
}

const canvases = [];
let canvasBlobBytes = () => new Uint8Array([1, 2, 3]);
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
        toBlob(callback) { callback(new Blob([canvasBlobBytes(canvas)], { type: 'image/png' })); },
      };
      context.canvas = canvas;
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
assert.deepEqual(
  Object.fromEntries(Object.entries(pdfFormRendererResourceSnapshot()).filter(([key]) => key.startsWith('active'))),
  {
    activeLoadingTasks: 0,
    activeDocuments: 1,
    activePages: 0,
    activeCanvases: 0,
    activeRenderTasks: 0,
  },
  'A paused iterator must report its live document without claiming that completed page resources remain active.',
);

const second = await iterator.next();
assert.equal(second.value.requestId, 'form-2');
assert.equal(renderCalls, 2);
assert.equal(canvases[1].width, 0);
assert.equal((await iterator.next()).done, true);
assert.equal(destroyedDocuments, 1);
assert.equal(cleanupCalls, 2);
assert.deepEqual(
  Object.fromEntries(Object.entries(pdfFormRendererResourceSnapshot()).filter(([key]) => key.startsWith('active'))),
  {
    activeLoadingTasks: 0,
    activeDocuments: 0,
    activePages: 0,
    activeCanvases: 0,
    activeRenderTasks: 0,
  },
  'Completing the incremental renderer must release every owned PDF.js resource.',
);

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

let failedLoadingTaskDestroyCalls = 0;
const failedLoad = await renderPdfFormRequests({
  ...options,
  requests: requests.slice(0, 1),
  pdfjsModule: {
    getDocument() {
      return {
        promise: Promise.reject(new Error('Synthetic PDF loading failure.')),
        async destroy() { failedLoadingTaskDestroyCalls += 1; },
      };
    },
  },
});
assert.match(failedLoad[0].error, /Synthetic PDF loading failure/);
assert.equal(failedLoadingTaskDestroyCalls, 1, 'A rejected PDF.js loading task must be destroyed before the iterator continues.');

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function pageRasterDimensions(pageBox, pageRotation) {
  const pageWidth = pageBox[2] - pageBox[0];
  const pageHeight = pageBox[3] - pageBox[1];
  const displayWidth = [90, 270].includes(pageRotation) ? pageHeight : pageWidth;
  const displayHeight = [90, 270].includes(pageRotation) ? pageWidth : pageHeight;
  const scale = Math.min(
    2,
    8192 / displayWidth,
    8192 / displayHeight,
    Math.sqrt(16_000_000 / (displayWidth * displayHeight)),
  );

  return {
    width: Math.ceil(displayWidth * scale),
    height: Math.ceil(displayHeight * scale),
  };
}

function pageRasterRequestDigest(request) {
  return sha256(new TextEncoder().encode([
    'pdf-page-raster-request-v1',
    `method=${request.method}`,
    `sourceSha256=${request.sourceSha256}`,
    `page=${request.page}`,
    `pageObject=${request.pageObject}`,
    `pageBox=${request.pageBox.map((value) => value.toFixed(6)).join(',')}`,
    `pageBoxSource=${request.pageBoxSource}`,
    `pageRotation=${request.pageRotation}`,
    `width=${request.width}`,
    `height=${request.height}`,
    `mimeType=${request.mimeType}`,
  ].join('\n')));
}

function sealPageRasterRequest(request) {
  const requestDigest = pageRasterRequestDigest(request);

  return {
    ...request,
    id: `pdf-page-raster-${requestDigest.slice(0, 32)}`,
    requestDigest,
  };
}

function makePageRasterRequest({
  sourceSha256,
  page,
  pageObject,
  pageBox,
  pageBoxSource,
  pageRotation,
}) {
  const dimensions = pageRasterDimensions(pageBox, pageRotation);

  return sealPageRasterRequest({
    version: 1,
    method: 'pdfjs-whole-page-raster',
    sourceSha256,
    page,
    pageObject,
    pageBox,
    pageBoxSource,
    pageRotation,
    ...dimensions,
    mimeType: 'image/png',
  });
}

function pageRasterProofDigest(requestDigest, byteLength, imageSha256) {
  return sha256(new TextEncoder().encode([
    'pdf-page-raster-proof-v1',
    `requestDigest=${requestDigest}`,
    `byteLength=${byteLength}`,
    `sha256=${imageSha256}`,
  ].join('\n')));
}

function uint32(value) {
  return new Uint8Array([
    (value >>> 24) & 0xff,
    (value >>> 16) & 0xff,
    (value >>> 8) & 0xff,
    value & 0xff,
  ]);
}

function concatenateBytes(parts) {
  const bytes = new Uint8Array(parts.reduce((total, part) => total + part.byteLength, 0));
  let offset = 0;
  for (const part of parts) {
    bytes.set(part, offset);
    offset += part.byteLength;
  }

  return bytes;
}

function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) {
      crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
    }
  }

  return (crc ^ 0xffffffff) >>> 0;
}

function pngChunk(type, data) {
  const typeBytes = new TextEncoder().encode(type);

  return concatenateBytes([
    uint32(data.byteLength),
    typeBytes,
    data,
    uint32(crc32(concatenateBytes([typeBytes, data]))),
  ]);
}

function pngBytes(width, height) {
  const ihdr = concatenateBytes([
    uint32(width),
    uint32(height),
    new Uint8Array([8, 6, 0, 0, 0]),
  ]);

  return concatenateBytes([
    new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10]),
    pngChunk('IHDR', ihdr),
    pngChunk('IDAT', new Uint8Array([0x78, 0x9c, 0x03, 0x00, 0x00, 0x00, 0x00, 0x01])),
    pngChunk('IEND', new Uint8Array()),
  ]);
}

canvasBlobBytes = (canvas) => pngBytes(canvas.width, canvas.height);

const pageSource = new TextEncoder().encode('%PDF whole-page fixture');
const pageSourceSha256 = sha256(pageSource);
const pageRequests = [
  makePageRasterRequest({
    sourceSha256: pageSourceSha256,
    page: 1,
    pageObject: 5,
    pageBox: [0, 0, 100, 200],
    pageBoxSource: 'MediaBox',
    pageRotation: 0,
  }),
  makePageRasterRequest({
    sourceSha256: pageSourceSha256,
    page: 2,
    pageObject: 8,
    pageBox: [10, 20, 210, 120],
    pageBoxSource: 'CropBox',
    pageRotation: 90,
  }),
];
assert.equal(pageSourceSha256, '46672197c7c7c5103f9c5f16793df6a3570d8e0508704e0a48c5f99162bea2ed');
assert.equal(
  pageRequests[0].requestDigest,
  '2eafe689d6acafc259be95434f5d460914744a49788449bf2033f04da7eb736e',
  'The JavaScript request canonicalization must match the PHP digest contract.',
);
assert.equal(
  pageRequests[1].requestDigest,
  '8b7dfe02fdb677e8daa37537ef5bf7d34bb97c056a60eca01aae316dc1a009ea',
  'CropBox and rotation request canonicalization must match the PHP digest contract.',
);
const pageDefinitions = new Map([
  [1, { pageObject: 5, view: [0, 0, 100, 200], rotation: 0 }],
  [2, { pageObject: 8, view: [10, 20, 210, 120], rotation: 90 }],
]);
let pageDocumentLoads = 0;
let pageDocumentDestroys = 0;
let pageCleanupCalls = 0;
let pageRenderCalls = 0;
let pageIdentityMismatch = '';
const pageViewportCalls = [];
const pageRenderOptions = [];
const pageCanvasSizes = [];
const pagePdfjsModule = {
  AnnotationMode: { DISABLE: 17 },
  getDocument(options) {
    pageDocumentLoads += 1;
    assert(options.data instanceof Uint8Array);
    assert.equal(options.enableXfa, false);
    assert.equal(options.isEvalSupported, false);
    return {
      promise: Promise.resolve({
        numPages: 2,
        async getPage(pageNumber) {
          const definition = pageDefinitions.get(pageNumber);
          assert(definition);
          const pageWidth = definition.view[2] - definition.view[0];
          const pageHeight = definition.view[3] - definition.view[1];
          return {
            pageNumber: pageIdentityMismatch === 'page-number' ? pageNumber + 1 : pageNumber,
            ref: {
              num: pageIdentityMismatch === 'page-object'
                ? definition.pageObject + 1
                : definition.pageObject,
              gen: 0,
            },
            view: pageIdentityMismatch === 'view'
              ? [definition.view[0], definition.view[1], definition.view[2] + 1, definition.view[3]]
              : [...definition.view],
            rotate: pageIdentityMismatch === 'rotation'
              ? (definition.rotation + 90) % 360
              : definition.rotation,
            getViewport(viewportOptions) {
              pageViewportCalls.push({ pageNumber, ...viewportOptions });
              const rotated = [90, 270].includes(viewportOptions.rotation);
              return {
                width: rotated ? pageHeight : pageWidth,
                height: rotated ? pageWidth : pageHeight,
              };
            },
            render(options) {
              pageRenderCalls += 1;
              pageRenderOptions.push(options);
              pageCanvasSizes.push([
                options.canvasContext.canvas.width,
                options.canvasContext.canvas.height,
              ]);
              return { promise: Promise.resolve() };
            },
            cleanup() { pageCleanupCalls += 1; },
          };
        },
        async destroy() { pageDocumentDestroys += 1; },
      }),
    };
  },
};
const pageOptions = {
  source: pageSource,
  requests: pageRequests,
  pdfjs: {},
  pdfjsModule: pagePdfjsModule,
};

const pageCanvasStart = canvases.length;
const pageIterator = renderPdfPageRasterRequestsIncrementally(pageOptions);
const firstPage = await pageIterator.next();
const responseKeys = [
  'byteLength',
  'contents',
  'height',
  'method',
  'mimeType',
  'page',
  'pageBox',
  'pageBoxSource',
  'pageObject',
  'pageRotation',
  'proofDigest',
  'requestDigest',
  'requestId',
  'sha256',
  'sourceSha256',
  'version',
  'width',
].sort();
assert.deepEqual(Object.keys(firstPage.value).sort(), responseKeys, 'A page success must have the exact server response field set.');
assert.equal(firstPage.value.requestId, pageRequests[0].id);
assert.equal(firstPage.value.sourceSha256, pageSourceSha256);
assert.equal(firstPage.value.page, 1);
assert.equal(firstPage.value.pageObject, 5);
assert.deepEqual(firstPage.value.pageBox, [0, 0, 100, 200]);
assert.equal(firstPage.value.pageBoxSource, 'MediaBox');
assert.equal(firstPage.value.pageRotation, 0);
assert.equal(firstPage.value.width, 200);
assert.equal(firstPage.value.height, 400);
assert(firstPage.value.contents instanceof Uint8Array);
assert.equal(firstPage.value.byteLength, firstPage.value.contents.byteLength);
assert.equal(firstPage.value.sha256, sha256(firstPage.value.contents));
assert.equal(
  firstPage.value.proofDigest,
  pageRasterProofDigest(
    firstPage.value.requestDigest,
    firstPage.value.byteLength,
    firstPage.value.sha256,
  ),
);
assert.equal(pageRenderCalls, 1, 'The second physical page must wait for acknowledgement of the first.');
assert.deepEqual(pageCanvasSizes[0], [200, 400], 'The page must paint directly into the exact requested dimensions.');
assert.equal(pageRenderOptions[0].annotationMode, 17, 'PDF annotations must be disabled during the page paint.');
assert.deepEqual(pageRenderOptions[0].transform, [2, 0, 0, 2, 0, 0]);
assert.equal(canvases[pageCanvasStart].width, 0);
assert.equal(canvases[pageCanvasStart].height, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 1);
assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeCanvases, 0);

const secondPage = await pageIterator.next();
assert.equal(secondPage.value.requestId, pageRequests[1].id);
assert.deepEqual(secondPage.value.pageBox, [10, 20, 210, 120]);
assert.equal(secondPage.value.pageBoxSource, 'CropBox');
assert.equal(secondPage.value.pageRotation, 90);
assert.equal(secondPage.value.width, 200, 'A quarter-turn must swap the normative display width.');
assert.equal(secondPage.value.height, 400, 'A quarter-turn must swap the normative display height.');
assert.deepEqual(pageViewportCalls[1], { pageNumber: 2, scale: 1, rotation: 90 });
assert.deepEqual(pageCanvasSizes[1], [200, 400]);
assert.equal((await pageIterator.next()).done, true);
assert.equal(pageDocumentLoads, 1);
assert.equal(pageDocumentDestroys, 1);
assert.equal(pageCleanupCalls, 2);
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 0);

const collectedPages = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: pageRequests.slice(0, 1),
});
assert.equal(collectedPages.length, 1, 'The whole-page compatibility wrapper must collect exact responses.');
assert.deepEqual(Object.keys(collectedPages[0]).sort(), responseKeys);
assert.equal(pageDocumentDestroys, 2);

const loadsBeforeTamper = pageDocumentLoads;
const tamperedDigest = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: [{ ...pageRequests[0], requestDigest: '0'.repeat(64) }],
});
assert.match(tamperedDigest[0].error, /digest|invalid|stale/i);
assert.equal(pageDocumentLoads, loadsBeforeTamper, 'A tampered request digest must fail before PDF.js loads the document.');
assert.deepEqual(Object.keys(tamperedDigest[0]).sort(), ['error', 'requestId']);

const staleSourceRequest = sealPageRasterRequest({
  ...pageRequests[0],
  sourceSha256: '0'.repeat(64),
});
const staleSource = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: [staleSourceRequest],
});
assert.match(staleSource[0].error, /invalid|stale/i);
assert.equal(pageDocumentLoads, loadsBeforeTamper, 'A stale source hash must fail before PDF.js loads the document.');

const wrongDimensionRequest = sealPageRasterRequest({
  ...pageRequests[0],
  width: pageRequests[0].width + 1,
});
const wrongDimensions = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: [wrongDimensionRequest],
});
assert.match(wrongDimensions[0].error, /dimensions/i);
assert.equal(pageDocumentLoads, loadsBeforeTamper, 'Re-signed but wrong dimensions must fail before PDF.js loads the document.');

pageIdentityMismatch = 'view';
const rendersBeforeViewMismatch = pageRenderCalls;
const cleanupBeforeViewMismatch = pageCleanupCalls;
const viewMismatch = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: pageRequests.slice(0, 1),
});
pageIdentityMismatch = '';
assert.match(viewMismatch[0].error, /identity|geometry/i);
assert.equal(pageRenderCalls, rendersBeforeViewMismatch, 'A PDF.js page-view mismatch must fail before painting.');
assert.equal(pageCleanupCalls, cleanupBeforeViewMismatch + 1);
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 0);
assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);

for (const mismatch of ['page-number', 'page-object', 'rotation']) {
  pageIdentityMismatch = mismatch;
  const rendersBeforeMismatch = pageRenderCalls;
  const mismatchResult = await renderPdfPageRasterRequests({
    ...pageOptions,
    requests: pageRequests.slice(0, 1),
  });
  assert.match(mismatchResult[0].error, /identity|geometry/i);
  assert.equal(
    pageRenderCalls,
    rendersBeforeMismatch,
    `A PDF.js ${mismatch} mismatch must fail before painting.`,
  );
  assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 0);
  assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);
}
pageIdentityMismatch = '';

const validCanvasBlobBytes = canvasBlobBytes;
canvasBlobBytes = (canvas) => pngBytes(canvas.width + 1, canvas.height);
const rendersBeforeWrongPng = pageRenderCalls;
const wrongPngDimensions = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: pageRequests.slice(0, 1),
});
canvasBlobBytes = validCanvasBlobBytes;
assert.match(wrongPngDimensions[0].error, /wrong-size|invalid/i);
assert.equal(pageRenderCalls, rendersBeforeWrongPng + 1);
assert.deepEqual(Object.keys(wrongPngDimensions[0]).sort(), ['error', 'requestId']);
assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeCanvases, 0);

let failedPageLoadingTaskDestroyCalls = 0;
const unavailablePages = await renderPdfPageRasterRequests({
  ...pageOptions,
  requests: pageRequests.slice(0, 1),
  pdfjsModule: {
    getDocument() {
      return {
        promise: Promise.reject(new Error('Synthetic whole-page PDF loading failure.')),
        async destroy() { failedPageLoadingTaskDestroyCalls += 1; },
      };
    },
  },
});
assert.match(unavailablePages[0].error, /Synthetic whole-page PDF loading failure/);
assert.equal(failedPageLoadingTaskDestroyCalls, 1);
assert.deepEqual(Object.keys(unavailablePages[0]).sort(), ['error', 'requestId']);
assert.equal(pdfFormRendererResourceSnapshot().activeLoadingTasks, 0);

const loadsBeforeEmptyBudget = pageDocumentLoads;
const emptyPageBudget = await renderPdfPageRasterRequests({
  ...pageOptions,
  maxTotalPixels: 0,
});
assert.equal(emptyPageBudget.length, 2);
assert(emptyPageBudget.every((item) => item.budgetExhausted === 'pixels'));
assert.equal(pageDocumentLoads, loadsBeforeEmptyBudget, 'An empty page pixel budget must stop before source parsing.');

const rendersBeforeResidualPageBudget = pageRenderCalls;
const residualPageBudget = await renderPdfPageRasterRequests({
  ...pageOptions,
  maxTotalImageBytes: 1,
});
assert.equal(residualPageBudget.length, 2);
assert(residualPageBudget.every((item) => item.budgetExhausted === 'image-bytes'));
assert.equal(
  pageRenderCalls,
  rendersBeforeResidualPageBudget + 1,
  'One oversized page encode must exhaust the residual byte budget before another page paints.',
);
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 0);
assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeCanvases, 0);

const releaseIterator = renderPdfPageRasterRequestsIncrementally(pageOptions);
const releaseFirst = await releaseIterator.next();
assert.equal(releaseFirst.value.requestId, pageRequests[0].id);
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 1);
await releaseIterator.return();
assert.equal(pdfFormRendererResourceSnapshot().activeDocuments, 0, 'Returning early must destroy the whole-page PDF.js document.');
assert.equal(pdfFormRendererResourceSnapshot().activePages, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeCanvases, 0);
assert.equal(pdfFormRendererResourceSnapshot().activeRenderTasks, 0);

const finalResources = pdfFormRendererResourceSnapshot();
assert.equal(finalResources.activeLoadingTasks, 0);
assert.equal(finalResources.activeDocuments, 0);
assert.equal(finalResources.activePages, 0);
assert.equal(finalResources.activeCanvases, 0);
assert.equal(finalResources.activeRenderTasks, 0);
assert.ok(finalResources.peakDocuments >= 1);
assert.ok(finalResources.peakPages >= 1);
assert.equal(finalResources.peakCanvases, 1, 'Incremental rendering must never own more than one canvas.');
assert.equal(finalResources.peakRenderTasks, 1, 'Incremental rendering must never own more than one render task.');

console.log('PDF Form and whole-page rasterizer checks passed.');
