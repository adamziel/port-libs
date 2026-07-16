#!/usr/bin/env node

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import JBig2 from '../pandoc-showcase/vendor/pdfjs-jbig2/jbig2.mjs';
import OpenJPEG from '../pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.mjs';
import { decodePdfJbig2Rasters } from '../pandoc-showcase/pdf-jbig2-rasterizer.mjs';
import { decodePdfJpxRasters } from '../pandoc-showcase/pdf-jpx-rasterizer.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = parseArguments(process.argv.slice(2));
if (!args.input || !args.output) {
  console.error('Usage: node tools/decode-pdf-raster-media.mjs --input <pdf> --output <json> [--image-mode important|all|none]');
  process.exit(2);
}

const source = new Uint8Array(fs.readFileSync(args.input));
const imageMode = args.imageMode || 'important';
const decoded = await Promise.allSettled([
  decodePdfJbig2Rasters(source, {
    imageMode,
    decoderFactory: () => JBig2({
      wasmBinary: fs.readFileSync(path.join(root, 'pandoc-showcase/vendor/pdfjs-jbig2/jbig2.wasm')),
    }),
  }),
  decodePdfJpxRasters(source, {
    imageMode,
    decoderFactory: () => OpenJPEG({
      wasmBinary: fs.readFileSync(path.join(root, 'pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.wasm')),
      print: () => {},
      printErr: () => {},
    }),
  }),
]);
const jbig2 = decoded[0].status === 'fulfilled'
  ? decoded[0].value
  : { rasters: [], diagnostics: [`pdf-jbig2-raster-unavailable:${errorToken(decoded[0].reason)}`] };
const jpx = decoded[1].status === 'fulfilled'
  ? decoded[1].value
  : { rasters: [], diagnostics: [`pdf-jpx-raster-unavailable:${errorToken(decoded[1].reason)}`] };

const { rasters: jpxRasters, diagnostics: webCodecDiagnostics } = optimizeJpxStaticRasters(jpx.rasters);
const rasters = mergeRasters([...jbig2.rasters, ...jpxRasters]);
const manifest = {
  rasters: rasters.map((raster) => ({
    object: raster.object,
    bytes: Buffer.from(raster.bytes).toString('base64'),
    mimeType: raster.mimeType,
    width: raster.width,
    height: raster.height,
  })),
  diagnostics: [...jbig2.diagnostics, ...jpx.diagnostics, ...webCodecDiagnostics],
};
fs.writeFileSync(args.output, `${JSON.stringify(manifest)}\n`);
console.log(JSON.stringify({ rasters: manifest.rasters.length, diagnostics: manifest.diagnostics }));

/**
 * The browser/Playground path always receives lossless PNGs. For the checked
 * in static showcase, an opaque high-resolution true-colour JPX can be much
 * smaller as 4:4:4 AVIF without reducing its original compressed byte budget.
 * Palette or alpha-bearing graphics stay lossless PNG, where they preserve
 * sharp edges and transparency better than a second lossy encode.
 */
function optimizeJpxStaticRasters(rasters) {
  const diagnostics = [];
  const optimized = [];
  for (const raster of rasters) {
    if (!shouldUseAvif(raster)) {
      optimized.push(raster);
      continue;
    }
    const avif = encodeAvifWithinSourceBudget(raster, diagnostics);
    if (avif) {
      optimized.push({ ...raster, bytes: avif, mimeType: 'image/avif' });
    } else {
      optimized.push(raster);
    }
  }

  return { rasters: optimized, diagnostics };
}

function shouldUseAvif(raster) {
  return raster.mimeType === 'image/png'
    && raster.isIndexed !== true
    && raster.width * raster.height >= 250_000
    && pngColorType(raster.bytes) === 2
    && Number.isInteger(raster.encodedByteLength)
    && raster.encodedByteLength > 0;
}

