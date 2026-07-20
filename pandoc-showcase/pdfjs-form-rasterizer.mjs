const DEFAULT_MAX_PIXELS = 16_000_000;
const DEFAULT_MAX_DIMENSION = 8_192;
const DEFAULT_PADDING = 2;
const DEFAULT_MAX_PAGE_RASTER_REQUESTS = 96;
const DEFAULT_MAX_PAGE_RASTER_IMAGE_BYTES = 16 * 1024 * 1024;
const DEFAULT_MAX_PAGE_RASTER_TOTAL_IMAGE_BYTES = 64 * 1024 * 1024;
const PDF_PAGE_RASTER_METHOD = 'pdfjs-whole-page-raster';
const PDF_PAGE_RASTER_TARGET_SCALE = 2;
// PDF.js retains its own copy of the source and can allocate substantial
// parser/graphics state on top. Keep the browser-assisted *figure*
// enhancement below the server-side handoff limit; the PHP text import can
// still finish with the original-file placeholder when this is exceeded.
const DEFAULT_MAX_SOURCE_BYTES = 24 * 1024 * 1024;

let pdfjsModulePromise = null;
const rendererResources = {
  activeLoadingTasks: 0,
  activeDocuments: 0,
  activePages: 0,
  activeCanvases: 0,
  activeRenderTasks: 0,
  peakLoadingTasks: 0,
  peakDocuments: 0,
  peakPages: 0,
  peakCanvases: 0,
  peakRenderTasks: 0,
};

function changeRendererResource(name, delta) {
  const activeKey = `active${name}`;
  const peakKey = `peak${name}`;
  // Do not clamp an ownership imbalance to zero. A negative count is invalid
  // release evidence and must remain observable to the E2E assertion.
  rendererResources[activeKey] += delta;
  rendererResources[peakKey] = Math.max(rendererResources[peakKey], rendererResources[activeKey]);
}

/**
 * Expose counts, not document contents, so release E2E can prove that a
 * successful import owns no live PDF.js loading task, page, canvas, or render
 * task. The counters are useful even when the browser allocator retains a
 * reusable process heap after the backing resources have been destroyed.
 */
export function pdfFormRendererResourceSnapshot() {
  return { ...rendererResources };
}

/**
 * Render requested PDF Form-XObject page crops in the browser. PDF.js does
 * the complete page paint, then the canvas is clipped to MarkerPDF's known
 * Form rectangle. That preserves graphics state, nested Forms, clipping,
 * gradients, soft masks, and compositing without teaching PHP a graphics
 * interpreter.
 *
 * @param {Object} options
 * @param {Map<string, File|Uint8Array|ArrayBuffer>} options.filesByPath
 * @param {Array<{id:string,path:string,page:number,bbox:{x1:number,y1:number,x2:number,y2:number}}>} options.requests
 * @param {{pdfjsModuleUrl:string,pdfjsWorkerUrl:string,pdfjsWasmUrl?:string,pdfjsCMapUrl?:string,pdfjsStandardFontDataUrl?:string}} options.pdfjs
 * @param {Object} [options.pdfjsModule] Test/host injection for an already loaded PDF.js module.
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxPixels]
 * @param {number} [options.maxTotalPixels]
 * @param {number} [options.maxTotalImageBytes]
 * @param {number} [options.maxSourceBytes]
 * @param {AbortSignal} [options.signal]
 * @returns {Promise<Array<{requestId:string,bytes?:Uint8Array,mimeType?:string,width?:number,height?:number,error?:string}>>}
 */
export async function renderPdfFormRequests({
  ...options
}) {
  const results = [];
  for await (const result of renderPdfFormRequestsIncrementally(options)) {
    results.push(result);
  }

  return results;
}

/**
 * Render one PDF crop at a time. Callers can upload and acknowledge each
 * yielded result before the next canvas is allocated, so a late network or
 * WordPress failure never strands an array of large PNGs in browser memory.
 * Breaking out of the iterator destroys every open PDF.js document.
 *
 * @param {Object} options
 * @param {Map<string, File|Uint8Array|ArrayBuffer>} options.filesByPath
 * @param {Array<{id:string,path:string,page:number,bbox:{x1:number,y1:number,x2:number,y2:number}}>} options.requests
 * @param {{pdfjsModuleUrl:string,pdfjsWorkerUrl:string,pdfjsWasmUrl?:string,pdfjsCMapUrl?:string,pdfjsStandardFontDataUrl?:string}} options.pdfjs
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxPixels]
 * @param {number} [options.maxTotalPixels]
 * @param {number} [options.maxTotalImageBytes]
 * @param {number} [options.maxSourceBytes]
 * @param {AbortSignal} [options.signal]
 * @returns {AsyncGenerator<{requestId:string,bytes?:Uint8Array,mimeType?:string,width?:number,height?:number,error?:string,budgetExhausted?:string}>}
 */
