#!/usr/bin/env node

/**
 * Materialize the PDF visual request plans used by the static showcase.
 *
 * The converter's render requests remain the source-of-truth provenance. This
 * publisher deduplicates requests which are covered by the same page image,
 * paints the remaining requests in headless Chrome with the production PDF.js
 * renderer, and records immutable file metadata beside the original plan.
 */

import { spawn } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { once } from 'node:events';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { PDF_STATIC_PREVIEW_RENDERER_SCHEMA } from '../pandoc-showcase/pdfjs-form-rasterizer.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const defaults = {
  site: path.join(root, 'pandoc-showcase'),
  chrome: process.env.PORT_LIBS_CHROME_BINARY || '',
};
const pageRasterMethod = 'pdfjs-whole-page-raster';
// A fresh Chrome target owns at most four assets. Closing that target releases
// its PDF.js document, canvases, and renderer process before the next batch,
// which keeps long visual books from accumulating browser memory.
const PDF_PRERENDER_BATCH_SIZE = 4;
const auditedExamples = new Set([
  'pdf-layout-unstructured-ocr-overlay',
  'pdf-layout-docling-right-to-left',
  'pdf-layout-docling-aircraft-handbook',
  'pdf-layout-docling-table-picture-boundary',
  'pdf-layout-mineru-small-ocr',
  'pdf-layout-vdl-theatre-script',
  'pdf-tracemonkey',
  'pdf-grand-canyon-north-rim-map',
  'pdf-archive-motograph-book',
  'pdf-muir-beach-brochure',
  'pdf-quickbooks-invoice-template',
]);

