#!/usr/bin/env node

/**
 * Drive the public examples-page importer through an already-running Chrome
 * DevTools endpoint. This deliberately has no Playwright/Puppeteer dependency:
 * a fresh checkout can use Node 22+ and Chrome's built-in CDP WebSocket.
 *
 * Usage:
 *   node tools/e2e-playground-import.mjs --file path/to/input.pdf \
 *     --pdf-output-mode single
 *
 * The page starts a real WordPress Playground, installs the packaged plugin,
 * stages the selected document, and waits for the visible success/error state.
 * It is intended for manual release verification as well as CI-like local
 * debugging of the browser/server import conversation.
 */

import process from 'node:process';
import { browserProcessTreeMemory } from './browser-memory-monitor.mjs';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html',
  timeoutMs: 10 * 60 * 1000,
  pollMs: 500,
  maxElapsedMs: 0,
  // The release gate requires enough headroom for ordinary developer laptops
  // and CI workers. Completed canvases, render tasks, and staged source copies
  // must be released incrementally instead of expanding this ceiling.
  maxBrowserMemoryMb: 1536,
  chromePid: Math.max(0, Number(process.env.PORT_LIBS_CHROME_PID) || 0),
  pdfOutputMode: 'single',
  expectedPdfPages: 0,
  expectedImageCount: -1,
};

function parseOptions(args) {
  const options = { ...defaults, file: '' };
  for (let index = 0; index < args.length; index += 1) {
    const argument = args[index];
    const value = args[index + 1];
    if (argument === '--file') {
      options.file = value || '';
      index += 1;
    } else if (argument === '--url') {
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
    } else if (argument === '--max-elapsed-ms') {
      options.maxElapsedMs = Math.max(0, Number(value) || 0);
      index += 1;
    } else if (argument === '--max-browser-memory-mb' || argument === '--max-browser-rss-mb') {
      options.maxBrowserMemoryMb = Math.max(0, Number(value) || 0);
      index += 1;
    } else if (argument === '--chrome-pid') {
      options.chromePid = Math.max(0, Number(value) || 0);
      index += 1;
    } else if (argument === '--pdf-output-mode') {
      if (!['single', 'pages'].includes(value)) {
        fail('--pdf-output-mode must be either "single" or "pages".');
      }
      options.pdfOutputMode = value;
      index += 1;
    } else if (argument === '--expected-pdf-pages') {
      options.expectedPdfPages = Math.max(0, Number(value) || 0);
      index += 1;
    } else if (argument === '--expected-image-count') {
      options.expectedImageCount = Math.max(0, Number(value) || 0);
      index += 1;
    } else {
      fail(`Unknown argument: ${argument}`);
    }
  }
  return options;
}

async function browserWebSocketUrl(baseUrl) {
  const response = await fetch(new URL('/json/version', baseUrl));
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
  page.on('Log.entryAdded', (params) => {
    if (params.entry?.level === 'error') {
      console.error(`browser log: ${params.entry.text || 'error'}`);
    }
  });
  return observations;
}

async function setFileInput(page, selector, file) {
  const document = await page.call('DOM.getDocument', { depth: 1 });
  const result = await page.call('DOM.querySelector', { nodeId: document.root.nodeId, selector });
  if (!result.nodeId) {
    throw new Error(`Could not find file input ${selector}.`);
  }
  await page.call('DOM.setFileInputFiles', { files: [file], nodeId: result.nodeId });
}

async function submitPdfOutputDialog(page, mode, options, description) {
  await waitForCondition(
    page,
    `Boolean(document.querySelector('#own-pdf-output-dialog')?.open)`,
    options,
    description,
  );
  const submitted = await evaluate(page, `(() => {
    const dialog = document.querySelector('#own-pdf-output-dialog');
    const input = document.querySelector('input[name="own-pdf-output-mode"][value="${mode}"]');
    const submit = dialog?.querySelector('.dialog-import');
    if (!dialog?.open || !input || input.disabled || !submit) return false;
    input.checked = true;
    submit.click();
    return true;
  })()`);
  if (!submitted) {
    throw new Error(`Could not choose the PDF output mode ${mode}.`);
  }
}

