#!/usr/bin/env node

import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import OpenJPEG from '../pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.mjs';
import { decodePdfJpxRasters, encodePng } from '../pandoc-showcase/pdf-jpx-rasterizer.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const motographPath = path.join(root, 'pandoc-showcase/samples/pdf-archive-motograph-book-motograph-moving-picture-book.pdf');
const cdcPath = path.join(root, 'pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf');
const wasmBinary = fs.readFileSync(path.join(root, 'pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.wasm'));
const decoderFactory = () => OpenJPEG({ wasmBinary, print: () => {}, printErr: () => {} });

const motograph = await decodePdfJpxRasters(new Uint8Array(fs.readFileSync(motographPath)), {
  imageMode: 'important',
  maxImages: 1,
  decoderFactory,
});
assert.equal(motograph.rasters.length, 1, 'Expected the anchored Google logo JPX object to rasterize.');
const logo = motograph.rasters[0];
assert.equal(logo.object, '00003');
assert.equal(logo.mimeType, 'image/png');
assert.equal(logo.width, 1800);
assert.equal(logo.height, 750);
assert.equal(logo.encodedByteLength, 28923);
assert.equal(logo.isIndexed, false);
assert.deepEqual([...logo.bytes.subarray(0, 8)], [137, 80, 78, 71, 13, 10, 26, 10]);
assert.equal(readUint32(logo.bytes, 16), logo.width);
assert.equal(readUint32(logo.bytes, 20), logo.height);
assert.equal(logo.bytes[25], 2, 'An opaque JPX should emit a compact RGB PNG rather than an RGBA PNG.');
assert.equal(inflatePngIdat(logo.bytes).length, (logo.width * 3 + 1) * logo.height);
assert.ok(motograph.diagnostics.includes('pdf-jpx-raster-loaded:00003:important'));

const cdc = await decodePdfJpxRasters(new Uint8Array(fs.readFileSync(cdcPath)), {
  imageMode: 'all',
  decoderFactory,
});
for (const expected of [
  { object: '227', width: 146, height: 68 },
  { object: '241', width: 117, height: 369 },
]) {
  const raster = cdc.rasters.find((candidate) => candidate.object === expected.object);
  assert.ok(raster, `Expected CDC JPX object ${expected.object} to rasterize.`);
  assert.equal(raster.isIndexed, true, `Object ${expected.object} should preserve its JP2 palette classification.`);
  assert.equal(raster.mimeType, 'image/png');
  assert.equal(raster.width, expected.width);
  assert.equal(raster.height, expected.height);
  assert.equal(raster.bytes[25], 3, `Object ${expected.object} should emit a palette PNG.`);
  assert.ok(raster.bytes.length <= raster.encodedByteLength,
    `The lossless palette PNG for object ${expected.object} should not exceed its JPX source.`);

  const png = decodeIndexedPngRgb(raster.bytes);
  assert.equal(png.width, expected.width);
  assert.equal(png.height, expected.height);
  assert.ok(png.paletteEntries <= 256, `Object ${expected.object} must remain a valid PNG palette.`);
  const decoded = await decodePdfJpxObject(cdcPath, expected.object);
  const decodedRgb = opaqueRgbPixels(decoded, expected.object);
  assert.equal(Buffer.compare(Buffer.from(png.pixels), Buffer.from(decodedRgb)), 0,
    `The indexed PNG for object ${expected.object} must expand exactly to its decoded JP2 pixels.`);
}

const disabled = await decodePdfJpxRasters(new Uint8Array(fs.readFileSync(motographPath)), { imageMode: 'none' });
assert.equal(disabled.rasters.length, 0);

const unavailableDecoder = await decodePdfJpxRasters(new Uint8Array(fs.readFileSync(motographPath)), {
  imageMode: 'important',
  decoderFactory: () => {
    throw new Error('missing server decoder');
  },
});
assert.equal(unavailableDecoder.rasters.length, 0, 'A missing JPEG 2000 decoder must not abort PDF import preparation.');
assert.ok(unavailableDecoder.diagnostics.includes('pdf-jpx-raster-unavailable:missing-server-decoder'));