function parseOptions(args) {
  const options = { ...defaults };
  for (let index = 0; index < args.length; index += 1) {
    const argument = args[index];
    const value = args[index + 1];
    if (argument === '--site') {
      options.site = path.resolve(value || options.site);
      index += 1;
    } else if (argument === '--chrome') {
      options.chrome = value || options.chrome;
      index += 1;
    } else if (argument === '--help' || argument === '-h') {
      console.log('Usage: node tools/prerender-showcase-pdf-assets.mjs [--site PATH] [--chrome PATH]');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${argument}`);
    }
  }
  return options;
}

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function normalizedAnchor(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

export function pdfPreviewRequestBounds(request) {
  const value = request?.bbox;
  if (value && [value.x1, value.y1, value.x2, value.y2].every(Number.isFinite)) {
    return value;
  }
  const pageBox = request?.method === pageRasterMethod && Array.isArray(request?.pageBox)
    ? request.pageBox
    : null;
  if (!pageBox || pageBox.length !== 4 || !pageBox.every(Number.isFinite)) return null;
  const [left, bottom, right, top] = pageBox;
  const normalized = {
    x1: Math.min(left, right),
    y1: Math.min(bottom, top),
    x2: Math.max(left, right),
    y2: Math.max(bottom, top),
  };
  return normalized.x2 > normalized.x1 && normalized.y2 > normalized.y1
    ? normalized
    : null;
}

function bboxContains(outer, inner, tolerance = 0.01) {
  return Boolean(outer && inner
    && outer.x1 <= inner.x1 + tolerance
    && outer.y1 <= inner.y1 + tolerance
    && outer.x2 + tolerance >= inner.x2
    && outer.y2 + tolerance >= inner.y2);
}

function pageRasterRequest(request) {
  const keys = [
    'version', 'method', 'sourceSha256', 'page', 'pageObject', 'pageBox',
    'pageBoxSource', 'pageRotation', 'width', 'height', 'mimeType', 'id',
    'requestDigest',
  ];
  return Object.fromEntries(keys.map((key) => [key, request[key]]));
}

function renderIdentity(sourceSha256, request) {
  const canonical = request.method === pageRasterMethod
    ? pageRasterRequest(request)
    : {
      method: 'pdfjs-form-xobject',
      page: request.page,
      bbox: request.bbox,
      formId: request.formId,
      object: request.object,
      paintOrder: request.paintOrder,
    };
  return sha256(JSON.stringify({
    version: 1,
    rendererSchema: PDF_STATIC_PREVIEW_RENDERER_SCHEMA,
    sourceSha256,
    request: canonical,
  }));
}

function placementFor(exampleId, request, sharedPageComposite = false) {
  if (exampleId === 'pdf-layout-unstructured-ocr-overlay') return 'existing-page-image';
  if (exampleId === 'pdf-archive-motograph-book' || sharedPageComposite) return 'page-gallery';
  if (normalizedAnchor(request.followingText || request.anchorAfter).length >= 3) return 'before-anchor';
  if (normalizedAnchor(request.precedingText || request.anchorBefore).length >= 3) return 'after-anchor';
  return 'page-gallery';
}

function mimeTypeForPath(file) {
  const extension = path.extname(file).toLowerCase();
  if (extension === '.jpg' || extension === '.jpeg') return 'image/jpeg';
  if (extension === '.webp') return 'image/webp';
  if (extension === '.avif') return 'image/avif';
  return 'image/png';
}

function imageDimensions(bytes, mimeType) {
  if (mimeType === 'image/png'
    && bytes.length >= 24
    && bytes.subarray(1, 4).toString('ascii') === 'PNG') {
    return { width: bytes.readUInt32BE(16), height: bytes.readUInt32BE(20) };
  }
  if (mimeType === 'image/jpeg') {
    let offset = 2;
    while (offset + 8 < bytes.length) {
      if (bytes[offset] !== 0xff) {
        offset += 1;
        continue;
      }
      const marker = bytes[offset + 1];
      if (marker === 0xd8 || marker === 0xd9) {
        offset += 2;
        continue;
      }
      const length = bytes.readUInt16BE(offset + 2);
      if (length < 2 || offset + 2 + length > bytes.length) break;
      if ([0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf].includes(marker)) {
        return { width: bytes.readUInt16BE(offset + 7), height: bytes.readUInt16BE(offset + 5) };
      }
      offset += 2 + length;
    }
  }
  throw new Error(`Could not read image dimensions for ${mimeType}.`);
}

function assetMetadata(site, relativePath) {
  const absolute = path.join(site, relativePath);
  const bytes = fs.readFileSync(absolute);
  const mimeType = mimeTypeForPath(absolute);
  const dimensions = imageDimensions(bytes, mimeType);
  return {
    path: relativePath.split(path.sep).join('/'),
    mimeType,
    byteLength: bytes.length,
    width: dimensions.width,
    height: dimensions.height,
    sha256: sha256(bytes),
  };
}

function sourceDigest(payload, sourceSha256) {
  return sha256(JSON.stringify({
    version: 1,
    rendererSchema: PDF_STATIC_PREVIEW_RENDERER_SCHEMA,
    samplePath: payload.samplePath,
    sourceSha256,
    requests: payload.requests,
  }));
}

function publishedPlanIsCurrent(site, payload, digest) {
  if (payload.prerenderVersion !== 1
    || payload.prerenderRendererSchema !== PDF_STATIC_PREVIEW_RENDERER_SCHEMA
    || payload.prerenderedSourceDigest !== digest) return false;
  const requests = Array.isArray(payload.requests) ? payload.requests : [];
  const assets = Array.isArray(payload.prerenderedAssets) ? payload.prerenderedAssets : [];
  const coverage = Array.isArray(payload.prerenderedRequestCoverage)
    ? payload.prerenderedRequestCoverage
    : [];
  if (coverage.length !== requests.length || assets.length < 1) return false;
  const requestIds = new Set(requests.map((request) => String(request?.id || '')));
  const coveredIds = new Set(coverage.map((item) => String(item?.requestId || '')));
  const assetPaths = new Set(assets.map((asset) => String(asset?.path || '')));
  if (requestIds.has('') || requestIds.size !== requests.length
    || coveredIds.has('') || coveredIds.size !== coverage.length
    || [...requestIds].some((requestId) => !coveredIds.has(requestId))
    || coverage.some((item) => !assetPaths.has(String(item?.assetPath || '')))) {
    return false;
  }
  return assets.every((asset) => {
    const relativePath = String(asset?.path || '');
    const absolute = path.join(site, relativePath);
    if (!relativePath || !fs.existsSync(absolute)) return false;
    const bytes = fs.readFileSync(absolute);
    return bytes.length === asset.byteLength && sha256(bytes) === asset.sha256;
  });
}

function existingOcrAsset(site, exampleId, request) {
  const outputDirectory = path.join(site, 'outputs', exampleId);
  const htmlPath = path.join(outputDirectory, 'php.html');
  const html = fs.readFileSync(htmlPath, 'utf8');
  const imageTags = html.match(/<img\b[^>]*>/gi) || [];
  const page = String(request.page);
  const tag = imageTags.find((candidate) => (
    new RegExp(`data-pandoc-pdf-page=["']${page}["']`, 'i').test(candidate)
    && /data-pandoc-pdf-image-placement=["']page["']/i.test(candidate)
  ));
  const source = tag?.match(/\bsrc=["']([^"']+)["']/i)?.[1] || '';
  if (!source || /^(?:[a-z]+:|\/\/|\/)/i.test(source)) {
    throw new Error(`${exampleId} has no reusable page-${page} image in php.html.`);
  }
  const absolute = path.resolve(outputDirectory, source);
  const relative = path.relative(site, absolute);
  if (relative.startsWith('..') || path.isAbsolute(relative) || !fs.existsSync(absolute)) {
    throw new Error(`${exampleId} existing page image resolves outside the showcase.`);
  }
  return relative.split(path.sep).join('/');
}

function coveringGrandCanyonRequest(requests, page) {
  const candidates = requests.filter((request) => (
    request.page === page && pdfPreviewRequestBounds(request)
  ));
  return candidates.find((candidate) => candidates.every((request) => (
    bboxContains(
      pdfPreviewRequestBounds(candidate),
      pdfPreviewRequestBounds(request),
      15,
    )
  ))) || null;
}

function buildPublicationPlan(site, exampleId, planPath, payload) {
  const requests = Array.isArray(payload.requests) ? payload.requests : [];
  const samplePath = String(payload.samplePath || '');
  const sourceFile = path.join(site, samplePath);
  if (requests.length < 1 || !samplePath || !fs.existsSync(sourceFile)) {
    throw new Error(`${exampleId} has an incomplete PDF render plan.`);
  }
  const sourceSha256 = sha256(fs.readFileSync(sourceFile));
  const digest = sourceDigest(payload, sourceSha256);
  if (publishedPlanIsCurrent(site, payload, digest)) {
    return { current: true, exampleId, planPath, payload };
  }

  const units = [];
  const unitByIdentity = new Map();
  const unitByRequestId = new Map();
  const sharedPageRequests = new Set();
  if (exampleId === 'pdf-grand-canyon-north-rim-map') {
    for (const page of [1, 2]) {
      const covering = coveringGrandCanyonRequest(requests, page);
      if (!covering) throw new Error(`Grand Canyon page ${page} has no covering composite request.`);
      const unit = {
        identity: renderIdentity(sourceSha256, covering),
        request: covering,
        requestIds: [],
      };
      units.push(unit);
      for (const request of requests.filter((candidate) => candidate.page === page)) {
        if (!bboxContains(
          pdfPreviewRequestBounds(covering),
          pdfPreviewRequestBounds(request),
          15,
        )) {
          throw new Error(`Grand Canyon request ${request.id} is not covered by its page-${page} composite.`);
        }
        unit.requestIds.push(request.id);
        unitByRequestId.set(request.id, unit);
        sharedPageRequests.add(request.id);
      }
    }
  } else if (exampleId === 'pdf-layout-unstructured-ocr-overlay') {
    const request = requests[0];
    const assetPath = existingOcrAsset(site, exampleId, request);
    const unit = { identity: `existing:${assetPath}`, request, requestIds: [request.id], assetPath, existing: true };
    units.push(unit);
    unitByRequestId.set(request.id, unit);
  } else {
    for (const request of requests) {
      let identity = renderIdentity(sourceSha256, request);
      if (exampleId === 'pdf-layout-docling-table-picture-boundary') {
        identity = sha256(JSON.stringify({
          rendererSchema: PDF_STATIC_PREVIEW_RENDERER_SCHEMA,
          sourceSha256,
          page: request.page,
          bbox: request.bbox,
        }));
      }
      let unit = unitByIdentity.get(identity);
      if (!unit) {
        unit = { identity, request, requestIds: [] };
        unitByIdentity.set(identity, unit);
        units.push(unit);
      }
      unit.requestIds.push(request.id);
      unitByRequestId.set(request.id, unit);
    }
  }

  for (const unit of units) {
    if (!unit.assetPath) {
      unit.assetPath = `outputs/${exampleId}/pdf-preview-${unit.identity.slice(0, 24)}.png`;
    }
    unit.absolutePath = path.join(site, unit.assetPath);
  }
  const coverage = requests.map((request) => {
    const unit = unitByRequestId.get(request.id);
    if (!unit) throw new Error(`${exampleId} request ${request.id} has no publication unit.`);
    return {
      requestId: request.id,
      assetPath: unit.assetPath,
      placement: placementFor(exampleId, request, sharedPageRequests.has(request.id)),
    };
  });
  return {
    current: false,
    exampleId,
    planPath,
    payload,
    sourceFile,
    sourceSha256,
    digest,
    units,
    coverage,
  };
}

function chromeCandidates(explicit) {
  return [
    explicit,
    process.env.CHROME_BIN,
    process.env.GOOGLE_CHROME_BIN,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
  ].filter(Boolean);
}

function findChrome(explicit) {
  const candidate = chromeCandidates(explicit).find((file) => fs.existsSync(file));
  if (!candidate) {
    throw new Error('Headless Chrome is required to publish missing PDF preview assets. Set PORT_LIBS_CHROME_BINARY.');
  }
  return candidate;
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
    socket.addEventListener('message', (event) => this.receive(event.data));
    socket.addEventListener('close', () => {
      for (const { reject } of this.pending.values()) reject(new Error('Chrome DevTools closed the connection.'));
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

  receive(raw) {
    const message = JSON.parse(String(raw));
    if (!message.id) return;
    const pending = this.pending.get(message.id);
    if (!pending) return;
    this.pending.delete(message.id);
    if (message.error) pending.reject(new Error(message.error.message || 'Chrome DevTools error'));
    else pending.resolve(message.result || {});
  }

  async close() {
    this.socket.close();
  }
}

async function waitForFile(file, timeoutMs = 15_000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (fs.existsSync(file)) return;
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  throw new Error(`Timed out waiting for ${file}.`);
}

export async function fetchJsonWithRetry(url, description, options = {}) {
  const timeoutMs = options.timeoutMs ?? 10_000;
  const retryDelayMs = options.retryDelayMs ?? 50;
  const fetchImpl = options.fetchImpl ?? globalThis.fetch;
  const deadline = Date.now() + timeoutMs;
  let lastError;
  do {
    try {
      const response = await fetchImpl(url);
      if (!response?.ok) {
        throw new Error(`HTTP ${response?.status ?? 'unknown'}`);
      }
      return await response.json();
    } catch (error) {
      lastError = error instanceof Error ? error : new Error(String(error));
      if (Date.now() >= deadline) break;
      await new Promise((resolve) => setTimeout(resolve, retryDelayMs));
    }
  } while (Date.now() <= deadline);
  throw new Error(
    `Timed out waiting for ${description}: ${lastError?.message || 'unknown fetch failure'}`,
    { cause: lastError },
  );
}

function staticMimeType(file) {
  const extension = path.extname(file).toLowerCase();
  return ({
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.pdf': 'application/pdf',
    '.wasm': 'application/wasm',
    '.bcmap': 'application/octet-stream',
    '.bin': 'application/octet-stream',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
  })[extension] || 'application/octet-stream';
}

function createStaticServer(uploadTargets, uploadToken) {
  return http.createServer(async (request, response) => {
    try {
      const url = new URL(request.url || '/', 'http://127.0.0.1');
      if (request.method === 'POST' && url.pathname === '/__prerender_asset') {
        const token = String(request.headers['x-prerender-token'] || '');
        const key = String(request.headers['x-prerender-key'] || '');
        const target = uploadTargets.get(key);
        if (token !== uploadToken || !target) {
          response.writeHead(403).end('Forbidden');
          return;
        }
        const chunks = [];
        let length = 0;
        for await (const chunk of request) {
          length += chunk.length;
          if (length > 64 * 1024 * 1024) throw new Error('A prerendered image exceeded 64 MiB.');
          chunks.push(chunk);
        }
        const bytes = Buffer.concat(chunks);
        fs.mkdirSync(path.dirname(target), { recursive: true });
        const temporary = `${target}.tmp-${process.pid}`;
        fs.writeFileSync(temporary, bytes);
        fs.renameSync(temporary, target);
        response.writeHead(200, { 'content-type': 'application/json' });
        response.end(JSON.stringify({ byteLength: bytes.length, sha256: sha256(bytes) }));
        return;
      }
      if (url.pathname === '/__prerender__') {
        response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store' });
        response.end('<!doctype html><html><body>PDF preview publisher</body></html>');
        return;
      }
      const requested = decodeURIComponent(url.pathname).replace(/^\/+/, '');
      const absolute = path.resolve(root, requested);
      if (absolute !== root && !absolute.startsWith(`${root}${path.sep}`)) {
        response.writeHead(403).end('Forbidden');
        return;
      }
      if (!fs.existsSync(absolute) || !fs.statSync(absolute).isFile()) {
        response.writeHead(404).end('Not found');
        return;
      }
      response.writeHead(200, {
        'content-type': staticMimeType(absolute),
        'content-length': fs.statSync(absolute).size,
        'cache-control': 'no-store',
      });
      fs.createReadStream(absolute).pipe(response);
    } catch (error) {
      response.writeHead(500).end(error instanceof Error ? error.message : String(error));
    }
  });
}

async function targetWebSocketUrl(port, targetId) {
  const deadline = Date.now() + 10_000;
  let lastError;
  while (Date.now() < deadline) {
    try {
      const targets = await fetchJsonWithRetry(
        `http://127.0.0.1:${port}/json/list`,
        'Chrome DevTools target list',
        { timeoutMs: Math.min(500, Math.max(1, deadline - Date.now())) },
      );
      const target = targets.find((candidate) => candidate.id === targetId);
      if (target?.webSocketDebuggerUrl) return target.webSocketDebuggerUrl;
    } catch (error) {
      lastError = error instanceof Error ? error : new Error(String(error));
    }
    await new Promise((resolve) => setTimeout(resolve, 50));
  }
  throw new Error(
    `Chrome did not expose the PDF publisher target${lastError ? `: ${lastError.message}` : '.'}`,
    { cause: lastError },
  );
}

async function waitForTargetOrigin(page, host, timeoutMs = 10_000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const result = await page.call('Runtime.evaluate', {
        expression: 'location.origin',
        returnByValue: true,
      });
      if (result.result?.value === host) return;
    } catch {
      // Navigation may replace the execution context between the CDP call and
      // its response. The next bounded poll evaluates in the new context.
    }
    await new Promise((resolve) => setTimeout(resolve, 50));
  }
  throw new Error(`Chrome target did not finish navigating to ${host}.`);
}

function browserRenderExpression(plan, host, uploadToken) {
  const groups = [];
  for (const pageRaster of [false, true]) {
    const units = plan.units.filter((unit) => !unit.existing
      && (unit.request.method === pageRasterMethod) === pageRaster);
    if (units.length < 1) continue;
    groups.push({
      pageRaster,
      units: units.map((unit) => ({
        key: `${plan.exampleId}:${unit.identity}`,
        request: pageRaster ? pageRasterRequest(unit.request) : unit.request,
      })),
    });
  }
  const payload = {
    samplePath: path.relative(root, plan.sourceFile).split(path.sep).join('/'),
    requestPath: String(plan.payload.samplePath),
    groups,
    host,
    uploadToken,
  };
  return `(async () => {
    const input = ${JSON.stringify(payload)};
    const module = await import(input.host + '/pandoc-showcase/pdfjs-form-rasterizer.mjs');
    const sourceResponse = await fetch(input.host + '/' + input.samplePath, { cache: 'no-store' });
    if (!sourceResponse.ok) throw new Error('Source PDF returned HTTP ' + sourceResponse.status);
    const source = new Uint8Array(await sourceResponse.arrayBuffer());
    const pdfjs = {
      pdfjsModuleUrl: input.host + '/pandoc-showcase/vendor/pdfjs/pdf.min.mjs',
      pdfjsWorkerUrl: input.host + '/pandoc-showcase/vendor/pdfjs/pdf.worker.min.mjs',
      pdfjsWasmUrl: input.host + '/pandoc-showcase/vendor/pdfjs/wasm/',
      pdfjsCMapUrl: input.host + '/pandoc-showcase/vendor/pdfjs/cmaps/',
      pdfjsStandardFontDataUrl: input.host + '/pandoc-showcase/vendor/pdfjs/standard_fonts/',
    };
    const published = [];
    for (const group of input.groups) {
      const requests = group.units.map((unit) => unit.request);
      const renderer = group.pageRaster
        ? module.renderPdfPageRasterRequestsIncrementally
        : module.renderPdfFormRequestsIncrementally;
      const options = group.pageRaster
        ? { source, requests, pdfjs }
        : { filesByPath: new Map([[input.requestPath, source]]), requests, pdfjs };
      const unitByRequest = new Map(group.units.map((unit) => [String(unit.request.id), unit]));
      for await (const result of renderer({
        ...options,
        maxPixels: 16_000_000,
        maxTotalPixels: Number.POSITIVE_INFINITY,
        maxImageBytes: 32_000_000,
        maxTotalImageBytes: Number.POSITIVE_INFINITY,
        maxSourceBytes: 64_000_000,
      })) {
        if (result.error) throw new Error(result.requestId + ': ' + result.error);
        const bytes = result.bytes || result.contents;
        if (!(bytes instanceof Uint8Array) || bytes.byteLength < 1) {
          throw new Error(result.requestId + ': renderer returned no image bytes');
        }
        const unit = unitByRequest.get(String(result.requestId));
        if (!unit) throw new Error('Renderer returned unknown request ' + result.requestId);
        const response = await fetch(input.host + '/__prerender_asset', {
          method: 'POST',
          headers: {
            'content-type': String(result.mimeType || 'image/png'),
            'x-prerender-token': input.uploadToken,
            'x-prerender-key': unit.key,
          },
          body: bytes,
        });
        if (!response.ok) {
          const detail = await response.text().catch(() => '');
          throw new Error('Publisher rejected ' + result.requestId + ' with HTTP ' + response.status
            + (detail ? ': ' + detail : ''));
        }
        const receipt = await response.json();
        published.push({
          key: unit.key,
          requestId: String(result.requestId),
          mimeType: String(result.mimeType || 'image/png'),
          width: Number(result.width),
          height: Number(result.height),
          byteLength: Number(receipt.byteLength),
          sha256: String(receipt.sha256),
        });
      }
    }
    return published;
  })()`;
}

async function renderPlans(plans, chromePath, site) {
  const uploadTargets = new Map();
  for (const plan of plans) {
    for (const unit of plan.units.filter((candidate) => !candidate.existing)) {
      uploadTargets.set(`${plan.exampleId}:${unit.identity}`, unit.absolutePath);
    }
  }
  if (uploadTargets.size < 1) return;

  const uploadToken = randomBytes(24).toString('hex');
  const server = createStaticServer(uploadTargets, uploadToken);
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  const serverPort = server.address().port;
  const host = `http://127.0.0.1:${serverPort}`;
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'port-libs-pdf-prerender-'));
  const chrome = spawn(chromePath, [
    '--headless=new',
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-background-networking',
    '--remote-debugging-port=0',
    `--user-data-dir=${profile}`,
    'about:blank',
  ], { stdio: ['ignore', 'ignore', 'pipe'] });
  let chromeErrors = '';
  chrome.stderr.on('data', (chunk) => { chromeErrors += String(chunk); });
  let browser;
  let activePage;
  let activeTargetId = '';
  try {
    const activePortFile = path.join(profile, 'DevToolsActivePort');
    await waitForFile(activePortFile);
    const [debugPort] = fs.readFileSync(activePortFile, 'utf8').trim().split(/\r?\n/);
    const browserInfo = await fetchJsonWithRetry(
      `http://127.0.0.1:${debugPort}/json/version`,
      'Chrome DevTools browser endpoint',
    );
    if (!browserInfo?.webSocketDebuggerUrl) {
      throw new Error('Chrome DevTools browser endpoint returned no WebSocket URL.');
    }
    browser = await CdpClient.connect(browserInfo.webSocketDebuggerUrl);
    for (const plan of plans) {
      const renderUnits = plan.units.filter((unit) => !unit.existing);
      if (renderUnits.length < 1) continue;
      const batchCount = Math.ceil(renderUnits.length / PDF_PRERENDER_BATCH_SIZE);
      console.log(`Prerendering ${plan.exampleId} (${renderUnits.length} unique assets in ${batchCount} bounded batches)…`);
      for (let batchStart = 0; batchStart < renderUnits.length; batchStart += PDF_PRERENDER_BATCH_SIZE) {
        const units = renderUnits.slice(batchStart, batchStart + PDF_PRERENDER_BATCH_SIZE);
        const target = await browser.call('Target.createTarget', { url: `${host}/__prerender__` });
        activeTargetId = target.targetId;
        activePage = await CdpClient.connect(await targetWebSocketUrl(debugPort, activeTargetId));
        try {
          await activePage.call('Page.enable');
          await activePage.call('Runtime.enable');
          await waitForTargetOrigin(activePage, host);
          const result = await activePage.call('Runtime.evaluate', {
            expression: browserRenderExpression({ ...plan, units }, host, uploadToken),
            awaitPromise: true,
            returnByValue: true,
          });
          if (result.exceptionDetails) {
            throw new Error(result.exceptionDetails.exception?.description
              || result.exceptionDetails.text
              || `Chrome failed while rendering ${plan.exampleId}.`);
          }
          const receipts = result.result?.value;
          if (!Array.isArray(receipts)) throw new Error(`${plan.exampleId} returned no publication receipts.`);
          const receiptByKey = new Map(receipts.map((receipt) => [receipt.key, receipt]));
          for (const unit of units) {
            const receipt = receiptByKey.get(`${plan.exampleId}:${unit.identity}`);
            if (!receipt || !fs.existsSync(unit.absolutePath)) {
              throw new Error(`${plan.exampleId} did not publish ${unit.assetPath}.`);
            }
            unit.receipt = receipt;
          }
        } finally {
          await activePage?.close().catch(() => {});
          activePage = undefined;
          await browser.call('Target.closeTarget', { targetId: activeTargetId }).catch(() => {});
          activeTargetId = '';
        }
      }
    }
  } catch (error) {
    if (chrome.exitCode !== null && chromeErrors.trim()) {
      throw new Error(`${error.message}\n${chromeErrors.trim()}`);
    }
    throw error;
  } finally {
    await activePage?.close().catch(() => {});
    if (browser && activeTargetId) {
      await browser.call('Target.closeTarget', { targetId: activeTargetId }).catch(() => {});
    }
    await browser?.close().catch(() => {});
    if (chrome.exitCode === null) chrome.kill('SIGTERM');
    await Promise.race([
      once(chrome, 'exit').catch(() => {}),
      new Promise((resolve) => setTimeout(resolve, 5_000)),
    ]);
    if (chrome.exitCode === null) chrome.kill('SIGKILL');
    await new Promise((resolve) => server.close(resolve));
    fs.rmSync(profile, { recursive: true, force: true });
  }
}

