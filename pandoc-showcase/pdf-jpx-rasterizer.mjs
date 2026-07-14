import OpenJPEG from './vendor/pdfjs-openjpeg/openjpeg.mjs';

// Keep direct JPEG 2000 extraction bounded enough for browser imports. The
// PHP media extractor verifies every returned raster again before it becomes
// document media.
const MAX_PDF_BYTES = 24 * 1024 * 1024;
const MAX_IMAGE_BYTES = 32 * 1024 * 1024;
// OpenJPEG yields up to four bytes per pixel. This is intentionally lower
// than the one-bit JBIG2 budget so a single browser import cannot materialize
// a 192 MB decoded bitmap before PNG encoding begins.
const MAX_IMAGE_PIXELS = 12_000_000;
// An image can be within the total-pixel limit yet be one absurdly wide row.
// Keep the PNG filter scratch space bounded on mobile heaps as well.
const MAX_PNG_ROW_BYTES = 4 * 1024 * 1024;
const MAX_IMAGES = 96;
const MAX_TOTAL_PNG_BYTES = 24_000_000;
const PNG_SIGNATURE = new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10]);
const LENGTH_BASE = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31, 35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258];
const LENGTH_EXTRA = [0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 0];
const DISTANCE_BASE = [1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193, 257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145, 8193, 12289, 16385, 24577];
const DISTANCE_EXTRA = [0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9];

/**
 * Decodes simple, direct `/JPXDecode` PDF image XObjects into lossless PNG
 * rasters. Object streams, arbitrary filter chains, external masks, and PDF
 * color transforms deliberately remain outside this small media fallback.
 *
 * @param {ArrayBuffer|Uint8Array} input
 * @param {{
 *   imageMode?: string,
 *   maxImages?: number,
 *   maxPngBytes?: number,
 *   decoderFactory?: () => Promise<unknown>|unknown,
 *   onProgress?: (progress: {completed:number,total:number,object:string}) => void,
 * }} [options]
 * @returns {Promise<{rasters: Array<{object:string,bytes:Uint8Array,mimeType:'image/png',width:number,height:number,encodedByteLength:number,isIndexed:boolean}>, diagnostics:string[]}>}
 */
export async function decodePdfJpxRasters(input, options = {}) {
  const imageMode = normalizeImageMode(options.imageMode);
  const source = input instanceof Uint8Array ? input : new Uint8Array(input);
  const diagnostics = [];
  if (imageMode === 'none' || source.length === 0 || !containsAscii(source, '/JPXDecode')) {
    return { rasters: [], diagnostics };
  }
  if (source.length > MAX_PDF_BYTES) {
    return { rasters: [], diagnostics: ['pdf-jpx-raster-scan-skipped:too-large'] };
  }
  if (typeof WebAssembly === 'undefined' && !options.decoderFactory) {
    return { rasters: [], diagnostics: ['pdf-jpx-raster-unavailable:wasm'] };
  }

  const candidates = findJpxImages(source, imageMode, diagnostics);
  if (candidates.length === 0) {
    return { rasters: [], diagnostics };
  }

  const requestedLimit = options.maxImages === undefined ? MAX_IMAGES : Number(options.maxImages);
  const limit = Math.max(0, Math.min(Number.isFinite(requestedLimit) ? requestedLimit : MAX_IMAGES, MAX_IMAGES));
  const selected = candidates.slice(0, limit);
  if (candidates.length > selected.length) {
    diagnostics.push('pdf-jpx-raster-limit');
  }

  let decoder;
  try {
    decoder = await (options.decoderFactory || (() => OpenJPEG()))();
  } catch (error) {
    diagnostics.push(`pdf-jpx-raster-unavailable:${errorToken(error)}`);
    return { rasters: [], diagnostics };
  }
  if (!decoder || typeof decoder._malloc !== 'function' || typeof decoder._free !== 'function'
    || typeof decoder._jp2_decode !== 'function' || typeof decoder.writeArrayToMemory !== 'function') {
    diagnostics.push('pdf-jpx-raster-unavailable:decoder-api');
    return { rasters: [], diagnostics };
  }

  const rasters = [];
  let totalPngBytes = 0;
  const maxPngBytes = pngByteLimit(options.maxPngBytes);
  for (let index = 0; index < selected.length; index += 1) {
    const image = selected[index];
    options.onProgress?.({ completed: index, total: selected.length, object: image.object });
    try {
      const decoded = decodeJpxImage(decoder, image);
      const png = await encodeJpxPng(image, decoded);
      if (totalPngBytes + png.length > maxPngBytes) {
        diagnostics.push('pdf-jpx-raster-total-limit');
        break;
      }
      totalPngBytes += png.length;
      rasters.push({
        object: image.object,
        bytes: png,
        mimeType: 'image/png',
        width: image.width,
        height: image.height,
        // The original JPX stream length and palette flag are intentionally
        // nonbreaking metadata. A later output policy can choose a compact
        // web codec without scanning the PDF a second time.
        encodedByteLength: image.bytes.length,
        isIndexed: image.isIndexed,
      });
      diagnostics.push(`pdf-jpx-raster-loaded:${image.object}:${image.importance}`);
    } catch (error) {
      diagnostics.push(`pdf-jpx-raster-skipped:${image.object}:${errorToken(error)}`);
    }
    options.onProgress?.({ completed: index + 1, total: selected.length, object: image.object });
    await yieldToBrowser();
  }

  return { rasters, diagnostics };
}

