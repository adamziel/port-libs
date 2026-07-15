#!/usr/bin/env node

/**
 * Exercise every PDF layout corpus example in the static browser through an
 * already-running Chrome DevTools endpoint. This intentionally uses CDP
 * directly so a checkout needs no Playwright/Puppeteer dependency.
 *
 * Usage:
 *   node tools/e2e-pdf-layout-corpus.mjs
 *   node tools/e2e-pdf-layout-corpus.mjs --url http://127.0.0.1:4174/examples.html
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html',
  manifest: 'tools/pdf-layout-corpus-manifest.json',
  output: 'artifacts/pdf-layout-screenshots',
  timeoutMs: 60_000,
  pollMs: 150,
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
    } else if (argument === '--help' || argument === '-h') {
      console.log('Usage: node tools/e2e-pdf-layout-corpus.mjs [--url URL] [--chrome URL] [--manifest FILE] [--output DIR]');
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
    textBytes: new TextEncoder().encode(bodyText).length,
    paragraphs: paragraphs.length,
    headings: document.querySelectorAll('h1, h2, h3, h4, h5, h6').length,
    tables: document.querySelectorAll('table').length,
    lists: document.querySelectorAll('ul, ol').length,
    codeBlocks: document.querySelectorAll('pre.wp-block-code, .wp-block-code pre').length,
    lineOrientedBlocks: document.querySelectorAll('pre.wp-block-verse, .wp-block-verse').length,
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
    minLineOrientedBlocks: ['lineOrientedBlocks', (actual, expected) => actual >= expected],
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
    await setViewport(page, viewports.mobile);

    const firstId = `pdf-layout-${manifest[0].id}`;
    const initialUrl = new URL(options.url);
    initialUrl.searchParams.set('example', firstId);
    initialUrl.searchParams.set('e2e', `pdf-layout-${Date.now()}`);
    await page.call('Page.navigate', { url: initialUrl.href });

    const results = [];
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
      results.push({ id: expectedId, outer: snapshot.outer, frame: snapshot.frame, screenshots, navigation });
      console.log(`PASS ${expectedId}: ${snapshot.frame.textBytes} text bytes, ${snapshot.frame.paragraphs} paragraphs`);
    }

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
      manifest: options.manifest,
      documents: results.length,
      viewports,
      results,
      observations,
    };
    await writeFile(path.join(options.output, 'report.json'), `${JSON.stringify(report, null, 2)}\n`);
    console.log(`Verified ${results.length} PDF layout documents; screenshots are in ${options.output}.`);
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
