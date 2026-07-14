const DEFAULT_MAX_PIXELS = 16_000_000;
const DEFAULT_MAX_DIMENSION = 8_192;
const DEFAULT_PADDING = 2;
// PDF.js retains its own copy of the source and can allocate substantial
// parser/graphics state on top. Keep the browser-assisted *figure*
// enhancement below the server-side handoff limit; the PHP text import can
// still finish with the original-file placeholder when this is exceeded.
const DEFAULT_MAX_SOURCE_BYTES = 24 * 1024 * 1024;

let pdfjsModulePromise = null;

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
 * @param {(progress:{completed:number,total:number,label:string}) => void} [options.onProgress]
 * @param {number} [options.maxPixels]
 * @param {number} [options.maxSourceBytes]
 * @returns {Promise<Array<{requestId:string,bytes?:Uint8Array,mimeType?:string,width?:number,height?:number,error?:string}>>}
 */
export async function renderPdfFormRequests({
  filesByPath,
  requests,
  pdfjs,
  onProgress = () => {},
  maxPixels = DEFAULT_MAX_PIXELS,
  maxSourceBytes = DEFAULT_MAX_SOURCE_BYTES,
}) {
  if (!Array.isArray(requests) || requests.length === 0) {
    return [];
  }
  // Loading PDF.js itself is an optional browser-assisted enhancement.  A
  // missing worker, a CSP/module policy, or an unsupported browser must not
  // strand the server-side import in `awaiting_renderer`: report every
  // outstanding crop as unavailable so the caller can acknowledge it and let
  // WordPress continue with its normal source/placeholder handling.
  let module;
  try {
    module = await loadPdfJs(pdfjs);
  } catch (error) {
    const message = errorMessage(error);
    const results = requests.map((request) => ({
      requestId: String(request?.id || ''),
      error: message,
    }));
    onProgress({
      completed: requests.length,
      total: requests.length,
      label: 'PDF figure rendering is unavailable; continuing the text import.',
    });

    return results;
  }
  const documents = new Map();
  const results = [];
  try {
    for (let index = 0; index < requests.length; index += 1) {
      const request = requests[index];
      const requestId = String(request?.id || '');
      const path = String(request?.path || '');
      onProgress({
        completed: index,
        total: requests.length,
        label: `Rendering PDF figure ${index + 1} of ${requests.length}…`,
      });
      try {
        if (!requestId || !path || !filesByPath?.has(path)) {
          throw new Error('The original PDF is no longer available in this browser. Choose the file again to render this figure.');
        }
        let document = documents.get(path);
        if (!document) {
          const data = await pdfBytes(filesByPath.get(path), maxSourceBytes);
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
          document = await loadingTask.promise;
          documents.set(path, document);
        }
        const rendered = await renderRequest(module, document, request, maxPixels);
        results.push({ requestId, ...rendered });
      } catch (error) {
        results.push({ requestId, error: errorMessage(error) });
      }
    }
  } finally {
    for (const document of documents.values()) {
      try {
        await document.destroy();
      } catch {
        // A failed renderer should not prevent the rest of the import from
        // continuing; releasing memory is best effort.
      }
    }
  }
  onProgress({ completed: requests.length, total: requests.length, label: 'PDF figure rendering complete.' });

  return results;
}

async function loadPdfJs(pdfjs) {
  if (!pdfjs?.pdfjsModuleUrl || !pdfjs?.pdfjsWorkerUrl) {
    throw new Error('The PDF renderer assets are unavailable.');
  }
  if (!pdfjsModulePromise) {
    pdfjsModulePromise = import(pdfjs.pdfjsModuleUrl).then((module) => {
      module.GlobalWorkerOptions.workerSrc = pdfjs.pdfjsWorkerUrl;
      return module;
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

async function renderRequest(module, pdfDocument, request, maxPixels) {
  const pageNumber = Number(request?.page);
  const bbox = normalizeBBox(request?.bbox);
  if (!Number.isInteger(pageNumber) || pageNumber < 1 || pageNumber > pdfDocument.numPages || !bbox) {
    throw new Error('The requested PDF figure crop was invalid.');
  }
  const page = await pdfDocument.getPage(pageNumber);
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
    const canvas = window.document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { alpha: false });
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
    await renderTask.promise;
    const blob = await canvasBlob(canvas, 'image/png');
    const bytes = new Uint8Array(await blob.arrayBuffer());
    if (bytes.length === 0) {
      throw new Error('The PDF figure rendered as an empty image.');
    }

    return { bytes, mimeType: 'image/png', width, height };
  } finally {
    page.cleanup?.();
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
