const DEFAULT_MAX_SOURCE_BYTES = 24 * 1024 * 1024;
const DEFAULT_MAX_HANDOFF_BYTES = 4 * 1024 * 1024;
const DEFAULT_MAX_TEXT_SPANS = 100_000;
const DEFAULT_MAX_TEXT_BYTES = 2 * 1024 * 1024;
const DEFAULT_MAX_STRUCTURE_NODES = 50_000;

let pdfjsModulePromise = null;

/**
 * Collect bounded PDF.js text-content and tagged-structure observations.
 * The result contains no PDF bytes and is cryptographically tied to the
 * source so PHP can reject a stale handoff and retain its native fallback.
 *
 * @param {Object} options
 * @param {File|Uint8Array|ArrayBuffer} options.source
 * @param {{pdfjsModuleUrl?:string,pdfjsWorkerUrl?:string,pdfjsWasmUrl?:string,pdfjsCMapUrl?:string,pdfjsStandardFontDataUrl?:string}} [options.pdfjs]
 * @param {Object} [options.pdfjsModule] Test/host injection for an already loaded PDF.js module.
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxSourceBytes]
 * @param {number} [options.maxHandoffBytes]
 * @param {number} [options.maxTextSpans]
 * @param {number} [options.maxTextBytes]
 * @param {number} [options.maxStructureNodes]
 * @param {number} [options.startPage] First one-based page to collect. This
 * lets a consumer request facts only when it is ready to ingest a page range.
 * @param {number} [options.maxPages] Maximum pages in this handoff.
 * @param {AbortSignal} [options.signal]
 */