function pngByteLimit(value) {
  if (value === undefined) {
    return MAX_TOTAL_PNG_BYTES;
  }
  const numeric = Number(value);

  return Number.isFinite(numeric)
    ? Math.max(0, Math.min(Math.floor(numeric), MAX_TOTAL_PNG_BYTES))
    : MAX_TOTAL_PNG_BYTES;
}

/**
 * @param {{_malloc:(size:number)=>number,_free:(pointer:number)=>void,_jp2_decode:(pointer:number,length:number,numComponents:number,isIndexed:boolean,smaskInData:boolean,reducePower:number)=>number,writeArrayToMemory:(bytes:Uint8Array,pointer:number)=>void,imageData?:Uint8Array,errorMessages?:string}} decoder
 * @param {{bytes:Uint8Array,width:number,height:number,numComponents:number,isJp2Container:boolean,isIndexed:boolean,smaskInData:boolean}} image
 */
function decodeJpxImage(decoder, image) {
  let pointer = 0;
  try {
    pointer = decoder._malloc(image.bytes.length);
    if (!pointer) {
      throw new Error('memory');
    }
    decoder.writeArrayToMemory(image.bytes, pointer);
    decoder.imageData = undefined;
    decoder.errorMessages = undefined;
    // JP2 containers carry their own color/palette metadata. Raw codestreams
    // need the simple PDF DeviceGray/DeviceRGB component count we parsed.
    const numComponents = image.isJp2Container ? 0 : image.numComponents;
    const status = decoder._jp2_decode(
      pointer,
      image.bytes.length,
      numComponents,
      !image.isJp2Container && image.isIndexed,
      image.smaskInData,
      0,
    );
    if (status !== 0) {
      throw new Error(decoder.errorMessages || 'decode');
    }
    const pixels = decoder.imageData;
    if (!(pixels instanceof Uint8Array) && !(pixels instanceof Uint8ClampedArray)) {
      throw new Error('empty-decoder-result');
    }
    const pixelCount = image.width * image.height;
    if (!Number.isSafeInteger(pixelCount) || pixelCount <= 0 || pixels.length % pixelCount !== 0) {
      throw new Error('unexpected-decoder-result');
    }
    const channels = pixels.length / pixelCount;
    if (![1, 3, 4].includes(channels)) {
      throw new Error('unsupported-components');
    }

    return { pixels: new Uint8Array(pixels), channels };
  } finally {
    if (pointer) {
      decoder._free(pointer);
    }
  }
}

