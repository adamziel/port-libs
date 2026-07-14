#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import OpenJPEG from '../pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.mjs';
import { decodePdfJpxRasters } from '../pandoc-showcase/pdf-jpx-rasterizer.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = parseArguments(process.argv.slice(2));
if (!args.input || !args.output) {
  console.error('Usage: node tools/decode-pdf-jpx-media.mjs --input <pdf> --output <json> [--image-mode important|all|none]');
  process.exit(2);
}

const source = new Uint8Array(fs.readFileSync(args.input));
const wasmBinary = fs.readFileSync(path.join(root, 'pandoc-showcase/vendor/pdfjs-openjpeg/openjpeg.wasm'));
const result = await decodePdfJpxRasters(source, {
  imageMode: args.imageMode || 'important',
  decoderFactory: () => OpenJPEG({
    wasmBinary,
    print: () => {},
    printErr: () => {},
  }),
});
const manifest = {
  rasters: result.rasters.map((raster) => ({
    object: raster.object,
    bytes: Buffer.from(raster.bytes).toString('base64'),
    mimeType: raster.mimeType,
    width: raster.width,
    height: raster.height,
    encodedByteLength: raster.encodedByteLength,
    isIndexed: raster.isIndexed,
  })),
  diagnostics: result.diagnostics,
};
fs.writeFileSync(args.output, `${JSON.stringify(manifest)}\n`);
console.log(JSON.stringify({ rasters: manifest.rasters.length, diagnostics: manifest.diagnostics }));

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