export async function collectPdfJsFacts({
  source,
  pdfjs = {},
  pdfjsModule = null,
  onProgress = () => {},
  maxSourceBytes = DEFAULT_MAX_SOURCE_BYTES,
  maxHandoffBytes = DEFAULT_MAX_HANDOFF_BYTES,
  maxTextSpans = DEFAULT_MAX_TEXT_SPANS,
  maxTextBytes = DEFAULT_MAX_TEXT_BYTES,
  maxStructureNodes = DEFAULT_MAX_STRUCTURE_NODES,
  startPage = 1,
  maxPages = Number.POSITIVE_INFINITY,
  signal,
}) {
  throwIfAborted(signal);
  const bytes = await sourceBytes(source, positiveLimit(maxSourceBytes, DEFAULT_MAX_SOURCE_BYTES));
  const sourceSha256 = await sha256Hex(bytes);
  throwIfAborted(signal);
  const module = pdfjsModule || await loadPdfJs(pdfjs);
  throwIfAborted(signal);
  const loadingTask = module.getDocument({
    // PDF.js workers may transfer this buffer. Keep the caller's source
    // usable for WordPress staging and optional image decoding.
    data: new Uint8Array(bytes),
    cMapUrl: pdfjs.pdfjsCMapUrl || undefined,
    cMapPacked: true,
    standardFontDataUrl: pdfjs.pdfjsStandardFontDataUrl || undefined,
    wasmUrl: pdfjs.pdfjsWasmUrl || undefined,
    isEvalSupported: false,
    enableXfa: false,
    stopAtErrors: false,
    verbosity: 0,
  });
  const document = await loadingTask.promise;
  const result = {
    schemaVersion: 1,
    provider: 'pdfjs-v1',
    sourceSha256,
    pageCount: Number(document.numPages) || 0,
    pages: [],
    failures: [],
  };
  const rangeStart = Math.min(
    Math.max(1, Number.isSafeInteger(Number(startPage)) ? Number(startPage) : 1),
    Math.max(1, result.pageCount),
  );
  const requestedPages = Number.isSafeInteger(Number(maxPages)) && Number(maxPages) > 0
    ? Number(maxPages)
    : result.pageCount;
  const rangeEnd = Math.min(result.pageCount, rangeStart + requestedPages - 1);
  result.range = {
    startPage: result.pageCount > 0 ? rangeStart : 0,
    endPage: result.pageCount > 0 ? rangeEnd : 0,
  };
  const rangeLength = Math.max(0, rangeEnd - rangeStart + 1);
  const spanLimit = positiveLimit(maxTextSpans, DEFAULT_MAX_TEXT_SPANS);
  const textByteLimit = positiveLimit(maxTextBytes, DEFAULT_MAX_TEXT_BYTES);
  const structureLimit = positiveLimit(maxStructureNodes, DEFAULT_MAX_STRUCTURE_NODES);
  const handoffLimit = positiveLimit(maxHandoffBytes, DEFAULT_MAX_HANDOFF_BYTES);
  let spanCount = 0;
  let textBytes = 0;

  try {
    for (let pageNumber = rangeStart; pageNumber <= rangeEnd; pageNumber += 1) {
      throwIfAborted(signal);
      onProgress({
        completed: pageNumber - rangeStart,
        total: rangeLength,
        label: `Reading PDF text and structure for page ${pageNumber} of ${result.pageCount}…`,
      });
      let page;
      try {
        page = await document.getPage(pageNumber);
        const textContent = await page.getTextContent({
          includeMarkedContent: true,
          disableNormalization: false,
        });
        const spans = [];
        const markedContent = [];
        let pageTextBytes = 0;
        for (const item of textContent?.items || []) {
          if (typeof item?.str !== 'string') {
            const marked = sanitizeMarkedContent(item);
            if (marked) markedContent.push(marked);
            continue;
          }
          const span = sanitizeTextSpan(item);
          if (!span) continue;
          const spanBytes = utf8ByteLength(span.text);
          if (spanCount + spans.length >= spanLimit || textBytes + pageTextBytes + spanBytes > textByteLimit) {
            throw new Error('The PDF.js text-facts safety budget was reached. Remaining pages will use native PHP facts.');
          }
          spans.push(span);
          pageTextBytes += spanBytes;
        }

        let structure = null;
        if (typeof page.getStructTree === 'function') {
          try {
            const rawStructure = await page.getStructTree();
            structure = sanitizeStructure(rawStructure, structureLimit);
          } catch (error) {
            result.failures.push({
              pageNumber,
              reason: `Tagged structure was unavailable: ${errorMessage(error)}`.slice(0, 500),
            });
          }
        }
        const viewport = page.getViewport({ scale: 1 });
        const pageFacts = {
          pageNumber,
          viewport: sanitizeViewport(viewport),
          spans,
          markedContent,
          styles: sanitizeStyles(textContent?.styles || {}),
          structure,
        };
        result.pages.push(pageFacts);
        if (serializedBytes(result) > handoffLimit) {
          result.pages.pop();
          throw new Error('The PDF.js facts handoff safety budget was reached. Remaining pages will use native PHP facts.');
        }
        spanCount += spans.length;
        textBytes += pageTextBytes;
      } catch (error) {
        if (signal?.aborted) throw abortError(signal);
        result.failures.push({ pageNumber, reason: errorMessage(error).slice(0, 500) });
        if (String(errorMessage(error)).includes('safety budget')) {
          break;
        }
      } finally {
        if (page && typeof page.cleanup === 'function') {
          try { page.cleanup(); } catch { /* Best-effort PDF.js memory release. */ }
        }
      }
    }
  } finally {
    try { await document.destroy(); } catch { /* Native fallback remains usable. */ }
  }
  throwIfAborted(signal);
  onProgress({
    completed: result.pages.length,
    total: rangeLength,
    label: result.pages.length === rangeLength
      ? (rangeLength === result.pageCount
        ? 'PDF text and structure facts are ready.'
        : `PDF text and structure facts for pages ${rangeStart}–${rangeEnd} are ready.`)
      : 'Browser facts are partially available; native PDF extraction will fill the gaps.',
  });

  return result;
}

async function loadPdfJs(pdfjs) {
  if (!pdfjs?.pdfjsModuleUrl || !pdfjs?.pdfjsWorkerUrl) {
    throw new Error('The PDF.js text and structure provider assets are unavailable.');
  }
  if (!pdfjsModulePromise) {
    pdfjsModulePromise = import(pdfjs.pdfjsModuleUrl).then((module) => {
      module.GlobalWorkerOptions.workerSrc = pdfjs.pdfjsWorkerUrl;
      return module;
    }).catch((error) => {
      pdfjsModulePromise = null;
      throw error;
    });
  }

  return pdfjsModulePromise;
}