function findJpxImages(bytes, imageMode, diagnostics) {
  const source = binaryString(bytes);
  const objects = new Map();
  const objectPattern = /(\d+)\s+(\d+)\s+obj\b([\s\S]*?)\bendobj/g;
  for (const match of source.matchAll(objectPattern)) {
    const entry = { object: match[1], body: match[3] };
    objects.set(entry.object, entry);
    objects.set(String(Number(entry.object)), entry);
  }

  const images = [];
  const seen = new Set();
  for (const entry of objects.values()) {
    if (seen.has(entry)) {
      continue;
    }
    seen.add(entry);
    const filters = pdfFilterNames(entry.body);
    if (filters.length !== 1 || filters[0] !== 'JPXDecode' || !/\/Subtype\s*\/Image\b/.test(entry.body)) {
      continue;
    }
    const width = pdfInteger(entry.body, 'Width');
    const height = pdfInteger(entry.body, 'Height');
    const imageMask = /\/ImageMask\s+true\b/i.test(entry.body);
    const hasExternalMask = /\/(?:SMask|Mask)\b/.test(entry.body);
    const hasDecodeTransform = /\/Decode\s*\[/.test(entry.body);
    const imageBytes = pdfStreamBytes(entry.body);
    if (!imageBytes || imageBytes.length > MAX_IMAGE_BYTES || !validImageDimensions(width, height)) {
      diagnostics.push(`pdf-jpx-raster-skipped:${entry.object}:stream`);
      continue;
    }
    if (imageMask || hasExternalMask || hasDecodeTransform) {
      diagnostics.push(`pdf-jpx-raster-skipped:${entry.object}:unsupported-pdf-image-state`);
      continue;
    }
    const importance = pdfImageImportance(width, height, imageBytes.length, imageMask);
    if (imageMode === 'important' && importance !== 'important') {
      continue;
    }

    // The PDF dictionary is untrusted. OpenJPEG allocates for dimensions from
    // the embedded JP2/J2K header, so verify that header before decoding and
    // bind it to the image XObject that will receive the raster.
    const intrinsicDimensions = jpxIntrinsicDimensions(imageBytes);
    if (!intrinsicDimensions || intrinsicDimensions.width !== width || intrinsicDimensions.height !== height) {
      diagnostics.push(`pdf-jpx-raster-skipped:${entry.object}:dimensions`);
      continue;
    }
    const isJp2Container = hasJp2Signature(imageBytes);
    const isIndexed = hasPdfIndexedColorSpace(entry.body) || hasJp2Palette(imageBytes);
    const numComponents = pdfImageComponents(entry.body);
    // Raw codestreams rely on the PDF color space. Without a self-contained
    // JP2 palette we cannot faithfully expand Indexed data here, and CMYK
    // components must not be mislabeled as an RGBA PNG.
    if (hasDeviceCmykColorSpace(entry.body) || (!isJp2Container && (numComponents === 0 || isIndexed))) {
      diagnostics.push(`pdf-jpx-raster-skipped:${entry.object}:colorspace`);
      continue;
    }
    images.push({
      object: entry.object,
      width,
      height,
      bytes: imageBytes,
      importance,
      isJp2Container,
      isIndexed,
      numComponents,
      smaskInData: /\/SMaskInData\s+(?:[1-9]\d*)\b/.test(entry.body),
    });
  }

  return images;
}

function validImageDimensions(width, height) {
  if (!Number.isSafeInteger(width) || !Number.isSafeInteger(height) || width <= 0 || height <= 0) {
    return false;
  }
  const pixels = width * height;

  return Number.isSafeInteger(pixels) && pixels <= MAX_IMAGE_PIXELS;
}

function jpxIntrinsicDimensions(bytes) {
  return hasJp2Signature(bytes) ? jp2IntrinsicDimensions(bytes) : j2kIntrinsicDimensions(bytes);
}

function jp2IntrinsicDimensions(bytes) {
  let offset = 0;
  while (offset + 8 <= bytes.length) {
    const box = jpxBoxBounds(bytes, offset, bytes.length);
    if (!box) {
      return null;
    }
    if (box.type === 'jp2h') {
      let childOffset = box.dataStart;
      while (childOffset + 8 <= box.end) {
        const child = jpxBoxBounds(bytes, childOffset, box.end);
        if (!child) {
          return null;
        }
        if (child.type === 'ihdr') {
          if (child.dataStart + 14 > child.end) {
            return null;
          }
          const height = readUint32(bytes, child.dataStart);
          const width = readUint32(bytes, child.dataStart + 4);

          return validImageDimensions(width, height) ? { width, height } : null;
        }
        childOffset = child.end;
      }
      return null;
    }
    offset = box.end;
  }

  return null;
}

function j2kIntrinsicDimensions(bytes) {
  if (bytes.length < 42 || bytes[0] !== 0xff || bytes[1] !== 0x4f) {
    return null;
  }
  const maximumOffset = Math.min(bytes.length - 4, 1024);
  for (let offset = 2; offset <= maximumOffset; offset += 1) {
    if (bytes[offset] !== 0xff || bytes[offset + 1] !== 0x51) {
      continue;
    }
    const length = (bytes[offset + 2] << 8) | bytes[offset + 3];
    if (length < 38 || offset + 2 + length > bytes.length || offset + 22 > bytes.length) {
      return null;
    }
    const right = readUint32(bytes, offset + 6);
    const bottom = readUint32(bytes, offset + 10);
    const left = readUint32(bytes, offset + 14);
    const top = readUint32(bytes, offset + 18);
    const width = right - left;
    const height = bottom - top;

    return validImageDimensions(width, height) ? { width, height } : null;
  }

  return null;
}

function jpxBoxBounds(bytes, offset, end) {
  if (offset + 8 > end) {
    return null;
  }
  let length = readUint32(bytes, offset);
  const type = asciiAt(bytes, offset + 4, 4);
  let headerLength = 8;
  if (length === 1) {
    if (offset + 16 > end) {
      return null;
    }
    const high = readUint32(bytes, offset + 8);
    const low = readUint32(bytes, offset + 12);
    if (high !== 0 || low < 16) {
      return null;
    }
    length = low;
    headerLength = 16;
  } else if (length === 0) {
    length = end - offset;
  }
  if (length < headerLength || offset + length > end) {
    return null;
  }

  return { type, dataStart: offset + headerLength, end: offset + length };
}

function pdfFilterNames(body) {
  const match = body.match(/\/Filter\s*(\[[^\]]*\]|\/[A-Za-z0-9]+)/s);
  if (!match) {
    return [];
  }

  return [...match[1].matchAll(/\/([A-Za-z0-9]+)/g)].map((item) => item[1]);
}