const mismatchedDimensions = await decodePdfJpxRasters(jpxDimensionMismatchFixture(10, 10, 11, 10), {
  imageMode: 'all',
  decoderFactory: () => {
    throw new Error('The decoder must not run for mismatched JP2 dimensions.');
  },
});
assert.equal(mismatchedDimensions.rasters.length, 0);
assert.ok(mismatchedDimensions.diagnostics.includes('pdf-jpx-raster-skipped:17:dimensions'),
  'The embedded JP2 dimensions must be checked before invoking OpenJPEG.');
await assert.rejects(
  () => encodePng(4 * 1024 * 1024 + 1, 1, new Uint8Array(), 1),
  /invalid-png-input/,
  'An extremely wide image must be rejected before allocating PNG filter scratch space.',
);

const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'port-libs-jpx-raster-'));
try {
  const manifestPath = path.join(temporaryDirectory, 'rasters.json');
  const cli = spawnSync(process.execPath, [
    path.join(root, 'tools/decode-pdf-jpx-media.mjs'),
    '--input', motographPath,
    '--output', manifestPath,
    '--image-mode', 'important',
  ], { cwd: root, encoding: 'utf8' });
  assert.equal(cli.status, 0, cli.stderr || cli.stdout);
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  assert.equal(manifest.rasters[0].object, '00003');
  assert.equal(manifest.rasters[0].mimeType, 'image/png');
  assert.equal(manifest.rasters[0].encodedByteLength, 28923);
  assert.equal(manifest.rasters[0].isIndexed, false);
  assert.ok(typeof manifest.rasters[0].bytes === 'string' && manifest.rasters[0].bytes.length > 0);

  const combinedManifestPath = path.join(temporaryDirectory, 'combined-rasters.json');
  const combined = spawnSync(process.execPath, [
    path.join(root, 'tools/decode-pdf-raster-media.mjs'),
    '--input', motographPath,
    '--output', combinedManifestPath,
    '--image-mode', 'important',
  ], { cwd: root, encoding: 'utf8' });
  assert.equal(combined.status, 0, combined.stderr || combined.stdout);
  const combinedManifest = JSON.parse(fs.readFileSync(combinedManifestPath, 'utf8'));
  const combinedLogo = combinedManifest.rasters.find((raster) => raster.object === '00003');
  assert.ok(combinedLogo, 'Expected the combined PDF raster provider to retain the Motograph logo.');
  assert.equal(combinedLogo.width, 1800);
  assert.equal(combinedLogo.height, 750);
  const combinedLogoBytes = Buffer.from(combinedLogo.bytes, 'base64');
  const avifencAvailable = spawnSync('avifenc', ['--version'], { encoding: 'utf8' }).status === 0;
  if (avifencAvailable) {
    assert.equal(combinedLogo.mimeType, 'image/avif');
    assert.ok(combinedLogoBytes.length <= 28923, 'The AVIF wordmark should not exceed the original JPX byte budget.');
  } else {
    assert.equal(combinedLogo.mimeType, 'image/png');
  }
} finally {
  fs.rmSync(temporaryDirectory, { recursive: true, force: true });
}

console.log(`JPX rasterizer: ${logo.width}x${logo.height} PNG (${logo.bytes.length} bytes), CDC palette PNGs verified lossless.`);

