import JBig2 from './vendor/pdfjs-jbig2/jbig2.mjs';

// Keep the browser fallback below the Playground per-file budget. It avoids
// creating a second full-document string alongside WordPress on constrained
// Safari/iOS heaps; the PHP path still reports an explicit media review when
// a larger file cannot use this optional raster provider.
const MAX_PDF_BYTES = 24 * 1024 * 1024;
const MAX_IMAGE_BYTES = 32 * 1024 * 1024;
const MAX_IMAGE_PIXELS = 48_000_000;
const MAX_IMAGES = 96;
const MAX_TOTAL_PNG_BYTES = 24 * 1024 * 1024;
const PNG_SIGNATURE = new Uint8Array([137, 80, 78, 71, 13, 10, 26, 10]);
const LENGTH_BASE = [3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31, 35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258];
const LENGTH_EXTRA = [0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0];
const DISTANCE_BASE = [1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193, 257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145, 8193, 12289, 16385, 24577];
const DISTANCE_EXTRA = [0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13];

/**
 * Decode direct, uncompressed PDF JBIG2 image XObjects into compact 1-bit PNGs.
 * It intentionally mirrors the core extractor's bounded direct-object scope;
 * object streams and general PDF rendering remain outside this media fallback.
 */
export async function decodePdfJbig2Rasters(input, options = {}) {
  const imageMode = normalizeImageMode(options.imageMode);
  const source = input instanceof Uint8Array ? input : new Uint8Array(input);
  const diagnostics = [];
  if (imageMode === 'none' || source.length === 0 || !containsAscii(source, '/JBIG2Decode')) {
    return { rasters: [], diagnostics };
  }
  if (source.length > MAX_PDF_BYTES) {
    return { rasters: [], diagnostics: ['pdf-jbig2-raster-scan-skipped:too-large'] };
  }
  if (typeof WebAssembly === 'undefined' && !options.decoderFactory) {
    return { rasters: [], diagnostics: ['pdf-jbig2-raster-unavailable:wasm'] };
  }

  const candidates = findJbig2Images(source, imageMode, diagnostics);
  if (candidates.length === 0) {
    return { rasters: [], diagnostics };
  }

  const requestedLimit = options.maxImages === undefined ? MAX_IMAGES : Number(options.maxImages);
  const limit = Math.max(0, Math.min(Number.isFinite(requestedLimit) ? requestedLimit : MAX_IMAGES, MAX_IMAGES));
  const selected = candidates.slice(0, limit);
  if (candidates.length > selected.length) {
    diagnostics.push('pdf-jbig2-raster-limit');
  }

  let decoder;
  try {
    decoder = await (options.decoderFactory || (() => JBig2()))();
  } catch (error) {
    diagnostics.push(`pdf-jbig2-raster-unavailable:${errorToken(error)}`);
    return { rasters: [], diagnostics };
  }

  const rasters = [];
  let totalPngBytes = 0;
  for (let index = 0; index < selected.length; index += 1) {
    const image = selected[index];
    options.onProgress?.({ completed: index, total: selected.length, object: image.object });
    try {
      const packed = decodeJbig2Bitmap(decoder, image);
      const png = encodeOneBitPng(image.width, image.height, packed);
      if (totalPngBytes + png.length > (options.maxPngBytes || MAX_TOTAL_PNG_BYTES)) {
        diagnostics.push('pdf-jbig2-raster-total-limit');
        break;
      }
      totalPngBytes += png.length;
      rasters.push({
        object: image.object,
        bytes: png,
        mimeType: 'image/png',
        width: image.width,
        height: image.height,
      });
      diagnostics.push(`pdf-jbig2-raster-loaded:${image.object}:${image.importance}`);
    } catch (error) {
      diagnostics.push(`pdf-jbig2-raster-skipped:${image.object}:${errorToken(error)}`);
    }
    options.onProgress?.({ completed: index + 1, total: selected.length, object: image.object });
    await yieldToBrowser();
  }

  return { rasters, diagnostics };
}

export function encodeOneBitPng(width, height, packedPixels) {
  const rowBytes = Math.ceil(width / 8);
  if (!Number.isInteger(width) || !Number.isInteger(height) || width <= 0 || height <= 0
    || packedPixels.length !== rowBytes * height) {
    throw new Error('invalid-bitmap-dimensions');
  }
  const scanlines = new Uint8Array((rowBytes + 1) * height);
  for (let row = 0; row < height; row += 1) {
    scanlines.set(packedPixels.subarray(row * rowBytes, (row + 1) * rowBytes), row * (rowBytes + 1) + 1);
  }
  const ihdr = new Uint8Array(13);
  writeUint32(ihdr, 0, width);
  writeUint32(ihdr, 4, height);
  ihdr[8] = 1;
  ihdr[9] = 0;

  return concatBytes([
    PNG_SIGNATURE,
    pngChunk('IHDR', ihdr),
    pngChunk('IDAT', zlibFixedDeflate(scanlines)),
    pngChunk('IEND', new Uint8Array()),
  ]);
}

function findJbig2Images(bytes, imageMode, diagnostics) {
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
    if (!filters.includes('JBIG2Decode') || !/\/Subtype\s*\/Image\b/.test(entry.body)) {
      continue;
    }
    const width = pdfInteger(entry.body, 'Width');
    const height = pdfInteger(entry.body, 'Height');
    const imageMask = /\/ImageMask\s+true\b/i.test(entry.body);
    const bytesForImage = pdfStreamBytes(entry.body);
    if (!bytesForImage || bytesForImage.length > MAX_IMAGE_BYTES || !width || !height || width * height > MAX_IMAGE_PIXELS) {
      diagnostics.push(`pdf-jbig2-raster-skipped:${entry.object}:stream`);
      continue;
    }
    const importance = pdfImageImportance(width, height, bytesForImage.length, imageMask);
    if (imageMode === 'important' && importance !== 'important') {
      continue;
    }
    const globalsReference = entry.body.match(/\/JBIG2Globals\s+(\d+)\s+\d+\s+R/);
    const globals = globalsReference ? pdfStreamBytes(objects.get(String(Number(globalsReference[1])))?.body || '') : null;
    images.push({
      object: entry.object,
      width,
      height,
      bytes: bytesForImage,
      globals: globals || new Uint8Array(),
      importance,
    });
  }

  return images;
}

function decodeJbig2Bitmap(decoder, image) {
  let imagePointer = 0;
  let globalsPointer = 0;
  try {
    imagePointer = decoder._malloc(image.bytes.length);
    decoder.writeArrayToMemory(image.bytes, imagePointer);
    if (image.globals.length > 0) {
      globalsPointer = decoder._malloc(image.globals.length);
      decoder.writeArrayToMemory(image.globals, globalsPointer);
    }
    decoder._jbig2_decode(imagePointer, image.bytes.length, image.width, image.height, globalsPointer, image.globals.length);
    if (!decoder.imageData) {
      throw new Error('empty-decoder-result');
    }
    const bitmap = new Uint8Array(decoder.imageData);
    const expectedLength = Math.ceil(image.width / 8) * image.height;
    if (bitmap.length !== expectedLength) {
      throw new Error('unexpected-decoder-result');
    }

    return bitmap;
  } finally {
    decoder.imageData = null;
    if (imagePointer) {
      decoder._free(imagePointer);
    }
    if (globalsPointer) {
      decoder._free(globalsPointer);
    }
  }
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