function pdfInteger(body, name) {
  const match = body.match(new RegExp(`/${name}\\s+(\\d+)\\b`));
  return match ? Number(match[1]) : 0;
}

function pdfImageComponents(body) {
  if (/\/ColorSpace\s*\/DeviceGray\b/.test(body)) {
    return 1;
  }
  if (/\/ColorSpace\s*\/DeviceRGB\b/.test(body)) {
    return 3;
  }
  if (/\/ColorSpace\s*\/DeviceCMYK\b/.test(body)) {
    return 4;
  }
  if (hasPdfIndexedColorSpace(body)) {
    return 1;
  }

  return 0;
}

function hasPdfIndexedColorSpace(body) {
  return /\/ColorSpace\s*(?:\[\s*)?\/Indexed\b/.test(body);
}

function hasDeviceCmykColorSpace(body) {
  return /\/ColorSpace\s*\/DeviceCMYK\b/.test(body);
}

function pdfStreamBytes(body) {
  const streamIndex = body.indexOf('stream');
  if (streamIndex < 0) {
    return null;
  }
  let start = streamIndex + 6;
  if (body.slice(start, start + 2) === '\r\n') {
    start += 2;
  } else if (body[start] === '\r' || body[start] === '\n') {
    start += 1;
  }
  const end = body.lastIndexOf('endstream');
  if (end < start) {
    return null;
  }
  const stream = body.slice(start, end).replace(/(?:\r\n|\n|\r)$/, '');
  return bytesFromBinaryString(stream);
}

function pdfImageImportance(width, height, byteLength, imageMask) {
  if (imageMask) {
    return 'mask';
  }
  if (width < 16 || height < 16) {
    return 'tiny';
  }
  if (byteLength >= 8192 || width * height >= 10000 || (width >= 96 && height >= 96)) {
    return 'important';
  }

  return 'small';
}

function hasJp2Signature(bytes) {
  return bytes.length >= 12
    && bytes[0] === 0 && bytes[1] === 0 && bytes[2] === 0 && bytes[3] === 12
    && bytes[4] === 0x6a && bytes[5] === 0x50 && bytes[6] === 0x20 && bytes[7] === 0x20
    && bytes[8] === 0x0d && bytes[9] === 0x0a && bytes[10] === 0x87 && bytes[11] === 0x0a;
}

function hasJp2Palette(bytes) {
  if (!hasJp2Signature(bytes)) {
    return false;
  }
  let offset = 0;
  while (offset + 8 <= bytes.length) {
    let length = readUint32(bytes, offset);
    const type = asciiAt(bytes, offset + 4, 4);
    let headerLength = 8;
    if (length === 1) {
      if (offset + 16 > bytes.length) {
        return false;
      }
      const high = readUint32(bytes, offset + 8);
      const low = readUint32(bytes, offset + 12);
      if (high !== 0 || low < 16) {
        return false;
      }
      length = low;
      headerLength = 16;
    } else if (length === 0) {
      length = bytes.length - offset;
    }
    if (length < headerLength || offset + length > bytes.length) {
      return false;
    }
    if (type === 'jp2h' && hasPaletteBox(bytes, offset + headerLength, offset + length)) {
      return true;
    }
    offset += length;
  }

  return false;
}

function hasPaletteBox(bytes, start, end) {
  let offset = start;
  let palette = false;
  let mapping = false;
  while (offset + 8 <= end) {
    let length = readUint32(bytes, offset);
    const type = asciiAt(bytes, offset + 4, 4);
    let headerLength = 8;
    if (length === 1) {
      if (offset + 16 > end) {
        return false;
      }
      const high = readUint32(bytes, offset + 8);
      const low = readUint32(bytes, offset + 12);
      if (high !== 0 || low < 16) {
        return false;
      }
      length = low;
      headerLength = 16;
    } else if (length === 0) {
      length = end - offset;
    }
    if (length < headerLength || offset + length > end) {
      return false;
    }
    palette ||= type === 'pclr';
    mapping ||= type === 'cmap';
    if (palette && mapping) {
      return true;
    }
    offset += length;
  }

  return false;
}

/**
 * Encodes one-, three-, or four-channel decoded pixels as a lossless PNG.
 * The encoder intentionally stays local so the same module works in Node and
 * browser Playground without native image dependencies.
 */
export async function encodePng(width, height, pixels, channels) {
  if (!Number.isInteger(width) || !Number.isInteger(height) || width <= 0 || height <= 0
    || ![1, 3, 4].includes(channels) || width * channels > MAX_PNG_ROW_BYTES
    || pixels.length !== width * height * channels) {
    throw new Error('invalid-png-input');
  }
  const normalized = normalizePngPixels(pixels, channels, width * height);
  const scanlines = pngScanlines(normalized.pixels, width, height, normalized.channels);
  const compressed = await zlibDeflate(scanlines);
  const ihdr = new Uint8Array(13);
  writeUint32(ihdr, 0, width);
  writeUint32(ihdr, 4, height);
  ihdr[8] = 8;
  ihdr[9] = normalized.channels === 1 ? 0 : normalized.channels === 3 ? 2 : 6;

  return concatBytes([
    PNG_SIGNATURE,
    pngChunk('IHDR', ihdr),
    pngChunk('IDAT', compressed),
    pngChunk('IEND', new Uint8Array()),
  ]);
}

/**
 * Retains the compact palette representation of indexed JP2 images when the
 * decoded result is an opaque RGB image with no more than 256 distinct
 * colors. The resulting PNG remains byte-for-byte lossless after expansion;
 * images that do not meet those conservative conditions use the normal PNG
 * encoder instead.
 */
async function encodeJpxPng(image, decoded) {
  if (image.isIndexed) {
    const indexed = await encodeIndexedPng(image.width, image.height, decoded.pixels, decoded.channels);
    if (indexed) {
      return indexed;
    }
  }

  return encodePng(image.width, image.height, decoded.pixels, decoded.channels);
}

/**
 * @returns {Promise<Uint8Array|null>} a color-type 3 PNG, or null when the
 * decoded pixels cannot be represented as an opaque <=256-color palette.
 */
async function encodeIndexedPng(width, height, pixels, channels) {
  if (!Number.isInteger(width) || !Number.isInteger(height) || width <= 0 || height <= 0
    || ![3, 4].includes(channels) || width > MAX_PNG_ROW_BYTES
    || pixels.length !== width * height * channels) {
    return null;
  }
  const indexed = indexedPngPixels(pixels, channels, width * height);
  if (!indexed) {
    return null;
  }
  const scanlines = pngScanlines(indexed.indices, width, height, 1);
  const compressed = await zlibDeflate(scanlines);
  const ihdr = new Uint8Array(13);
  writeUint32(ihdr, 0, width);
  writeUint32(ihdr, 4, height);
  ihdr[8] = 8;
  ihdr[9] = 3;

  return concatBytes([
    PNG_SIGNATURE,
    pngChunk('IHDR', ihdr),
    pngChunk('PLTE', indexed.palette),
    pngChunk('IDAT', compressed),
    pngChunk('IEND', new Uint8Array()),
  ]);
}

function indexedPngPixels(pixels, channels, pixelCount) {
  const indices = new Uint8Array(pixelCount);
  const palette = [];
  const paletteIndexes = new Map();
  let source = 0;
  for (let pixel = 0; pixel < pixelCount; pixel += 1) {
    const red = pixels[source++];
    const green = pixels[source++];
    const blue = pixels[source++];
    if (channels === 4 && pixels[source++] !== 255) {
      return null;
    }
    const color = (red << 16) | (green << 8) | blue;
    let paletteIndex = paletteIndexes.get(color);
    if (paletteIndex === undefined) {
      if (paletteIndexes.size === 256) {
        return null;
      }
      paletteIndex = paletteIndexes.size;
      paletteIndexes.set(color, paletteIndex);
      palette.push(red, green, blue);
    }
    indices[pixel] = paletteIndex;
  }

  return { indices, palette: new Uint8Array(palette) };
}

function normalizePngPixels(pixels, channels, pixelCount) {
  if (channels !== 4) {
    return { pixels, channels };
  }
  for (let offset = 3; offset < pixels.length; offset += 4) {
    if (pixels[offset] !== 255) {
      return { pixels, channels };
    }
  }
  const rgb = new Uint8Array(pixelCount * 3);
  for (let source = 0, target = 0; source < pixels.length; source += 4) {
    rgb[target++] = pixels[source];
    rgb[target++] = pixels[source + 1];
    rgb[target++] = pixels[source + 2];
  }

  return { pixels: rgb, channels: 3 };
}

function pngScanlines(pixels, width, height, channels) {
  const rowBytes = width * channels;
  const scanlines = new Uint8Array((rowBytes + 1) * height);
  const candidate = new Uint8Array(rowBytes);
  const bestCandidate = new Uint8Array(rowBytes);
  for (let row = 0; row < height; row += 1) {
    const sourceOffset = row * rowBytes;
    let bestFilter = 0;
    let bestScore = Number.POSITIVE_INFINITY;
    for (let filter = 0; filter <= 4; filter += 1) {
      let score = 0;
      for (let column = 0; column < rowBytes; column += 1) {
        const raw = pixels[sourceOffset + column];
        const left = column >= channels ? pixels[sourceOffset + column - channels] : 0;
        const up = row > 0 ? pixels[sourceOffset - rowBytes + column] : 0;
        const upperLeft = row > 0 && column >= channels ? pixels[sourceOffset - rowBytes + column - channels] : 0;
        let predictor = 0;
        if (filter === 1) {
          predictor = left;
        } else if (filter === 2) {
          predictor = up;
        } else if (filter === 3) {
          predictor = (left + up) >> 1;
        } else if (filter === 4) {
          predictor = paethPredictor(left, up, upperLeft);
        }
        const value = (raw - predictor) & 0xff;
        candidate[column] = value;
        score += value < 128 ? value : 256 - value;
      }
      if (score < bestScore) {
        bestScore = score;
        bestFilter = filter;
        bestCandidate.set(candidate);
      }
    }
    const destination = row * (rowBytes + 1);
    scanlines[destination] = bestFilter;
    scanlines.set(bestCandidate, destination + 1);
  }

  return scanlines;
}

function paethPredictor(left, up, upperLeft) {
  const estimate = left + up - upperLeft;
  const leftDistance = Math.abs(estimate - left);
  const upDistance = Math.abs(estimate - up);
  const upperLeftDistance = Math.abs(estimate - upperLeft);
  if (leftDistance <= upDistance && leftDistance <= upperLeftDistance) {
    return left;
  }
  if (upDistance <= upperLeftDistance) {
    return up;
  }

  return upperLeft;
}

async function zlibDeflate(input) {
  if (typeof CompressionStream === 'function' && typeof Response === 'function') {
    try {
      const stream = new CompressionStream('deflate');
      // Start consuming before writing: Node's Web Streams implementation can
      // otherwise apply backpressure to a complete in-memory image and leave
      // a top-level await unsettled.
      const result = new Response(stream.readable).arrayBuffer();
      const writer = stream.writable.getWriter();
      await writer.write(input);
      await writer.close();
      return new Uint8Array(await result);
    } catch {
      // The pure-JS fallback keeps older browser contexts usable.
    }
  }

  return zlibFixedDeflate(input);
}

function normalizeImageMode(mode) {
  const value = String(mode || 'important').toLowerCase().replaceAll('_', '-').replaceAll(' ', '-');
  if (['none', 'no', 'off', 'false', '0', 'no-images', 'without-images'].includes(value)) {
    return 'none';
  }
  if (['important', 'auto', 'selected', 'significant'].includes(value)) {
    return 'important';
  }

  return 'all';
}

function binaryString(bytes) {
  const chunks = [];
  const chunkSize = 0x4000;
  for (let offset = 0; offset < bytes.length; offset += chunkSize) {
    chunks.push(String.fromCharCode(...bytes.subarray(offset, Math.min(offset + chunkSize, bytes.length))));
  }

  return chunks.join('');
}

function bytesFromBinaryString(text) {
  const bytes = new Uint8Array(text.length);
  for (let index = 0; index < text.length; index += 1) {
    bytes[index] = text.charCodeAt(index);
  }

  return bytes;
}

function containsAscii(bytes, value) {
  const needle = [...value].map((char) => char.charCodeAt(0));
  outer: for (let offset = 0; offset <= bytes.length - needle.length; offset += 1) {
    for (let index = 0; index < needle.length; index += 1) {
      if (bytes[offset + index] !== needle[index]) {
        continue outer;
      }
    }
    return true;
  }

  return false;
}

function asciiAt(bytes, offset, length) {
  if (offset < 0 || offset + length > bytes.length) {
    return '';
  }

  return String.fromCharCode(...bytes.subarray(offset, offset + length));
}

function errorToken(error) {
  const message = error instanceof Error ? error.message : String(error);
  return message.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48) || 'decode';
}