export async function* renderPdfFormRequestsIncrementally({
  filesByPath,
  requests,
  pdfjs,
  pdfjsModule = null,
  onProgress = () => {},
  maxPixels = DEFAULT_MAX_PIXELS,
  maxTotalPixels = Number.POSITIVE_INFINITY,
  maxTotalImageBytes = Number.POSITIVE_INFINITY,
  maxSourceBytes = DEFAULT_MAX_SOURCE_BYTES,
  signal,
}) {
  if (!Array.isArray(requests) || requests.length === 0) {
    return;
  }
  throwIfAborted(signal);
  const totalPixelsLimit = nonNegativeRenderLimit(maxTotalPixels);
  const totalImageBytesLimit = nonNegativeRenderLimit(maxTotalImageBytes);
  const initiallyExhausted = totalPixelsLimit <= 0
    ? 'pixels'
    : (totalImageBytesLimit <= 0 ? 'image-bytes' : '');
  if (initiallyExhausted) {
    const error = renderBudgetError(initiallyExhausted);
    for (const request of requests) {
      yield {
        requestId: String(request?.id || ''),
        error: error.message,
        budgetExhausted: initiallyExhausted,
      };
    }
    return;
  }
  // Loading PDF.js itself is an optional browser-assisted enhancement.  A
  // missing worker, a CSP/module policy, or an unsupported browser must not
  // strand the server-side import in `awaiting_renderer`: report every
  // outstanding crop as unavailable so the caller can acknowledge it and let
  // WordPress continue with its normal source/placeholder handling.
  let module;
  try {
    module = pdfjsModule || await loadPdfJs(pdfjs);
    throwIfAborted(signal);
  } catch (error) {
    if (signal?.aborted) {
      throw abortError(signal);
    }
    const message = errorMessage(error);
    onProgress({
      completed: requests.length,
      total: requests.length,
      label: 'PDF figure rendering is unavailable; continuing the text import.',
    });
    for (const request of requests) {
      yield {
        requestId: String(request?.id || ''),
        error: message,
      };
    }
    return;
  }
  const documents = new Map();
  let renderedPixels = 0;
  let renderedImageBytes = 0;
  try {
    for (let index = 0; index < requests.length; index += 1) {
      throwIfAborted(signal);
      const request = requests[index];
      const requestId = String(request?.id || '');
      const path = String(request?.path || '');
      onProgress({
        completed: index,
        total: requests.length,
        label: `Rendering PDF figure ${index + 1} of ${requests.length}…`,
      });
      try {
        const remainingPixels = totalPixelsLimit - renderedPixels;
        if (remainingPixels <= 0) {
          throw renderBudgetError('pixels');
        }
        const remainingImageBytes = totalImageBytesLimit - renderedImageBytes;
        if (remainingImageBytes <= 0) {
          throw renderBudgetError('image-bytes');
        }
        if (!requestId || !path || !filesByPath?.has(path)) {
          throw new Error('The original PDF is no longer available in this browser. Choose the file again to render this figure.');
        }
        let document = documents.get(path);
        if (!document) {
          const data = await pdfBytes(filesByPath.get(path), maxSourceBytes);
          throwIfAborted(signal);
          const loadingTask = module.getDocument({
            data,
            cMapUrl: pdfjs.pdfjsCMapUrl || undefined,
            cMapPacked: true,
            standardFontDataUrl: pdfjs.pdfjsStandardFontDataUrl || undefined,
            wasmUrl: pdfjs.pdfjsWasmUrl || undefined,
            isEvalSupported: false,
            enableXfa: false,
            stopAtErrors: false,
            verbosity: 0,
          });
          changeRendererResource('LoadingTasks', 1);
          try {
            document = await loadingTask.promise;
          } catch (error) {
            // A rejected loading task can still own a worker/parser. Release
            // it before reporting the crop as unavailable, while preserving
            // the original load error for the caller.
            try {
              await loadingTask.destroy?.();
            } catch {
              // The original loading failure is the actionable diagnostic.
            }
            throw error;
          } finally {
            changeRendererResource('LoadingTasks', -1);
          }
          changeRendererResource('Documents', 1);
          documents.set(path, document);
          throwIfAborted(signal);
        }
        const rendered = await renderRequest(
          module,
          document,
          request,
          Math.min(maxPixels, remainingPixels),
          signal,
        );
        throwIfAborted(signal);
        const pixelCount = rendered.width * rendered.height;
        if (pixelCount > remainingPixels) {
          renderedPixels = totalPixelsLimit;
          throw renderBudgetError('pixels');
        }
        if (rendered.bytes.length > remainingImageBytes) {
          // The failed encode proves that the residual byte budget cannot
          // accept this output. Exhaust it so later requests fail before
          // another full PDF.js paint/PNG encode rather than repeating the
          // same expensive miss hundreds of times.
          renderedImageBytes = totalImageBytesLimit;
          throw renderBudgetError('image-bytes');
        }
        renderedPixels += pixelCount;
        renderedImageBytes += rendered.bytes.length;
        yield { requestId, ...rendered };
      } catch (error) {
        if (signal?.aborted) {
          throw abortError(signal);
        }
        const budgetExhausted = renderBudgetExhausted(error);
        yield {
          requestId,
          error: errorMessage(error),
          ...(budgetExhausted ? { budgetExhausted } : {}),
        };
      }
    }
  } finally {
    for (const document of documents.values()) {
      try {
        await document.destroy();
      } catch {
        // A failed renderer should not prevent the rest of the import from
        // continuing; releasing memory is best effort.
      } finally {
        changeRendererResource('Documents', -1);
      }
    }
  }
  throwIfAborted(signal);
  onProgress({ completed: requests.length, total: requests.length, label: 'PDF figure rendering complete.' });
}

