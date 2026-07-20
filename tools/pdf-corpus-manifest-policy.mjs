import { createHash } from 'node:crypto';
import { existsSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const sha256Pattern = /^[a-f0-9]{64}$/;
const failureClassPattern = /^(?:[A-F]\d+|G)$/;
const pinnedArtifactStates = new Set(['checked-in', 'remote-hash-pinned']);
const semanticExpectationStates = new Set([
  'verified_baseline',
  'pending_manual_review',
  'excluded_license_blocked',
]);
const semanticExpectedKeys = [
  'headings',
  'paragraphs',
  'listStarts',
  'tableHeaders',
  'tableCells',
  'spans',
  'order',
  'links',
  'pageCoverage',
  'mediaOccurrences',
  'unresolvedDispositions',
];
const semanticForbiddenKeys = [
  'headings',
  'paragraphs',
  'listStarts',
  'tableHeaders',
  'tableCells',
  'spans',
  'order',
  'links',
  'pageCoverage',
  'mediaOccurrences',
  'unresolvedDispositions',
];
const semanticExactCountKeys = [
  'headings',
  'paragraphs',
  'listStarts',
  'tables',
  'links',
  'mediaOccurrences',
  'unresolvedSourceDispositions',
  'unresolvedMediaDispositions',
];

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

function plainObject(value) {
  return value && typeof value === 'object' && !Array.isArray(value);
}

function exactObjectKeys(value, keys) {
  if (!plainObject(value)) return false;
  const actual = Object.keys(value).sort();
  const expected = [...keys].sort();
  return actual.length === expected.length && actual.every((key, index) => key === expected[index]);
}

function nonEmptyText(value) {
  return typeof value === 'string' && value.trim().length > 0;
}

function positiveOccurrenceCount(value) {
  return Number.isSafeInteger(value) && value > 0;
}

function validateTextExpectation(record, label, errors, { expected, level = false } = {}) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const keys = ['text', ...(level ? ['level'] : []), ...(expected ? ['occurrences'] : [])];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, nonEmptyText(record.text), `${label} needs exact non-empty text.`);
  if (level) push(errors, Number.isSafeInteger(record.level) && record.level >= 1 && record.level <= 6, `${label} level must be 1..6.`);
  if (expected) push(errors, positiveOccurrenceCount(record.occurrences), `${label} occurrences must be a positive integer.`);
}

function validateListStartExpectation(record, label, errors, { expected } = {}) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const hasStart = Object.hasOwn(record, 'start');
  const keys = ['text', 'ordered', ...(hasStart ? ['start'] : []), ...(expected ? ['occurrences'] : [])];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, nonEmptyText(record.text), `${label} needs exact non-empty text.`);
  push(errors, typeof record.ordered === 'boolean', `${label} ordered must be boolean.`);
  if (hasStart) {
    push(errors, record.ordered === true, `${label} start is valid only for an ordered list.`);
    push(errors, Number.isSafeInteger(record.start) && record.start >= 1, `${label} start must be a positive integer.`);
  }
  if (expected) push(errors, positiveOccurrenceCount(record.occurrences), `${label} occurrences must be a positive integer.`);
}

function validateTableHeaderExpectation(record, label, errors) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  push(errors, exactObjectKeys(record, ['tableIndex', 'cells']), `${label} has unknown or missing fields.`);
  push(errors, Number.isSafeInteger(record.tableIndex) && record.tableIndex >= 0, `${label} tableIndex must be non-negative.`);
  push(errors, Array.isArray(record.cells) && record.cells.length > 0 && record.cells.every((cell) => typeof cell === 'string'), `${label} cells must be an exact non-empty string array.`);
}

function validateTableCellExpectation(record, label, errors, { expected } = {}) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const keys = ['tableIndex', 'text', ...(expected ? ['occurrences'] : [])];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, Number.isSafeInteger(record.tableIndex) && record.tableIndex >= 0, `${label} tableIndex must be non-negative.`);
  push(errors, typeof record.text === 'string', `${label} text must be an exact string.`);
  if (expected) push(errors, positiveOccurrenceCount(record.occurrences), `${label} occurrences must be a positive integer.`);
}

