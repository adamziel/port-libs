#!/usr/bin/env node

/**
 * Verify that the static TraceMonkey showcase renders vector PDF Form XObjects
 * in the browser rather than silently falling back to the text-only preview.
 *
 * This talks directly to an already-running Chrome DevTools endpoint so it has
 * no Playwright or Puppeteer dependency.
 *
 * Usage:
 *   node tools/e2e-showcase-pdf-forms.mjs
 *   node tools/e2e-showcase-pdf-forms.mjs --url https://example.test/examples.html?example=pdf-tracemonkey
 */

import process from 'node:process';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html?example=pdf-tracemonkey',
  timeoutMs: 2 * 60 * 1000,
  pollMs: 500,
  expectedImages: 8,
  expectedExample: 'pdf-tracemonkey',
};

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
      options.expectedImages = Math.max(1, Math.floor(Number(value) || options.expectedImages));
      index += 1;
    } else if (argument === '--expected-example') {
      options.expectedExample = value || options.expectedExample;
      index += 1;
    } else if (argument === '--help' || argument === '-h') {
      printUsage();
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${argument}`);
    }
  }
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
    '  --expected-images N       Minimum injected chart images (default 8).',
    '  --expected-example ID     Expected selected example (default pdf-tracemonkey).',
  ].join('\n'));
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

function attachObservationLog(page) {
  const observations = {
    consoleErrors: [],
    pageErrors: [],
    networkFailures: [],
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
    const failure = `${params.errorText || 'network failure'} (${params.type || 'Other'})`;
    observations.networkFailures.push(failure);
    console.error(`network failure: ${failure}`);
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
    return snapshot;
  })()`;
}

function iframeSnapshotExpression() {
  return `(() => {
    const text = (node) => String(node?.textContent || '').replace(/\\s+/g, ' ').trim();
    const fallbackSelector = [
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
    return {
      documentReady: document.readyState === 'complete' || document.readyState === 'interactive',
      bodyPresent: Boolean(document.body),
      injectedImages: images.length,
      decodedImages: images.filter((image) => image.complete && image.naturalWidth > 0 && image.naturalHeight > 0).length,
      imageSources: images.slice(0, 12).map((image) => String(image.getAttribute('src') || '').slice(0, 80)),
      fallbackSignals: Array.from(document.querySelectorAll(fallbackSelector))
        .map((node) => text(node) || node.getAttribute('data-pandoc-pdf-form-render-status') || node.getAttribute('data-pdf-form-render-status') || node.tagName)
        .slice(0, 10),
    };
  })()`;
}

function isRenderingFailure(snapshot) {
  const status = snapshot.status || {};
  const visibleStatus = status.visible ? status.text : '';
  const statusImpliesFailure = status.tone === 'error'
    || /\\b(?:fallback|failed|failure|could not|unable to|rendering error|error)\\b/i.test(visibleStatus);
  return statusImpliesFailure
    || snapshot.outerFallbackSignals.length > 0
    || snapshot.iframe.fallbackSignals.length > 0;
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

async function iframeClientForFrame(baseUrl, frameId, iframeClients) {
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
  await client.call('Runtime.enable');
  iframeClients.set(frameId, client);
  return client;
}

async function snapshotIframe(page, chromeUrl, iframeClients, parentFrameContexts) {
  const frameId = await currentIframeFrameId(page);
  const iframeClient = await iframeClientForFrame(chromeUrl, frameId, iframeClients);
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
      imageSources: [],
      fallbackSignals: [],
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
      imageSources: [],
      fallbackSignals: [],
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

async function main() {
  const options = parseOptions(process.argv.slice(2));
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
        ...await snapshotIframe(page, options.chrome, iframeClients, parentFrameContexts),
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
      if (selectedExampleMatches && iframeReady
        && snapshot.iframe.injectedImages >= options.expectedImages
        && snapshot.iframe.decodedImages >= options.expectedImages) {
        const result = {
          ok: true,
          url: options.url,
          elapsedMs: Date.now() - startedAt,
          expectedExample: options.expectedExample,
          expectedImages: options.expectedImages,
          snapshot,
          statusHistory,
          observations,
        };
        console.log(JSON.stringify(result, null, 2));
        if (observations.pageErrors.length > 0) {
          throw new VerificationError('The charts rendered, but the browser reported a page exception.', result);
        }
        return;
      }
      await sleep(options.pollMs);
    }
    throw new VerificationError(`Timed out after ${formatElapsed(options.timeoutMs)} waiting for ${options.expectedImages} injected PDF Form chart images.`, {
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

try {
  await main();
} catch (error) {
  console.error(error.message || String(error));
  if (error.details) {
    console.error(JSON.stringify({ ok: false, ...error.details }, null, 2));
  }
  process.exitCode = 1;
}