/**
 * Render the immutable whole-page requests emitted by PdfReader. Successful
 * records use the exact PandocMediaExtractor response field set. `contents`
 * is a browser-facing Uint8Array; the HTTP/RPC boundary must preserve those
 * bytes as the raw binary string consumed by PHP.
 *
 * @param {Object} options
 * @param {File|Uint8Array|ArrayBuffer} options.source
 * @param {Array<Object>} options.requests
 * @param {{pdfjsModuleUrl:string,pdfjsWorkerUrl:string,pdfjsWasmUrl?:string,pdfjsCMapUrl?:string,pdfjsStandardFontDataUrl?:string}} options.pdfjs
 * @param {Object} [options.pdfjsModule]
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxPixels]
 * @param {number} [options.maxTotalPixels]
 * @param {number} [options.maxImageBytes]
 * @param {number} [options.maxTotalImageBytes]
 * @param {number} [options.maxSourceBytes]
 * @param {AbortSignal} [options.signal]
 * @returns {Promise<Array<Object>>}
 */
export async function renderPdfPageRasterRequests({
  ...options
}) {
  const results = [];
  for await (const result of renderPdfPageRasterRequestsIncrementally(options)) {
    results.push(result);
  }

  return results;
}

/**
 * Render and release one exact physical page at a time. Breaking out of this
 * iterator destroys the open PDF.js document before control returns to the
 * caller. Invalid, unavailable, and budget-exhausted requests yield only an
 * error acknowledgement; they never resemble an integrity-bearing response.
 *
 * @param {Object} options
 * @param {File|Uint8Array|ArrayBuffer} options.source
 * @param {Array<Object>} options.requests
 * @param {{pdfjsModuleUrl:string,pdfjsWorkerUrl:string,pdfjsWasmUrl?:string,pdfjsCMapUrl?:string,pdfjsStandardFontDataUrl?:string}} options.pdfjs
 * @param {Object} [options.pdfjsModule]
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxPixels]
 * @param {number} [options.maxTotalPixels]
 * @param {number} [options.maxImageBytes]
 * @param {number} [options.maxTotalImageBytes]
 * @param {number} [options.maxSourceBytes]
 * @param {AbortSignal} [options.signal]
 * @returns {AsyncGenerator<Object>}
 */