async function decodePdfJpxObject(pdfPath, object) {
  const source = fs.readFileSync(pdfPath).toString('latin1');
  const match = source.match(new RegExp(`(?:^|[\\r\\n])${object}\\s+\\d+\\s+obj\\b([\\s\\S]*?)\\bendobj`));
  assert.ok(match, `Expected PDF object ${object}.`);
  const body = match[1];
  const width = pdfInteger(body, 'Width');
  const height = pdfInteger(body, 'Height');
  const bytes = pdfStreamBytes(body);
  assert.ok(width > 0 && height > 0 && bytes.length > 0, `Expected image data for PDF object ${object}.`);

  const decoder = await decoderFactory();
  let pointer = 0;
  try {
    pointer = decoder._malloc(bytes.length);
    assert.ok(pointer, `Expected OpenJPEG memory for PDF object ${object}.`);
    decoder.writeArrayToMemory(bytes, pointer);
    decoder.imageData = undefined;
    decoder.errorMessages = undefined;
    assert.equal(decoder._jp2_decode(pointer, bytes.length, 0, false, false, 0), 0,
      decoder.errorMessages || `Expected OpenJPEG to decode PDF object ${object}.`);
    const pixels = new Uint8Array(decoder.imageData);
    assert.equal(pixels.length % (width * height), 0, `Expected aligned decoded pixels for PDF object ${object}.`);
    return { width, height, channels: pixels.length / (width * height), pixels };
  } finally {
    if (pointer) {
      decoder._free(pointer);
    }
  }
}

function pdfInteger(body, name) {
  const match = body.match(new RegExp(`/${name}\\s+(\\d+)\\b`));
  return match ? Number(match[1]) : 0;
}

function pdfStreamBytes(body) {
  const streamIndex = body.indexOf('stream');
  assert.ok(streamIndex >= 0, 'Expected a PDF stream.');
  let start = streamIndex + 6;
  if (body.slice(start, start + 2) === '\r\n') {
    start += 2;
  } else if (body[start] === '\r' || body[start] === '\n') {
    start += 1;
  }
  const end = body.lastIndexOf('endstream');
  assert.ok(end >= start, 'Expected the end of a PDF stream.');
  const stream = body.slice(start, end).replace(/(?:\r\n|\n|\r)$/, '');
  const bytes = new Uint8Array(stream.length);
  for (let index = 0; index < stream.length; index += 1) {
    bytes[index] = stream.charCodeAt(index);
  }
  return bytes;
}

function opaqueRgbPixels(decoded, object) {
  if (decoded.channels === 3) {
    return decoded.pixels;
  }
  assert.equal(decoded.channels, 4, `Fixture object ${object} should decode as RGB or RGBA.`);
  const rgb = new Uint8Array((decoded.pixels.length / 4) * 3);
  for (let source = 0, target = 0; source < decoded.pixels.length; source += 4) {
    assert.equal(decoded.pixels[source + 3], 255, `Fixture object ${object} should be opaque.`);
    rgb[target++] = decoded.pixels[source];
    rgb[target++] = decoded.pixels[source + 1];
    rgb[target++] = decoded.pixels[source + 2];
  }
  return rgb;
}

function decodeIndexedPngRgb(bytes) {
  const chunks = pngChunks(bytes);
  const header = chunks.find((chunk) => chunk.type === 'IHDR');
  const palette = chunks.find((chunk) => chunk.type === 'PLTE');
  assert.ok(header && header.data.length === 13, 'Expected a PNG header.');
  assert.ok(palette && palette.data.length >= 3 && palette.data.length <= 768 && palette.data.length % 3 === 0,
    'Expected a bounded PNG palette.');
  assert.equal(header.data[8], 8, 'Expected eight-bit palette entries.');
  assert.equal(header.data[9], 3, 'Expected indexed PNG color type.');
  const width = readUint32(header.data, 0);
  const height = readUint32(header.data, 4);
  const filtered = inflatePngIdat(bytes);
  assert.equal(filtered.length, (width + 1) * height, 'Expected one eight-bit index per PNG pixel.');

  const indices = new Uint8Array(width * height);
  for (let row = 0; row < height; row += 1) {
    const rowOffset = row * (width + 1);
    const filter = filtered[rowOffset];
    assert.ok(filter <= 4, 'Expected a standard PNG row filter.');
    for (let column = 0; column < width; column += 1) {
      const offset = row * width + column;
      const encoded = filtered[rowOffset + 1 + column];
      const left = column > 0 ? indices[offset - 1] : 0;
      const up = row > 0 ? indices[offset - width] : 0;
      const upperLeft = row > 0 && column > 0 ? indices[offset - width - 1] : 0;
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
      indices[offset] = (encoded + predictor) & 0xff;
    }
  }

  const pixels = new Uint8Array(indices.length * 3);
  for (let index = 0; index < indices.length; index += 1) {
    const paletteOffset = indices[index] * 3;
    assert.ok(paletteOffset + 2 < palette.data.length, 'Expected every PNG index to resolve in its palette.');
    const pixelOffset = index * 3;
    pixels[pixelOffset] = palette.data[paletteOffset];
    pixels[pixelOffset + 1] = palette.data[paletteOffset + 1];
    pixels[pixelOffset + 2] = palette.data[paletteOffset + 2];
  }

  return { width, height, paletteEntries: palette.data.length / 3, pixels };
}

