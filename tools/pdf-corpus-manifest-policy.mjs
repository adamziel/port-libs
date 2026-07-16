import { createHash } from 'node:crypto';
import { existsSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const sha256Pattern = /^[a-f0-9]{64}$/;
const failureClassPattern = /^(?:[A-F]\d+|G)$/;
const pinnedArtifactStates = new Set(['checked-in', 'remote-hash-pinned']);

export function sha256Bytes(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

export function sha256FileSync(filename) {
  return sha256Bytes(readFileSync(filename));
}

function push(errors, condition, message) {
  if (!condition) errors.push(message);
}

function validHttpsUrl(value) {
  try {
    const url = new URL(value);
    return url.protocol === 'https:' && !url.username && !url.password && !url.hash;
  } catch {
    return false;
  }
}

function checkedInPath(rootDir, relativePath, label, errors) {
  const absoluteRoot = path.resolve(rootDir);
  const absolutePath = path.resolve(absoluteRoot, relativePath);
  push(errors, absolutePath.startsWith(`${absoluteRoot}${path.sep}`), `${label} localPath escapes the repository.`);
  return absolutePath;
}

function validateCommonEntry(entry, label, errors) {
  push(errors, entry && typeof entry === 'object' && !Array.isArray(entry), `${label} must be an object.`);
  if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return;

  for (const key of ['id', 'label', 'kind', 'url', 'notes']) {
    push(errors, typeof entry[key] === 'string' && entry[key].trim().length > 0, `${label} needs a non-empty ${key}.`);
  }
  push(errors, validHttpsUrl(entry.url), `${label} needs an HTTPS source URL without credentials or fragments.`);

  const artifact = entry.artifact;
  push(errors, artifact && typeof artifact === 'object' && !Array.isArray(artifact), `${label} needs artifact identity metadata.`);
  if (artifact && typeof artifact === 'object' && !Array.isArray(artifact)) {
    push(errors, pinnedArtifactStates.has(artifact.pinStatus) || artifact.pinStatus === 'blocked-license-review', `${label} has an invalid artifact pinStatus.`);
    if (pinnedArtifactStates.has(artifact.pinStatus)) {
      push(errors, sha256Pattern.test(String(artifact.sha256 || '')), `${label} needs a lowercase SHA-256 pin.`);
      push(errors, Number.isSafeInteger(artifact.bytes) && artifact.bytes > 4, `${label} needs a positive pinned byte size.`);
    } else if (artifact.pinStatus === 'blocked-license-review') {
      push(errors, artifact.sha256 === null, `${label} must use a null SHA-256 while source licensing blocks retrieval.`);
      push(errors, artifact.bytes === null, `${label} must use a null byte size while source licensing blocks retrieval.`);
    }
  }

  const provenance = entry.provenance;
  push(errors, provenance && typeof provenance === 'object' && !Array.isArray(provenance), `${label} needs provenance metadata.`);
  if (provenance && typeof provenance === 'object' && !Array.isArray(provenance)) {
    for (const key of ['sourceType', 'project', 'upstreamPath']) {
      push(errors, typeof provenance[key] === 'string' && provenance[key].trim().length > 0, `${label} provenance needs ${key}.`);
    }
  }

  const license = entry.license;
  push(errors, license && typeof license === 'object' && !Array.isArray(license), `${label} needs license review metadata.`);
  if (license && typeof license === 'object' && !Array.isArray(license)) {
    push(errors, ['verified-project-license', 'us-government-work', 'review-required'].includes(license.status), `${label} has an invalid license status.`);
    push(errors, license.spdx === null || (typeof license.spdx === 'string' && license.spdx.trim().length > 0), `${label} license spdx must be a string or null.`);
    push(errors, validHttpsUrl(license.reference), `${label} needs an HTTPS license/provenance reference.`);
    if (license.status === 'review-required') {
      push(errors, license.spdx === null, `${label} must not claim an SPDX license while legal review is required.`);
    }
  }

  const review = entry.review;
  push(errors, review && typeof review === 'object' && !Array.isArray(review), `${label} needs review metadata.`);
  if (review && typeof review === 'object' && !Array.isArray(review)) {
    push(errors, ['baseline-recorded', 'candidate', 'blocked'].includes(review.status), `${label} has an invalid review status.`);
    push(errors, /^\d{4}-\d{2}-\d{2}$/.test(String(review.asOf || '')), `${label} review needs an ISO date.`);
    push(errors, ['screenshots-recorded', 'required', 'blocked'].includes(review.visual), `${label} has an invalid visual review state.`);
    push(errors, Array.isArray(review.unresolved) && review.unresolved.every((item) => typeof item === 'string' && item.trim().length > 0), `${label} unresolved review items must be strings.`);
  }

  push(errors, Array.isArray(entry.failureClasses) && entry.failureClasses.length > 0, `${label} needs at least one failure-taxonomy class.`);
  if (Array.isArray(entry.failureClasses)) {
    push(errors, new Set(entry.failureClasses).size === entry.failureClasses.length, `${label} failure-taxonomy classes must be unique.`);
    for (const failureClass of entry.failureClasses) {
      push(errors, failureClassPattern.test(String(failureClass)), `${label} has unknown failure class ${failureClass}.`);
    }
  }
}

function validateVerification(entry, label, errors, { allowEmptySignificantText = false } = {}) {
  const verification = entry.verification;
  push(errors, verification && typeof verification === 'object' && !Array.isArray(verification), `${label} needs E2E verification criteria.`);
  if (!verification || typeof verification !== 'object' || Array.isArray(verification)) return;

  push(errors, verification.forbidControlCharacters === true, `${label} must reject forbidden C0/C1 controls.`);
  push(errors, verification.forbidReplacementCharacter === true, `${label} must reject U+FFFD replacement characters.`);
  push(errors, Array.isArray(verification.exactSignificantText), `${label} exactSignificantText must be an array.`);
  if (Array.isArray(verification.exactSignificantText)) {
    push(errors, allowEmptySignificantText || verification.exactSignificantText.length > 0, `${label} needs at least one exact significant-text assertion.`);
    push(errors, verification.exactSignificantText.every((value) => typeof value === 'string' && value.trim().length > 0), `${label} exact significant-text assertions must be non-empty strings.`);
  }

  const media = verification.media;
  push(errors, media && typeof media === 'object' && !Array.isArray(media), `${label} needs media-disposition criteria.`);
  if (media && typeof media === 'object' && !Array.isArray(media)) {
    push(errors, media.requireExplicitDisposition === true, `${label} must require a disposition for every visible media occurrence.`);
    push(errors, media.maxBroken === 0, `${label} must reject broken media.`);
    push(errors, media.maxUnresolved === 0, `${label} must reject unresolved visible media.`);
    if ('exactOccurrences' in media) {
      push(errors, Number.isSafeInteger(media.exactOccurrences) && media.exactOccurrences >= 0, `${label} exact media occurrence count must be a non-negative integer.`);
    }
    if ('orderedOccurrenceIds' in media) {
      push(errors, Array.isArray(media.orderedOccurrenceIds) && media.orderedOccurrenceIds.every((value) => typeof value === 'string' && value.length > 0), `${label} ordered media occurrence IDs must be strings.`);
    }
  }
}

export function validatePdfLayoutManifest(manifest, { rootDir } = {}) {
  const errors = [];
  push(errors, Array.isArray(manifest) && manifest.length >= 10, 'The PDF layout corpus needs at least 10 documents.');
  if (!Array.isArray(manifest)) return { errors, summary: { documents: 0, pinned: 0 } };

  const ids = new Set();
  let pinned = 0;
  let checkedIn = 0;
  for (let index = 0; index < manifest.length; index += 1) {
    const entry = manifest[index];
    const label = `layout corpus entry ${entry?.id || index}`;
    validateCommonEntry(entry, label, errors);
    if (!entry || typeof entry !== 'object') continue;
    push(errors, typeof entry.source === 'string' && entry.source.trim().length > 0, `${label} needs a human-readable source.`);
    push(errors, typeof entry.filename === 'string' && /\.pdf$/i.test(entry.filename), `${label} needs a PDF filename.`);
    push(errors, entry.success && typeof entry.success === 'object' && !Array.isArray(entry.success), `${label} needs reviewer success criteria.`);
    push(errors, !ids.has(entry.id), `Duplicate layout corpus ID: ${entry.id}`);
    ids.add(entry.id);
    validateVerification(entry, label, errors, { allowEmptySignificantText: entry.success?.allowNoText === true });

    const exactText = entry.verification?.exactSignificantText;
    const requiredText = entry.success?.requiredText;
    if (Array.isArray(exactText) && exactText.length > 0) {
      push(errors, Array.isArray(requiredText), `${label} must expose exact significant-text assertions in reviewer requiredText criteria.`);
      if (Array.isArray(requiredText)) {
        for (const value of exactText) push(errors, requiredText.includes(value), `${label} reviewer criteria omit exact text ${JSON.stringify(value)}.`);
      }
    }

    if (pinnedArtifactStates.has(entry.artifact?.pinStatus)) pinned += 1;
    if (entry.artifact?.pinStatus === 'checked-in') {
      checkedIn += 1;
      push(errors, typeof entry.artifact.localPath === 'string' && entry.artifact.localPath.length > 0, `${label} needs its checked-in localPath.`);
      if (rootDir && typeof entry.artifact.localPath === 'string') {
        const absolutePath = checkedInPath(rootDir, entry.artifact.localPath, label, errors);
        push(errors, existsSync(absolutePath), `${label} checked-in PDF is missing at ${entry.artifact.localPath}.`);
        if (existsSync(absolutePath)) {
          const bytes = readFileSync(absolutePath);
          push(errors, bytes.subarray(0, 5).toString() === '%PDF-', `${label} checked-in artifact is not a PDF.`);
          push(errors, statSync(absolutePath).size === entry.artifact.bytes, `${label} byte-size pin does not match ${entry.artifact.localPath}.`);
          push(errors, sha256Bytes(bytes) === entry.artifact.sha256, `${label} SHA-256 pin does not match ${entry.artifact.localPath}.`);
        }
      }
    }
  }
  push(errors, pinned === manifest.length, 'Every layout corpus artifact must be SHA-256 pinned.');
  push(errors, checkedIn === manifest.length, 'Every layout reviewer artifact must identify its checked-in PDF.');
  return { errors, summary: { documents: manifest.length, pinned, checkedIn } };
}

export function validatePdfTableManifest(manifest, { rootDir } = {}) {
  const errors = [];
  push(errors, Array.isArray(manifest) && manifest.length >= 24, 'The protected table corpus needs at least 24 candidates.');
  if (!Array.isArray(manifest)) return { errors, summary: { documents: 0, pinned: 0, blocked: 0, checkedIn: 0 } };

  const ids = new Set();
  const kinds = new Set();
  let pinned = 0;
  let blocked = 0;
  let checkedIn = 0;
  for (let index = 0; index < manifest.length; index += 1) {
    const entry = manifest[index];
    const label = `table corpus entry ${entry?.id || index}`;
    validateCommonEntry(entry, label, errors);
    if (!entry || typeof entry !== 'object') continue;
    push(errors, !ids.has(entry.id), `Duplicate table corpus ID: ${entry.id}`);
    ids.add(entry.id);
    kinds.add(entry.kind);
    push(errors, Number.isSafeInteger(entry.expectedTables) && entry.expectedTables >= 0, `${label} needs a non-negative expectedTables count.`);
    const hasCompositeCounts = ['expectedPhysicalTables', 'expectedLogicalInstances', 'expectedLogicalFamilies']
      .some((key) => Object.hasOwn(entry, key));
    if (hasCompositeCounts) {
      for (const key of ['expectedPhysicalTables', 'expectedLogicalInstances', 'expectedLogicalFamilies']) {
        push(errors, Number.isSafeInteger(entry[key]) && entry[key] >= 0, `${label} needs a non-negative ${key} count.`);
      }
      push(errors, entry.expectedPhysicalTables >= entry.expectedLogicalInstances, `${label} cannot have more logical instances than physical tables.`);
      push(errors, entry.expectedLogicalInstances >= entry.expectedLogicalFamilies, `${label} cannot have more logical families than instances.`);
      push(errors, entry.expectedLogicalFamilies === entry.expectedTables, `${label} composite family count must match expectedTables.`);
    }

    const success = entry.success;
    push(errors, success && typeof success === 'object' && !Array.isArray(success), `${label} needs encoded table success criteria.`);
    if (success && typeof success === 'object' && !Array.isArray(success)) {
      push(errors, success.expectedLogicalTables === entry.expectedTables, `${label} logical table target must match expectedTables.`);
      if (hasCompositeCounts) {
        for (const key of ['expectedPhysicalTables', 'expectedLogicalInstances', 'expectedLogicalFamilies']) {
          push(errors, success[key] === entry[key], `${label} ${key} success target must match the manifest count.`);
        }
      }
      push(errors, success.preserveCellOrder === true, `${label} must require ordered cell preservation.`);
      push(errors, success.maxCodeBlocks === 0, `${label} must reject code-block fallback.`);
      push(errors, success.forbidControlCharacters === true, `${label} must reject forbidden controls.`);
      if (entry.expectedTables === 0) push(errors, success.maxTables === 0, `${label} non-table control must forbid tables.`);
      else push(errors, success.minTables === 1, `${label} table candidate must require at least one table.`);
    }

    const pinStatus = entry.artifact?.pinStatus;
    if (pinnedArtifactStates.has(pinStatus)) pinned += 1;
    if (pinStatus === 'blocked-license-review') {
      blocked += 1;
      push(errors, entry.license?.status === 'review-required', `${label} may be unpinned only while license review is explicit.`);
      push(errors, entry.review?.status === 'blocked' && entry.review?.visual === 'blocked', `${label} unpinned candidate must be blocked from review.`);
      push(errors, entry.review?.unresolved?.includes('artifact-sha256'), `${label} must record the missing artifact SHA-256.`);
    }
    if (pinStatus === 'checked-in') {
      checkedIn += 1;
      push(errors, typeof entry.artifact.localPath === 'string' && entry.artifact.localPath.length > 0, `${label} needs its checked-in localPath.`);
      if (rootDir && typeof entry.artifact.localPath === 'string') {
        const absolutePath = checkedInPath(rootDir, entry.artifact.localPath, label, errors);
        push(errors, existsSync(absolutePath), `${label} checked-in PDF is missing at ${entry.artifact.localPath}.`);
        if (existsSync(absolutePath)) {
          const bytes = readFileSync(absolutePath);
          push(errors, bytes.subarray(0, 5).toString() === '%PDF-', `${label} checked-in artifact is not a PDF.`);
          push(errors, statSync(absolutePath).size === entry.artifact.bytes, `${label} byte-size pin does not match ${entry.artifact.localPath}.`);
          push(errors, sha256Bytes(bytes) === entry.artifact.sha256, `${label} SHA-256 pin does not match ${entry.artifact.localPath}.`);
        }
      }
    }
  }
  push(errors, kinds.size >= 10, `The protected table corpus needs diverse layout types; observed ${kinds.size}.`);
  push(errors, pinned >= 20, `At least 20 table candidates must be immutable-hash pinned; observed ${pinned}.`);
  push(errors, checkedIn >= 4, `At least four protected table controls must be checked in; observed ${checkedIn}.`);
  return { errors, summary: { documents: manifest.length, pinned, blocked, checkedIn, kinds: kinds.size } };
}