export async function* renderPdfPageRasterRequestsIncrementally({
  source,
  requests,
  pdfjs,
  pdfjsModule = null,
  onProgress = () => {},
  maxPixels = DEFAULT_MAX_PIXELS,
  maxTotalPixels = Number.POSITIVE_INFINITY,
  maxImageBytes = DEFAULT_MAX_PAGE_RASTER_IMAGE_BYTES,
  maxTotalImageBytes = DEFAULT_MAX_PAGE_RASTER_TOTAL_IMAGE_BYTES,
  maxSourceBytes = DEFAULT_MAX_SOURCE_BYTES,
  signal,
}) {
  if (!Array.isArray(requests) || requests.length === 0) {
    return;
  }
  throwIfAborted(signal);
  if (requests.length > DEFAULT_MAX_PAGE_RASTER_REQUESTS) {
    const message = `The PDF page renderer accepts at most ${DEFAULT_MAX_PAGE_RASTER_REQUESTS} requests.`;
    for (const request of requests) {
      yield pageRasterErrorResult(request, message);
    }
    return;
  }

  const perImagePixelLimit = positiveRenderLimit(maxPixels, DEFAULT_MAX_PIXELS);
  const perImageByteLimit = positiveRenderLimit(maxImageBytes, DEFAULT_MAX_PAGE_RASTER_IMAGE_BYTES);
  const totalPixelsLimit = nonNegativeRenderLimit(maxTotalPixels);
  const totalImageBytesLimit = nonNegativeRenderLimit(maxTotalImageBytes);
  const initiallyExhausted = totalPixelsLimit <= 0
    ? 'pixels'
    : (totalImageBytesLimit <= 0 ? 'image-bytes' : '');
  if (initiallyExhausted) {
    const error = renderBudgetError(initiallyExhausted);
    for (const request of requests) {
      yield pageRasterErrorResult(request, error.message, initiallyExhausted);
    }
    return;
  }

  let data;
  let sourceSha256;
  try {
    data = await pdfBytes(source, maxSourceBytes);
    throwIfAborted(signal);
    sourceSha256 = await sha256Hex(data);
    throwIfAborted(signal);
  } catch (error) {
    if (signal?.aborted) {
      throw abortError(signal);
    }
    const message = errorMessage(error);
    for (const request of requests) {
      yield pageRasterErrorResult(request, message);
    }
    return;
  }

  const duplicateCounts = pageRasterDuplicateCounts(requests);
  const validated = [];
  for (const request of requests) {
    try {
      validated.push({
        request: await validatePageRasterRequest(request, sourceSha256, duplicateCounts),
        error: '',
      });
    } catch (error) {
      validated.push({ request, error: errorMessage(error) });
    }
  }
  if (!validated.some((item) => item.error === '')) {
    for (const item of validated) {
      yield pageRasterErrorResult(item.request, item.error);
    }
    return;
  }

  let module;
  try {
    module = pdfjsModule || await loadPdfJs(pdfjs);
    throwIfAborted(signal);
  } catch (error) {
    if (signal?.aborted) {
      throw abortError(signal);
    }
    const message = errorMessage(error);
    onProgress({
      completed: requests.length,
      total: requests.length,
      label: 'PDF page rendering is unavailable; continuing without page images.',
    });
    for (const item of validated) {
      yield pageRasterErrorResult(item.request, item.error || message);
    }
    return;
  }

  let pdfDocument = null;
  let loadingTask = null;
  let ownsLoadingTask = false;
  let ownsPdfDocument = false;
  try {
    try {
      try {
        loadingTask = module.getDocument(pdfDocumentOptions(data, pdfjs));
        if (!loadingTask || !('promise' in Object(loadingTask))) {
          throw new Error('The PDF renderer did not create a loading task.');
        }
        changeRendererResource('LoadingTasks', 1);
        ownsLoadingTask = true;
        pdfDocument = await loadingTask.promise;
      } catch (error) {
        try {
          await loadingTask?.destroy?.();
        } catch {
          // Preserve the loading failure as the actionable diagnostic.
        }
        throw error;
      } finally {
        if (ownsLoadingTask) {
          changeRendererResource('LoadingTasks', -1);
          ownsLoadingTask = false;
        }
      }
      if (!pdfDocument || !Number.isInteger(pdfDocument.numPages) || pdfDocument.numPages < 1) {
        throw new Error('The PDF renderer returned an invalid document.');
      }
      changeRendererResource('Documents', 1);
      ownsPdfDocument = true;
      throwIfAborted(signal);
    } catch (error) {
      if (signal?.aborted) {
        throw abortError(signal);
      }
      const message = errorMessage(error);
      for (const item of validated) {
        yield pageRasterErrorResult(item.request, item.error || message);
      }
      return;
    }

    let renderedPixels = 0;
    let renderedImageBytes = 0;
    for (let index = 0; index < validated.length; index += 1) {
      throwIfAborted(signal);
      const item = validated[index];
      const request = item.request;
      onProgress({
        completed: index,
        total: requests.length,
        label: `Rendering PDF page image ${index + 1} of ${requests.length}…`,
      });
      if (item.error) {
        yield pageRasterErrorResult(request, item.error);
        continue;
      }
      try {
        const pixelCount = request.width * request.height;
        const remainingPixels = totalPixelsLimit - renderedPixels;
        if (pixelCount > perImagePixelLimit) {
          throw new Error('The requested PDF page image exceeds the per-image pixel budget.');
        }
        if (pixelCount > remainingPixels) {
          renderedPixels = totalPixelsLimit;
          throw renderBudgetError('pixels');
        }
        const remainingImageBytes = totalImageBytesLimit - renderedImageBytes;
        if (remainingImageBytes <= 0) {
          throw renderBudgetError('image-bytes');
        }
        if (perImageByteLimit <= 0) {
          throw new Error('The requested PDF page image exceeds the per-image byte budget.');
        }
        const contents = await renderPageRasterRequest(module, pdfDocument, request, signal);
        throwIfAborted(signal);
        if (contents.byteLength > perImageByteLimit) {
          throw new Error('The rendered PDF page image exceeds the per-image byte budget.');
        }
        if (contents.byteLength > remainingImageBytes) {
          renderedImageBytes = totalImageBytesLimit;
          throw renderBudgetError('image-bytes');
        }
        const sha256 = await sha256Hex(contents);
        throwIfAborted(signal);
        const proofDigest = await pdfPageRasterProofDigest(
          request.requestDigest,
          contents.byteLength,
          sha256,
        );
        renderedPixels += pixelCount;
        renderedImageBytes += contents.byteLength;
        yield pageRasterSuccessResult(request, contents, sha256, proofDigest);
      } catch (error) {
        if (signal?.aborted) {
          throw abortError(signal);
        }
        const budgetExhausted = renderBudgetExhausted(error);
        yield pageRasterErrorResult(request, errorMessage(error), budgetExhausted);
      }
    }
  } finally {
    if (pdfDocument) {
      try {
        await pdfDocument.destroy?.();
      } catch {
        // Resource release is best effort; counters still expose imbalance.
      } finally {
        if (ownsPdfDocument) {
          changeRendererResource('Documents', -1);
        }
      }
    }
  }
  throwIfAborted(signal);
  onProgress({ completed: requests.length, total: requests.length, label: 'PDF page rendering complete.' });
}