function validateSpanExpectation(record, label, errors, { expected } = {}) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const keys = ['tableIndex', 'text', 'rowspan', 'colspan', ...(expected ? ['occurrences'] : [])];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, Number.isSafeInteger(record.tableIndex) && record.tableIndex >= 0, `${label} tableIndex must be non-negative.`);
  push(errors, typeof record.text === 'string', `${label} text must be an exact string.`);
  push(errors, Number.isSafeInteger(record.rowspan) && record.rowspan >= 1, `${label} rowspan must be positive.`);
  push(errors, Number.isSafeInteger(record.colspan) && record.colspan >= 1, `${label} colspan must be positive.`);
  if (expected) push(errors, positiveOccurrenceCount(record.occurrences), `${label} occurrences must be a positive integer.`);
}

function validateOrderExpectation(record, label, errors) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  push(errors, exactObjectKeys(record, ['sequence']), `${label} has unknown or missing fields.`);
  push(errors, Array.isArray(record.sequence) && record.sequence.length >= 2, `${label} sequence needs at least two exact anchors.`);
  for (const [index, anchor] of (Array.isArray(record.sequence) ? record.sequence : []).entries()) {
    const anchorLabel = `${label} sequence ${index}`;
    push(errors, plainObject(anchor) && exactObjectKeys(anchor, ['kind', 'text']), `${anchorLabel} must contain only kind and text.`);
    if (!plainObject(anchor)) continue;
    push(errors, ['heading', 'paragraph', 'list_start', 'table_cell', 'link', 'media'].includes(anchor.kind), `${anchorLabel} has an invalid kind.`);
    push(errors, nonEmptyText(anchor.text), `${anchorLabel} needs exact non-empty text.`);
  }
}

function validateLinkExpectation(record, label, errors, { expected } = {}) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const keys = ['text', 'url', ...(expected ? ['occurrences'] : [])];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, nonEmptyText(record.text), `${label} needs exact non-empty link text.`);
  push(errors, nonEmptyText(record.url), `${label} needs an exact non-empty link target.`);
  if (expected) push(errors, positiveOccurrenceCount(record.occurrences), `${label} occurrences must be a positive integer.`);
}

function validateMediaExpectation(record, label, errors) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const keys = Object.hasOwn(record, 'reason')
    ? ['id', 'page', 'object', 'disposition', 'reason']
    : ['id', 'page', 'object', 'disposition'];
  push(errors, exactObjectKeys(record, keys), `${label} has unknown or missing fields.`);
  push(errors, nonEmptyText(record.id), `${label} needs an exact occurrence ID.`);
  push(errors, Number.isSafeInteger(record.page) && record.page >= 1, `${label} page must be positive.`);
  push(errors, Number.isSafeInteger(record.object) && record.object >= 0, `${label} object must be non-negative.`);
  push(errors, ['resolved', 'intentional_omission', 'unresolved'].includes(record.disposition), `${label} has an invalid disposition.`);
  if (Object.hasOwn(record, 'reason')) push(errors, nonEmptyText(record.reason), `${label} reason must be non-empty when present.`);
}

function validateUnresolvedExpectation(record, label, errors) {
  push(errors, plainObject(record), `${label} must be an object.`);
  if (!plainObject(record)) return;
  const allowedKeys = ['domain', 'id', 'reason'];
  push(errors, Object.keys(record).every((key) => allowedKeys.includes(key)), `${label} has unknown fields.`);
  push(errors, ['source', 'media'].includes(record.domain), `${label} domain must be source or media.`);
  if (Object.hasOwn(record, 'id')) push(errors, nonEmptyText(record.id), `${label} id must be non-empty when present.`);
  if (Object.hasOwn(record, 'reason')) push(errors, nonEmptyText(record.reason), `${label} reason must be non-empty when present.`);
}