async function waitForCondition(page, expression, options, description) {
  const startedAt = Date.now();
  while (Date.now() - startedAt < options.timeoutMs) {
    if (await evaluate(page, expression)) {
      return;
    }
    await sleep(options.pollMs);
  }
  throw new Error(`Timed out waiting for ${description}.`);
}

async function evaluate(page, expression) {
  const result = await page.call('Runtime.evaluate', {
    expression,
    awaitPromise: true,
    returnByValue: true,
  });
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Chrome evaluation failed.');
  }
  return result.result?.value;
}

function withTestMarker(url) {
  const parsed = new URL(url);
  parsed.searchParams.set('e2e', `chrome-${Date.now()}`);
  return parsed.href;
}

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function formatElapsed(milliseconds) {
  const seconds = Math.floor(milliseconds / 1_000);
  return `${Math.floor(seconds / 60)}m${String(seconds % 60).padStart(2, '0')}s`;
}

function formatMemoryMeasurement(memory) {
  const measuredMiB = Math.ceil(memory.measurementBytes / 1024 / 1024);
  const rssMiB = Math.ceil(memory.rssBytes / 1024 / 1024);
  return memory.memoryMetric === 'pss'
    ? `${measuredMiB} MiB PSS (${rssMiB} MiB summed RSS across ${memory.processCount} processes)`
    : `${measuredMiB} MiB summed RSS across ${memory.processCount} processes`;
}