function yieldToBrowser() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function zlibFixedDeflate(input) {
  const writer = new BitWriter(Math.max(1024, Math.min(input.length + 1024, 65536)));
  writer.writeBits(3, 3);
  const previous = new Int32Array(65536);
  previous.fill(-1);
  let offset = 0;
  while (offset < input.length) {
    let matchLength = 0;
    let distance = 0;
    if (offset + 2 < input.length) {
      const hash = hashThree(input, offset);
      const candidate = previous[hash];
      if (candidate >= 0 && offset - candidate <= 32768
        && input[candidate] === input[offset]
        && input[candidate + 1] === input[offset + 1]
        && input[candidate + 2] === input[offset + 2]) {
        const maxLength = Math.min(258, input.length - offset);
        while (matchLength < maxLength && input[candidate + matchLength] === input[offset + matchLength]) {
          matchLength += 1;
        }
        distance = offset - candidate;
      }
      if (matchLength < 3) {
        matchLength = 0;
      }
    }
    if (matchLength > 0) {
      writeLengthDistance(writer, matchLength, distance);
      for (let index = 0; index < matchLength; index += 1) {
        if (offset + index + 2 < input.length) {
          previous[hashThree(input, offset + index)] = offset + index;
        }
      }
      offset += matchLength;
      continue;
    }
    writeFixedLiteral(writer, input[offset]);
    if (offset + 2 < input.length) {
      previous[hashThree(input, offset)] = offset;
    }
    offset += 1;
  }
  writeFixedLiteral(writer, 256);
  const deflate = writer.finish();
  const result = new Uint8Array(2 + deflate.length + 4);
  result[0] = 0x78;
  result[1] = 0x01;
  result.set(deflate, 2);
  writeUint32(result, result.length - 4, adler32(input));

  return result;
}