function validateSemanticExpectationLists(container, label, errors, { expected } = {}) {
  const keys = expected ? semanticExpectedKeys : semanticForbiddenKeys;
  push(errors, exactObjectKeys(container, keys), `${label} must contain the complete semantic assertion schema and no unknown fields.`);
  if (!plainObject(container)) return;
  for (const key of keys) {
    if (key === 'pageCoverage') continue;
    push(errors, Array.isArray(container[key]), `${label}.${key} must be an array.`);
  }
  for (const [index, item] of (Array.isArray(container.headings) ? container.headings : []).entries()) validateTextExpectation(item, `${label}.headings[${index}]`, errors, { expected, level: true });
  for (const [index, item] of (Array.isArray(container.paragraphs) ? container.paragraphs : []).entries()) validateTextExpectation(item, `${label}.paragraphs[${index}]`, errors, { expected });
  for (const [index, item] of (Array.isArray(container.listStarts) ? container.listStarts : []).entries()) validateListStartExpectation(item, `${label}.listStarts[${index}]`, errors, { expected });
  for (const [index, item] of (Array.isArray(container.tableHeaders) ? container.tableHeaders : []).entries()) validateTableHeaderExpectation(item, `${label}.tableHeaders[${index}]`, errors);
  for (const [index, item] of (Array.isArray(container.tableCells) ? container.tableCells : []).entries()) validateTableCellExpectation(item, `${label}.tableCells[${index}]`, errors, { expected });
  for (const [index, item] of (Array.isArray(container.spans) ? container.spans : []).entries()) validateSpanExpectation(item, `${label}.spans[${index}]`, errors, { expected });
  for (const [index, item] of (Array.isArray(container.order) ? container.order : []).entries()) validateOrderExpectation(item, `${label}.order[${index}]`, errors);
  for (const [index, item] of (Array.isArray(container.links) ? container.links : []).entries()) validateLinkExpectation(item, `${label}.links[${index}]`, errors, { expected });
  for (const [index, item] of (Array.isArray(container.mediaOccurrences) ? container.mediaOccurrences : []).entries()) validateMediaExpectation(item, `${label}.mediaOccurrences[${index}]`, errors);
  for (const [index, item] of (Array.isArray(container.unresolvedDispositions) ? container.unresolvedDispositions : []).entries()) validateUnresolvedExpectation(item, `${label}.unresolvedDispositions[${index}]`, errors);

  if (expected) {
    const coverage = container.pageCoverage;
    push(errors, coverage === null || plainObject(coverage), `${label}.pageCoverage must be null or an object.`);
    if (plainObject(coverage)) {
      push(errors, exactObjectKeys(coverage, ['pageCount', 'processedPages']), `${label}.pageCoverage has unknown or missing fields.`);
      push(errors, Number.isSafeInteger(coverage.pageCount) && coverage.pageCount >= 1, `${label}.pageCoverage pageCount must be positive.`);
      push(errors, Array.isArray(coverage.processedPages) && coverage.processedPages.length === coverage.pageCount, `${label}.pageCoverage must enumerate every processed page exactly once.`);
      push(errors, Array.isArray(coverage.processedPages) && coverage.processedPages.every((page, index) => page === index + 1), `${label}.pageCoverage pages must be the complete 1..pageCount sequence.`);
    }
  } else {
    push(errors, Array.isArray(container.pageCoverage) && container.pageCoverage.every((page) => Number.isSafeInteger(page) && page >= 1), `${label}.pageCoverage must be a positive page-number array.`);
  }
}

