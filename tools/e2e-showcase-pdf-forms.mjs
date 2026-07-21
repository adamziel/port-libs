#!/usr/bin/env node

/**
 * Verify that an audited static PDF showcase uses complete, decoded, published
 * preview assets rather than browser-time PDF rendering or placeholders.
 *
 * This talks directly to an already-running Chrome DevTools endpoint so it has
 * no Playwright or Puppeteer dependency.
 *
 * Usage:
 *   node tools/e2e-showcase-pdf-forms.mjs
 *   node tools/e2e-showcase-pdf-forms.mjs --url https://example.test/examples.html?example=pdf-tracemonkey
 *   node tools/e2e-showcase-pdf-forms.mjs --url https://example.test/examples.html --all-audited
 */

import { createHash } from 'node:crypto';
import process from 'node:process';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html?example=pdf-tracemonkey',
  timeoutMs: 2 * 60 * 1000,
  pollMs: 500,
  expectedImages: null,
  expectedRequests: null,
  expectedExample: 'pdf-tracemonkey',
  allAudited: false,
};

// These are the browser-visible regressions found in the Pages audit. Image
// counts are exact: page composites and exact duplicate crops share one asset,
// while request counts retain every source identity for coverage accounting.
const zeroPlaceholderExpectations = new Map([
  ['pdf-layout-unstructured-ocr-overlay', { images: 1, requests: 1 }],
  ['pdf-layout-docling-right-to-left', { images: 1, requests: 1 }],
  ['pdf-layout-docling-aircraft-handbook', { images: 9, requests: 9 }],
  ['pdf-layout-docling-table-picture-boundary', { images: 1, requests: 2 }],
  ['pdf-layout-mineru-small-ocr', { images: 8, requests: 8 }],
  ['pdf-layout-vdl-theatre-script', { images: 1, requests: 1 }],
  ['pdf-tracemonkey', { images: 8, requests: 8 }],
  ['pdf-grand-canyon-north-rim-map', { images: 2, requests: 16 }],
  ['pdf-archive-motograph-book', { images: 46, requests: 46 }],
  ['pdf-muir-beach-brochure', { images: 6, requests: 6 }],
  ['pdf-quickbooks-invoice-template', { images: 1, requests: 1 }],
]);

const traceMonkeyCaptionPrefixes = new Map([
  ['41', 'Figure 2.'],
  ['118', 'Figure 5.'],
  ['119', 'Figure 6.'],
  ['120', 'Figure 7.'],
  ['154', 'Figure 8.'],
  ['199', 'Figure 11.'],
  ['198', 'Figure 10.'],
  ['200', 'Figure 12.'],
]);