function finalizePlan(site, plan) {
  const assets = plan.units.map((unit) => assetMetadata(site, unit.assetPath));
  const output = {
    ...plan.payload,
    prerenderVersion: 1,
    prerenderRendererSchema: PDF_STATIC_PREVIEW_RENDERER_SCHEMA,
    prerenderedSourceDigest: plan.digest,
    prerenderedAssets: assets,
    prerenderedRequestCoverage: plan.coverage,
  };
  const temporary = `${plan.planPath}.tmp-${process.pid}`;
  fs.writeFileSync(temporary, `${JSON.stringify(output, null, 4)}\n`);
  fs.renameSync(temporary, plan.planPath);
  plan.payload = output;

  const expectedAssetPaths = new Set(assets.map((asset) => asset.path));
  const outputDirectory = path.join(site, 'outputs', plan.exampleId);
  for (const filename of fs.readdirSync(outputDirectory)) {
    if (!/^pdf-preview-[a-f0-9]{24}\.png$/.test(filename)) continue;
    const relative = `outputs/${plan.exampleId}/${filename}`;
    if (!expectedAssetPaths.has(relative)) fs.unlinkSync(path.join(outputDirectory, filename));
  }
}

function cleanupUncommittedPlanAssets(plan) {
  const committed = new Set((plan.payload?.prerenderedAssets || []).map((asset) => String(asset?.path || '')));
  for (const unit of plan.units || []) {
    if (unit.existing || committed.has(unit.assetPath)) continue;
    for (const candidate of [unit.absolutePath, `${unit.absolutePath}.tmp-${process.pid}`]) {
      if (candidate && fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
        fs.unlinkSync(candidate);
      }
    }
  }
}