export function validatePdfSemanticExpectations(entry, label, errors, { rootDir } = {}) {
  const semantic = entry.semanticExpectations;
  push(errors, plainObject(semantic), `${label} needs strict semantic expectations.`);
  if (!plainObject(semantic)) return;
  push(errors, exactObjectKeys(semantic, ['schemaVersion', 'status', 'baseline', 'reason', 'expected', 'forbidden', 'exactCounts']), `${label} semanticExpectations has unknown or missing fields.`);
  push(errors, semantic.schemaVersion === 1, `${label} semantic expectation schemaVersion must be 1.`);
  push(errors, semanticExpectationStates.has(semantic.status), `${label} has an invalid semantic expectation status.`);
  validateSemanticExpectationLists(semantic.expected, `${label} semantic expected`, errors, { expected: true });
  validateSemanticExpectationLists(semantic.forbidden, `${label} semantic forbidden`, errors, { expected: false });
  push(errors, plainObject(semantic.exactCounts), `${label} semantic exactCounts must be an object.`);

  const pinStatus = entry.artifact?.pinStatus;
  const assertedArrayCount = semanticExpectedKeys
    .filter((key) => key !== 'pageCoverage')
    .reduce((count, key) => count + (Array.isArray(semantic.expected?.[key]) ? semantic.expected[key].length : 0), 0);
  const forbiddenArrayCount = semanticForbiddenKeys
    .reduce((count, key) => count + (Array.isArray(semantic.forbidden?.[key]) ? semantic.forbidden[key].length : 0), 0);

  if (semantic.status === 'verified_baseline') {
    push(errors, pinStatus === 'checked-in', `${label} may claim a verified semantic baseline only for a checked-in immutable artifact.`);
    push(errors, plainObject(semantic.baseline), `${label} verified semantic baseline needs evidence.`);
    if (plainObject(semantic.baseline)) {
      push(errors, exactObjectKeys(semantic.baseline, ['sources', 'asOf']), `${label} semantic baseline evidence has unknown or missing fields.`);
      push(errors, Array.isArray(semantic.baseline.sources) && semantic.baseline.sources.length >= 2 && semantic.baseline.sources.every(nonEmptyText), `${label} semantic baseline needs independent text/geometry and converted-structure evidence paths.`);
      push(errors, /^\d{4}-\d{2}-\d{2}$/.test(String(semantic.baseline.asOf || '')), `${label} semantic baseline needs an ISO date.`);
      if (rootDir && Array.isArray(semantic.baseline.sources)) {
        for (const source of semantic.baseline.sources) {
          if (!nonEmptyText(source)) continue;
          const baselinePath = checkedInPath(rootDir, source, `${label} semantic baseline`, errors);
          push(errors, existsSync(baselinePath), `${label} semantic baseline evidence is missing at ${source}.`);
        }
      }
    }
    push(errors, semantic.reason === null, `${label} verified semantic baseline must use a null pending reason.`);
    push(errors, exactObjectKeys(semantic.exactCounts, semanticExactCountKeys), `${label} verified semantic baseline needs every exact count and no unknown counts.`);
    for (const key of semanticExactCountKeys) push(errors, Number.isSafeInteger(semantic.exactCounts?.[key]) && semantic.exactCounts[key] >= 0, `${label} exact ${key} count must be non-negative.`);
    push(errors, assertedArrayCount > 0, `${label} verified semantic baseline needs exact expected assertions.`);
    push(errors, semantic.expected?.pageCoverage !== null, `${label} verified semantic baseline needs exact page coverage.`);
    push(errors, Array.isArray(semantic.expected?.order) && semantic.expected.order.length > 0, `${label} verified semantic baseline needs at least one order assertion.`);
    push(errors, semantic.exactCounts?.tables === (entry.expectedPhysicalTables ?? entry.expectedTables), `${label} exact physical table count must match the manifest target.`);
    push(errors, semantic.exactCounts?.mediaOccurrences === semantic.expected?.mediaOccurrences?.length, `${label} must enumerate every expected media occurrence.`);
    const mediaIds = Array.isArray(semantic.expected?.mediaOccurrences)
      ? semantic.expected.mediaOccurrences.map((record) => record?.id)
      : [];
    push(errors, new Set(mediaIds).size === mediaIds.length, `${label} expected media occurrence IDs must be unique.`);
  } else {
    push(errors, semantic.baseline === null, `${label} unreviewed/excluded semantics must not claim baseline evidence.`);
    push(errors, nonEmptyText(semantic.reason), `${label} unreviewed/excluded semantics need a reason.`);
    push(errors, exactObjectKeys(semantic.exactCounts, []), `${label} unreviewed/excluded semantics must not invent exact counts.`);
    push(errors, assertedArrayCount === 0 && semantic.expected?.pageCoverage === null, `${label} unreviewed/excluded semantics must not invent expected content.`);
    push(errors, forbiddenArrayCount === 0, `${label} unreviewed/excluded semantics must not invent forbidden content.`);
  }

  if (pinStatus === 'checked-in') push(errors, semantic.status === 'verified_baseline', `${label} checked-in artifact needs a verified semantic baseline.`);
  if (pinStatus === 'remote-hash-pinned') push(errors, semantic.status === 'pending_manual_review', `${label} remote pinned artifact must remain pending_manual_review until evidence is recorded.`);
  if (pinStatus === 'blocked-license-review') push(errors, semantic.status === 'excluded_license_blocked', `${label} license-blocked artifact must be semantically excluded.`);
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
    if (artifact?.pinStatus === 'checked-in') {
      push(errors, review.status === 'baseline-recorded', `${label} checked-in semantic control must retain baseline-recorded review status.`);
      push(errors, review.visual === 'screenshots-recorded', `${label} checked-in semantic control must retain its recorded visual baseline status.`);
    } else if (artifact?.pinStatus === 'remote-hash-pinned') {
      push(errors, review.status === 'candidate', `${label} remote pin is identity only and must not claim reviewed/baseline status.`);
      push(errors, review.visual === 'required', `${label} remote candidate must remain visually pending until hashed review evidence is validated.`);
    } else if (artifact?.pinStatus === 'blocked-license-review') {
      push(errors, review.status === 'blocked', `${label} license-blocked artifact must remain blocked from review.`);
      push(errors, review.visual === 'blocked', `${label} license-blocked artifact must not claim visual evidence.`);
    }
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
    const conversionExpectation = entry.conversionExpectation;
    if (conversionExpectation !== undefined) {
      push(errors, exactObjectKeys(conversionExpectation, ['phpHtml', 'wpBlocks']), `${label} conversionExpectation must name exactly phpHtml and wpBlocks.`);
      const expectedStatuses = [];
      for (const view of ['phpHtml', 'wpBlocks']) {
        const expected = conversionExpectation?.[view];
        push(errors, exactObjectKeys(expected, ['ok', 'status']), `${label} ${view} conversion expectation must contain exactly ok and status.`);
        push(errors, expected?.ok === false, `${label} ${view} expected failed conversion must use ok=false.`);
        push(errors, ['incomplete', 'unsupported_no_text'].includes(expected?.status), `${label} ${view} expected failed conversion must use an allowed typed PDF refusal status.`);
        expectedStatuses.push(expected?.status);
      }
      push(errors, expectedStatuses[0] === expectedStatuses[1], `${label} phpHtml and wpBlocks expected refusal statuses must match.`);
      push(errors, entry.success?.allowNoText !== true, `${label} expected failed conversion must not claim the successful allowNoText reviewer boundary.`);
    }
    push(errors, !ids.has(entry.id), `Duplicate layout corpus ID: ${entry.id}`);
    ids.add(entry.id);
    validateVerification(entry, label, errors, {
      allowEmptySignificantText: entry.success?.allowNoText === true
        || ['incomplete', 'unsupported_no_text'].includes(conversionExpectation?.wpBlocks?.status),
    });

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
    validatePdfSemanticExpectations(entry, label, errors, { rootDir });
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