function parseOptions(args) {
  const options = { ...defaults };
  for (let index = 0; index < args.length; index += 1) {
    const argument = args[index];
    const value = args[index + 1];
    if (argument === '--url') {
      options.url = value || options.url;
      index += 1;
    } else if (argument === '--chrome') {
      options.chrome = value || options.chrome;
      index += 1;
    } else if (argument === '--timeout-ms') {
      options.timeoutMs = Math.max(1_000, Number(value) || options.timeoutMs);
      index += 1;
    } else if (argument === '--poll-ms') {
      options.pollMs = Math.max(100, Number(value) || options.pollMs);
      index += 1;
    } else if (argument === '--expected-images') {
      options.expectedImages = Math.max(1, Math.floor(Number(value) || 0));
      index += 1;
    } else if (argument === '--expected-requests') {
      options.expectedRequests = Math.max(1, Math.floor(Number(value) || 0));
      index += 1;
    } else if (argument === '--expected-example') {
      options.expectedExample = value || options.expectedExample;
      index += 1;
    } else if (argument === '--all-audited') {
      options.allAudited = true;
    } else if (argument === '--help' || argument === '-h') {
      printUsage();
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${argument}`);
    }
  }
  const preset = zeroPlaceholderExpectations.get(options.expectedExample) || { images: 1, requests: 1 };
  options.expectedImages ??= preset.images;
  options.expectedRequests ??= preset.requests;
  return options;
}

function printUsage() {
  console.log([
    'Usage: node tools/e2e-showcase-pdf-forms.mjs [options]',
    '',
    'Options:',
    '  --url URL                 Static examples page to verify.',
    '  --chrome URL              Chrome DevTools endpoint (default http://127.0.0.1:9222).',
    '  --timeout-ms N            Maximum wait time (default 120000).',
    '  --poll-ms N               Poll interval (default 500).',
    '  --expected-images N       Exact published image count (fixture default).',
    '  --expected-requests N     Exact covered render-request count (fixture default).',
    '  --expected-example ID     Expected selected example (default pdf-tracemonkey).',
    '  --all-audited             Verify all eleven audited PDF examples sequentially.',
  ].join('\n'));
}

async function fetchJson(url, label) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`${label} returned HTTP ${response.status}: ${url}`);
  }
  try {
    return await response.json();
  } catch (error) {
    throw new Error(`${label} is not valid JSON (${url}): ${error.message || error}`);
  }
}

async function loadExpectedRenderPlan(pageUrl, exampleId, expectedRequestCount, expectedImageCount) {
  const catalogueUrl = new URL('examples-index.json', pageUrl);
  const catalogue = await fetchJson(catalogueUrl, 'The showcase catalogue');
  const example = (catalogue.examples || []).find((candidate) => candidate?.id === exampleId);
  if (!example) {
    throw new Error(`The showcase catalogue does not contain ${exampleId}.`);
  }
  const planPath = String(example.pdfFormRenders?.path || '');
  if (!planPath) {
    throw new Error(`The showcase catalogue does not publish a PDF render plan for ${exampleId}.`);
  }
  const planUrl = new URL(planPath, catalogueUrl);
  const plan = await fetchJson(planUrl, `The ${exampleId} PDF render plan`);
  const requests = Array.isArray(plan.requests) ? plan.requests : [];
  const requestIds = requests.map((request) => String(request?.id || ''));
  if (requestIds.length !== expectedRequestCount) {
    throw new Error(
      `${exampleId} must retain exactly ${expectedRequestCount} source render requests; its published plan has ${requestIds.length}.`,
    );
  }
  if (requestIds.includes('') || new Set(requestIds).size !== requestIds.length) {
    throw new Error(`${exampleId} has blank or duplicate source render-request IDs in ${planUrl}.`);
  }
  const assets = Array.isArray(plan.prerenderedAssets) ? plan.prerenderedAssets : [];
  const coverage = Array.isArray(plan.prerenderedRequestCoverage)
    ? plan.prerenderedRequestCoverage
    : [];
  if (coverage.length !== requestIds.length) {
    throw new Error(
      `${exampleId} must publish one asset-coverage row for each of its ${requestIds.length} source requests; found ${coverage.length}.`,
    );
  }
  const coverageIds = coverage.map((item) => String(item?.requestId || ''));
  if (coverageIds.includes('')
    || new Set(coverageIds).size !== coverageIds.length
    || coverageIds.some((requestId) => !requestIds.includes(requestId))) {
    throw new Error(`${exampleId} has blank, duplicate, or unknown request IDs in prerenderedRequestCoverage.`);
  }
  const assetPaths = assets.map((asset) => String(asset?.path || ''));
  if (assetPaths.includes('') || new Set(assetPaths).size !== assetPaths.length) {
    throw new Error(`${exampleId} has blank or duplicate prerendered asset paths.`);
  }
  for (const asset of assets) {
    if (!/^[a-f0-9]{64}$/.test(String(asset?.sha256 || ''))) {
      throw new Error(`${exampleId} asset ${asset?.path || '(missing path)'} has no valid SHA-256 publication digest.`);
    }
  }
  const coveredAssetPaths = [...new Set(coverage.map((item) => String(item?.assetPath || '')))];
  if (coveredAssetPaths.includes('')
    || coveredAssetPaths.some((assetPath) => !assetPaths.includes(assetPath))
    || coveredAssetPaths.length !== expectedImageCount
    || assets.length !== coveredAssetPaths.length) {
    throw new Error(
      `${exampleId} must bind its ${expectedImageCount} expected DOM images exactly to published asset metadata and coverage.`,
    );
  }
  return {
    ...plan,
    url: planUrl.href,
    catalogueUrl: catalogueUrl.href,
    requestIds,
  };
}

async function browserWebSocketUrl(baseUrl) {
  const response = await fetch(new URL('/json/version', baseUrl));
  if (!response.ok) {
    throw new Error(`Chrome DevTools returned ${response.status} for /json/version.`);
  }
  const payload = await response.json();
  if (!payload.webSocketDebuggerUrl) {
    throw new Error('Chrome DevTools did not provide a browser WebSocket URL.');
  }
  return payload.webSocketDebuggerUrl;
}

async function targetWebSocketUrl(baseUrl, targetId) {
  const deadline = Date.now() + 10_000;
  while (Date.now() < deadline) {
    const response = await fetch(new URL('/json/list', baseUrl));
    const targets = await response.json();
    const target = targets.find((candidate) => candidate.id === targetId);
    if (target?.webSocketDebuggerUrl) {
      return target.webSocketDebuggerUrl;
    }
    await sleep(50);
  }
  throw new Error('Chrome did not expose the new page target.');
}

function attachObservationLog(page, sharedObservations = null) {
  const observations = sharedObservations || {
    consoleErrors: [],
    pageErrors: [],
    networkFailures: [],
    networkRequests: [],
    imageResponses: [],
  };
  page.on('Runtime.consoleAPICalled', (params) => {
    if (params.type !== 'error') {
      return;
    }
    const message = params.args.map((arg) => arg.value ?? arg.description ?? arg.type).join(' ');
    observations.consoleErrors.push(message);
    console.error(`console.error: ${message}`);
  });
  page.on('Runtime.exceptionThrown', (params) => {
    const message = params.exceptionDetails?.exception?.description
      || params.exceptionDetails?.text
      || 'Unknown page exception';
    observations.pageErrors.push(message);
    console.error(`page exception: ${message}`);
  });
  page.on('Network.loadingFailed', (params) => {
    const request = observations.networkRequests.findLast((candidate) => candidate.requestId === params.requestId);
    const failure = {
      requestId: String(params.requestId || ''),
      url: String(request?.url || ''),
      type: String(params.type || request?.type || 'Other'),
      errorText: String(params.errorText || 'network failure'),
      canceled: Boolean(params.canceled),
    };
    observations.networkFailures.push(failure);
    console.error(`network failure: ${failure.errorText} (${failure.type}) ${failure.url}`);
  });
  page.on('Network.requestWillBeSent', (params) => {
    observations.networkRequests.push({
      requestId: String(params.requestId || ''),
      url: String(params.request?.url || ''),
      type: String(params.type || 'Other'),
    });
  });
  page.on('Network.responseReceived', (params) => {
    const mimeType = String(params.response?.mimeType || '');
    if (params.type !== 'Image' && !/^image\//i.test(mimeType)) {
      return;
    }
    observations.imageResponses.push({
      requestId: String(params.requestId || ''),
      url: String(params.response?.url || ''),
      status: Number(params.response?.status || 0),
      mimeType,
      fromDiskCache: Boolean(params.response?.fromDiskCache),
      fromServiceWorker: Boolean(params.response?.fromServiceWorker),
    });
  });
  return observations;
}

async function evaluate(page, expression, contextId) {
  const params = {
    expression,
    awaitPromise: true,
    returnByValue: true,
  };
  if (contextId) {
    params.contextId = contextId;
  }
  const result = await page.call('Runtime.evaluate', {
    ...params,
  });
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Chrome evaluation failed.');
  }
  return result.result?.value;
}

function pageSnapshotExpression() {
  return `(() => {
    const visible = (node) => {
      if (!node || node.hidden) return false;
      const style = window.getComputedStyle(node);
      return style.display !== 'none' && style.visibility !== 'hidden';
    };
    const text = (node) => String(node?.textContent || '').replace(/\\s+/g, ' ').trim();
    const status = document.querySelector('#viewer-status');
    const picker = document.querySelector('#example-picker');
    const frame = document.querySelector('#example-frame');
    const snapshot = {
      selectedExample: String(picker?.value || ''),
      status: {
        text: text(status),
        visible: visible(status),
        tone: String(status?.dataset?.tone || ''),
      },
      iframe: {
        present: Boolean(frame),
        loadedPath: String(frame?.dataset?.loadedPath || ''),
        hidden: Boolean(frame?.hidden),
        frameId: '',
      },
      outerFallbackSignals: [],
    };
    const fallbackSelector = [
      '.pandoc-pdf-form-placeholder',
      '[data-pandoc-pdf-form-render-fallback]',
      '[data-pdf-form-render-fallback]',
      '[data-pandoc-pdf-form-render-status="fallback"]',
      '[data-pandoc-pdf-form-render-status="error"]',
      '[data-pdf-form-render-status="fallback"]',
      '[data-pdf-form-render-status="error"]',
    ].join(',');
    snapshot.outerFallbackSignals = Array.from(document.querySelectorAll(fallbackSelector))
      .map((node) => text(node) || node.getAttribute('data-pandoc-pdf-form-render-status') || node.getAttribute('data-pdf-form-render-status') || node.tagName)
      .slice(0, 10);
    const warningPattern = /(?:could not be rendered in this browser|renders at most \\d+ PDF figures|figure\\/page-image placeholders?|shown as a placeholder)/i;
    snapshot.outerWarningSignals = Array.from(document.querySelectorAll('body *'))
      .map((node) => text(node))
      .filter((value) => warningPattern.test(value))
      .sort((left, right) => left.length - right.length)
      .slice(0, 10);
    return snapshot;
  })()`;
}

function iframeSnapshotExpression() {
  return `(async () => {
    const text = (node) => String(node?.textContent || '').replace(/\\s+/g, ' ').trim();
    const fallbackSelector = [
      '.pandoc-pdf-form-placeholder',
      '[data-pandoc-pdf-form-render-fallback]',
      '[data-pdf-form-render-fallback]',
      '[data-pandoc-pdf-form-render-status="fallback"]',
      '[data-pandoc-pdf-form-render-status="error"]',
      '[data-pdf-form-render-status="fallback"]',
      '[data-pdf-form-render-status="error"]',
    ].join(',');
    const imageSelector = [
      'img[data-pandoc-pdf-form-rendered]',
      'img[data-pdf-form-rendered]',
      'img.pandoc-pdf-form-rendered',
    ].join(',');
    const images = Array.from(document.querySelectorAll(imageSelector));
    const originalScroll = { x: window.scrollX, y: window.scrollY };
    const decodeFailures = [];
    const imageDetails = [];
    // A lazy marker is not proof that the file exists. Process one asset at a
    // time so the 46-page gallery test does not create an artificial burst of
    // concurrent decodes while still proving every file can be fetched.
    for (let index = 0; index < images.length; index += 1) {
      const image = images[index];
      image.loading = 'eager';
      image.scrollIntoView({ block: 'center', inline: 'nearest' });
      let timer = 0;
      let decodeError = '';
      try {
        await Promise.race([
          image.decode(),
          new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error('decode timed out after 15 seconds')), 15_000);
          }),
        ]);
      } catch (error) {
        decodeError = String(error?.message || error || 'image decode failed');
      } finally {
        clearTimeout(timer);
      }
      if (!decodeError && (!image.complete || image.naturalWidth <= 0 || image.naturalHeight <= 0)) {
        decodeError = 'decode completed without positive natural dimensions';
      }
      const source = String(image.currentSrc || image.src || image.getAttribute('src') || '');
      if (decodeError) {
        decodeFailures.push({ index, source, error: decodeError });
      }
      imageDetails.push({
        index,
        source,
        loading: String(image.loading || image.getAttribute('loading') || ''),
        complete: Boolean(image.complete),
        naturalWidth: Number(image.naturalWidth || 0),
        naturalHeight: Number(image.naturalHeight || 0),
      });
      if (decodeError) break;
    }
    window.scrollTo(originalScroll.x, originalScroll.y);
    const detailByImage = new Map(images.map((image, index) => [image, imageDetails[index]]));
    const decodedImages = images.filter((image) => image.complete && image.naturalWidth > 0 && image.naturalHeight > 0);
    const bodyElements = Array.from(document.body?.children || []);
    const formFigures = Array.from(document.querySelectorAll(
      'figure[data-pdf-form-request], figure[data-pdf-form-request-ids]',
    ));
    const warningPattern = /(?:could not be rendered in this browser|renders at most \\d+ PDF figures|figure\\/page-image placeholders?|shown as a placeholder)/i;
    const warningSignals = Array.from(document.querySelectorAll('body *'))
      .map((node) => text(node))
      .filter((value) => warningPattern.test(value))
      .sort((left, right) => left.length - right.length)
      .slice(0, 10);
    return {
      documentReady: document.readyState === 'complete' || document.readyState === 'interactive',
      bodyPresent: Boolean(document.body),
      injectedImages: images.length,
      decodedImages: decodedImages.length,
      deferredLazyImages: 0,
      decodeFailures,
      imageSources: imageDetails.map((image) => image.source),
      imageDetails,
      fallbackSignals: Array.from(document.querySelectorAll(fallbackSelector))
        .map((node) => text(node) || node.getAttribute('data-pandoc-pdf-form-render-status') || node.getAttribute('data-pdf-form-render-status') || node.tagName)
        .slice(0, 10),
      warningSignals,
      formFigures: formFigures.map((figure) => {
        const next = figure.nextElementSibling;
        const figureImages = Array.from(figure.querySelectorAll(imageSelector));
        const coveredRequestIds = String(figure.dataset.pdfFormRequestIds || figure.dataset.pdfFormRequest || '')
          .split(/[\\s,]+/)
          .filter(Boolean);
        return {
          requestId: String(figure.dataset.pdfFormRequest || ''),
          coveredRequestIds,
          object: String(figure.dataset.pdfFormObject || ''),
          renderedImages: figureImages.length,
          images: figureImages.map((image) => detailByImage.get(image)),
          placeholders: figure.querySelectorAll('.pandoc-pdf-form-placeholder').length
            + (figure.classList.contains('pandoc-pdf-form-placeholder') ? 1 : 0),
          warningText: warningPattern.test(text(figure)) ? text(figure) : '',
          bodyIndex: bodyElements.indexOf(figure),
          nextBodyIndex: bodyElements.indexOf(next),
          nextTag: String(next?.tagName || ''),
          nextText: text(next),
        };
      }),
      trailingBodyElements: bodyElements.slice(-12).map((element) => ({
        tag: String(element.tagName || ''),
        text: text(element).slice(0, 180),
      })),
    };
  })()`;
}

function parentImageSampleExpression(imageSources) {
  const serializedSources = JSON.stringify([...new Set(imageSources)]);
  return `(async () => {
    const sources = ${serializedSources};
    const samples = [];
    for (const source of sources) {
      let bitmap = null;
      try {
        const response = await fetch(source, { cache: 'force-cache' });
        const mimeType = String(response.headers.get('content-type') || '');
        if (!response.ok || !/^image\\//i.test(mimeType)) {
          samples.push({ source, error: 'image fetch returned HTTP ' + response.status + ' (' + mimeType + ')' });
          continue;
        }
        bitmap = await createImageBitmap(await response.blob());
        const maximumSampleDimension = 128;
        const scale = Math.min(1, maximumSampleDimension / Math.max(bitmap.width, bitmap.height));
        const sampleWidth = Math.max(1, Math.round(bitmap.width * scale));
        const sampleHeight = Math.max(1, Math.round(bitmap.height * scale));
        const canvas = document.createElement('canvas');
        canvas.width = sampleWidth;
        canvas.height = sampleHeight;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) throw new Error('2D canvas is unavailable');
        context.drawImage(bitmap, 0, 0, sampleWidth, sampleHeight);
        const pixels = context.getImageData(0, 0, sampleWidth, sampleHeight).data;
        const buckets = new Map();
        let opaque = 0;
        let nonWhite = 0;
        let luminanceMin = 255;
        let luminanceMax = 0;
        let luminanceSum = 0;
        let luminanceSquaredSum = 0;
        for (let offset = 0; offset < pixels.length; offset += 4) {
          const red = pixels[offset];
          const green = pixels[offset + 1];
          const blue = pixels[offset + 2];
          const alpha = pixels[offset + 3];
          if (alpha <= 16) continue;
          opaque += 1;
          if (red < 248 || green < 248 || blue < 248) nonWhite += 1;
          const luminance = (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
          luminanceMin = Math.min(luminanceMin, luminance);
          luminanceMax = Math.max(luminanceMax, luminance);
          luminanceSum += luminance;
          luminanceSquaredSum += luminance * luminance;
          const bucket = ((red >> 4) << 8) | ((green >> 4) << 4) | (blue >> 4);
          buckets.set(bucket, (buckets.get(bucket) || 0) + 1);
        }
        const pixelCount = sampleWidth * sampleHeight;
        const mean = opaque > 0 ? luminanceSum / opaque : 0;
        const variance = opaque > 0 ? Math.max(0, (luminanceSquaredSum / opaque) - (mean * mean)) : 0;
        samples.push({
          source,
          naturalWidth: bitmap.width,
          naturalHeight: bitmap.height,
          pixels: {
            sampleWidth,
            sampleHeight,
            opaqueRatio: pixelCount > 0 ? opaque / pixelCount : 0,
            nonWhiteRatio: opaque > 0 ? nonWhite / opaque : 0,
            luminanceRange: opaque > 0 ? luminanceMax - luminanceMin : 0,
            luminanceStandardDeviation: Math.sqrt(variance),
            colorBuckets: buckets.size,
            dominantColorRatio: opaque > 0 ? Math.max(0, ...buckets.values()) / opaque : 1,
          },
        });
      } catch (error) {
        samples.push({ source, error: String(error?.message || error || 'image sampling failed') });
      } finally {
        bitmap?.close();
      }
    }
    return samples;
  })()`;
}

function traceMonkeyPlacementErrors(formFigures) {
  const byObject = new Map(formFigures.map((figure) => [String(figure.object || ''), figure]));
  const errors = [];
  for (const [object, captionPrefix] of traceMonkeyCaptionPrefixes) {
    const figure = byObject.get(object);
    if (!figure) {
      errors.push(`missing Form object ${object}`);
      continue;
    }
    if (figure.nextTag !== 'P' || !figure.nextText.startsWith(captionPrefix)) {
      errors.push(`Form object ${object} is not immediately before ${captionPrefix}`);
    }
  }
  return errors;
}

function isRenderingFailure(snapshot) {
  const status = snapshot.status || {};
  const visibleStatus = status.visible ? status.text : '';
  const statusImpliesFailure = status.tone === 'error'
    || /\\b(?:fallback|failed|failure|could not|unable to|rendering error|error)\\b/i.test(visibleStatus);
  return statusImpliesFailure
    || snapshot.outerFallbackSignals.length > 0
    || snapshot.outerWarningSignals.length > 0
    || snapshot.iframe.fallbackSignals.length > 0
    || snapshot.iframe.warningSignals.length > 0;
}

function forbiddenRuntimePdfRequests(observations) {
  return (observations?.networkRequests || []).filter((request) => {
    try {
      const url = new URL(request.url);
      return /\.pdf$/i.test(url.pathname) || /\/vendor\/pdfjs\//i.test(url.pathname);
    } catch {
      return false;
    }
  });
}

function nonSameOriginImageSources(snapshot, pageUrl) {
  const expectedOrigin = new URL(pageUrl).origin;
  return (snapshot?.iframe?.imageSources || []).filter((source) => {
    try {
      const url = new URL(source, pageUrl);
      return !['http:', 'https:'].includes(url.protocol) || url.origin !== expectedOrigin;
    } catch {
      return true;
    }
  });
}

function requestCoverageDifference(actualRequestIds, expectedRequestIds) {
  const actualCounts = new Map();
  for (const requestId of actualRequestIds) {
    actualCounts.set(requestId, (actualCounts.get(requestId) || 0) + 1);
  }
  const actual = new Set(actualCounts.keys());
  const expected = new Set(expectedRequestIds);
  const missing = expectedRequestIds.filter((requestId) => !actual.has(requestId));
  const unknown = [...actual].filter((requestId) => !expected.has(requestId));
  const duplicates = [...actualCounts]
    .filter(([, count]) => count !== 1)
    .map(([requestId, count]) => ({ requestId, count }));
  return {
    exact: missing.length === 0
      && unknown.length === 0
      && duplicates.length === 0
      && actualRequestIds.length === expectedRequestIds.length,
    missing,
    unknown,
    duplicates,
  };
}

function relevantImageNetworkErrors(observations, imageSources) {
  const sources = new Set(imageSources.map((source) => {
    try {
      return new URL(source).href;
    } catch {
      return source;
    }
  }));
  const loadingFailures = (observations.networkFailures || []).filter((failure) => (
    failure.type === 'Image' || sources.has(failure.url)
  ));
  const responseFailures = (observations.imageResponses || []).filter((response) => (
    sources.has(response.url) && (response.status < 200 || response.status >= 300)
  ));
  return { loadingFailures, responseFailures };
}

function planAssetUrl(expectedPlan, assetPath) {
  return new URL(assetPath, expectedPlan.catalogueUrl).href;
}

function planAssetBindingErrors(snapshot, expectedPlan) {
  const errors = [];
  const coverageByRequestId = new Map(
    expectedPlan.prerenderedRequestCoverage.map((item) => [String(item.requestId), item]),
  );
  const expectedUrls = new Set(expectedPlan.prerenderedRequestCoverage.map((item) => (
    planAssetUrl(expectedPlan, String(item.assetPath || ''))
  )));
  const domUrls = new Set(snapshot.iframe.imageSources);
  for (const figure of snapshot.iframe.formFigures || []) {
    const figureExpectedUrls = [...new Set(figure.coveredRequestIds.map((requestId) => (
      planAssetUrl(expectedPlan, String(coverageByRequestId.get(requestId)?.assetPath || ''))
    )))].sort();
    const figureActualUrls = (figure.images || []).map((image) => String(image?.source || '')).sort();
    if (figureActualUrls.length !== new Set(figureActualUrls).size
      || figureActualUrls.join('\n') !== figureExpectedUrls.join('\n')) {
      errors.push({
        requestIds: figure.coveredRequestIds,
        expectedAssetUrls: figureExpectedUrls,
        actualImageUrls: figureActualUrls,
      });
    }
  }
  const missingDomAssets = [...expectedUrls].filter((url) => !domUrls.has(url));
  const unknownDomAssets = [...domUrls].filter((url) => !expectedUrls.has(url));
  if (missingDomAssets.length > 0 || unknownDomAssets.length > 0) {
    errors.push({ missingDomAssets, unknownDomAssets });
  }
  return errors;
}

async function verifyPublishedAssets(expectedPlan, pageUrl) {
  const expectedOrigin = new URL(pageUrl).origin;
  const failures = [];
  const verified = [];
  for (const asset of expectedPlan.prerenderedAssets) {
    const source = planAssetUrl(expectedPlan, String(asset.path || ''));
    try {
      const response = await fetch(source, { redirect: 'follow' });
      const finalUrl = new URL(response.url || source);
      const mimeType = String(response.headers.get('content-type') || '').split(';', 1)[0].trim().toLowerCase();
      const bytes = Buffer.from(await response.arrayBuffer());
      const sha256 = createHash('sha256').update(bytes).digest('hex');
      const expectedMimeType = String(asset.mimeType || '').toLowerCase();
      const expectedSha256 = String(asset.sha256 || '').toLowerCase();
      const expectedByteLength = Number(asset.byteLength || 0);
      if (!response.ok
        || finalUrl.origin !== expectedOrigin
        || finalUrl.href !== source
        || !/^image\//i.test(mimeType)
        || mimeType !== expectedMimeType
        || sha256 !== expectedSha256
        || (expectedByteLength > 0 && bytes.length !== expectedByteLength)) {
        failures.push({
          source,
          finalUrl: finalUrl.href,
          status: response.status,
          mimeType,
          expectedMimeType,
          byteLength: bytes.length,
          expectedByteLength,
          sha256,
          expectedSha256,
        });
      } else {
        verified.push({ source, byteLength: bytes.length, sha256, mimeType });
      }
    } catch (error) {
      failures.push({ source, error: String(error?.message || error) });
    }
  }
  return { failures, verified };
}

function visualContentErrors(snapshot, imageSamples, expectedPlan, exampleId) {
  const errors = [];
  const coverageByRequestId = new Map(
    (expectedPlan.prerenderedRequestCoverage || []).map((item) => [String(item?.requestId || ''), item]),
  );
  const checkGenericContent = (image, label) => {
    if (image?.error) {
      errors.push(`${label}: ${image.error}`);
      return;
    }
    const pixels = image?.pixels || {};
    if (pixels.error) {
      errors.push(`${label}: ${pixels.error}`);
      return;
    }
    if (pixels.opaqueRatio < 0.05
      || pixels.luminanceRange < 2
      || pixels.luminanceStandardDeviation < 0.1
      || pixels.colorBuckets < 2) {
      errors.push(`${label}: decoded image is blank or effectively single-color (${JSON.stringify(pixels)})`);
    }
  };
  const checkPageGallery = (image, label, wholePageRaster) => {
    const pixels = image?.pixels || {};
    const aspectRatio = image.naturalWidth / Math.max(1, image.naturalHeight);
    const minimumDimension = wholePageRaster ? 128 : 32;
    if (image.naturalWidth < minimumDimension
      || image.naturalHeight < minimumDimension
      || aspectRatio < 0.2
      || aspectRatio > 5
      || pixels.opaqueRatio < 0.85
      // Some source-faithful scanned book leaves are intentionally almost
      // blank. Eight sampled non-white pixels still distinguish scan marks
      // from an actually empty 128x128 preview without inventing content.
      || pixels.nonWhiteRatio < 0.0005
      || pixels.luminanceRange < 8
      || pixels.luminanceStandardDeviation < 0.5
      || pixels.colorBuckets < 3
      || pixels.dominantColorRatio > 0.9995) {
      errors.push(`${label}: page-gallery asset looks blank, clipped, or implausibly small (${JSON.stringify({
        naturalWidth: image.naturalWidth,
        naturalHeight: image.naturalHeight,
        ...pixels,
      })})`);
    }
  };
  const checkGrandCanyonComposite = (image, label) => {
    const pixels = image?.pixels || {};
    const aspectRatio = image.naturalWidth / Math.max(1, image.naturalHeight);
    if (image.naturalWidth < 512
      || image.naturalHeight < 512
      || aspectRatio < 0.75
      || aspectRatio > 1.4
      || pixels.opaqueRatio < 0.9
      || pixels.nonWhiteRatio < 0.02
      || pixels.luminanceRange < 32
      || pixels.luminanceStandardDeviation < 8
      || pixels.colorBuckets < 16
      || pixels.dominantColorRatio > 0.98) {
      errors.push(`${label}: Grand Canyon page composite is blank or badly cropped (${JSON.stringify({
        naturalWidth: image.naturalWidth,
        naturalHeight: image.naturalHeight,
        ...pixels,
      })})`);
    }
  };

  const sampleBySource = new Map(imageSamples.map((image) => [String(image?.source || ''), image]));
  const requestById = new Map(
    (expectedPlan.requests || []).map((request) => [String(request?.id || ''), request]),
  );
  for (const image of imageSamples) {
    checkGenericContent(image, image.source || `image ${image.index + 1}`);
  }
  for (const figure of snapshot.iframe.formFigures || []) {
    const placements = new Set(figure.coveredRequestIds
      .map((requestId) => String(coverageByRequestId.get(requestId)?.placement || ''))
      .filter(Boolean));
    for (const iframeImage of figure.images || []) {
      const image = sampleBySource.get(String(iframeImage?.source || ''));
      const label = iframeImage?.source || 'published preview image';
      if (!image) {
        errors.push(`${label}: no unsandboxed parent-page pixel sample was captured`);
        continue;
      }
      if (image.naturalWidth !== iframeImage.naturalWidth || image.naturalHeight !== iframeImage.naturalHeight) {
        errors.push(`${label}: parent and sandboxed iframe decoded different intrinsic dimensions`);
      }
      if (placements.has('page-gallery')) {
        const wholePageRaster = figure.coveredRequestIds.every((requestId) => (
          requestById.get(requestId)?.method === 'pdfjs-whole-page-raster'
        ));
        checkPageGallery(image, label, wholePageRaster);
      }
      if (exampleId === 'pdf-grand-canyon-north-rim-map') {
        checkGrandCanyonComposite(image, label);
      }
    }
  }
  return errors;
}

async function currentIframeFrameId(page) {
  try {
    const document = await page.call('DOM.getDocument', { depth: 1 });
    const result = await page.call('DOM.querySelector', {
      nodeId: document.root.nodeId,
      selector: '#example-frame',
    });
    if (result.nodeId) {
      const description = await page.call('DOM.describeNode', { nodeId: result.nodeId });
      const directFrameId = description.node?.frameId || description.node?.contentDocument?.frameId;
      if (directFrameId) {
        return directFrameId;
      }
    }
  } catch {
    // Navigation and iframe srcdoc replacement can invalidate a DOM node
    // between the CDP calls above. The polling loop will retry on the next
    // tick with a fresh document tree.
    return '';
  }
  try {
    const tree = await page.call('Page.getFrameTree');
    return tree.frameTree?.childFrames?.[0]?.frame?.id || '';
  } catch {
    return '';
  }
}

async function availableTargetWebSocketUrl(baseUrl, targetId) {
  const response = await fetch(new URL('/json/list', baseUrl));
  const targets = await response.json();
  return targets.find((candidate) => candidate.id === targetId)?.webSocketDebuggerUrl || '';
}

async function iframeClientForFrame(baseUrl, frameId, iframeClients, observations) {
  if (!frameId) {
    return null;
  }
  const existing = iframeClients.get(frameId);
  if (existing) {
    return existing;
  }
  const url = await availableTargetWebSocketUrl(baseUrl, frameId);
  if (!url) {
    return null;
  }
  const client = await CdpClient.connect(url);
  attachObservationLog(client, observations);
  await Promise.all([
    client.call('Runtime.enable'),
    client.call('Network.enable'),
  ]);
  iframeClients.set(frameId, client);
  return client;
}

async function snapshotIframe(page, chromeUrl, iframeClients, parentFrameContexts, observations) {
  const frameId = await currentIframeFrameId(page);
  const iframeClient = await iframeClientForFrame(chromeUrl, frameId, iframeClients, observations);
  if (!iframeClient) {
    let contextId = parentFrameContexts.get(frameId);
    let inspectionError = '';
    if (!contextId && frameId) {
      try {
        const isolatedWorld = await page.call('Page.createIsolatedWorld', {
          frameId,
          worldName: 'port-libs-showcase-pdf-forms-e2e',
        });
        contextId = isolatedWorld.executionContextId;
        if (contextId) {
          parentFrameContexts.set(frameId, contextId);
        }
      } catch (error) {
        inspectionError = String(error?.message || error);
      }
    }
    if (contextId) {
      try {
        const snapshot = await evaluate(page, iframeSnapshotExpression(), contextId);
        return { frameId, ...snapshot };
      } catch (error) {
        parentFrameContexts.delete(frameId);
        inspectionError = String(error?.message || error);
      }
    }
    return {
      frameId,
      documentReady: false,
      bodyPresent: false,
      injectedImages: 0,
      decodedImages: 0,
      deferredLazyImages: 0,
      decodeFailures: [],
      imageSources: [],
      imageDetails: [],
      fallbackSignals: [],
      warningSignals: [],
      formFigures: [],
      inspectionError: inspectionError || (frameId ? 'The sandboxed iframe target is not available yet.' : 'The examples iframe is not available yet.'),
    };
  }
  try {
    const snapshot = await evaluate(iframeClient, iframeSnapshotExpression());
    return { frameId, ...snapshot };
  } catch (error) {
    iframeClients.delete(frameId);
    await iframeClient.close().catch(() => {});
    return {
      frameId,
      documentReady: false,
      bodyPresent: false,
      injectedImages: 0,
      decodedImages: 0,
      deferredLazyImages: 0,
      decodeFailures: [],
      imageSources: [],
      imageDetails: [],
      fallbackSignals: [],
      warningSignals: [],
      formFigures: [],
      inspectionError: String(error?.message || error),
    };
  }
}

function formatElapsed(milliseconds) {
  const seconds = Math.floor(milliseconds / 1_000);
  return `${Math.floor(seconds / 60)}m${String(seconds % 60).padStart(2, '0')}s`;
}

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

class CdpClient {
  static async connect(url) {
    const socket = new WebSocket(url);
    await new Promise((resolve, reject) => {
      socket.addEventListener('open', resolve, { once: true });
      socket.addEventListener('error', () => reject(new Error(`Could not connect to Chrome DevTools at ${url}.`)), { once: true });
    });
    return new CdpClient(socket);
  }

  constructor(socket) {
    this.socket = socket;
    this.nextId = 1;
    this.pending = new Map();
    this.listeners = new Map();
    socket.addEventListener('message', (event) => this.receive(event.data));
    socket.addEventListener('close', () => {
      for (const { reject } of this.pending.values()) {
        reject(new Error('Chrome DevTools closed the connection.'));
      }
      this.pending.clear();
    });
  }

  call(method, params = {}) {
    const id = this.nextId++;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.socket.send(JSON.stringify({ id, method, params }));
    });
  }

  on(method, listener) {
    const listeners = this.listeners.get(method) || [];
    listeners.push(listener);
    this.listeners.set(method, listeners);
  }

  receive(raw) {
    const message = JSON.parse(String(raw));
    if (message.id) {
      const pending = this.pending.get(message.id);
      if (!pending) return;
      this.pending.delete(message.id);
      if (message.error) {
        pending.reject(new Error(`${message.error.message || 'Chrome DevTools error'} (${message.error.code ?? 'unknown'})`));
      } else {
        pending.resolve(message.result || {});
      }
      return;
    }
    for (const listener of this.listeners.get(message.method) || []) {
      listener(message.params || {});
    }
  }

  async close() {
    this.socket.close();
  }
}

async function verifyExample(options) {
  const expectedPlan = await loadExpectedRenderPlan(
    options.url,
    options.expectedExample,
    options.expectedRequests,
    options.expectedImages,
  );
  const browser = await CdpClient.connect(await browserWebSocketUrl(options.chrome));
  let page;
  let targetId;
  let observations;
  let lastSnapshot;
  let iframeClients;
  let parentFrameContexts;
  const statusHistory = [];
  try {
    const target = await browser.call('Target.createTarget', { url: 'about:blank' });
    targetId = target.targetId;
    page = await CdpClient.connect(await targetWebSocketUrl(options.chrome, targetId));
    observations = attachObservationLog(page);
    iframeClients = new Map();
    parentFrameContexts = new Map();
    await Promise.all([
      page.call('Page.enable'),
      page.call('Runtime.enable'),
      page.call('DOM.enable'),
      page.call('Network.enable'),
    ]);

    await page.call('Page.navigate', { url: options.url });
    const startedAt = Date.now();
    let lastStatusKey = '';
    while (Date.now() - startedAt < options.timeoutMs) {
      const snapshot = await evaluate(page, pageSnapshotExpression());
      snapshot.iframe = {
        ...snapshot.iframe,
        ...await snapshotIframe(page, options.chrome, iframeClients, parentFrameContexts, observations),
      };
      lastSnapshot = snapshot;
      const statusKey = JSON.stringify(snapshot.status);
      if (snapshot.status.visible && statusKey !== lastStatusKey) {
        lastStatusKey = statusKey;
        statusHistory.push({ atMs: Date.now() - startedAt, ...snapshot.status });
        console.log(`[${formatElapsed(Date.now() - startedAt)}] ${snapshot.status.text || '(no status text)'}`);
      }
      if (isRenderingFailure(snapshot)) {
        throw new VerificationError('The static showcase reported a PDF Form rendering fallback or error.', {
          snapshot,
          statusHistory,
          observations,
        });
      }
      const selectedExampleMatches = !options.expectedExample || snapshot.selectedExample === options.expectedExample;
      const iframeReady = snapshot.iframe.documentReady && snapshot.iframe.bodyPresent;
      const coveredRequestIds = (snapshot.iframe.formFigures || [])
        .flatMap((figure) => figure.coveredRequestIds || [])
        .filter(Boolean);
      const coverageDifference = requestCoverageDifference(coveredRequestIds, expectedPlan.requestIds);
      if (coverageDifference.unknown.length > 0
        || coverageDifference.duplicates.length > 0
        || coveredRequestIds.length > expectedPlan.requestIds.length
        || (coveredRequestIds.length === expectedPlan.requestIds.length && !coverageDifference.exact)) {
        throw new VerificationError('The DOM covered request IDs do not exactly match the selected source render plan.', {
          snapshot,
          statusHistory,
          observations,
          expectedPlanUrl: expectedPlan.url,
          coverageDifference,
        });
      }
      if (selectedExampleMatches && iframeReady && coverageDifference.exact) {
        if (snapshot.iframe.injectedImages !== options.expectedImages
          || snapshot.iframe.decodedImages !== options.expectedImages
          || snapshot.iframe.decodeFailures.length > 0) {
          throw new VerificationError(
            `The static showcase covered all ${options.expectedRequests} requests, but did not fetch and decode exactly ${options.expectedImages} published images.`,
            { snapshot, statusHistory, observations },
          );
        }
        const figuresWithoutImages = (snapshot.iframe.formFigures || []).filter((figure) => (
          figure.renderedImages === 0 || figure.placeholders > 0 || figure.warningText
        ));
        if (figuresWithoutImages.length > 0) {
          throw new VerificationError('Every PDF request must resolve to a published image without a placeholder or warning.', {
            snapshot,
            statusHistory,
            observations,
            figuresWithoutImages,
          });
        }
        const runtimePdfRequests = forbiddenRuntimePdfRequests(observations);
        if (runtimePdfRequests.length > 0) {
          throw new VerificationError('Static Pages previews must not fetch a source PDF or PDF.js at runtime.', {
            snapshot,
            statusHistory,
            observations,
            runtimePdfRequests,
          });
        }
        const nonSameOriginImages = nonSameOriginImageSources(snapshot, options.url);
        if (nonSameOriginImages.length > 0) {
          throw new VerificationError('Every PDF preview image must be a deterministic same-origin published asset.', {
            snapshot,
            statusHistory,
            observations,
            nonSameOriginImages,
          });
        }
        const assetBindingErrors = planAssetBindingErrors(snapshot, expectedPlan);
        if (assetBindingErrors.length > 0) {
          throw new VerificationError('DOM request coverage must resolve to the exact assetPath declared by the render plan.', {
            snapshot,
            statusHistory,
            observations,
            assetBindingErrors,
          });
        }
        const imageNetworkErrors = relevantImageNetworkErrors(observations, snapshot.iframe.imageSources);
        const publishedAssetVerification = await verifyPublishedAssets(expectedPlan, options.url);
        if (imageNetworkErrors.loadingFailures.length > 0
          || imageNetworkErrors.responseFailures.length > 0
          || publishedAssetVerification.failures.length > 0) {
          throw new VerificationError('Every planned preview asset must load with exact published bytes and a successful 2xx image response.', {
            snapshot,
            statusHistory,
            observations,
            imageNetworkErrors,
            publishedAssetVerification,
          });
        }
        const imageSamples = await evaluate(
          page,
          parentImageSampleExpression(snapshot.iframe.imageSources),
        );
        const visualErrors = visualContentErrors(
          snapshot,
          imageSamples,
          expectedPlan,
          options.expectedExample,
        );
        if (visualErrors.length > 0) {
          throw new VerificationError('Published PDF previews failed nonblank or crop-sanity checks.', {
            snapshot,
            statusHistory,
            observations,
            imageSamples,
            visualErrors,
          });
        }
        const placementErrors = options.expectedExample === 'pdf-tracemonkey'
          ? traceMonkeyPlacementErrors(snapshot.iframe.formFigures || [])
          : [];
        if (placementErrors.length > 0) {
          throw new VerificationError('The charts rendered but did not keep their PDF-caption placement.', {
            snapshot,
            statusHistory,
            observations,
            placementErrors,
          });
        }
        const relevantConsoleErrors = observations.consoleErrors
          .map((message) => String(message || '').trim())
          .filter(Boolean);
        if (relevantConsoleErrors.length > 0 || observations.pageErrors.length > 0) {
          throw new VerificationError('The previews rendered, but the browser reported console errors or page exceptions.', {
            snapshot,
            statusHistory,
            observations,
            relevantConsoleErrors,
          });
        }
        const result = {
          ok: true,
          url: options.url,
          elapsedMs: Date.now() - startedAt,
          expectedExample: options.expectedExample,
          expectedImages: options.expectedImages,
          expectedRequests: options.expectedRequests,
          expectedPlanUrl: expectedPlan.url,
          expectedRequestIds: expectedPlan.requestIds,
          publishedAssets: publishedAssetVerification.verified,
          imageSamples,
          snapshot,
          statusHistory,
          observations,
        };
        if (!options.quietSuccess) {
          console.log(JSON.stringify(result, null, 2));
        }
        return result;
      }
      await sleep(options.pollMs);
    }
    throw new VerificationError(`Timed out after ${formatElapsed(options.timeoutMs)} waiting for exact coverage of ${options.expectedRequests} PDF requests by ${options.expectedImages} published images.`, {
      snapshot: lastSnapshot,
      statusHistory,
      observations,
    });
  } finally {
    for (const iframeClient of iframeClients?.values() || []) {
      await iframeClient.close().catch(() => {});
    }
    await page?.close().catch(() => {});
    if (targetId) {
      await browser.call('Target.closeTarget', { targetId }).catch(() => {});
    }
    await browser.close().catch(() => {});
  }
}

class VerificationError extends Error {
  constructor(message, details) {
    super(message);
    this.name = 'VerificationError';
    this.details = details;
  }
}

function conciseFailureDiagnostics(error) {
  const details = error?.details || {};
  return {
    message: String(error?.message || error),
    expectedPlanUrl: details.expectedPlanUrl,
    status: details.snapshot?.status,
    selectedExample: details.snapshot?.selectedExample,
    outerFallbackSignals: details.snapshot?.outerFallbackSignals,
    outerWarningSignals: details.snapshot?.outerWarningSignals,
    iframeFallbackSignals: details.snapshot?.iframe?.fallbackSignals,
    iframeWarningSignals: details.snapshot?.iframe?.warningSignals,
    coverageDifference: details.coverageDifference,
    assetBindingErrors: details.assetBindingErrors,
    runtimePdfRequests: details.runtimePdfRequests,
    imageNetworkErrors: details.imageNetworkErrors,
    publishedAssetFailures: details.publishedAssetVerification?.failures,
    visualErrors: details.visualErrors,
    placementErrors: details.placementErrors,
    consoleErrors: details.relevantConsoleErrors || details.observations?.consoleErrors,
    pageErrors: details.observations?.pageErrors,
  };
}

async function main() {
  const options = parseOptions(process.argv.slice(2));
  if (!options.allAudited) {
    return verifyExample(options);
  }

  const outcomes = [];
  for (const [exampleId, preset] of zeroPlaceholderExpectations) {
    const exampleUrl = new URL(options.url);
    exampleUrl.searchParams.set('example', exampleId);
    console.log(`[all-audited] verifying ${exampleId}`);
    try {
      const result = await verifyExample({
        ...options,
        allAudited: false,
        quietSuccess: true,
        url: exampleUrl.href,
        expectedExample: exampleId,
        expectedImages: preset.images,
        expectedRequests: preset.requests,
      });
      outcomes.push({
        exampleId,
        ok: true,
        elapsedMs: result.elapsedMs,
        images: preset.images,
        requests: preset.requests,
      });
    } catch (error) {
      outcomes.push({
        exampleId,
        ok: false,
        images: preset.images,
        requests: preset.requests,
        diagnostics: conciseFailureDiagnostics(error),
      });
    }
  }

  const failures = outcomes.filter((outcome) => !outcome.ok);
  console.log(JSON.stringify({
    ok: failures.length === 0,
    allAudited: true,
    examples: outcomes.length,
    failures: failures.length,
    outcomes,
  }, null, 2));
  if (failures.length > 0) {
    throw new Error(`${failures.length} of ${outcomes.length} audited PDF showcase examples failed.`);
  }
  return outcomes;
}

try {
  await main();
} catch (error) {
  console.error(error.message || String(error));
  if (error.details) {
    console.error(JSON.stringify({ ok: false, ...error.details }, null, 2));
  }
  process.exitCode = 1;
}