const PDF_PAGE_RASTER_REQUEST_KEYS = [
  'height',
  'id',
  'method',
  'mimeType',
  'page',
  'pageBox',
  'pageBoxSource',
  'pageObject',
  'pageRotation',
  'requestDigest',
  'sourceSha256',
  'version',
  'width',
];

function pdfDocumentOptions(data, pdfjs) {
  return {
    data,
    cMapUrl: pdfjs?.pdfjsCMapUrl || undefined,
    cMapPacked: true,
    standardFontDataUrl: pdfjs?.pdfjsStandardFontDataUrl || undefined,
    wasmUrl: pdfjs?.pdfjsWasmUrl || undefined,
    isEvalSupported: false,
    enableXfa: false,
    stopAtErrors: false,
    verbosity: 0,
  };
}

function pageRasterErrorResult(request, message, budgetExhausted = '') {
  return {
    requestId: typeof request?.id === 'string' ? request.id : '',
    error: String(message || 'The requested PDF page image could not be rendered.'),
    ...(budgetExhausted ? { budgetExhausted } : {}),
  };
}

function pageRasterSuccessResult(request, contents, sha256, proofDigest) {
  return {
    version: request.version,
    method: request.method,
    requestId: request.id,
    sourceSha256: request.sourceSha256,
    page: request.page,
    pageObject: request.pageObject,
    pageBox: [...request.pageBox],
    pageBoxSource: request.pageBoxSource,
    pageRotation: request.pageRotation,
    width: request.width,
    height: request.height,
    mimeType: request.mimeType,
    byteLength: contents.byteLength,
    sha256,
    requestDigest: request.requestDigest,
    proofDigest,
    contents,
  };
}

function pageRasterDuplicateCounts(requests) {
  const counts = { ids: new Map(), pages: new Map(), digests: new Map() };
  for (const request of requests) {
    if (typeof request?.id === 'string') {
      counts.ids.set(request.id, (counts.ids.get(request.id) || 0) + 1);
    }
    if (Number.isInteger(request?.page)) {
      counts.pages.set(request.page, (counts.pages.get(request.page) || 0) + 1);
    }
    if (typeof request?.requestDigest === 'string') {
      counts.digests.set(
        request.requestDigest,
        (counts.digests.get(request.requestDigest) || 0) + 1,
      );
    }
  }

  return counts;
}

async function validatePageRasterRequest(request, sourceSha256, duplicateCounts) {
  if (!request || typeof request !== 'object' || Array.isArray(request)
    || !hasExactObjectKeys(request, PDF_PAGE_RASTER_REQUEST_KEYS)
    || request.version !== 1
    || request.method !== PDF_PAGE_RASTER_METHOD
    || typeof request.id !== 'string'
    || !/^[a-f0-9]{64}$/.test(request.sourceSha256 || '')
    || request.sourceSha256 !== sourceSha256
    || !Number.isInteger(request.page)
    || request.page < 1
    || !Number.isInteger(request.pageObject)
    || request.pageObject < 1
    || !normalizePageBox(request.pageBox)
    || !['CropBox', 'MediaBox'].includes(request.pageBoxSource)
    || ![0, 90, 180, 270].includes(request.pageRotation)
    || !Number.isInteger(request.width)
    || !Number.isInteger(request.height)
    || request.width < 1
    || request.height < 1
    || request.width > DEFAULT_MAX_DIMENSION
    || request.height > DEFAULT_MAX_DIMENSION
    || request.width * request.height > DEFAULT_MAX_PIXELS
    || request.mimeType !== 'image/png'
    || !/^[a-f0-9]{64}$/.test(request.requestDigest || '')
    || duplicateCounts.ids.get(request.id) !== 1
    || duplicateCounts.pages.get(request.page) !== 1
    || duplicateCounts.digests.get(request.requestDigest) !== 1) {
    throw new Error('The PDF page raster request metadata was invalid or stale.');
  }
  const dimensions = expectedPageRasterDimensions(request.pageBox, request.pageRotation);
  if (!dimensions || request.width !== dimensions.width || request.height !== dimensions.height) {
    throw new Error('The PDF page raster request dimensions did not match its normative page box.');
  }
  const requestDigest = await pdfPageRasterRequestDigest(request);
  if (request.requestDigest !== requestDigest
    || request.id !== `pdf-page-raster-${requestDigest.slice(0, 32)}`) {
    throw new Error('The PDF page raster request digest was invalid or stale.');
  }

  return { ...request, pageBox: [...request.pageBox] };
}

function hasExactObjectKeys(value, expectedKeys) {
  const keys = Object.keys(value).sort();
  const expected = [...expectedKeys].sort();

  return keys.length === expected.length && keys.every((key, index) => key === expected[index]);
}

function normalizePageBox(box) {
  if (!Array.isArray(box) || box.length !== 4 || !box.every((value) => (
    typeof value === 'number' && Number.isFinite(value)
  ))) {
    return null;
  }
  if (box[2] - box[0] <= 0.000001 || box[3] - box[1] <= 0.000001) {
    return null;
  }

  return [...box];
}