function pngChunks(bytes) {
  assert.deepEqual([...bytes.subarray(0, 8)], [137, 80, 78, 71, 13, 10, 26, 10], 'Expected a PNG signature.');
  const chunks = [];
  let offset = 8;
  while (offset + 12 <= bytes.length) {
    const length = readUint32(bytes, offset);
    const dataStart = offset + 8;
    assert.ok(dataStart + length + 4 <= bytes.length, 'Expected a complete PNG chunk.');
    const type = String.fromCharCode(...bytes.subarray(offset + 4, offset + 8));
    chunks.push({ type, data: bytes.subarray(dataStart, dataStart + length) });
    offset = dataStart + length + 4;
    if (type === 'IEND') {
      break;
    }
  }
  return chunks;
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

function readUint32(bytes, offset) {
  return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
}

function jpxDimensionMismatchFixture(pdfWidth, pdfHeight, jpxWidth, jpxHeight) {
  const jp2 = minimalJp2Header(jpxWidth, jpxHeight);
  const prefix = Buffer.from('%PDF-1.4\n17 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + pdfWidth
    + ' /Height ' + pdfHeight + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /JPXDecode /Length ' + jp2.length
    + ' >>\nstream\n', 'latin1');
  const suffix = Buffer.from('\nendstream\nendobj\n%%EOF\n', 'latin1');

  return new Uint8Array(Buffer.concat([prefix, Buffer.from(jp2), suffix]));
}

function minimalJp2Header(width, height) {
  const bytes = new Uint8Array(42);
  writeUint32(bytes, 0, 12);
  bytes.set(Buffer.from('jP  ', 'ascii'), 4);
  bytes.set([0x0d, 0x0a, 0x87, 0x0a], 8);
  writeUint32(bytes, 12, 30);
  bytes.set(Buffer.from('jp2h', 'ascii'), 16);
  writeUint32(bytes, 20, 22);
  bytes.set(Buffer.from('ihdr', 'ascii'), 24);
  writeUint32(bytes, 28, height);
  writeUint32(bytes, 32, width);
  bytes.set([0, 3, 7, 0, 0, 0], 36);

  return bytes;
}

function writeUint32(bytes, offset, value) {
  bytes[offset] = (value >>> 24) & 0xff;
  bytes[offset + 1] = (value >>> 16) & 0xff;
  bytes[offset + 2] = (value >>> 8) & 0xff;
  bytes[offset + 3] = value & 0xff;
}

function inflatePngIdat(bytes) {
  let offset = 8;
  const parts = [];
  while (offset + 12 <= bytes.length) {
    const length = readUint32(bytes, offset);
    const type = String.fromCharCode(...bytes.subarray(offset + 4, offset + 8));
    const dataStart = offset + 8;
    if (type === 'IDAT') {
      parts.push(bytes.subarray(dataStart, dataStart + length));
    }
    offset = dataStart + length + 4;
    if (type === 'IEND') {
      break;
    }
  }

  return zlib.inflateSync(Buffer.concat(parts.map((part) => Buffer.from(part))));
}