function writeLengthDistance(writer, length, distance) {
  let lengthIndex = LENGTH_BASE.length - 1;
  for (let index = 0; index < LENGTH_BASE.length - 1; index += 1) {
    if (length < LENGTH_BASE[index + 1]) {
      lengthIndex = index;
      break;
    }
  }
  writeFixedLiteral(writer, 257 + lengthIndex);
  writer.writeBits(length - LENGTH_BASE[lengthIndex], LENGTH_EXTRA[lengthIndex]);

  let distanceIndex = DISTANCE_BASE.length - 1;
  for (let index = 0; index < DISTANCE_BASE.length - 1; index += 1) {
    if (distance < DISTANCE_BASE[index + 1]) {
      distanceIndex = index;
      break;
    }
  }
  writer.writeBits(reverseBits(distanceIndex, 5), 5);
  writer.writeBits(distance - DISTANCE_BASE[distanceIndex], DISTANCE_EXTRA[distanceIndex]);
}

function writeFixedLiteral(writer, value) {
  if (value <= 143) {
    writer.writeBits(reverseBits(0x30 + value, 8), 8);
  } else if (value <= 255) {
    writer.writeBits(reverseBits(0x190 + value - 144, 9), 9);
  } else if (value <= 279) {
    writer.writeBits(reverseBits(value - 256, 7), 7);
  } else {
    writer.writeBits(reverseBits(0xc0 + value - 280, 8), 8);
  }
}

