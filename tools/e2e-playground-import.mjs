#!/usr/bin/env node

/**
 * Drive the public examples-page importer through an already-running Chrome
 * DevTools endpoint. This deliberately has no Playwright/Puppeteer dependency:
 * a fresh checkout can use Node 22+ and Chrome's built-in CDP WebSocket.
 *
 * Usage:
 *   node tools/e2e-playground-import.mjs --file path/to/input.pdf
 *
 * The page starts a real WordPress Playground, installs the packaged plugin,
 * stages the selected document, and waits for the visible success/error state.
 * It is intended for manual release verification as well as CI-like local
 * debugging of the browser/server import conversation.
 */

import process from 'node:process';
import { execFileSync } from 'node:child_process';

const defaults = {
  chrome: 'http://127.0.0.1:9222',
  url: 'http://127.0.0.1:4174/examples.html',
  timeoutMs: 10 * 60 * 1000,
  pollMs: 500,
  maxElapsedMs: 0,
  // A single WordPress Playground import currently peaks around 1.8 GiB on
  // the dense stress corpus. Fail closed well before one target can threaten
  // an 8 GiB developer machine; pass 0 explicitly only when a larger,
  // externally supervised browser budget is intentional.
  maxBrowserRssMb: 3 * 1024,
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
    } else if (argument === '--max-browser-rss-mb') {
      options.maxBrowserRssMb = Math.max(0, Number(value) || 0);
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

function browserProcessTreeRss(baseUrl) {
  const port = new URL(baseUrl).port;
  if (!port) {
    return null;
  }
  let output;
  try {
    output = execFileSync('/bin/ps', ['-axo', 'pid=,ppid=,rss=,command='], {
      encoding: 'utf8',
      maxBuffer: 8 * 1024 * 1024,
    });
  } catch {
    return null;
  }
  const rows = output.split('\n').map((line) => {
    const match = line.match(/^\s*(\d+)\s+(\d+)\s+(\d+)\s+(.*)$/);
    return match ? {
      pid: Number(match[1]),
      ppid: Number(match[2]),
      rssKb: Number(match[3]),
      command: match[4],
    } : null;
  }).filter(Boolean);
  const roots = rows.filter((row) => row.command.includes(`--remote-debugging-port=${port}`)
    && !row.command.includes('--type='));
  if (roots.length === 0) {
    return null;
  }
  const included = new Set(roots.map((row) => row.pid));
  let changed = true;
  while (changed) {
    changed = false;
    for (const row of rows) {
      if (!included.has(row.pid) && included.has(row.ppid)) {
        included.add(row.pid);
        changed = true;
      }
    }
  }
  const rssBytes = rows
    .filter((row) => included.has(row.pid))
    .reduce((sum, row) => sum + (row.rssKb * 1024), 0);
  return { rssBytes, processCount: included.size };
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
    fail('Usage: node tools/e2e-playground-import.mjs --file /absolute/path/to/document [--url URL] [--chrome URL] [--timeout-ms N] [--max-elapsed-ms N] [--max-browser-rss-mb N]');
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
    await setFileInput(page, '#own-file-input', options.file);

    const statusHistory = [];
    const startedAt = Date.now();
    let lastStatus = '';
    let completed = false;
    let peakBrowserRssBytes = 0;
    let nextMemoryPollAt = 0;
    while (Date.now() - startedAt < options.timeoutMs) {
      if (options.maxBrowserRssMb > 0 && Date.now() >= nextMemoryPollAt) {
        nextMemoryPollAt = Date.now() + 1_000;
        const memory = browserProcessTreeRss(options.chrome);
        if (memory) {
          peakBrowserRssBytes = Math.max(peakBrowserRssBytes, memory.rssBytes);
          const ceilingBytes = options.maxBrowserRssMb * 1024 * 1024;
          if (memory.rssBytes > ceilingBytes) {
            throw new Error(`Chrome exceeded the ${options.maxBrowserRssMb} MiB RSS safety ceiling (${Math.ceil(memory.rssBytes / 1024 / 1024)} MiB across ${memory.processCount} processes).`);
          }
        }
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
      const importFinished = /^(?:(?:Import complete\..* )?Opened a new WordPress page for |The import completed and the WordPress page was saved)/.test(status.text);
      if (status.tone === 'success' && importFinished) {
        const integrity = await evaluate(page, `window.__portLibsImportE2E?.inspectLastImport()`);
        if (!integrity || !Array.isArray(integrity.posts) || integrity.posts.length < 1) {
          throw new Error('The UI reported success without inspectable WordPress pages.');
        }
        for (const post of integrity.posts) {
          if (post.status !== 'publish' || post.contentBytes < 1
              || (post.visibleTextBytes < 1 && post.imageCount < 1)) {
            throw new Error(`WordPress page ${post.postId} failed publication integrity: ${JSON.stringify(post)}`);
          }
          if (post.rawDataProvenanceCount !== 0) {
            throw new Error(`WordPress page ${post.postId} retained an encoded data-URI provenance attribute.`);
          }
        }
        if (/\.pdf$/i.test(options.file)) {
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
          peakBrowserRssBytes,
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