function expectedPageRasterDimensions(box, rotation) {
  const normalized = normalizePageBox(box);
  if (!normalized || ![0, 90, 180, 270].includes(rotation)) {
    return null;
  }
  const pageWidth = normalized[2] - normalized[0];
  const pageHeight = normalized[3] - normalized[1];
  const displayWidth = [90, 270].includes(rotation) ? pageHeight : pageWidth;
  const displayHeight = [90, 270].includes(rotation) ? pageWidth : pageHeight;
  const scale = Math.min(
    PDF_PAGE_RASTER_TARGET_SCALE,
    DEFAULT_MAX_DIMENSION / displayWidth,
    DEFAULT_MAX_DIMENSION / displayHeight,
    Math.sqrt(DEFAULT_MAX_PIXELS / (displayWidth * displayHeight)),
  );
  if (!Number.isFinite(scale) || scale <= 0) {
    return null;
  }
  const width = Math.max(1, Math.ceil(displayWidth * scale));
  const height = Math.max(1, Math.ceil(displayHeight * scale));
  if (width > DEFAULT_MAX_DIMENSION
    || height > DEFAULT_MAX_DIMENSION
    || width * height > DEFAULT_MAX_PIXELS) {
    return null;
  }

  return { width, height, displayWidth, displayHeight };
}

async function pdfPageRasterRequestDigest(request) {
  const pageBox = request.pageBox.map((value) => value.toFixed(6)).join(',');
  const canonical = [
    'pdf-page-raster-request-v1',
    `method=${request.method}`,
    `sourceSha256=${request.sourceSha256}`,
    `page=${request.page}`,
    `pageObject=${request.pageObject}`,
    `pageBox=${pageBox}`,
    `pageBoxSource=${request.pageBoxSource}`,
    `pageRotation=${request.pageRotation}`,
    `width=${request.width}`,
    `height=${request.height}`,
    `mimeType=${request.mimeType}`,
  ].join('\n');

  return sha256Hex(new TextEncoder().encode(canonical));
}

async function pdfPageRasterProofDigest(requestDigest, byteLength, sha256) {
  const canonical = [
    'pdf-page-raster-proof-v1',
    `requestDigest=${requestDigest}`,
    `byteLength=${byteLength}`,
    `sha256=${sha256}`,
  ].join('\n');

  return sha256Hex(new TextEncoder().encode(canonical));
}

async function sha256Hex(bytes) {
  const subtle = globalThis.crypto?.subtle;
  if (!subtle || typeof subtle.digest !== 'function') {
    throw new Error('SHA-256 is unavailable in this browser.');
  }
  const digest = new Uint8Array(await subtle.digest('SHA-256', bytes));

  return [...digest].map((value) => value.toString(16).padStart(2, '0')).join('');
}

async function renderPageRasterRequest(module, pdfDocument, request, signal) {
  throwIfAborted(signal);
  if (request.page > pdfDocument.numPages) {
    throw new Error('The requested physical PDF page was unavailable.');
  }
  const page = await pdfDocument.getPage(request.page);
  changeRendererResource('Pages', 1);
  let canvas = null;
  let context = null;
  try {
    const pageView = normalizePdfJsPageView(page?.view);
    const dimensions = expectedPageRasterDimensions(request.pageBox, request.pageRotation);
    if (!page
      || page.pageNumber !== request.page
      || page.ref?.num !== request.pageObject
      || page.rotate !== request.pageRotation
      || !pageView
      || !pageBoxesMatch(pageView, request.pageBox)
      || !dimensions) {
      throw new Error('PDF.js page identity or normative geometry did not match the raster request.');
    }
    const viewport = page.getViewport({ scale: 1, rotation: request.pageRotation });
    if (!viewport
      || !Number.isFinite(viewport.width)
      || !Number.isFinite(viewport.height)
      || viewport.width.toFixed(6) !== dimensions.displayWidth.toFixed(6)
      || viewport.height.toFixed(6) !== dimensions.displayHeight.toFixed(6)) {
      throw new Error('PDF.js viewport geometry did not match the requested physical page.');
    }

    canvas = window.document.createElement('canvas');
    changeRendererResource('Canvases', 1);
    canvas.width = request.width;
    canvas.height = request.height;
    context = canvas.getContext('2d', { alpha: false });
    if (!context) {
      throw new Error('This browser could not create a canvas for the PDF page image.');
    }
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, request.width, request.height);
    const annotationMode = module.AnnotationMode?.DISABLE ?? 0;
    const renderTask = page.render({
      canvasContext: context,
      viewport,
      transform: [
        request.width / viewport.width,
        0,
        0,
        request.height / viewport.height,
        0,
        0,
      ],
      annotationMode,
      background: '#ffffff',
      intent: 'display',
    });
    if (!renderTask || !renderTask.promise) {
      throw new Error('PDF.js did not create a page render task.');
    }
    changeRendererResource('RenderTasks', 1);
    try {
      await renderTask.promise;
    } finally {
      changeRendererResource('RenderTasks', -1);
    }
    throwIfAborted(signal);
    const blob = await canvasBlob(canvas, 'image/png');
    const contents = new Uint8Array(await blob.arrayBuffer());
    throwIfAborted(signal);
    if (!pngHasExactDimensions(contents, request.width, request.height)) {
      throw new Error('The browser returned an invalid or wrong-size PDF page PNG.');
    }

    return contents;
  } finally {
    if (canvas) {
      try { context?.clearRect(0, 0, canvas.width, canvas.height); } catch { /* Best effort. */ }
      canvas.width = 0;
      canvas.height = 0;
      changeRendererResource('Canvases', -1);
    }
    try {
      page?.cleanup?.();
    } finally {
      changeRendererResource('Pages', -1);
    }
  }
}

