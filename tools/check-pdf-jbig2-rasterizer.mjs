#!/usr/bin/env node

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import JBig2 from '../pandoc-showcase/vendor/pdfjs-jbig2/jbig2.mjs';
import { decodePdfJbig2Rasters } from '../pandoc-showcase/pdf-jbig2-rasterizer.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = new Uint8Array(fs.readFileSync(path.join(
  root,
  'pandoc-showcase/samples/pdf-archive-motograph-book-motograph-moving-picture-book.pdf',
)));
const wasmBinary = fs.readFileSync(path.join(root, 'pandoc-showcase/vendor/pdfjs-jbig2/jbig2.wasm'));
const result = await decodePdfJbig2Rasters(source, {
  imageMode: 'important',
  maxImages: 2,
  decoderFactory: () => JBig2({ wasmBinary }),
});

assert.equal(result.rasters.length, 2, 'Expected the direct image and a JBIG2Globals-backed page image.');
const pageRaster = result.rasters.find((raster) => raster.width === 5512 && raster.height === 6796);
assert.ok(pageRaster, 'Expected a page-sized JBIG2 raster.');
assert.equal(pageRaster.mimeType, 'image/png');
assert.deepEqual([...pageRaster.bytes.subarray(0, 8)], [137, 80, 78, 71, 13, 10, 26, 10]);
assert.equal(readUint32(pageRaster.bytes, 16), pageRaster.width);
assert.equal(readUint32(pageRaster.bytes, 20), pageRaster.height);
const inflated = inflatePngIdat(pageRaster.bytes);
assert.equal(inflated.length, (Math.ceil(pageRaster.width / 8) + 1) * pageRaster.height);
assert.ok(result.diagnostics.some((diagnostic) => diagnostic.startsWith('pdf-jbig2-raster-loaded:')));

const disabled = await decodePdfJbig2Rasters(source, { imageMode: 'none' });
assert.equal(disabled.rasters.length, 0);

console.log(`JBIG2 rasterizer: ${result.rasters.length} validated PNG rasters; page raster ${pageRaster.bytes.length} bytes.`);

function readUint32(bytes, offset) {
  return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
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
