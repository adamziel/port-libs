<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
The source export starts with a migration preface before metadata.

---
title: "Migration **Packet**" # source export title
author:
  - Data Liberation reviewer
  - "WordPress #import editor"
date: 2026-06-03
keywords: [migration, wordpress, metadata] # reviewer labels
reviewDefaults_: &review_defaults
  status: queued
  priority: 3
  labels: &review_labels [front-matter, wordpress]
reviewBase_: &merge_review_base
  status: queued
  priority: 5
  labels: [base, import]
  reviewer: Base Desk
reviewOverride_: &merge_review_override
  status: approved
  labels: [override, review]
review:
  <<: *review_defaults
  owner: !wp-reviewer "Import Desk"
typed-review:
  source-revision: !!str 007
  typed-revision: !!int "007"
  confidence: !!float "0.75"
  approved: !!bool "true"
  withdrawn: !!null "not carried"
source-captured-at: !!timestamp 2026-06-05 06:46:51Z
review-binary:
  note-bytes: !!binary "UmV2aWV3IG1ldGFkYXRh"
  digest-bytes: !!binary |
    U291cmNl
    IFBhY2tldA==
optional-deadline:
blank-note: # intentionally blank in source packet
explicit-empty: ""
flow-empty-review: {migration-ticket:, quoted-empty: ""}
typed-flow-review: {priority: !!int "4", enabled: !!bool "false", ticket: !!str 009}
multiline-flow-labels: [
  migration,
  "Data Liberation",
  wordpress
]
multiline-flow-review: {
  status: queued,
  labels: *review_labels,
  owners: [
    Import Desk,
    "QA #2"
  ]
}
flow-quoted-review: {
  note: "Line one
    line two",
  owner: 'Import
    Desk',
  labels: [
    "WordPress, import",
    'Data: Liberation'
  ],
  source-uri: "https://example.test/\
    exports/packet#flow-quoted"
}
flow-comment-labels: [
  migration, # source label
  wordpress
]
flow-comment-review: {
  status: queued, # reviewer queue state
  labels: [
    front-matter, # reviewer import tag
    wordpress
  ],
  source-uri: /exports/packet#commented-flow,
  note: "Keep # quoted hash"
}
review-label-set: !!set {front-matter, wordpress, "source:key"}
block-label-set: !!set
  ? migration
  ? "qa:review"
sequence-label-sets:
  - !!set {draft, published}
  - !!set
    ? queued
    ? "needs:review"
review-notes:
  - |-
    Preserve original front matter.
    Keep reviewer line breaks.
  - >-
    Fold reviewer note before
    block rendering.
handoff-gaps:
  -
  - status: queued
    reason:
compact-review-items:
  - label: Migration review
  - "source:key": "metadata: value"
  - <<: {status: queued, priority: 4}
  - source-uri: https://example.test/exports/packet#compact
compact-review-urls:
  - https://example.test/export:443/path
  - mailto:review@example.test
merge-sequence-review:
  <<: [*merge_review_override, *merge_review_base]
  priority: 1
merge-sequence-audit:
  <<:
    - *merge_review_override
    - *merge_review_base
  status: needs-review
flow-merge-review: {<<: [*merge_review_override, *merge_review_base], reviewer: Flow Desk}
? explicit-review-defaults_
: &explicit_review_defaults {status: queued, priority: 6, labels: [explicit, review]}
? explicit-review
:
  ? <<
  : *explicit_review_defaults
  ? status
  : approved
  ? "source:key"
  : "metadata: value"
?
  "explicit:source-uri"
: "https://example.test/exports/packet#explicit-key"
? [sequence, source-uri]
: "https://example.test/exports/packet#sequence-key"
sequence-key-review:
  ? [owner, desk]
  : Import Desk
  ? [labels, import]
  :
    - migration
    - wordpress
sequence-key-label-set: !!set
  ? [source, uri]
  ? [qa, true]
? {source: uri, type: review}
: "https://example.test/exports/packet#map-key"
?
  source: owner
  desk: import
: Import Desk
map-key-review:
  ? {owner: desk, ticket: 7}
  : queued
  ? {labels: [source, qa], active: true}
  :
    - migration
    - wordpress