async function main() {
  const options = parseOptions(process.argv.slice(2));
  const outputs = path.join(options.site, 'outputs');
  const planPaths = fs.readdirSync(outputs)
    .filter((exampleId) => auditedExamples.has(exampleId))
    .map((exampleId) => ({ exampleId, planPath: path.join(outputs, exampleId, 'pdf-form-renders.json') }))
    .filter(({ planPath }) => fs.existsSync(planPath));
  if (planPaths.length !== auditedExamples.size) {
    throw new Error(`Expected ${auditedExamples.size} audited PDF render plans, found ${planPaths.length}.`);
  }
  const plans = planPaths.map(({ exampleId, planPath }) => buildPublicationPlan(
    options.site,
    exampleId,
    planPath,
    JSON.parse(fs.readFileSync(planPath, 'utf8')),
  ));
  const stale = plans.filter((plan) => !plan.current);
  if (stale.length < 1) {
    console.log('PDF showcase preview assets are current.');
    return;
  }
  const needsChrome = stale.some((plan) => plan.units.some((unit) => !unit.existing));
  const chromePath = needsChrome ? findChrome(options.chrome) : '';
  for (const plan of stale) {
    try {
      await renderPlans([plan], chromePath, options.site);
      finalizePlan(options.site, plan);
    } catch (error) {
      cleanupUncommittedPlanAssets(plan);
      throw error;
    }
  }
  console.log(`Published ${stale.reduce((total, plan) => total + plan.units.length, 0)} deterministic PDF preview assets across ${stale.length} plans.`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  main().catch((error) => {
    console.error(error instanceof Error ? error.stack || error.message : String(error));
    process.exitCode = 1;
  });
}
