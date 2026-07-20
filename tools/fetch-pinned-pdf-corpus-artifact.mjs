#!/usr/bin/env node

/**
 * Fetch one license-reviewed PDF corpus artifact and verify its immutable pin.
 * Unpinned/license-blocked entries are deliberately not downloadable here.
 */

import { createHash } from 'node:crypto';
import { mkdir, readFile, rename, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..');
const manifests = [
  'tools/pdf-layout-corpus-manifest.json',
  'tools/pdf-corpus-table-manifest.json',
];
const allowedLicenseStates = new Set(['verified-project-license', 'us-government-work']);
const maximumArtifactBytes = 64 * 1024 * 1024;

function options(args) {
  const parsed = {
    id: '',
    cacheDir: path.join(root, '.port-libs/pdf-corpus-pinned'),
    verifyCheckedIn: false,
  };
  for (let index = 0; index < args.length; index += 1) {
    if (args[index] === '--id') {
      parsed.id = args[index + 1] || '';
      index += 1;
    } else if (args[index] === '--cache-dir') {
      parsed.cacheDir = path.resolve(args[index + 1] || parsed.cacheDir);
      index += 1;
    } else if (args[index] === '--verify-checked-in') {
      parsed.verifyCheckedIn = true;
    } else if (args[index] === '--help' || args[index] === '-h') {
      console.log('Usage: node tools/fetch-pinned-pdf-corpus-artifact.mjs --id CORPUS_ID [--cache-dir DIR]\n       node tools/fetch-pinned-pdf-corpus-artifact.mjs --verify-checked-in');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${args[index]}`);
    }
  }
  if (!parsed.id && !parsed.verifyCheckedIn) throw new Error('Pass --id for one pinned artifact, or --verify-checked-in for an offline audit.');
  return parsed;
}

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

async function corpusEntries() {
  const entries = [];
  for (const manifest of manifests) {
    const decoded = JSON.parse(await readFile(path.join(root, manifest), 'utf8'));
    for (const entry of decoded) entries.push({ ...entry, manifest });
  }
  return entries;
}

function verifyBytes(entry, bytes, source) {
  if (!Buffer.isBuffer(bytes)) bytes = Buffer.from(bytes);
  if (bytes.length !== entry.artifact.bytes) {
    throw new Error(`${entry.id} byte pin mismatch for ${source}: expected ${entry.artifact.bytes}, observed ${bytes.length}.`);
  }
  const digest = sha256(bytes);
  if (digest !== entry.artifact.sha256) {
    throw new Error(`${entry.id} SHA-256 mismatch for ${source}: expected ${entry.artifact.sha256}, observed ${digest}.`);
  }
  if (bytes.subarray(0, 5).toString() !== '%PDF-') throw new Error(`${entry.id} is not a PDF.`);
  return digest;
}

async function checkedInIdentity(entry) {
  const filename = path.resolve(root, entry.artifact.localPath || '');
  if (!filename.startsWith(`${root}${path.sep}`)) throw new Error(`${entry.id} localPath escapes the repository.`);
  const bytes = await readFile(filename);
  return { path: filename, bytes: bytes.length, sha256: verifyBytes(entry, bytes, filename), cached: true };
}

async function cachedIdentity(entry, cacheDir) {
  const filename = path.join(cacheDir, `${entry.id}-${entry.artifact.sha256.slice(0, 16)}.pdf`);
  try {
    const bytes = await readFile(filename);
    return { path: filename, bytes: bytes.length, sha256: verifyBytes(entry, bytes, filename), cached: true };
  } catch (error) {
    if (error?.code !== 'ENOENT') throw error;
  }
  return null;
}

async function boundedResponseBytes(response, id) {
  if (!response.body) throw new Error(`${id} response has no body.`);
  const reader = response.body.getReader();
  const chunks = [];
  let total = 0;
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      total += value.byteLength;
      if (total > maximumArtifactBytes) throw new Error(`${id} response exceeds the corpus fetch limit.`);
      chunks.push(Buffer.from(value));
    }
  } catch (error) {
    await reader.cancel().catch(() => {});
    throw error;
  }
  return Buffer.concat(chunks, total);
}

async function fetchPinned(entry, cacheDir) {
  if (entry.artifact.pinStatus === 'blocked-license-review') {
    throw new Error(`${entry.id} is blocked: its license and immutable artifact hash have not been reviewed.`);
  }
  if (!allowedLicenseStates.has(entry.license?.status)) {
    throw new Error(`${entry.id} is not fetchable because license status is ${entry.license?.status || 'missing'}.`);
  }
  if (!/^[a-f0-9]{64}$/.test(entry.artifact.sha256 || '') || !Number.isSafeInteger(entry.artifact.bytes)) {
    throw new Error(`${entry.id} has no valid immutable artifact pin.`);
  }
  if (entry.artifact.bytes > maximumArtifactBytes) throw new Error(`${entry.id} exceeds the ${maximumArtifactBytes}-byte corpus fetch limit.`);
  if (entry.artifact.pinStatus === 'checked-in') return checkedInIdentity(entry);

  await mkdir(cacheDir, { recursive: true });
  const cached = await cachedIdentity(entry, cacheDir);
  if (cached) return cached;
  const response = await fetch(entry.url, { redirect: 'follow', headers: { 'user-agent': 'port-libs-pinned-pdf-corpus/1.0' } });
  if (!response.ok) throw new Error(`${entry.id} download failed with HTTP ${response.status}.`);
  const declaredLength = Number(response.headers.get('content-length'));
  if (Number.isFinite(declaredLength) && declaredLength > maximumArtifactBytes) throw new Error(`${entry.id} response exceeds the corpus fetch limit.`);
  const bytes = await boundedResponseBytes(response, entry.id);
  const digest = verifyBytes(entry, bytes, entry.url);
  const filename = path.join(cacheDir, `${entry.id}-${digest.slice(0, 16)}.pdf`);
  const temporary = `${filename}.tmp-${process.pid}`;
  try {
    await writeFile(temporary, bytes, { flag: 'wx', mode: 0o600 });
    await rename(temporary, filename);
  } finally {
    await rm(temporary, { force: true });
  }
  const metadata = await stat(filename);
  return { path: filename, bytes: metadata.size, sha256: digest, cached: false };
}

const parsed = options(process.argv.slice(2));
const entries = await corpusEntries();
if (parsed.verifyCheckedIn) {
  const checkedIn = entries.filter((entry) => entry.artifact?.pinStatus === 'checked-in');
  for (const entry of checkedIn) await checkedInIdentity(entry);
  console.log(`Verified ${checkedIn.length} checked-in PDF corpus SHA-256 pins without network access.`);
} else {
  const matches = entries.filter((entry) => entry.id === parsed.id);
  if (matches.length !== 1) throw new Error(`Expected one corpus entry named ${parsed.id}; observed ${matches.length}.`);
  const identity = await fetchPinned(matches[0], parsed.cacheDir);
  console.log(JSON.stringify(identity));
}