function encodeAvifWithinSourceBudget(raster, diagnostics) {
  const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'port-libs-jpx-avif-'));
  try {
    const sourcePath = path.join(temporaryDirectory, 'source.png');
    const outputPath = path.join(temporaryDirectory, 'image.avif');
    fs.writeFileSync(sourcePath, raster.bytes);
    // Start at higher quality and only step down until the converted image is
    // no larger than the original JPX stream. 4:4:4 avoids chroma bleeding on
    // coloured wordmarks and other sharp graphics.
    for (const quality of [65, 60, 55]) {
      const result = spawnSync('avifenc', [
        '--speed', '6',
        '--yuv', '444',
        '--qcolor', String(quality),
        '--ignore-exif',
        '--ignore-xmp',
        sourcePath,
        outputPath,
      ], { encoding: 'utf8', maxBuffer: 1024 * 1024 });
      if (result.error && result.error.code === 'ENOENT') {
        diagnostics.push('pdf-jpx-raster-web-codec-unavailable:avifenc');
        return null;
      }
      if (result.status !== 0 || !fs.existsSync(outputPath)) {
        diagnostics.push(`pdf-jpx-raster-web-codec-failed:${raster.object}`);
        return null;
      }
      const avif = new Uint8Array(fs.readFileSync(outputPath));
      if (avif.length <= raster.encodedByteLength && hasAvifDimensions(avif, raster.width, raster.height)) {
        diagnostics.push(`pdf-jpx-raster-web-codec:avif:${raster.object}:q${quality}`);
        return avif;
      }
      fs.rmSync(outputPath, { force: true });
    }
    diagnostics.push(`pdf-jpx-raster-web-codec-kept-png:${raster.object}:larger-than-source`);
    return null;
  } finally {
    fs.rmSync(temporaryDirectory, { recursive: true, force: true });
  }
}

function pngColorType(bytes) {
  return bytes.length >= 26
    && String.fromCharCode(...bytes.subarray(12, 16)) === 'IHDR'
    ? bytes[25]
    : -1;
}

function hasAvifDimensions(bytes, width, height) {
  if (bytes.length < 24 || !hasAvifBrand(bytes)) {
    return false;
  }
  for (let offset = 4; offset + 16 <= bytes.length; offset += 1) {
    if (String.fromCharCode(...bytes.subarray(offset, offset + 4)) !== 'ispe') {
      continue;
    }
    const start = offset - 4;
    const length = readUint32(bytes, start);
    if (length < 20 || start + length > bytes.length) {
      continue;
    }
    if (readUint32(bytes, start + 12) === width && readUint32(bytes, start + 16) === height) {
      return true;
    }
  }

  return false;
}

function hasAvifBrand(bytes) {
  let offset = 0;
  while (offset + 8 <= bytes.length) {
    const length = readUint32(bytes, offset);
    if (length < 8 || offset + length > bytes.length) {
      return false;
    }
    if (String.fromCharCode(...bytes.subarray(offset + 4, offset + 8)) === 'ftyp') {
      for (let brandOffset = offset + 8; brandOffset + 4 <= offset + length; brandOffset += 4) {
        const brand = String.fromCharCode(...bytes.subarray(brandOffset, brandOffset + 4));
        if (brand === 'avif' || brand === 'avis') {
          return true;
        }
      }
    }
    offset += length;
  }

  return false;
}

function mergeRasters(rasters) {
  const byObject = new Map();
  for (const raster of rasters) {
    if (!raster || !raster.object || byObject.has(String(Number(raster.object)))) {
      continue;
    }
    byObject.set(String(Number(raster.object)), raster);
  }

  return [...byObject.values()].sort((left, right) => Number(left.object) - Number(right.object));
}

function readUint32(bytes, offset) {
  return ((bytes[offset] << 24) | (bytes[offset + 1] << 16) | (bytes[offset + 2] << 8) | bytes[offset + 3]) >>> 0;
}

function errorToken(error) {
  const message = error instanceof Error ? error.message : String(error);

  return message.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48) || 'decode';
}

function parseArguments(values) {
  const parsed = {};
  for (let index = 0; index < values.length; index += 1) {
    const key = values[index];
    if (!key.startsWith('--')) {
      continue;
    }
    const name = key.slice(2);
    const value = values[index + 1];
    if (!value || value.startsWith('--')) {
      continue;
    }
    parsed[name.replaceAll('-', '')] = value;
    index += 1;
  }

  return {
    input: parsed.input,
    output: parsed.output,
    imageMode: parsed.imagemode,
  };
}