function normalizePdfJsPageView(view) {
  const values = Array.isArray(view)
    ? view
    : (ArrayBuffer.isView(view) ? Array.from(view) : null);
  if (!values || values.length !== 4 || !values.every(Number.isFinite)) {
    return null;
  }

  return values;
}

function pageBoxesMatch(left, right) {
  const leftBox = normalizePageBox(left);
  const rightBox = normalizePageBox(right);

  return Boolean(leftBox && rightBox && leftBox.every((value, index) => (
    value.toFixed(6) === rightBox[index].toFixed(6)
  )));
}

function pngHasExactDimensions(bytes, width, height) {
  const signature = [137, 80, 78, 71, 13, 10, 26, 10];
  if (!(bytes instanceof Uint8Array)
    || bytes.byteLength < 24
    || !signature.every((value, index) => bytes[index] === value)
    || bytes[8] !== 0
    || bytes[9] !== 0
    || bytes[10] !== 0
    || bytes[11] !== 13
    || String.fromCharCode(...bytes.slice(12, 16)) !== 'IHDR') {
    return false;
  }
  const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);

  return view.getUint32(16) === width && view.getUint32(20) === height;
}

async function loadPdfJs(pdfjs) {
  if (!pdfjs?.pdfjsModuleUrl || !pdfjs?.pdfjsWorkerUrl) {
    throw new Error('The PDF renderer assets are unavailable.');
  }
  if (!pdfjsModulePromise) {
    pdfjsModulePromise = import(pdfjs.pdfjsModuleUrl).then((module) => {
      module.GlobalWorkerOptions.workerSrc = pdfjs.pdfjsWorkerUrl;
      return module;
    }).catch((error) => {
      // Do not turn a transient module/worker failure into a permanent
      // failure for every later static preview or Playground import.
      pdfjsModulePromise = null;
      throw error;
    });
  }

  return pdfjsModulePromise;
}

async function pdfBytes(file, maxSourceBytes) {
  const limit = Number.isSafeInteger(maxSourceBytes) && maxSourceBytes > 0
    ? maxSourceBytes
    : DEFAULT_MAX_SOURCE_BYTES;
  const sourceSize = file instanceof Uint8Array || file instanceof ArrayBuffer
    ? file.byteLength
    : Number(file?.size);
  if (Number.isFinite(sourceSize) && sourceSize > limit) {
    throw new Error('The PDF is too large to render figures safely in this browser. The text import will continue with original-file placeholders.');
  }
  if (file instanceof Uint8Array) {
    return new Uint8Array(file);
  }
  if (file instanceof ArrayBuffer) {
    return new Uint8Array(file.slice(0));
  }
  if (file && typeof file.arrayBuffer === 'function') {
    const bytes = new Uint8Array(await file.arrayBuffer());
    if (bytes.byteLength > limit) {
      throw new Error('The PDF is too large to render figures safely in this browser. The text import will continue with original-file placeholders.');
    }

    return bytes;
  }
  throw new Error('The selected PDF could not be read in this browser.');
}