function fail(message) {
  console.error(message);
  process.exit(2);
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
      if (!pending) {
        return;
      }
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
  if (!options.file) {
    fail('Usage: node tools/e2e-playground-import.mjs --file /absolute/path/to/document [--pdf-output-mode single|pages] [--expected-pdf-pages N] [--expected-image-count N] [--url URL] [--chrome URL] [--chrome-pid PID] [--timeout-ms N] [--max-elapsed-ms N] [--max-browser-memory-mb N]');
  }

  const browser = await CdpClient.connect(await browserWebSocketUrl(options.chrome));
  let page;
  let targetId = '';
  try {
    const target = await browser.call('Target.createTarget', { url: 'about:blank' });
    targetId = target.targetId;
    const pageWebSocketUrl = await targetWebSocketUrl(options.chrome, target.targetId);
    page = await CdpClient.connect(pageWebSocketUrl);
    const observations = attachObservationLog(page);

    await Promise.all([
      page.call('Page.enable'),
      page.call('Runtime.enable'),
      page.call('DOM.enable'),
      page.call('Log.enable'),
      page.call('Network.enable'),
    ]);

    const testUrl = withTestMarker(options.url);
    await page.call('Page.navigate', { url: testUrl });
    await waitForCondition(page, `Boolean(document.querySelector('#example-picker:not([disabled])'))`, options, 'the example catalogue to load');

    let browserMemoryMetric = '';
    let initialBrowserMemoryBytes = 0;
    let initialBrowserRssBytes = 0;
    let initialBrowserPssBytes = 0;
    let peakBrowserMemoryBytes = 0;
    let peakBrowserRssBytes = 0;
    let peakBrowserPssBytes = 0;
    let peakBrowserProcessCount = 0;
    const sampleBrowserMemory = () => {
      if (options.maxBrowserMemoryMb <= 0) {
        return null;
      }
      let memory;
      try {
        memory = browserProcessTreeMemory(options.chrome, { rootPid: options.chromePid });
      } catch (error) {
        const reason = error instanceof Error ? error.message : String(error);
        throw new Error(`Could not measure Chrome memory while the safety ceiling is enabled: ${reason}`);
      }
      browserMemoryMetric = memory.memoryMetric;
      peakBrowserRssBytes = Math.max(peakBrowserRssBytes, memory.rssBytes);
      peakBrowserPssBytes = Math.max(peakBrowserPssBytes, memory.pssBytes);
      if (memory.measurementBytes > peakBrowserMemoryBytes) {
        peakBrowserMemoryBytes = memory.measurementBytes;
        peakBrowserProcessCount = memory.processCount;
      }
      const ceilingBytes = options.maxBrowserMemoryMb * 1024 * 1024;
      if (memory.measurementBytes > ceilingBytes) {
        throw new Error(`Chrome exceeded the ${options.maxBrowserMemoryMb} MiB browser-memory safety ceiling (${formatMemoryMeasurement(memory)}).`);
      }
      return memory;
    };
    const initialMemory = sampleBrowserMemory();
    if (initialMemory) {
      initialBrowserMemoryBytes = initialMemory.measurementBytes;
      initialBrowserRssBytes = initialMemory.rssBytes;
      initialBrowserPssBytes = initialMemory.pssBytes;
      console.log(`[memory] Initial Chrome footprint: ${formatMemoryMeasurement(initialMemory)}.`);
    }

    await setFileInput(page, '#own-file-input', options.file);
    const isPdf = /\.pdf$/i.test(options.file);
    if (isPdf) {
      await submitPdfOutputDialog(page, options.pdfOutputMode, options, 'the PDF output-mode dialog');
    }

    const statusHistory = [];
    const startedAt = Date.now();
    let lastStatus = '';
    let completed = false;
    let nextMemoryPollAt = 0;
    let recoveredToPages = false;
    while (Date.now() - startedAt < options.timeoutMs) {
      if (options.maxBrowserMemoryMb > 0 && Date.now() >= nextMemoryPollAt) {
        nextMemoryPollAt = Date.now() + 500;
        sampleBrowserMemory();
      }
      const status = await evaluate(page, `(() => {
        const status = document.querySelector('#viewer-status');
        const button = document.querySelector('#try-own-file');
        return {
          text: String(status?.textContent || '').trim(),
          visible: Boolean(status && !status.hidden),
          tone: String(status?.dataset?.tone || ''),
          button: String(button?.textContent || '').trim(),
          disabled: Boolean(button?.disabled),
        };
      })()`);
      if (status.text && status.text !== lastStatus) {
        lastStatus = status.text;
        statusHistory.push({ atMs: Date.now() - startedAt, ...status });
        console.log(`[${formatElapsed(Date.now() - startedAt)}] ${status.text}`);
      }
      const recoveryDialogOpen = isPdf && await evaluate(
        page,
        `Boolean(document.querySelector('#own-pdf-output-dialog')?.open
          && document.querySelector('input[name="own-pdf-output-mode"][value="single"]')?.disabled)`,
      );
      if (recoveryDialogOpen) {
        await submitPdfOutputDialog(page, 'pages', options, 'the recoverable oversized-PDF dialog');
        recoveredToPages = true;
        console.log(`[${formatElapsed(Date.now() - startedAt)}] Continuing the saved conversion as one child page per PDF page.`);
        continue;
      }
      const importFinished = /^(?:(?:Import complete\..* )?Opened a new WordPress page for |The import completed and the WordPress page was saved)/.test(status.text);
      if (status.tone === 'success' && importFinished) {
        sampleBrowserMemory();
        const integrity = await evaluate(page, `window.__portLibsImportE2E?.inspectLastImport()`);
        if (!integrity || !Array.isArray(integrity.posts) || integrity.posts.length < 1) {
          throw new Error('The UI reported success without inspectable WordPress pages.');
        }
        for (const post of integrity.posts) {
          const emptyButCertified = post.intentionalBlank
            && post.contentBytes === 0
            && post.visibleTextBytes === 0
            && post.imageCount === 0;
          if (post.status !== 'publish' || (!emptyButCertified && (post.contentBytes < 1
              || (post.visibleTextBytes < 1 && post.imageCount < 1)))) {
            throw new Error(`WordPress page ${post.postId} failed publication integrity: ${JSON.stringify(post)}`);
          }
          if (post.rawDataProvenanceCount !== 0) {
            throw new Error(`WordPress page ${post.postId} retained an encoded data-URI provenance attribute.`);
          }
          if (post.importNoticeCount !== 0) {
            throw new Error(`WordPress page ${post.postId} prepended import diagnostics to the document body.`);
          }
        }
        if (isPdf) {
          const effectiveMode = recoveredToPages ? 'pages' : options.pdfOutputMode;
          const expectedKind = effectiveMode === 'pages' ? 'pdf-page-tree' : 'single';
          if (integrity.resultKind !== expectedKind || integrity.pdfOutputMode !== effectiveMode) {
            throw new Error(`The PDF publication topology was ${integrity.resultKind}/${integrity.pdfOutputMode}; expected ${expectedKind}/${effectiveMode}.`);
          }
          const expectedPosts = effectiveMode === 'pages'
            ? Math.max(1, integrity.pageCount + 1)
            : 1;
          if (integrity.posts.length !== expectedPosts) {
            throw new Error(`The ${effectiveMode} PDF import published ${integrity.posts.length} posts; expected ${expectedPosts}.`);
          }
          if (options.expectedPdfPages > 0 && integrity.pageCount !== options.expectedPdfPages) {
            throw new Error(`The PDF import retained ${integrity.pageCount} physical pages; expected ${options.expectedPdfPages}.`);
          }
          if (options.expectedImageCount >= 0) {
            const imageCount = integrity.posts.reduce((sum, post) => sum + post.imageCount, 0);
            if (imageCount !== options.expectedImageCount) {
              throw new Error(`The PDF import retained ${imageCount} images; expected ${options.expectedImageCount}.`);
            }
          }
          const messages = statusHistory.map((entry) => entry.text).join('\n');
          if (!messages.includes('verified privately') || !/(?:Import complete|import completed)/.test(messages)) {
            throw new Error('The PDF did not expose verified-draft and completion stages in the UI.');
          }
        }
        const elapsedMs = Date.now() - startedAt;
        if (options.maxElapsedMs > 0 && elapsedMs > options.maxElapsedMs) {
          throw new Error(`Import exceeded the ${formatElapsed(options.maxElapsedMs)} performance ceiling (${formatElapsed(elapsedMs)}).`);
        }
        const result = {
          ok: true,
          file: options.file,
          url: testUrl,
          elapsedMs,
          browserMemoryMetric,
          initialBrowserMemoryBytes,
          initialBrowserRssBytes,
          initialBrowserPssBytes,
          peakBrowserMemoryBytes,
          peakBrowserRssBytes,
          peakBrowserPssBytes,
          peakBrowserProcessCount,
          requestedPdfOutputMode: isPdf ? options.pdfOutputMode : null,
          recoveredToPages,
          integrity,
          statusHistory,
          consoleErrors: observations.consoleErrors,
          pageErrors: observations.pageErrors,
          networkFailures: observations.networkFailures,
        };
        console.log(JSON.stringify(result, null, 2));
        if (observations.pageErrors.length > 0) {
          throw new Error('The import completed, but the browser reported page errors.');
        }
        completed = true;
        break;
      }
      if (status.tone === 'error' || /^Could not open /.test(status.text)) {
        throw new Error(`Visible importer error: ${status.text}`);
      }
      await sleep(options.pollMs);
    }

    if (!completed) {
      throw new Error(`Timed out after ${formatElapsed(options.timeoutMs)} waiting for the visible importer result.`);
    }
  } finally {
    await page?.close().catch(() => {});
    if (targetId) {
      await browser.call('Target.closeTarget', { targetId }).catch(() => {});
    }
    await browser.close().catch(() => {});
  }
}

await main();