map-key-label-set: !!set
  ? {source: uri}
  ? {qa: true}
source-uri: /exports/packet#front-matter
escaped-source-title: "Escaped \u201cmetadata\u201d \U0001F4DD"
escaped-source-uri: "https:\/\/example.test\/exports\/packet\x23front-matter"
multiline-source-title: "Imported
  **Metadata** packet"
source-continuation-uri: "https://example.test/\
  exports/packet#front-matter"
single-quoted-source-note: 'Reviewer''s
  front matter keeps # literal and C:\exports\packet'
single-quoted-labels: ['don''t normalize', 'backslash\n literal']
source-summary: >- # folded source note for reviewer queue
  Preserve front matter
  comments before rendering.
audit-note: |+ # keep final newline for audit packets
  YAML parser keeps this note.

aliases:
  labels: *review_labels
source-revision: !!str 007
references:
  - &source_reference
    id: source-export
    type: article-journal
    title: "Source: Metadata export"
    issued:
      date-parts:
        - - 2026
          - 6
          - 3
---

# Imported Body

---
review: {status: needs-review, priority: 2, labels: [qa, follow-up]}
summary: >- # later metadata block overrides the first review status
  Preserve front matter for reviewer handoff
  before rendering the imported body.
---

The block import keeps the source metadata available for audit tooling.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$meta = $document->attr('meta', []);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (($meta['review']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing later review override');
    }
    if (($meta['typed-review']['source-revision'] ?? '') !== '007') {
        throw new RuntimeException('YAML metadata self-test failed to preserve explicit string revision');
    }
    if (($meta['typed-review']['typed-revision'] ?? null) !== 7) {
        throw new RuntimeException('YAML metadata self-test missing explicit integer tag coercion');
    }
    if (($meta['typed-review']['confidence'] ?? null) !== 0.75) {
        throw new RuntimeException('YAML metadata self-test missing explicit float tag coercion');
    }
    if (($meta['typed-review']['approved'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit bool tag coercion');
    }
    if (!array_key_exists('withdrawn', $meta['typed-review'] ?? []) || $meta['typed-review']['withdrawn'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit null tag coercion');
    }
    if (($meta['source-captured-at'] ?? '') !== '2026-06-05T06:46:51Z') {
        throw new RuntimeException('YAML metadata self-test missing explicit timestamp tag normalization');
    }
    if (($meta['review-binary']['note-bytes'] ?? '') !== 'Review metadata') {
        throw new RuntimeException('YAML metadata self-test missing explicit binary scalar decoding');
    }
    if (($meta['review-binary']['digest-bytes'] ?? '') !== 'Source Packet') {
        throw new RuntimeException('YAML metadata self-test missing explicit binary block-scalar decoding');
    }
    if (!array_key_exists('optional-deadline', $meta) || $meta['optional-deadline'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing empty scalar deadline null');
    }
    if (!array_key_exists('blank-note', $meta) || $meta['blank-note'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing comment-only scalar null');
    }
    if (($meta['explicit-empty'] ?? null) !== '') {
        throw new RuntimeException('YAML metadata self-test confused quoted empty scalar with null');
    }
    if (
        !array_key_exists('migration-ticket', $meta['flow-empty-review'] ?? [])
        || $meta['flow-empty-review']['migration-ticket'] !== null
    ) {
        throw new RuntimeException('YAML metadata self-test missing flow empty scalar null');
    }
    if (($meta['flow-empty-review']['quoted-empty'] ?? null) !== '') {
        throw new RuntimeException('YAML metadata self-test missing flow quoted empty scalar');
    }
    if (($meta['typed-flow-review']['priority'] ?? null) !== 4) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit integer tag coercion');
    }
    if (($meta['typed-flow-review']['enabled'] ?? null) !== false) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit bool tag coercion');
    }
    if (($meta['typed-flow-review']['ticket'] ?? null) !== '009') {
        throw new RuntimeException('YAML metadata self-test missing flow explicit string tag preservation');
    }
    if (($meta['multiline-flow-labels'] ?? []) !== ['migration', 'Data Liberation', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing multiline flow sequence metadata');
    }
    if (($meta['multiline-flow-review']['labels'] ?? []) !== ['front-matter', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing multiline flow alias metadata');
    }
    if (($meta['multiline-flow-review']['owners'] ?? []) !== ['Import Desk', 'QA #2']) {
        throw new RuntimeException('YAML metadata self-test missing nested multiline flow sequence metadata');
    }
    if (($meta['flow-quoted-review']['note'] ?? '') !== 'Line one line two') {
        throw new RuntimeException('YAML metadata self-test missing flow quoted multiline note folding');
    }
    if (($meta['flow-quoted-review']['owner'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow single-quoted multiline owner folding');
    }
    if (($meta['flow-quoted-review']['labels'] ?? []) !== ['WordPress, import', 'Data: Liberation']) {
        throw new RuntimeException('YAML metadata self-test missing flow quoted comma/colon labels');
    }
    if (($meta['flow-quoted-review']['source-uri'] ?? '') !== 'https://example.test/exports/packet#flow-quoted') {
        throw new RuntimeException('YAML metadata self-test missing flow quoted escaped continuation URI');
    }
    if (($meta['flow-comment-labels'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing flow comment label stripping');
    }
    if (($meta['flow-comment-review']['status'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing flow comment map status');
    }
    if (($meta['flow-comment-review']['labels'] ?? []) !== ['front-matter', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing flow comment nested labels');
    }
    if (($meta['flow-comment-review']['source-uri'] ?? '') !== '/exports/packet#commented-flow') {
        throw new RuntimeException('YAML metadata self-test stripped flow comment source URI fragment');
    }
    if (($meta['flow-comment-review']['note'] ?? '') !== 'Keep # quoted hash') {
        throw new RuntimeException('YAML metadata self-test stripped quoted flow comment hash');
    }
    if (!array_key_exists('source:key', $meta['review-label-set'] ?? []) || $meta['review-label-set']['source:key'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit flow set tag metadata');
    }
    if (!array_key_exists('qa:review', $meta['block-label-set'] ?? []) || $meta['block-label-set']['qa:review'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit block set tag metadata');
    }
    if (!array_key_exists('published', $meta['sequence-label-sets'][0] ?? []) || $meta['sequence-label-sets'][0]['published'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence flow set tag metadata');
    }
    if (!array_key_exists('needs:review', $meta['sequence-label-sets'][1] ?? []) || $meta['sequence-label-sets'][1]['needs:review'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence block set tag metadata');
    }
    if (($meta['review-notes'][0] ?? '') !== "Preserve original front matter.\nKeep reviewer line breaks.") {
        throw new RuntimeException('YAML metadata self-test missing literal sequence block scalar note');
    }
    if (($meta['review-notes'][1] ?? '') !== 'Fold reviewer note before block rendering.') {
        throw new RuntimeException('YAML metadata self-test missing folded sequence block scalar note');
    }
    if (!array_key_exists(0, $meta['handoff-gaps'] ?? []) || $meta['handoff-gaps'][0] !== null) {
        throw new RuntimeException('YAML metadata self-test missing bare sequence item null');
    }
    if (
        !array_key_exists('reason', $meta['handoff-gaps'][1] ?? [])
        || $meta['handoff-gaps'][1]['reason'] !== null
    ) {
        throw new RuntimeException('YAML metadata self-test missing sequence map empty scalar null');
    }
    if (($meta['compact-review-items'][0]['label'] ?? '') !== 'Migration review') {
        throw new RuntimeException('YAML metadata self-test missing compact sequence map label');
    }
    if (($meta['compact-review-items'][1]['source:key'] ?? '') !== 'metadata: value') {
        throw new RuntimeException('YAML metadata self-test missing compact sequence quoted key');
    }
    if (($meta['compact-review-items'][2]['status'] ?? '') !== 'queued' || ($meta['compact-review-items'][2]['priority'] ?? null) !== 4) {
        throw new RuntimeException('YAML metadata self-test missing compact sequence merge map');
    }
    if (($meta['compact-review-items'][3]['source-uri'] ?? '') !== 'https://example.test/exports/packet#compact') {
        throw new RuntimeException('YAML metadata self-test missing compact sequence source URI');
    }
    if (($meta['compact-review-urls'] ?? []) !== ['https://example.test/export:443/path', 'mailto:review@example.test']) {
        throw new RuntimeException('YAML metadata self-test misparsed compact sequence scalar URLs');
    }
    if (($meta['merge-sequence-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing earlier merge-sequence precedence');
    }
    if (($meta['merge-sequence-review']['priority'] ?? null) !== 1) {
        throw new RuntimeException('YAML metadata self-test missing explicit merge-sequence override');
    }
    if (($meta['merge-sequence-review']['labels'] ?? []) !== ['override', 'review']) {
        throw new RuntimeException('YAML metadata self-test missing merge-sequence labels');
    }
    if (($meta['merge-sequence-audit']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing block merge-sequence explicit override');
    }
    if (($meta['flow-merge-review']['reviewer'] ?? '') !== 'Flow Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow merge-sequence override');
    }
    if (($meta['explicit-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing explicit-key review override');
    }
    if (($meta['explicit-review']['priority'] ?? null) !== 6) {
        throw new RuntimeException('YAML metadata self-test missing explicit-key merge priority');
    }
    if (($meta['explicit-review']['labels'] ?? []) !== ['explicit', 'review']) {
        throw new RuntimeException('YAML metadata self-test missing explicit-key merge labels');
    }
    if (($meta['explicit-review']['source:key'] ?? '') !== 'metadata: value') {
        throw new RuntimeException('YAML metadata self-test missing explicit quoted metadata key');
    }
    if (($meta['explicit:source-uri'] ?? '') !== 'https://example.test/exports/packet#explicit-key') {
        throw new RuntimeException('YAML metadata self-test missing block-form explicit source URI key');
    }
    if (($meta['[sequence, source-uri]'] ?? '') !== 'https://example.test/exports/packet#sequence-key') {
        throw new RuntimeException('YAML metadata self-test missing explicit sequence source URI key');
    }
    if (($meta['sequence-key-review']['[owner, desk]'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing nested explicit sequence key owner');
    }
    if (($meta['sequence-key-review']['[labels, import]'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing nested explicit sequence key labels');
    }
    if (!array_key_exists('[qa, true]', $meta['sequence-key-label-set'] ?? []) || $meta['sequence-key-label-set']['[qa, true]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit sequence key in set metadata');
    }
    if (($meta['{source: uri, type: review}'] ?? '') !== 'https://example.test/exports/packet#map-key') {
        throw new RuntimeException('YAML metadata self-test missing explicit map source URI key');
    }
    if (($meta['{source: owner, desk: import}'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing block-form explicit map owner key');
    }
    if (($meta['map-key-review']['{owner: desk, ticket: 7}'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing nested explicit map ticket key');
    }
    if (($meta['map-key-review']['{labels: [source, qa], active: true}'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing nested explicit map labels key');
    }
    if (!array_key_exists('{qa: true}', $meta['map-key-label-set'] ?? []) || $meta['map-key-label-set']['{qa: true}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit map key in set metadata');
    }
    if (($meta['references'][0]['issued']['date-parts'][0] ?? []) !== [2026, 6, 3]) {
        throw new RuntimeException('YAML metadata self-test missing block-style date-parts');
    }
    if (($meta['aliases']['labels'] ?? []) !== ['front-matter', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing anchor alias labels');
    }
    if (($meta['authors'][1] ?? '') !== 'WordPress #import editor') {
        throw new RuntimeException('YAML metadata self-test stripped quoted author hash');
    }
    if (($meta['source-summary'] ?? '') !== 'Preserve front matter comments before rendering.') {
        throw new RuntimeException('YAML metadata self-test missing folded source comment summary');
    }
    if (($meta['summary'] ?? '') !== 'Preserve front matter for reviewer handoff before rendering the imported body.') {
        throw new RuntimeException('YAML metadata self-test missing later folded comment summary');
    }
    if (($meta['audit-note'] ?? '') !== "YAML parser keeps this note.\n") {
        throw new RuntimeException('YAML metadata self-test missing literal keep-chomp note');
    }
    if (($meta['source-uri'] ?? '') !== '/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test stripped unspaced source hash');
    }
    if (($meta['escaped-source-title'] ?? '') !== "Escaped \u{201C}metadata\u{201D} \u{1F4DD}") {
        throw new RuntimeException('YAML metadata self-test missing escaped Unicode source title');
    }
    if (($meta['escaped-source-uri'] ?? '') !== 'https://example.test/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test missing escaped source URI');
    }
    if (($meta['multiline-source-title'] ?? '') !== 'Imported **Metadata** packet') {
        throw new RuntimeException('YAML metadata self-test missing folded multiline source title');
    }
    if (($meta['source-continuation-uri'] ?? '') !== 'https://example.test/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test missing escaped continuation source URI');
    }
    if (($meta['single-quoted-source-note'] ?? '') !== "Reviewer's front matter keeps # literal and C:\\exports\\packet") {
        throw new RuntimeException('YAML metadata self-test missing folded single-quoted source note');
    }
    if (($meta['single-quoted-labels'] ?? []) !== ["don't normalize", 'backslash\n literal']) {
        throw new RuntimeException('YAML metadata self-test missing single-quoted label list');
    }
    if (($meta['source-revision'] ?? '') !== '007') {
        throw new RuntimeException('YAML metadata self-test missing tagged string revision');
    }
    if (!str_contains($blocks, '<h1 id="imported-body">Imported Body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing imported body heading');
    }

    echo "yaml metadata handoff self-test ok\n";
    return;
}

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Review labels: ' . implode(', ', $meta['review']['labels'] ?? []) . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo 'Review optional deadline is null: ' . ((array_key_exists('optional-deadline', $meta) && $meta['optional-deadline'] === null) ? 'yes' : 'no') . "\n";
echo 'Merge sequence review: ' . ($meta['merge-sequence-review']['status'] ?? '') . ' / priority ' . ($meta['merge-sequence-review']['priority'] ?? '') . "\n";
echo 'Explicit key review: ' . ($meta['explicit-review']['status'] ?? '') . ' / ' . ($meta['explicit-review']['source:key'] ?? '') . "\n";
echo 'Sequence key review: ' . ($meta['sequence-key-review']['[owner, desk]'] ?? '') . ' / ' . ($meta['[sequence, source-uri]'] ?? '') . "\n";
echo 'Map key review: ' . ($meta['map-key-review']['{owner: desk, ticket: 7}'] ?? '') . ' / ' . ($meta['{source: uri, type: review}'] ?? '') . "\n";
echo 'Compact sequence item: ' . ($meta['compact-review-items'][0]['label'] ?? '') . ' / ' . ($meta['compact-review-items'][1]['source:key'] ?? '') . "\n";
echo 'Source revision: ' . ($meta['source-revision'] ?? '') . "\n";
echo 'Typed review revision: ' . ($meta['typed-review']['typed-revision'] ?? '') . ' / confidence ' . ($meta['typed-review']['confidence'] ?? '') . "\n";
echo 'Source captured at: ' . ($meta['source-captured-at'] ?? '') . "\n";
echo 'Review binary bytes: ' . ($meta['review-binary']['note-bytes'] ?? '') . ' / ' . ($meta['review-binary']['digest-bytes'] ?? '') . "\n";
echo 'Multiline flow labels: ' . implode(', ', $meta['multiline-flow-labels'] ?? []) . "\n";
echo 'Flow comment labels: ' . implode(', ', $meta['flow-comment-labels'] ?? []) . "\n";
echo 'Escaped source title: ' . ($meta['escaped-source-title'] ?? '') . "\n";
echo 'Multiline source title: ' . ($meta['multiline-source-title'] ?? '') . "\n";
echo 'Single quoted source note: ' . ($meta['single-quoted-source-note'] ?? '') . "\n";
echo 'Reference: ' . ($meta['references'][0]['id'] ?? '') . ' / ' . ($meta['references'][0]['title'] ?? '') . "\n\n";
echo $blocks . "\n";