async function renderRequest(module, pdfDocument, request, maxPixels, signal) {
  throwIfAborted(signal);
  const pageNumber = Number(request?.page);
  const bbox = normalizeBBox(request?.bbox);
  if (!Number.isInteger(pageNumber) || pageNumber < 1 || pageNumber > pdfDocument.numPages || !bbox) {
    throw new Error('The requested PDF figure crop was invalid.');
  }
  const page = await pdfDocument.getPage(pageNumber);
  changeRendererResource('Pages', 1);
  let canvas = null;
  let context = null;
  try {
    const scale = cropScale(bbox, maxPixels);
    const viewport = page.getViewport({ scale });
    // PDF.js 6 removed convertToViewportRectangle() while retaining the
    // point conversion API. Use the old helper when present and otherwise
    // transform the diagonal corners ourselves; axis-aligned page-space Form
    // boxes remain axis-aligned under PageViewport's rotation/flip transform.
    const rectangle = typeof viewport.convertToViewportRectangle === 'function'
      ? viewport.convertToViewportRectangle([bbox.x1, bbox.y1, bbox.x2, bbox.y2])
      : [
        ...viewport.convertToViewportPoint(bbox.x1, bbox.y1),
        ...viewport.convertToViewportPoint(bbox.x2, bbox.y2),
      ];
    const left = Math.floor(Math.min(rectangle[0], rectangle[2]) - DEFAULT_PADDING);
    const top = Math.floor(Math.min(rectangle[1], rectangle[3]) - DEFAULT_PADDING);
    const right = Math.ceil(Math.max(rectangle[0], rectangle[2]) + DEFAULT_PADDING);
    const bottom = Math.ceil(Math.max(rectangle[1], rectangle[3]) + DEFAULT_PADDING);
    const width = Math.max(1, right - left);
    const height = Math.max(1, bottom - top);
    if (width > DEFAULT_MAX_DIMENSION || height > DEFAULT_MAX_DIMENSION || width * height > maxPixels) {
      throw new Error('The PDF figure crop is too large to render safely in this browser.');
    }
    canvas = window.document.createElement('canvas');
    changeRendererResource('Canvases', 1);
    canvas.width = width;
    canvas.height = height;
    context = canvas.getContext('2d', { alpha: false });
    if (!context) {
      throw new Error('This browser could not create a canvas for the PDF figure.');
    }
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    const annotationMode = module.AnnotationMode?.DISABLE ?? 0;
    const renderTask = page.render({
      canvasContext: context,
      viewport,
      transform: [1, 0, 0, 1, -left, -top],
      annotationMode,
      background: '#ffffff',
      intent: 'display',
    });
    changeRendererResource('RenderTasks', 1);
    try {
      await renderTask.promise;
    } finally {
      changeRendererResource('RenderTasks', -1);
    }
    throwIfAborted(signal);
    const blob = await canvasBlob(canvas, 'image/png');
    const bytes = new Uint8Array(await blob.arrayBuffer());
    throwIfAborted(signal);
    if (bytes.length === 0) {
      throw new Error('The PDF figure rendered as an empty image.');
    }

    return { bytes, mimeType: 'image/png', width, height };
  } finally {
    // A canvas keeps its full RGBA backing store even after its PNG Blob has
    // been encoded. Explicitly collapse it before the iterator lets the next
    // request allocate another crop; relying on a later GC caused large PDFs
    // to retain several figure-sized buffers at once.
    if (canvas) {
      try { context?.clearRect(0, 0, canvas.width, canvas.height); } catch { /* Best effort. */ }
      canvas.width = 0;
      canvas.height = 0;
      changeRendererResource('Canvases', -1);
    }
    try {
      page.cleanup?.();
    } finally {
      changeRendererResource('Pages', -1);
    }
  }
}

function nonNegativeRenderLimit(value) {
  return Number.isFinite(value) && value >= 0
    ? Math.floor(value)
    : Number.POSITIVE_INFINITY;
}

function positiveRenderLimit(value, fallback) {
  return Number.isFinite(value) && value >= 0
    ? Math.floor(value)
    : fallback;
}

function renderBudgetError(kind) {
  const normalized = kind === 'pixels' ? 'pixels' : 'image-bytes';
  const error = new Error(normalized === 'pixels'
    ? 'The PDF figure renderer reached its total pixel budget.'
    : 'The PDF figure renderer reached its total image-byte budget.');
  error.budgetExhausted = normalized;
  return error;
}

function renderBudgetExhausted(error) {
  return error && typeof error === 'object' && ['pixels', 'image-bytes'].includes(error.budgetExhausted)
    ? error.budgetExhausted
    : '';
}

function abortError(signal) {
  if (signal?.reason instanceof Error) {
    return signal.reason;
  }
  const error = new Error('PDF figure rendering was cancelled.');
  error.name = 'AbortError';
  return error;
}

function throwIfAborted(signal) {
  if (signal?.aborted) {
    throw abortError(signal);
  }
}

function normalizeBBox(bbox) {
  const x1 = Number(bbox?.x1);
  const y1 = Number(bbox?.y1);
  const x2 = Number(bbox?.x2);
  const y2 = Number(bbox?.y2);
  if (![x1, y1, x2, y2].every(Number.isFinite) || x2 <= x1 || y2 <= y1) {
    return null;
  }

  return { x1, y1, x2, y2 };
}

function cropScale(bbox, maxPixels) {
  const area = Math.max(1, (bbox.x2 - bbox.x1) * (bbox.y2 - bbox.y1));
  const scaleForBudget = Math.sqrt(Math.max(1, maxPixels - 4 * DEFAULT_PADDING) / area);
  const scaleForDimension = Math.min(
    DEFAULT_MAX_DIMENSION / Math.max(1, bbox.x2 - bbox.x1),
    DEFAULT_MAX_DIMENSION / Math.max(1, bbox.y2 - bbox.y1),
  );

  return Math.max(0.1, Math.min(2, scaleForBudget, scaleForDimension));
}

function canvasBlob(canvas, mimeType) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (blob) {
        resolve(blob);
      } else {
        reject(new Error('The browser could not encode the PDF figure image.'));
      }
    }, mimeType);
  });
}

function errorMessage(error) {
  return error instanceof Error ? error.message : String(error);
}