function hashThree(bytes, offset) {
  return ((bytes[offset] * 251) ^ (bytes[offset + 1] * 17) ^ bytes[offset + 2]) & 0xffff;
}

function reverseBits(value, length) {
  let reversed = 0;
  for (let index = 0; index < length; index += 1) {
    reversed = (reversed << 1) | (value & 1);
    value >>>= 1;
  }

  return reversed;
}

class BitWriter {
  constructor(capacity) {
    this.bytes = new Uint8Array(capacity);
    this.offset = 0;
    this.current = 0;
    this.bits = 0;
  }

  writeBits(value, count) {
    while (count > 0) {
      const take = Math.min(8 - this.bits, count);
      this.current |= (value & ((1 << take) - 1)) << this.bits;
      this.bits += take;
      value >>>= take;
      count -= take;
      if (this.bits === 8) {
        this.writeByte(this.current);
        this.current = 0;
        this.bits = 0;
      }
    }
  }

  writeByte(value) {
    if (this.offset >= this.bytes.length) {
      const grown = new Uint8Array(this.bytes.length * 2);
      grown.set(this.bytes);
      this.bytes = grown;
    }
    this.bytes[this.offset] = value;
    this.offset += 1;
  }

  finish() {
    if (this.bits > 0) {
      this.writeByte(this.current);
    }

    return this.bytes.slice(0, this.offset);
  }
}

