#!/usr/bin/env node

/**
 * Exercise every PDF layout corpus example in the static browser through an
 * already-running Chrome DevTools endpoint. This intentionally uses CDP
 * directly so a checkout needs no Playwright/Puppeteer dependency.
 *
 * Usage:
 *   node tools/e2e-pdf-layout-corpus.mjs
 *   node tools/e2e-pdf-layout-corpus.mjs --url http://127.0.0.1:4174/examples.html \
 *     --review-url http://127.0.0.1:4174/pdf-layout-corpus.html
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html',
  reviewUrl: 'http://127.0.0.1:4174/pdf-layout-corpus.html',
  manifest: 'tools/pdf-layout-corpus-manifest.json',
  output: 'artifacts/pdf-layout-screenshots',
  timeoutMs: 60_000,
  pollMs: 150,
  reviewOnly: false,
};

const viewports = {
  mobile: { width: 390, height: 844, deviceScaleFactor: 1, mobile: true },
  desktop: { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false },
};

function parseOptions(args) {
  const options = { ...defaults };
  for (let index = 0; index < args.length; index += 1) {
    const argument = args[index];
    const value = args[index + 1];
    if (argument === '--chrome') {
      options.chrome = value || options.chrome;
      index += 1;
    } else if (argument === '--url') {
      options.url = value || options.url;
      index += 1;
    } else if (argument === '--review-url') {
      options.reviewUrl = value || options.reviewUrl;
      index += 1;
    } else if (argument === '--manifest') {
      options.manifest = value || options.manifest;
      index += 1;
    } else if (argument === '--output') {
      options.output = value || options.output;
      index += 1;
    } else if (argument === '--timeout-ms') {
      options.timeoutMs = Math.max(1_000, Number(value) || options.timeoutMs);
      index += 1;
    } else if (argument === '--poll-ms') {
      options.pollMs = Math.max(50, Number(value) || options.pollMs);
      index += 1;
    } else if (argument === '--review-only') {
      options.reviewOnly = true;
    } else if (argument === '--help' || argument === '-h') {
      console.log('Usage: node tools/e2e-pdf-layout-corpus.mjs [--url URL] [--review-url URL] [--review-only] [--chrome URL] [--manifest FILE] [--output DIR]');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${argument}`);
    }
  }
  return options;
}

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
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

async function availableTargetWebSocketUrl(baseUrl, targetId) {
  const response = await fetch(new URL('/json/list', baseUrl));
  const targets = await response.json();
  return targets.find((candidate) => candidate.id === targetId)?.webSocketDebuggerUrl || '';
}

async function evaluate(client, expression, contextId = undefined) {
  const params = { expression, awaitPromise: true, returnByValue: true };
  if (contextId) {
    params.contextId = contextId;
  }
  const result = await client.call('Runtime.evaluate', params);
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Chrome evaluation failed.');
  }
  return result.result?.value;
}

async function currentIframeFrameId(page) {
  try {
    const document = await page.call('DOM.getDocument', { depth: 1 });
    const result = await page.call('DOM.querySelector', { nodeId: document.root.nodeId, selector: '#example-frame' });
    if (result.nodeId) {
      const description = await page.call('DOM.describeNode', { nodeId: result.nodeId });
      const frameId = description.node?.frameId || description.node?.contentDocument?.frameId;
      if (frameId) return frameId;
    }
  } catch {
    return '';
  }
  try {
    const tree = await page.call('Page.getFrameTree');
    return tree.frameTree?.childFrames?.[0]?.frame?.id || '';
  } catch {
    return '';
  }
}

async function iframeClientForFrame(chromeUrl, frameId, clients) {
  if (!frameId) return null;
  if (clients.has(frameId)) return clients.get(frameId);
  const url = await availableTargetWebSocketUrl(chromeUrl, frameId);
  if (!url) return null;
  const client = await CdpClient.connect(url);
  await client.call('Runtime.enable');
  clients.set(frameId, client);
  return client;
}

const outerSnapshotExpression = `(() => {
  const picker = document.querySelector('#example-picker');
  const download = document.querySelector('#download-source');
  const previous = document.querySelector('#previous-example');
  const next = document.querySelector('#next-example');
  const toolbar = document.querySelector('.example-toolbar');
  const frame = document.querySelector('#example-frame');
  const status = document.querySelector('#viewer-status');
  const activeTab = document.querySelector('[data-example-view][aria-pressed="true"]');
  const rect = (node) => {
    const value = node?.getBoundingClientRect();
    return value ? { left: value.left, top: value.top, right: value.right, bottom: value.bottom, width: value.width, height: value.height } : null;
  };
  return {
    ready: Boolean(
      picker
      && !picker.disabled
      && frame
      && !frame.hidden
      && frame.dataset.loadedPath
      && (!status || status.hidden || status.dataset.tone !== 'info')
    ),
    selectedExample: String(picker?.value || ''),
    urlExample: new URL(location.href).searchParams.get('example') || '',
    loadedPath: String(frame?.dataset?.loadedPath || ''),
    activeTab: String(activeTab?.dataset?.exampleView || ''),
    tabCount: document.querySelectorAll('[data-example-view]').length,
    statusText: String(status?.textContent || '').replace(/\\s+/g, ' ').trim(),
    statusTone: String(status?.dataset?.tone || ''),
    statusVisible: Boolean(status && !status.hidden),
    documentOverflow: document.documentElement
      ? Math.max(0, document.documentElement.scrollWidth - window.innerWidth)
      : 0,
    picker: rect(picker),
    download: rect(download),
    previous: rect(previous),
    next: rect(next),
    toolbar: rect(toolbar),
    previousLabel: String(previous?.getAttribute('aria-label') || ''),
    nextLabel: String(next?.getAttribute('aria-label') || ''),
    viewport: { width: innerWidth, height: innerHeight },
  };
})()`;

const iframeSnapshotExpression = `(() => {
  const bodyText = String(document.body?.innerText || '').replace(/\\s+/g, ' ').trim();
  const paragraphs = Array.from(document.querySelectorAll('p'));
  const compactLength = (value) => Array.from(String(value || '').replace(/\\s+/gu, '')).length;
  const singleGlyphParagraphs = paragraphs
    .map((node) => String(node.textContent || '').trim())
    .filter((value) => compactLength(value) === 1);
  const spacedGlyphPattern = /(?:^|[^\\p{L}\\p{M}])(?:[\\p{L}\\p{M}]\\s+){4,}[\\p{L}\\p{M}](?=$|[^\\p{L}\\p{M}])/gu;
  const spacedGlyphRuns = [];
  for (const node of document.querySelectorAll('p, h1, h2, h3, h4, h5, h6, li, pre, td, th')) {
    const value = String(node.textContent || '');
    for (const match of value.matchAll(spacedGlyphPattern)) {
      spacedGlyphRuns.push(match[0].trim().slice(0, 120));
      if (spacedGlyphRuns.length >= 8) break;
    }
    if (spacedGlyphRuns.length >= 8) break;
  }
  const rgb = (value) => {
    const match = String(value || '').match(/^rgba?\\(\\s*(\\d+(?:\\.\\d+)?)\\D+(\\d+(?:\\.\\d+)?)\\D+(\\d+(?:\\.\\d+)?)/i);
    return match ? match.slice(1, 4).map(Number) : null;
  };
  const luminance = (channels) => channels
    .map((channel) => channel / 255)
    .map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
    .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
  const lowContrastPdfFillCells = [];
  for (const node of document.querySelectorAll('[data-pdf-fill-color]')) {
    if (!String(node.textContent || '').trim()) continue;
    const style = getComputedStyle(node);
    const foreground = rgb(style.color);
    const background = rgb(style.backgroundColor);
    if (!foreground || !background) continue;
    const light = Math.max(luminance(foreground), luminance(background));
    const dark = Math.min(luminance(foreground), luminance(background));
    const ratio = (light + 0.05) / (dark + 0.05);
    if (ratio < 4.5) lowContrastPdfFillCells.push(String(node.textContent || '').trim().slice(0, 120));
  }
  return {
    ready: document.readyState === 'complete' || document.readyState === 'interactive',
    bodyText,
    textBytes: new TextEncoder().encode(bodyText).length,
    paragraphs: paragraphs.length,
    headings: document.querySelectorAll('h1, h2, h3, h4, h5, h6').length,
    tables: document.querySelectorAll('table').length,
    lists: document.querySelectorAll('ul, ol').length,
    codeBlocks: document.querySelectorAll('pre.wp-block-code, .wp-block-code pre').length,
    lineOrientedBlocks: document.querySelectorAll('pre.wp-block-verse, .wp-block-verse').length,
    dialogueParagraphs: document.querySelectorAll('p > strong:first-child + br').length,
    singleGlyphParagraphs,
    spacedGlyphRuns,
    lowContrastPdfFillCells,
    documentOverflow: document.documentElement
      ? Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)
      : 0,
    sampleText: bodyText.slice(0, 240),
  };
})()`;

async function iframeSnapshot(page, chromeUrl, clients, contexts) {
  const frameId = await currentIframeFrameId(page);
  if (!frameId) return null;
  const iframeClient = await iframeClientForFrame(chromeUrl, frameId, clients);
  if (iframeClient) {
    try {
      return await evaluate(iframeClient, iframeSnapshotExpression);
    } catch {
      clients.delete(frameId);
      await iframeClient.close().catch(() => {});
    }
  }
  try {
    let contextId = contexts.get(frameId);
    if (!contextId) {
      const world = await page.call('Page.createIsolatedWorld', {
        frameId,
        worldName: 'port-libs-pdf-layout-corpus-e2e',
      });
      contextId = world.executionContextId;
      contexts.set(frameId, contextId);
    }
    return await evaluate(page, iframeSnapshotExpression, contextId);
  } catch {
    contexts.delete(frameId);
    return null;
  }
}

function criterionErrors(criteria, snapshot) {
  const checks = {
    minTextBytes: ['textBytes', (actual, expected) => actual >= expected],
    minParagraphs: ['paragraphs', (actual, expected) => actual >= expected],
    minHeadings: ['headings', (actual, expected) => actual >= expected],
    minTables: ['tables', (actual, expected) => actual >= expected],
    maxTables: ['tables', (actual, expected) => actual <= expected],
    minLists: ['lists', (actual, expected) => actual >= expected],
    minCodeBlocks: ['codeBlocks', (actual, expected) => actual >= expected],
    maxCodeBlocks: ['codeBlocks', (actual, expected) => actual <= expected],
    minLineOrientedBlocks: ['lineOrientedBlocks', (actual, expected) => actual >= expected],
    maxLineOrientedBlocks: ['lineOrientedBlocks', (actual, expected) => actual <= expected],
    minDialogueParagraphs: ['dialogueParagraphs', (actual, expected) => actual >= expected],
    maxSingleGlyphParagraphs: ['singleGlyphParagraphs', (actual, expected) => actual.length <= expected],
  };
  const errors = [];
  for (const [criterion, [field, passes]] of Object.entries(checks)) {
    if (!(criterion in criteria)) continue;
    const actual = snapshot[field];
    const expected = criteria[criterion];
    if (!passes(actual, expected)) {
      errors.push(`${criterion}: expected ${expected}, observed ${Array.isArray(actual) ? actual.length : actual}`);
    }
  }
  if (!criteria.allowNoText && snapshot.textBytes === 0) {
    errors.push('the preview is unexpectedly empty');
  }
  for (const requiredText of Array.isArray(criteria.requiredText) ? criteria.requiredText : []) {
    if (!snapshot.bodyText.includes(String(requiredText))) {
      errors.push(`requiredText: missing ${JSON.stringify(requiredText)}`);
    }
  }
  let orderedOffset = -1;
  for (const orderedText of Array.isArray(criteria.orderedText) ? criteria.orderedText : []) {
    orderedOffset = snapshot.bodyText.indexOf(String(orderedText), orderedOffset + 1);
    if (orderedOffset < 0) {
      errors.push(`orderedText: missing or out of order ${JSON.stringify(orderedText)}`);
      break;
    }
  }
  return errors;
}

function layoutErrors(snapshot) {
  const errors = [];
  if (snapshot.activeTab !== 'wpBlocks') errors.push(`default tab is ${snapshot.activeTab || 'missing'}, not wpBlocks`);
  if (snapshot.tabCount !== 3) errors.push(`expected 3 preview tabs, observed ${snapshot.tabCount}`);
  if (!/wordpress-blocks-preview\.html(?:$|[?#])/.test(snapshot.loadedPath)) errors.push(`unexpected preview path ${snapshot.loadedPath}`);
  if (snapshot.statusTone === 'error' || /(?:failed|could not|unable to|error)/i.test(snapshot.statusVisible ? snapshot.statusText : '')) {
    errors.push(`the page reported an error: ${snapshot.statusText}`);
  }
  if (snapshot.documentOverflow > 2) errors.push(`outer page overflows by ${snapshot.documentOverflow}px`);
  if (!snapshot.picker || !snapshot.download || Math.abs(snapshot.picker.height - snapshot.download.height) > 2) {
    errors.push('download control is not the same height as the picker');
  }
  if (!snapshot.previous || !snapshot.next || !snapshot.toolbar) {
    errors.push('previous/next toolbar controls are missing');
  } else {
    if (snapshot.previous.width < 50 || snapshot.next.width < 50) errors.push('previous/next arrows are too narrow');
    if (Math.abs(snapshot.previous.top - snapshot.toolbar.top) > 2 || Math.abs(snapshot.previous.bottom - snapshot.toolbar.bottom) > 2
      || Math.abs(snapshot.next.top - snapshot.toolbar.top) > 2 || Math.abs(snapshot.next.bottom - snapshot.toolbar.bottom) > 2) {
      errors.push('previous/next arrows do not span the picker toolbar height');
    }
    if (snapshot.previous.right > snapshot.picker.left + 2 || snapshot.next.left < snapshot.picker.right - 2) {
      errors.push('previous/next arrows are not on opposite sides of the picker area');
    }
  }
  if (snapshot.previousLabel.toLowerCase() !== 'previous example' || snapshot.nextLabel.toLowerCase() !== 'next example') {
    errors.push('previous/next accessible labels are missing');
  }
  return errors;
}

const reviewerSnapshotExpression = `(() => {
  const root = document.querySelector('.reviewer');
  const picker = document.querySelector('#review-picker');
  const previous = document.querySelector('#review-previous');
  const next = document.querySelector('#review-next');
  const download = document.querySelector('#review-download');
  const detail = document.querySelector('#review-detail');
  const original = document.querySelector('#original-viewer');
  const converted = document.querySelector('#converted-frame');
  const activeView = String(root?.dataset?.activeView || '');
  const criteria = Array.from(document.querySelectorAll('#quality-criteria li'));
  const visible = (node) => {
    if (!node) return false;
    const style = getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  };
  const rect = (node) => {
    const value = node?.getBoundingClientRect();
    return value ? { left: value.left, top: value.top, right: value.right, bottom: value.bottom, width: value.width, height: value.height } : null;
  };
  const pendingCriteria = criteria.filter((node) => node.dataset.status === 'pending').map((node) => node.textContent.trim());
  const failingCriteria = criteria.filter((node) => node.dataset.status === 'fail').map((node) => node.textContent.trim());
  const originalLoadedPath = String(original?.dataset?.loadedPath || '');
  const originalRequestedPath = String(original?.dataset?.requestedPath || '');
  const originalStatus = String(document.querySelector('#original-status')?.textContent || '').replace(/\s+/g, ' ').trim();
  const originalPageCount = document.querySelectorAll('#original-pages canvas.original-page').length;
  const convertedLoadedPath = String(converted?.dataset?.loadedPath || '');
  const originalRequired = activeView !== 'converted';
  return {
    ready: Boolean(
      picker
      && picker.options.length > 0
      && convertedLoadedPath
      && converted?.contentDocument
      && ['complete', 'interactive'].includes(converted.contentDocument.readyState)
      && criteria.length > 0
      && pendingCriteria.length === 0
      && (!originalRequired || (originalLoadedPath && originalPageCount > 0 && originalStatus.startsWith('Original loaded')))
    ),
    selectedExample: String(picker?.value || ''),
    urlExample: new URL(location.href).searchParams.get('example') || '',
    urlView: new URL(location.href).searchParams.get('view') || '',
    activeView,
    optionCount: picker?.options?.length || 0,
    viewButtonCount: document.querySelectorAll('[data-review-view]').length,
    verdict: String(document.querySelector('[data-verdict][aria-pressed="true"]')?.dataset?.verdict || ''),
    qualitySummary: String(document.querySelector('#quality-summary')?.textContent || '').replace(/\\s+/g, ' ').trim(),
    pendingCriteria,
    failingCriteria,
    criteriaCount: criteria.length,
    originalLoadedPath,
    originalRequestedPath,
    originalStatus,
    originalPageCount,
    convertedLoadedPath,
    downloadPath: String(download?.getAttribute('href') || ''),
    detailPath: String(detail?.getAttribute('href') || ''),
    previousLabel: String(previous?.getAttribute('aria-label') || ''),
    nextLabel: String(next?.getAttribute('aria-label') || ''),
    originalVisible: visible(original?.closest('[data-pane="original"]')),
    convertedVisible: visible(converted?.closest('[data-pane="converted"]')),
    originalPane: rect(original?.closest('[data-pane="original"]')),
    convertedPane: rect(converted?.closest('[data-pane="converted"]')),
    documentOverflow: document.documentElement
      ? Math.max(0, document.documentElement.scrollWidth - window.innerWidth)
      : 0,
    viewport: { width: innerWidth, height: innerHeight },
  };
})()`;

async function waitForReviewer(page, options, expectedId, expectedView) {
  const startedAt = Date.now();
  let last = null;
  while (Date.now() - startedAt < options.timeoutMs) {
    last = await evaluate(page, reviewerSnapshotExpression);
    if (last?.ready
      && last.selectedExample === expectedId
      && last.urlExample === expectedId
      && last.activeView === expectedView
      && last.urlView === expectedView) {
      return last;
    }
    await sleep(options.pollMs);
  }
  throw new VerificationError(`Timed out waiting for reviewer example ${expectedId} in ${expectedView} view.`, last);
}

async function selectReviewerExample(page, id) {
  return evaluate(page, `(() => {
    const picker = document.querySelector('#review-picker');
    if (!picker || !Array.from(picker.options).some((option) => option.value === ${JSON.stringify(id)})) return false;
    picker.value = ${JSON.stringify(id)};
    picker.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  })()`);
}

function reviewerErrors(snapshot, manifestLength, expectedId, expectedView) {
  const errors = [];
  if (!snapshot.ready) errors.push('reviewer did not become ready');
  if (snapshot.selectedExample !== expectedId || snapshot.urlExample !== expectedId) {
    errors.push(`reviewer selection/URL diverged from ${expectedId}`);
  }
  if (snapshot.activeView !== expectedView || snapshot.urlView !== expectedView) {
    errors.push(`reviewer view/URL diverged from ${expectedView}`);
  }
  if (snapshot.optionCount !== manifestLength) errors.push(`expected ${manifestLength} reviewer options, observed ${snapshot.optionCount}`);
  if (snapshot.viewButtonCount !== 3) errors.push(`expected 3 reviewer view buttons, observed ${snapshot.viewButtonCount}`);
  if (snapshot.criteriaCount < 3) errors.push('reviewer did not render its automatic criteria');
  if (snapshot.pendingCriteria.length > 0) errors.push(`pending criteria remain: ${snapshot.pendingCriteria.join(' | ')}`);
  if (snapshot.failingCriteria.length > 0) errors.push(`criteria failed: ${snapshot.failingCriteria.join(' | ')}`);
  if (snapshot.qualitySummary !== 'All automatic checks pass') errors.push(`unexpected quality summary: ${snapshot.qualitySummary}`);
  if (!/wordpress-blocks-preview\.html(?:$|[?#])/.test(snapshot.convertedLoadedPath)) {
    errors.push(`unexpected converted preview path ${snapshot.convertedLoadedPath}`);
  }
  if (!/\.pdf(?:$|[?#])/.test(snapshot.downloadPath)) errors.push(`unexpected original download path ${snapshot.downloadPath}`);
  if (!snapshot.detailPath.includes(`example=${encodeURIComponent(expectedId)}`)) errors.push(`unexpected detail URL ${snapshot.detailPath}`);
  if (snapshot.previousLabel !== 'Previous document' || snapshot.nextLabel !== 'Next document') {
    errors.push('reviewer previous/next accessible labels are missing');
  }
  if (snapshot.documentOverflow > 2) errors.push(`reviewer overflows by ${snapshot.documentOverflow}px`);
  if (!snapshot.convertedVisible) errors.push('converted pane is not visible');
  if (expectedView === 'converted') {
    if (snapshot.originalLoadedPath || snapshot.originalRequestedPath || snapshot.originalPageCount > 0) errors.push('converted-only view eagerly loaded the original PDF');
    if (snapshot.originalVisible) errors.push('converted-only view left the original pane visible');
  } else if (expectedView === 'compare') {
    if (!/\.pdf(?:$|[?#])/.test(snapshot.originalLoadedPath)) errors.push('compare view did not load the original PDF');
    if (snapshot.originalPageCount < 1 || !snapshot.originalStatus.startsWith('Original loaded')) errors.push('compare view did not render an original PDF page');
    if (!snapshot.originalVisible) errors.push('compare view did not expose the original pane');
    if (!snapshot.originalPane || !snapshot.convertedPane || snapshot.originalPane.width < 300 || snapshot.convertedPane.width < 300) {
      errors.push('compare view did not allocate useful width to both panes');
    }
  }
  return errors;
}

async function waitForExample(page, options, document, clients, contexts) {
  const expectedId = `pdf-layout-${document.id}`;
  const startedAt = Date.now();
  let last = null;
  while (Date.now() - startedAt < options.timeoutMs) {
    const outer = await evaluate(page, outerSnapshotExpression);
    const frame = outer.ready && outer.selectedExample === expectedId && outer.urlExample === expectedId
      ? await iframeSnapshot(page, options.chrome, clients, contexts)
      : null;
    last = { outer, frame };
    if (outer.ready && outer.selectedExample === expectedId && outer.urlExample === expectedId && frame?.ready) {
      return last;
    }
    await sleep(options.pollMs);
  }
  throw new VerificationError(`Timed out waiting for ${expectedId}.`, last);
}

async function selectExample(page, id) {
  return evaluate(page, `(() => {
    const picker = document.querySelector('#example-picker');
    if (!picker || !Array.from(picker.options).some((option) => option.value === ${JSON.stringify(id)})) return false;
    picker.value = ${JSON.stringify(id)};
    picker.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  })()`);
}

async function clickControl(page, selector) {
  return evaluate(page, `(() => {
    const control = document.querySelector(${JSON.stringify(selector)});
    if (!control || control.disabled) return false;
    control.click();
    return true;
  })()`);
}

async function setViewport(page, viewport) {
  await page.call('Emulation.setDeviceMetricsOverride', viewport);
  await sleep(100);
}

async function captureScreenshot(page, filename) {
  const capture = await page.call('Page.captureScreenshot', {
    format: 'png',
    fromSurface: true,
    captureBeyondViewport: false,
  });
  await writeFile(filename, Buffer.from(capture.data, 'base64'));
}

async function settleVisualPaint(page, milliseconds = 250) {
  await evaluate(page, `new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))`);
  await sleep(milliseconds);
}

function attachObservationLog(page) {
  const observations = { consoleErrors: [], pageErrors: [], networkFailures: [] };
  page.on('Runtime.consoleAPICalled', (params) => {
    if (params.type !== 'error') return;
    observations.consoleErrors.push(params.args.map((arg) => arg.value ?? arg.description ?? arg.type).join(' '));
  });
  page.on('Runtime.exceptionThrown', (params) => {
    observations.pageErrors.push(params.exceptionDetails?.exception?.description || params.exceptionDetails?.text || 'Unknown page exception');
  });
  page.on('Network.loadingFailed', (params) => {
    if (params.canceled || params.errorText === 'net::ERR_ABORTED') return;
    observations.networkFailures.push(`${params.errorText || 'network failure'} (${params.type || 'Other'})`);
  });
  return observations;
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
      for (const { reject, timer } of this.pending.values()) {
        clearTimeout(timer);
        reject(new Error('Chrome DevTools closed the connection.'));
      }
      this.pending.clear();
    });
  }

  call(method, params = {}, timeoutMs = 10_000) {
    const id = this.nextId++;
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pending.delete(id);
        reject(new Error(`Chrome DevTools timed out running ${method}.`));
      }, timeoutMs);
      this.pending.set(id, { resolve, reject, timer });
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
      clearTimeout(pending.timer);
      if (message.error) pending.reject(new Error(`${message.error.message || 'Chrome DevTools error'} (${message.error.code ?? 'unknown'})`));
      else pending.resolve(message.result || {});
      return;
    }
    for (const listener of this.listeners.get(message.method) || []) listener(message.params || {});
  }

  async close() {
    this.socket.close();
  }
}

class VerificationError extends Error {
  constructor(message, details = undefined) {
    super(message);
    this.name = 'VerificationError';
    this.details = details;
  }
}

async function main() {
  const options = parseOptions(process.argv.slice(2));
  const manifest = JSON.parse(await readFile(options.manifest, 'utf8'));
  if (!Array.isArray(manifest) || manifest.length < 10) {
    throw new Error(`Expected at least 10 PDF corpus documents in ${options.manifest}.`);
  }
  await mkdir(options.output, { recursive: true });

  const browser = await CdpClient.connect(await browserWebSocketUrl(options.chrome));
  const iframeClients = new Map();
  const parentFrameContexts = new Map();
  let page;
  let targetId;
  try {
    const target = await browser.call('Target.createTarget', { url: 'about:blank' });
    targetId = target.targetId;
    page = await CdpClient.connect(await targetWebSocketUrl(options.chrome, targetId));
    const observations = attachObservationLog(page);
    await Promise.all([
      page.call('Page.enable'),
      page.call('Runtime.enable'),
      page.call('DOM.enable'),
      page.call('Network.enable'),
    ]);
    const results = [];
    if (!options.reviewOnly) {
      await setViewport(page, viewports.mobile);
      const firstId = `pdf-layout-${manifest[0].id}`;
      const initialUrl = new URL(options.url);
      initialUrl.searchParams.set('example', firstId);
      initialUrl.searchParams.set('e2e', `pdf-layout-${Date.now()}`);
      await page.call('Page.navigate', { url: initialUrl.href });
      for (let index = 0; index < manifest.length; index += 1) {
      const document = manifest[index];
      const expectedId = `pdf-layout-${document.id}`;
      if (index > 0 && !(await selectExample(page, expectedId))) {
        throw new VerificationError(`The picker does not contain ${expectedId}.`);
      }
      const snapshot = await waitForExample(page, options, document, iframeClients, parentFrameContexts);
      const errors = [
        ...layoutErrors(snapshot.outer),
        ...criterionErrors(document.success || {}, snapshot.frame),
      ];
      if (snapshot.frame.documentOverflow > 2) errors.push(`preview overflows by ${snapshot.frame.documentOverflow}px`);
      if (snapshot.frame.spacedGlyphRuns.length > 0) errors.push(`sustained inter-glyph spacing remains: ${snapshot.frame.spacedGlyphRuns.join(' | ')}`);
      if (snapshot.frame.lowContrastPdfFillCells.length > 0) errors.push(`PDF table fill has low-contrast text: ${snapshot.frame.lowContrastPdfFillCells.join(' | ')}`);
      if (errors.length > 0) {
        throw new VerificationError(`${expectedId} failed browser verification: ${errors.join('; ')}`, snapshot);
      }

      const screenshots = {};
      for (const [viewportName, viewport] of Object.entries(viewports)) {
        await setViewport(page, viewport);
        const filename = path.join(options.output, `${document.id}-${viewportName}.png`);
        await captureScreenshot(page, filename);
        screenshots[viewportName] = filename;
      }
      await setViewport(page, viewports.mobile);
      const navigation = {};
      if (index === 0 && manifest.length > 1) {
        if (!(await clickControl(page, '#next-example'))) {
          throw new VerificationError('The next-example control could not be activated.');
        }
        const nextSnapshot = await waitForExample(page, options, manifest[1], iframeClients, parentFrameContexts);
        navigation.next = nextSnapshot.outer.selectedExample;
        if (!(await clickControl(page, '#previous-example'))) {
          throw new VerificationError('The previous-example control could not be activated.');
        }
        const previousSnapshot = await waitForExample(page, options, manifest[0], iframeClients, parentFrameContexts);
        navigation.previous = previousSnapshot.outer.selectedExample;
      }
      const frameSummary = { ...snapshot.frame };
      delete frameSummary.bodyText;
      results.push({ id: expectedId, outer: snapshot.outer, frame: frameSummary, screenshots, navigation });
        console.log(`PASS ${expectedId}: ${snapshot.frame.textBytes} text bytes, ${snapshot.frame.paragraphs} paragraphs`);
      }
    }

    const multicolumnDocument = manifest.find((document) => document.id === 'unstructured-multicolumn');
    const formulaDocument = manifest.find((document) => document.id === 'docling-code-formula');
    const theatreDocument = manifest.find((document) => document.id === 'vdl-theatre-script');
    if (!multicolumnDocument || !formulaDocument || !theatreDocument) {
      throw new VerificationError('The reviewer E2E requires the multicolumn, code/formula, and theatre corpus documents.');
    }
    const multicolumnId = `pdf-layout-${multicolumnDocument.id}`;
    const formulaId = `pdf-layout-${formulaDocument.id}`;
    const theatreId = `pdf-layout-${theatreDocument.id}`;
    const reviewer = { screenshots: {}, navigation: {}, verdictPersistence: false };

    await setViewport(page, viewports.desktop);
    const reviewerUrl = new URL(options.reviewUrl);
    reviewerUrl.searchParams.set('example', multicolumnId);
    reviewerUrl.searchParams.set('view', 'converted');
    reviewerUrl.searchParams.set('e2e', `pdf-reviewer-${Date.now()}`);
    await page.call('Page.navigate', { url: reviewerUrl.href });
    let reviewerSnapshot = await waitForReviewer(page, options, multicolumnId, 'converted');
    let reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, multicolumnId, 'converted');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The desktop converted reviewer failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    reviewer.screenshots.multicolumnDesktop = path.join(options.output, 'reviewer-multicolumn-desktop.png');
    await settleVisualPaint(page);
    await captureScreenshot(page, reviewer.screenshots.multicolumnDesktop);

    if (!(await selectReviewerExample(page, formulaId))) {
      throw new VerificationError(`The reviewer picker does not contain ${formulaId}.`);
    }
    reviewerSnapshot = await waitForReviewer(page, options, formulaId, 'converted');
    reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, formulaId, 'converted');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The formula reviewer failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    const formulaUrl = new URL(options.reviewUrl);
    formulaUrl.searchParams.set('example', formulaId);
    formulaUrl.searchParams.set('view', 'converted');
    formulaUrl.searchParams.set('e2e', `pdf-reviewer-formula-${Date.now()}`);
    await page.call('Page.navigate', { url: formulaUrl.href });
    reviewerSnapshot = await waitForReviewer(page, options, formulaId, 'converted');
    reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, formulaId, 'converted');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The formula reviewer deep link failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    reviewer.screenshots.formulaDesktop = path.join(options.output, 'reviewer-formula-desktop.png');
    await settleVisualPaint(page, 500);
    await captureScreenshot(page, reviewer.screenshots.formulaDesktop);

    if (!(await selectReviewerExample(page, theatreId))) {
      throw new VerificationError(`The reviewer picker does not contain ${theatreId}.`);
    }
    reviewerSnapshot = await waitForReviewer(page, options, theatreId, 'converted');
    reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, theatreId, 'converted');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The theatre reviewer failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    reviewer.screenshots.theatreDesktop = path.join(options.output, 'reviewer-theatre-desktop.png');
    await settleVisualPaint(page, 500);
    await captureScreenshot(page, reviewer.screenshots.theatreDesktop);

    if (!(await selectReviewerExample(page, formulaId))) {
      throw new VerificationError(`The reviewer picker does not contain ${formulaId}.`);
    }
    reviewerSnapshot = await waitForReviewer(page, options, formulaId, 'converted');

    let verdictSnapshot = await evaluate(page, reviewerSnapshotExpression);
    if (verdictSnapshot.verdict !== 'pass'
      && !(await clickControl(page, '[data-verdict="pass"]'))) {
      throw new VerificationError('The reviewer verdict control could not be activated.');
    }
    verdictSnapshot = await evaluate(page, reviewerSnapshotExpression);
    if (verdictSnapshot.verdict !== 'pass') throw new VerificationError('The reviewer did not retain the selected verdict.', verdictSnapshot);
    const formulaIndex = manifest.findIndex((document) => document.id === formulaDocument.id);
    const nextDocument = manifest[(formulaIndex + 1) % manifest.length];
    const nextId = `pdf-layout-${nextDocument.id}`;
    if (!(await clickControl(page, '#review-next'))) throw new VerificationError('The reviewer next control could not be activated.');
    reviewer.navigation.next = (await waitForReviewer(page, options, nextId, 'converted')).selectedExample;
    if (!(await clickControl(page, '#review-previous'))) throw new VerificationError('The reviewer previous control could not be activated.');
    verdictSnapshot = await waitForReviewer(page, options, formulaId, 'converted');
    reviewer.navigation.previous = verdictSnapshot.selectedExample;
    reviewer.verdictPersistence = verdictSnapshot.verdict === 'pass';
    if (!reviewer.verdictPersistence) throw new VerificationError('The reviewer verdict did not persist after navigation.', verdictSnapshot);

    if (!(await selectReviewerExample(page, multicolumnId))) {
      throw new VerificationError(`The reviewer picker does not contain ${multicolumnId}.`);
    }
    await setViewport(page, viewports.mobile);
    reviewerSnapshot = await waitForReviewer(page, options, multicolumnId, 'converted');
    reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, multicolumnId, 'converted');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The mobile reviewer failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    reviewer.screenshots.multicolumnMobile = path.join(options.output, 'reviewer-multicolumn-mobile.png');
    await settleVisualPaint(page);
    await captureScreenshot(page, reviewer.screenshots.multicolumnMobile);

    await setViewport(page, viewports.desktop);
    if (!(await clickControl(page, '[data-review-view="compare"]'))) {
      throw new VerificationError('The reviewer compare control could not be activated.');
    }
    reviewerSnapshot = await waitForReviewer(page, options, multicolumnId, 'compare');
    reviewerFailures = reviewerErrors(reviewerSnapshot, manifest.length, multicolumnId, 'compare');
    if (reviewerFailures.length > 0) {
      throw new VerificationError(`The compare reviewer failed: ${reviewerFailures.join('; ')}`, reviewerSnapshot);
    }
    reviewer.screenshots.multicolumnCompareDesktop = path.join(options.output, 'reviewer-multicolumn-compare-desktop.png');
    await settleVisualPaint(page, 2_000);
    await captureScreenshot(page, reviewer.screenshots.multicolumnCompareDesktop);
    reviewer.finalSnapshot = reviewerSnapshot;
    console.log(`PASS PDF layout reviewer: ${manifest.length} public documents, deep links, mobile safety, and comparison view`);

    const errors = [
      ...observations.consoleErrors.map((message) => `console: ${message}`),
      ...observations.pageErrors.map((message) => `page: ${message}`),
      ...observations.networkFailures.map((message) => `network: ${message}`),
    ];
    if (errors.length > 0) {
      throw new VerificationError(`Browser diagnostics were not clean: ${errors.join('; ')}`, observations);
    }

    const report = {
      ok: true,
      url: options.url,
      reviewUrl: options.reviewUrl,
      manifest: options.manifest,
      documents: results.length,
      viewports,
      results,
      reviewer,
      observations,
    };
    await writeFile(path.join(options.output, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
    console.log(`${options.reviewOnly ? 'Verified the PDF layout reviewer' : `Verified ${results.length} PDF layout documents`}; screenshots are in ${options.output}.`);
  } finally {
    for (const client of iframeClients.values()) await client.close().catch(() => {});
    await page?.close().catch(() => {});
    if (targetId) await browser.call('Target.closeTarget', { targetId }).catch(() => {});
    await browser.close().catch(() => {});
  }
}

try {
  await main();
} catch (error) {
  console.error(error.message || String(error));
  if (error.details) console.error(JSON.stringify(error.details, null, 2));
  process.exitCode = 1;
}