async function sourceBytes(source, limit) {
  const size = source instanceof Uint8Array || source instanceof ArrayBuffer
    ? source.byteLength
    : Number(source?.size);
  if (Number.isFinite(size) && size > limit) {
    throw new Error('This PDF is over the browser facts safety limit; native PHP extraction will be used.');
  }
  let bytes;
  if (source instanceof Uint8Array) bytes = new Uint8Array(source);
  else if (source instanceof ArrayBuffer) bytes = new Uint8Array(source.slice(0));
  else if (source && typeof source.arrayBuffer === 'function') bytes = new Uint8Array(await source.arrayBuffer());
  else throw new Error('The selected PDF could not be read for browser facts.');
  if (bytes.byteLength > limit) {
    throw new Error('This PDF is over the browser facts safety limit; native PHP extraction will be used.');
  }

  return bytes;
}

async function sha256Hex(bytes) {
  if (!globalThis.crypto?.subtle) {
    throw new Error('This browser cannot securely bind PDF.js facts to their source; native PHP extraction will be used.');
  }
  const digest = new Uint8Array(await globalThis.crypto.subtle.digest('SHA-256', bytes));

  return Array.from(digest, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function sanitizeTextSpan(item) {
  const transform = Array.isArray(item.transform) || ArrayBuffer.isView(item.transform)
    ? Array.from(item.transform, finiteNumber)
    : [];
  if (transform.length !== 6 || transform.some((number) => number === null)) return null;

  return {
    text: item.str,
    direction: typeof item.dir === 'string' ? item.dir.slice(0, 16) : '',
    transform,
    width: finiteNumber(item.width) ?? 0,
    height: finiteNumber(item.height) ?? 0,
    fontName: typeof item.fontName === 'string' ? item.fontName.slice(0, 512) : '',
    hasEol: item.hasEOL === true,
  };
}

function sanitizeMarkedContent(item) {
  if (!item || typeof item.type !== 'string') return null;

  return {
    type: item.type.slice(0, 100),
    ...(typeof item.id === 'string' ? { id: item.id.slice(0, 512) } : {}),
    ...(typeof item.tag === 'string' ? { tag: item.tag.slice(0, 100) } : {}),
  };
}

function sanitizeViewport(viewport) {
  return {
    width: finiteNumber(viewport?.width) ?? 0,
    height: finiteNumber(viewport?.height) ?? 0,
    rotation: finiteNumber(viewport?.rotation) ?? 0,
    viewBox: Array.isArray(viewport?.viewBox) ? viewport.viewBox.map((value) => finiteNumber(value) ?? 0).slice(0, 4) : [],
  };
}

function sanitizeStyles(styles) {
  const sanitized = {};
  for (const [name, style] of Object.entries(styles || {}).slice(0, 10_000)) {
    if (!style || typeof style !== 'object') continue;
    sanitized[String(name).slice(0, 512)] = {
      fontFamily: typeof style.fontFamily === 'string' ? style.fontFamily.slice(0, 512) : '',
      vertical: style.vertical === true,
      ascent: finiteNumber(style.ascent),
      descent: finiteNumber(style.descent),
    };
  }

  return sanitized;
}

function sanitizeStructure(value, maxNodes) {
  let nodes = 0;
  const visit = (node, depth) => {
    if (node === null || typeof node !== 'object') return null;
    nodes += 1;
    if (nodes > maxNodes || depth > 100) {
      throw new Error('The PDF.js tagged-structure safety budget was reached.');
    }
    if (Array.isArray(node)) {
      return node.map((child) => visit(child, depth + 1)).filter((child) => child !== null);
    }
    const result = {};
    for (const key of ['role', 'type', 'id', 'lang', 'alt', 'actualText']) {
      if (typeof node[key] === 'string') result[key] = node[key].slice(0, 16_384);
    }
    if (Array.isArray(node.children)) {
      result.children = node.children.map((child) => visit(child, depth + 1)).filter((child) => child !== null);
    }

    return result;
  };

  return visit(value, 0);
}

function finiteNumber(value) {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function positiveLimit(value, fallback) {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : fallback;
}

function serializedBytes(value) {
  return utf8ByteLength(JSON.stringify(value));
}

function utf8ByteLength(value) {
  return new TextEncoder().encode(String(value)).byteLength;
}

function throwIfAborted(signal) {
  if (signal?.aborted) throw abortError(signal);
}

function abortError(signal) {
  if (signal?.reason instanceof Error) return signal.reason;
  const error = new Error('PDF.js facts collection was cancelled.');
  error.name = 'AbortError';
  return error;
}

function errorMessage(error) {
  return error instanceof Error ? error.message : String(error);
}