function adler32(bytes) {
  let a = 1;
  let b = 0;
  for (let offset = 0; offset < bytes.length; offset += 1) {
    a += bytes[offset];
    b += a;
    if ((offset & 0x1fff) === 0x1fff) {
      a %= 65521;
      b %= 65521;
    }
  }

  return (((b % 65521) << 16) | (a % 65521)) >>> 0;
}

function pngChunk(type, data) {
  const typeBytes = new Uint8Array([...type].map((char) => char.charCodeAt(0)));
  const chunk = new Uint8Array(data.length + 12);
  writeUint32(chunk, 0, data.length);
  chunk.set(typeBytes, 4);
  chunk.set(data, 8);
  writeUint32(chunk, chunk.length - 4, crc32(chunk.subarray(4, chunk.length - 4)));

  return chunk;
}

function concatBytes(parts) {
  const length = parts.reduce((total, part) => total + part.length, 0);
  const result = new Uint8Array(length);
  let offset = 0;
  for (const part of parts) {
    result.set(part, offset);
    offset += part.length;
  }

  return result;
}

function readUint32(bytes, offset) {
  return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
}

function writeUint32(bytes, offset, value) {
  bytes[offset] = (value >>> 24) & 0xff;
  bytes[offset + 1] = (value >>> 16) & 0xff;
  bytes[offset + 2] = (value >>> 8) & 0xff;
  bytes[offset + 3] = value & 0xff;
}

let crcTable;

function crc32(bytes) {
  if (!crcTable) {
    crcTable = new Uint32Array(256);
    for (let index = 0; index < 256; index += 1) {
      let value = index;
      for (let bit = 0; bit < 8; bit += 1) {
        value = (value >>> 1) ^ (value & 1 ? 0xedb88320 : 0);
      }
      crcTable[index] = value >>> 0;
    }
  }
  let value = 0xffffffff;
  for (const byte of bytes) {
    value = crcTable[(value ^ byte) & 0xff] ^ (value >>> 8);
  }

  return (value ^ 0xffffffff) >>> 0;
}
