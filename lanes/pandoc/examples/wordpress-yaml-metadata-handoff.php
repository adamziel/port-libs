<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
The source export starts with a migration preface before metadata.

---
%YAML 1.2
%TAG !wpd! tag:directive.example,2026:
%TAG !yaml! tag:yaml.org,2002:
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
  hex-revision: !!int 0x2A
  binary-flags: !!int 0b101010
  octal-batch: !!int 0o52
  legacy-octal-batch: !!int "052"
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
tag-directive-review:
  owner: !wpd!reviewer Directive Desk
  ticket: !yaml!str 010
  priority: !yaml!int "10"
  labels: [!wpd!label directive, !wpd!label metadata]
flow-tag-directive-review: {? !wpd!key "source:key": !wpd!value directive metadata, owner: !wpd!reviewer Flow Directive Desk}
non-specific-review:
  owner: ! "Import Desk"
  status: ! queued
  labels: [! front-matter, ! "WordPress #import"]
flow-non-specific-review: {owner: ! "Flow Desk", status: ! approved, labels: [! yaml, ! metadata]}
verbatim-tag-review: {owner: !<tag:example.test,2026:reviewer> Import Desk, labels: [!<tag:example.test,2026:label> migration, !<tag:example.test,2026:label> wordpress], source-uri: !<tag:example.test,2026:source-uri> https://example.test/exports/packet#verbatim-tag}
verbatim-tag-label-set: !!set {!<tag:example.test,2026:label> migration, !<tag:example.test,2026:label> wordpress}
non-specific-defaults_: ! &non_specific_defaults {status: queued, priority: 8}
non-specific-merge:
  <<: ! *non_specific_defaults
  status: ! approved
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
review-order_: &review_order !!omap
  - source-title: Original export
  - source-title: Revised export
  - priority: !!int "3"
ordered-review:
  steps: *review_order
  reviewer-pairs: !!pairs
    - owner: Import Desk
    - owner: QA Desk
    - "source:key": "metadata: value"
flow-ordered-review: {steps: !!omap [{stage: collected}, {stage: normalized}], reviewers: !!pairs [{owner: Import Desk}, {owner: QA Desk}]}
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
flow-explicit-review: {? [source, uri]: https://example.test/exports/packet#flow-explicit-key, ? {owner: desk, ticket: 7}: queued, ? "source:key": "metadata: value"}
flow-explicit-reference:
  id: flow-explicit-key-ref
  metadata: {? [source, key]: metadata value, ? {type: review}: kept}
flow-explicit-null-review: {? source, ? [source, uri], ? {owner: desk, ticket: 7}, ? "source:key", status: approved}
flow-explicit-null-reference:
  id: flow-explicit-null-key-ref
  metadata: {? [source, key], ? {type: review}, state: kept}
sequence-explicit-review-items:
  - ? [source, uri]
    : https://example.test/exports/packet#sequence-explicit-item
    status: queued
    labels:
      - migration
      - wordpress
  - ? {owner: desk, ticket: 7}
    : approved
    source note: Reviewed by structured key
  - ? "source:key"
    : "metadata: value"
    owner: Import Desk
source label: Migration review
plain-key-review:
  source owner: Import Desk
  owner role: content steward
plain-key-items:
  - review label: Compact reviewer label
  - source url: https://example.test/exports/packet#plain-key
flow-plain-key-review: {source owner: Flow Desk, source label: Flow metadata}
flow-colon-key-review: {source:key: metadata value, dc:title: Source metadata title, source:uri: https://example.test/exports/packet#flow-colon-key}
yes: boolean-looking source field
True: uppercase boolean-looking source field
15: numeric-looking source field
0x2A: hexadecimal-looking source field
"no": quoted boolean-looking source field
? "Off"
: quoted off-looking source field
? '3.14'
: quoted float-looking source field
"0o52": quoted octal-looking source field
ambiguous-field-review:
  true: nested reviewer boolean key stays visible
  15: nested reviewer numeric key stays visible
  status: queued
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
plain-continuation-review:
  note:
    Imported reviewer
    plain scalar
  paragraph:
    First paragraph

    Second paragraph
  steps:
    - Collect source
      metadata packet
    - Approve
      WordPress import
plain-continuation-reference:
  id: plain-continuation-ref
  metadata:
    source note:
      Source reviewer
      plain scalar
source-summary: >- # folded source note for reviewer queue
  Preserve front matter
  comments before rendering.
source-review-log: >- # folded reviewer log with preserved nested lines
  Review steps:
    - preserve front matter
    - import blocks
  Confirm before publish.
audit-note: |+ # keep final newline for audit packets
  YAML parser keeps this note.

aliases:
  labels: *review_labels
alias-diagnostics:
  self: &alias_diag_self *alias_diag_self
  missing: *missing_alias
flow-alias-diagnostics: {owner: *missing_flow_owner, status: queued}
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

---
{
  flow-document-review: {status: queued, priority: !!int "2", labels: [flow, metadata]},
  flow-document-references: [{id: flow-document-ref, title: "Flow document source", issued: {date-parts: [[2026, 6, 5]]}}],
  "flow-document:no": quoted top-level flow field,
  ? "flow-document:15": quoted explicit flow key
}
---

The block import keeps the source metadata available for audit tooling.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$meta = $document->attr('meta', []);
$yamlDiagnostics = $document->attr('yamlMetadataDiagnostics', []);
$yamlTagProvenance = $document->attr('yamlMetadataTagProvenance', []);
$blocks = (new WordPressBlockWriter())->write($document);
$metadataMarkdown = (new MarkdownWriter(['yamlMetadata' => true]))->write($document);
$metadataRoundTripMeta = (new MarkdownReader())->read($metadataMarkdown)->attr('meta', []);

$implicitOpeningMarkdown = <<<'MARKDOWN'
title: "Implicit **Packet**"
author:
  - Data Liberation reviewer
keywords: [migration, wordpress]
reviewDefaults_: &review_defaults
  status: queued
  priority: 4
review:
  <<: *review_defaults
  owner: Import Desk
references:
  - id: implicit-yaml-ref
    title: Source metadata
...

# Imported Body
MARKDOWN;

$implicitOpeningDocument = (new MarkdownReader())->read($implicitOpeningMarkdown);
$implicitOpeningMeta = $implicitOpeningDocument->attr('meta', []);
$implicitOpeningBlocks = (new WordPressBlockWriter())->write($implicitOpeningDocument);

$invalidBlockScalarMarkdown = <<<'MARKDOWN'
---
title: Invalid block scalar **Packet**
abstract: |
This source line is not indented relative to the block scalar.
---

# Invalid scalar body
MARKDOWN;

$invalidBlockScalarDocument = (new MarkdownReader())->read($invalidBlockScalarMarkdown);
$invalidBlockScalarBlocks = (new WordPressBlockWriter())->write($invalidBlockScalarDocument);

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
    if (($meta['typed-review']['hex-revision'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit hexadecimal integer coercion');
    }
    if (($meta['typed-review']['binary-flags'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit binary integer coercion');
    }
    if (($meta['typed-review']['octal-batch'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit octal integer coercion');
    }
    if (($meta['typed-review']['legacy-octal-batch'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit legacy octal integer coercion');
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
    if (($meta['tag-directive-review']['owner'] ?? '') !== 'Directive Desk') {
        throw new RuntimeException('YAML metadata self-test missing tag directive owner metadata');
    }
    if (($meta['tag-directive-review']['ticket'] ?? '') !== '010') {
        throw new RuntimeException('YAML metadata self-test missing tag directive core string handle');
    }
    if (($meta['tag-directive-review']['priority'] ?? null) !== 10) {
        throw new RuntimeException('YAML metadata self-test missing tag directive core integer handle');
    }
    if (($meta['tag-directive-review']['labels'] ?? []) !== ['directive', 'metadata']) {
        throw new RuntimeException('YAML metadata self-test missing tag directive sequence labels');
    }
    if (($meta['flow-tag-directive-review']['source:key'] ?? '') !== 'directive metadata') {
        throw new RuntimeException('YAML metadata self-test missing flow tag directive explicit key metadata');
    }
    if (($meta['flow-tag-directive-review']['owner'] ?? '') !== 'Flow Directive Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow tag directive owner metadata');
    }
    if (($meta['non-specific-review']['owner'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test leaked bare non-specific tag on owner metadata');
    }
    if (($meta['non-specific-review']['labels'] ?? []) !== ['front-matter', 'WordPress #import']) {
        throw new RuntimeException('YAML metadata self-test missing bare non-specific tag sequence metadata');
    }
    if (($meta['flow-non-specific-review']['labels'] ?? []) !== ['yaml', 'metadata']) {
        throw new RuntimeException('YAML metadata self-test missing bare non-specific tag flow metadata');
    }
    $yamlTags = array_column($yamlTagProvenance, 'tag');
    if (!in_array('!wp-reviewer', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test missing local tag provenance');
    }
    if (!in_array('!<tag:example.test,2026:reviewer>', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test missing verbatim tag provenance');
    }
    if (!in_array('!<tag:directive.example,2026:reviewer>', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test missing tag directive reviewer provenance');
    }
    if (!in_array('!<tag:directive.example,2026:key>', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test missing tag directive key provenance');
    }
    if (in_array('!!str', $yamlTags, true) || in_array('!', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test confused core/non-specific tags with custom tag provenance');
    }
    $yamlTagPaths = array_column($yamlTagProvenance, 'path');
    foreach (['/review/owner', '/tag-directive-review/labels/0', '/flow-tag-directive-review/source:key', '/verbatim-tag-review/source-uri'] as $expectedPath) {
        if (!in_array($expectedPath, $yamlTagPaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing custom tag provenance path ' . $expectedPath);
        }
    }
    if (str_contains(json_encode($meta, JSON_THROW_ON_ERROR), '!wpd!')) {
        throw new RuntimeException('YAML metadata self-test leaked raw tag directive handle text');
    }
    if (($meta['verbatim-tag-review']['owner'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing verbatim tag owner metadata');
    }
    if (($meta['verbatim-tag-review']['labels'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing verbatim tag flow labels');
    }
    if (($meta['verbatim-tag-review']['source-uri'] ?? '') !== 'https://example.test/exports/packet#verbatim-tag') {
        throw new RuntimeException('YAML metadata self-test missing verbatim tag source URI');
    }
    if (array_keys($meta['verbatim-tag-label-set'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing verbatim tag set labels');
    }
    if (str_contains(json_encode($meta, JSON_THROW_ON_ERROR), '!<tag:example.test')) {
        throw new RuntimeException('YAML metadata self-test leaked raw verbatim tag text');
    }
    if (($meta['non-specific-merge']['priority'] ?? null) !== 8 || ($meta['non-specific-merge']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing bare non-specific tag alias merge metadata');
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
    if (($meta['ordered-review']['steps'][0]['key'] ?? '') !== 'source-title' || ($meta['ordered-review']['steps'][0]['value'] ?? '') !== 'Original export') {
        throw new RuntimeException('YAML metadata self-test missing ordered-map first source title');
    }
    if (($meta['ordered-review']['steps'][1]['key'] ?? '') !== 'source-title' || ($meta['ordered-review']['steps'][1]['value'] ?? '') !== 'Revised export') {
        throw new RuntimeException('YAML metadata self-test missing ordered-map duplicate source title');
    }
    if (($meta['ordered-review']['steps'][2]['value'] ?? null) !== 3) {
        throw new RuntimeException('YAML metadata self-test missing ordered-map explicit integer value');
    }
    if (($meta['ordered-review']['reviewer-pairs'][1]['key'] ?? '') !== 'owner' || ($meta['ordered-review']['reviewer-pairs'][1]['value'] ?? '') !== 'QA Desk') {
        throw new RuntimeException('YAML metadata self-test missing pairs duplicate owner metadata');
    }
    if (($meta['flow-ordered-review']['steps'][1]['value'] ?? '') !== 'normalized') {
        throw new RuntimeException('YAML metadata self-test missing flow ordered-map metadata');
    }
    if (($meta['flow-ordered-review']['reviewers'][0]['value'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow pairs metadata');
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
    if (($meta['flow-explicit-review']['[source, uri]'] ?? '') !== 'https://example.test/exports/packet#flow-explicit-key') {
        throw new RuntimeException('YAML metadata self-test missing flow explicit sequence key metadata');
    }
    if (($meta['flow-explicit-review']['{owner: desk, ticket: 7}'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing flow explicit map key metadata');
    }
    if (($meta['flow-explicit-review']['source:key'] ?? '') !== 'metadata: value') {
        throw new RuntimeException('YAML metadata self-test missing flow explicit quoted key metadata');
    }
    if (($meta['flow-explicit-reference']['metadata']['[source, key]'] ?? '') !== 'metadata value') {
        throw new RuntimeException('YAML metadata self-test missing nested flow explicit sequence key metadata');
    }
    if (($meta['flow-explicit-reference']['metadata']['{type: review}'] ?? '') !== 'kept') {
        throw new RuntimeException('YAML metadata self-test missing nested flow explicit map key metadata');
    }
    if (!array_key_exists('source', $meta['flow-explicit-null-review'] ?? []) || $meta['flow-explicit-null-review']['source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit null scalar key metadata');
    }
    if (!array_key_exists('[source, uri]', $meta['flow-explicit-null-review'] ?? []) || $meta['flow-explicit-null-review']['[source, uri]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit null sequence key metadata');
    }
    if (!array_key_exists('{owner: desk, ticket: 7}', $meta['flow-explicit-null-review'] ?? []) || $meta['flow-explicit-null-review']['{owner: desk, ticket: 7}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit null map key metadata');
    }
    if (!array_key_exists('source:key', $meta['flow-explicit-null-review'] ?? []) || $meta['flow-explicit-null-review']['source:key'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit null quoted key metadata');
    }
    if (($meta['flow-explicit-null-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing flow explicit null status metadata');
    }
    if (($meta['flow-explicit-null-reference']['metadata']['state'] ?? '') !== 'kept') {
        throw new RuntimeException('YAML metadata self-test missing nested flow explicit null reference state');
    }
    if (!array_key_exists('[source, key]', $meta['flow-explicit-null-reference']['metadata'] ?? []) || $meta['flow-explicit-null-reference']['metadata']['[source, key]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested flow explicit null sequence key metadata');
    }
    if (!array_key_exists('{type: review}', $meta['flow-explicit-null-reference']['metadata'] ?? []) || $meta['flow-explicit-null-reference']['metadata']['{type: review}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested flow explicit null map key metadata');
    }
    if (($meta['sequence-explicit-review-items'][0]['[source, uri]'] ?? '') !== 'https://example.test/exports/packet#sequence-explicit-item') {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit sequence key metadata');
    }
    if (($meta['sequence-explicit-review-items'][0]['labels'] ?? []) !== ['migration', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit-key labels');
    }
    if (($meta['sequence-explicit-review-items'][1]['{owner: desk, ticket: 7}'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit map key metadata');
    }
    if (($meta['sequence-explicit-review-items'][1]['source note'] ?? '') !== 'Reviewed by structured key') {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit-key source note');
    }
    if (($meta['sequence-explicit-review-items'][2]['source:key'] ?? '') !== 'metadata: value') {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit quoted key metadata');
    }
    if (array_key_exists('? [source, uri]', $meta['sequence-explicit-review-items'][0] ?? [])) {
        throw new RuntimeException('YAML metadata self-test leaked raw sequence item explicit key');
    }
    if (($meta['source label'] ?? '') !== 'Migration review') {
        throw new RuntimeException('YAML metadata self-test missing plain spaced source label');
    }
    if (($meta['plain-key-review']['source owner'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test missing nested plain spaced key metadata');
    }
    if (($meta['plain-key-items'][0]['review label'] ?? '') !== 'Compact reviewer label') {
        throw new RuntimeException('YAML metadata self-test missing compact plain spaced key metadata');
    }
    if (($meta['flow-plain-key-review']['source owner'] ?? '') !== 'Flow Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow plain spaced key metadata');
    }
    if (($meta['flow-colon-key-review']['source:key'] ?? '') !== 'metadata value') {
        throw new RuntimeException('YAML metadata self-test missing flow plain colon key metadata');
    }
    if (($meta['flow-colon-key-review']['dc:title'] ?? '') !== 'Source metadata title') {
        throw new RuntimeException('YAML metadata self-test missing flow dc title colon key metadata');
    }
    if (($meta['flow-colon-key-review']['source:uri'] ?? '') !== 'https://example.test/exports/packet#flow-colon-key') {
        throw new RuntimeException('YAML metadata self-test missing flow source URI colon key metadata');
    }
    if (array_key_exists('source', $meta['flow-colon-key-review'] ?? []) || array_key_exists('dc', $meta['flow-colon-key-review'] ?? [])) {
        throw new RuntimeException('YAML metadata self-test split a flow plain colon key too early');
    }
    if (($meta['flow-document-review']['priority'] ?? null) !== 2) {
        throw new RuntimeException('YAML metadata self-test missing top-level flow document integer metadata');
    }
    if (($meta['flow-document-review']['labels'] ?? []) !== ['flow', 'metadata']) {
        throw new RuntimeException('YAML metadata self-test missing top-level flow document label metadata');
    }
    if (($meta['flow-document-references'][0]['id'] ?? '') !== 'flow-document-ref') {
        throw new RuntimeException('YAML metadata self-test missing top-level flow document reference metadata');
    }
    if (($meta['flow-document-references'][0]['issued']['date-parts'][0] ?? []) !== [2026, 6, 5]) {
        throw new RuntimeException('YAML metadata self-test missing top-level flow document date-parts');
    }
    if (($meta['flow-document:no'] ?? '') !== 'quoted top-level flow field') {
        throw new RuntimeException('YAML metadata self-test missing quoted top-level flow field');
    }
    if (($meta['flow-document:15'] ?? '') !== 'quoted explicit flow key') {
        throw new RuntimeException('YAML metadata self-test missing quoted explicit top-level flow key');
    }
    if (array_key_exists('yes', $meta) || array_key_exists('True', $meta) || array_key_exists('15', $meta) || array_key_exists('0x2A', $meta)) {
        throw new RuntimeException('YAML metadata self-test promoted ambiguous top-level field names');
    }
    if (($meta['no'] ?? '') !== 'quoted boolean-looking source field') {
        throw new RuntimeException('YAML metadata self-test dropped quoted boolean-looking top-level field');
    }
    if (($meta['Off'] ?? '') !== 'quoted off-looking source field') {
        throw new RuntimeException('YAML metadata self-test dropped quoted off-looking top-level field');
    }
    if (($meta['3.14'] ?? '') !== 'quoted float-looking source field') {
        throw new RuntimeException('YAML metadata self-test dropped quoted float-looking top-level field');
    }
    if (($meta['0o52'] ?? '') !== 'quoted octal-looking source field') {
        throw new RuntimeException('YAML metadata self-test dropped quoted octal-looking top-level field');
    }
    if (array_intersect(['no', 'Off', '3.14', '0o52'], array_column($yamlDiagnostics, 'field')) !== []) {
        throw new RuntimeException('YAML metadata self-test flagged quoted ambiguous top-level field names');
    }
    if (($meta['ambiguous-field-review']['true'] ?? '') !== 'nested reviewer boolean key stays visible') {
        throw new RuntimeException('YAML metadata self-test dropped nested ambiguous reviewer key');
    }
    if (($meta['ambiguous-field-review'][15] ?? '') !== 'nested reviewer numeric key stays visible') {
        throw new RuntimeException('YAML metadata self-test dropped nested numeric reviewer key');
    }
    if (($meta['references'][0]['issued']['date-parts'][0] ?? []) !== [2026, 6, 3]) {
        throw new RuntimeException('YAML metadata self-test missing block-style date-parts');
    }
    if (($meta['aliases']['labels'] ?? []) !== ['front-matter', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing anchor alias labels');
    }
    if (($meta['alias-diagnostics']['self'] ?? '') !== '*alias_diag_self') {
        throw new RuntimeException('YAML metadata self-test missing self-referential alias audit value');
    }
    if (($meta['alias-diagnostics']['missing'] ?? '') !== '*missing_alias') {
        throw new RuntimeException('YAML metadata self-test missing unresolved alias audit value');
    }
    if (($meta['flow-alias-diagnostics']['owner'] ?? '') !== '*missing_flow_owner') {
        throw new RuntimeException('YAML metadata self-test missing flow unresolved alias audit value');
    }
    if (count($yamlDiagnostics) !== 7) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostics');
    }
    if (array_slice(array_column($yamlDiagnostics, 'reason'), 0, 4) !== ['ambiguous-field-name', 'ambiguous-field-name', 'ambiguous-field-name', 'ambiguous-field-name']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name diagnostics');
    }
    if (array_slice(array_column($yamlDiagnostics, 'field'), 0, 4) !== ['yes', 'True', '15', '0x2A']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name provenance');
    }
    if (array_slice(array_column($yamlDiagnostics, 'interpretedAs'), 0, 4) !== ['bool', 'bool', 'number', 'number']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name type provenance');
    }
    if (array_slice(array_column($yamlDiagnostics, 'reason'), 4) !== ['self-reference', 'unresolved-alias', 'unresolved-alias']) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic reasons');
    }
    if (array_column(array_slice($yamlDiagnostics, 4), 'path') !== ['/alias-diagnostics/self', '/alias-diagnostics/missing', '/flow-alias-diagnostics/owner']) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic metadata paths');
    }
    if (($yamlDiagnostics[4]['definedAnchor'] ?? '') !== 'alias_diag_self') {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic anchor provenance');
    }
    if (($meta['authors'][1] ?? '') !== 'WordPress #import editor') {
        throw new RuntimeException('YAML metadata self-test stripped quoted author hash');
    }
    if (($meta['source-summary'] ?? '') !== 'Preserve front matter comments before rendering.') {
        throw new RuntimeException('YAML metadata self-test missing folded source comment summary');
    }
    if (($meta['source-review-log'] ?? '') !== "Review steps:\n  - preserve front matter\n  - import blocks\nConfirm before publish.") {
        throw new RuntimeException('YAML metadata self-test missing folded source review log indentation');
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
    if (($meta['plain-continuation-review']['note'] ?? '') !== 'Imported reviewer plain scalar') {
        throw new RuntimeException('YAML metadata self-test missing plain multiline note folding');
    }
    if (($meta['plain-continuation-review']['paragraph'] ?? '') !== "First paragraph\nSecond paragraph") {
        throw new RuntimeException('YAML metadata self-test missing plain multiline blank-line folding');
    }
    if (($meta['plain-continuation-review']['steps'] ?? []) !== ['Collect source metadata packet', 'Approve WordPress import']) {
        throw new RuntimeException('YAML metadata self-test missing sequence-item plain multiline folding');
    }
    if (($meta['plain-continuation-reference']['metadata']['source note'] ?? '') !== 'Source reviewer plain scalar') {
        throw new RuntimeException('YAML metadata self-test missing nested reference plain multiline folding');
    }
    if (($meta['source-revision'] ?? '') !== '007') {
        throw new RuntimeException('YAML metadata self-test missing tagged string revision');
    }
    if (!str_contains($blocks, '<h1 id="imported-body">Imported Body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing imported body heading');
    }
    if (!str_contains($metadataMarkdown, "---\ntitle: \"Migration **Packet**\"")) {
        throw new RuntimeException('YAML metadata self-test missing writer YAML metadata block');
    }
    if (str_contains($metadataMarkdown, 'titleInlines') || str_contains($metadataMarkdown, 'authorInlines')) {
        throw new RuntimeException('YAML metadata self-test leaked derived inline metadata into writer front matter');
    }
    if (($metadataRoundTripMeta['review']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test failed writer metadata round trip');
    }
    if (($metadataRoundTripMeta['source-uri'] ?? '') !== '/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test lost quoted writer source URI during round trip');
    }
    if (($implicitOpeningMeta['title'] ?? '') !== 'Implicit **Packet**') {
        throw new RuntimeException('YAML metadata self-test missing omitted-opening title metadata');
    }
    if (($implicitOpeningMeta['review']['priority'] ?? null) !== 4) {
        throw new RuntimeException('YAML metadata self-test missing omitted-opening merge metadata');
    }
    if (($implicitOpeningMeta['references'][0]['id'] ?? '') !== 'implicit-yaml-ref') {
        throw new RuntimeException('YAML metadata self-test missing omitted-opening reference metadata');
    }
    if (!str_contains($implicitOpeningBlocks, '<h1 id="imported-body">Imported Body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing omitted-opening imported body heading');
    }
    if ($invalidBlockScalarDocument->attr('meta') !== null) {
        throw new RuntimeException('YAML metadata self-test accepted invalid unindented block scalar metadata');
    }
    if (!str_contains($invalidBlockScalarBlocks, '<p>title: Invalid block scalar <strong>Packet</strong>')) {
        throw new RuntimeException('YAML metadata self-test failed to keep invalid block scalar title source visible');
    }
    if (!str_contains($invalidBlockScalarBlocks, 'This source line is not indented relative to the block scalar.</p>')) {
        throw new RuntimeException('YAML metadata self-test failed to keep invalid block scalar body source visible');
    }

    echo "yaml metadata handoff self-test ok\n";
    return;
}

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Review labels: ' . implode(', ', $meta['review']['labels'] ?? []) . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo 'Writer YAML round-trip review: ' . ($metadataRoundTripMeta['review']['status'] ?? '') . "\n";
echo 'Review optional deadline is null: ' . ((array_key_exists('optional-deadline', $meta) && $meta['optional-deadline'] === null) ? 'yes' : 'no') . "\n";
echo 'Merge sequence review: ' . ($meta['merge-sequence-review']['status'] ?? '') . ' / priority ' . ($meta['merge-sequence-review']['priority'] ?? '') . "\n";
echo 'Explicit key review: ' . ($meta['explicit-review']['status'] ?? '') . ' / ' . ($meta['explicit-review']['source:key'] ?? '') . "\n";
echo 'Sequence key review: ' . ($meta['sequence-key-review']['[owner, desk]'] ?? '') . ' / ' . ($meta['[sequence, source-uri]'] ?? '') . "\n";
echo 'Map key review: ' . ($meta['map-key-review']['{owner: desk, ticket: 7}'] ?? '') . ' / ' . ($meta['{source: uri, type: review}'] ?? '') . "\n";
echo 'Flow explicit key review: ' . ($meta['flow-explicit-review']['[source, uri]'] ?? '') . ' / ' . ($meta['flow-explicit-review']['{owner: desk, ticket: 7}'] ?? '') . "\n";
echo 'Sequence item explicit key: ' . ($meta['sequence-explicit-review-items'][0]['[source, uri]'] ?? '') . ' / ' . ($meta['sequence-explicit-review-items'][1]['{owner: desk, ticket: 7}'] ?? '') . "\n";
echo 'Ordered review duplicate key: ' . ($meta['ordered-review']['steps'][0]['key'] ?? '') . ' => ' . ($meta['ordered-review']['steps'][0]['value'] ?? '') . ' / ' . ($meta['ordered-review']['steps'][1]['value'] ?? '') . "\n";
echo 'Plain key review: ' . ($meta['plain-key-review']['source owner'] ?? '') . ' / ' . ($meta['source label'] ?? '') . "\n";
echo 'Flow colon key review: ' . ($meta['flow-colon-key-review']['source:key'] ?? '') . ' / ' . ($meta['flow-colon-key-review']['dc:title'] ?? '') . "\n";
echo 'Flow document review: ' . ($meta['flow-document-review']['status'] ?? '') . ' / priority ' . ($meta['flow-document-review']['priority'] ?? '') . "\n";
echo 'Ambiguous field diagnostics: ' . implode(', ', array_column(array_slice($yamlDiagnostics, 0, 4), 'field')) . "\n";
echo 'Quoted ambiguous fields: ' . ($meta['no'] ?? '') . ' / ' . ($meta['Off'] ?? '') . ' / ' . ($meta['3.14'] ?? '') . ' / ' . ($meta['0o52'] ?? '') . "\n";
echo 'YAML alias diagnostics: ' . count($yamlDiagnostics) . "\n";
echo 'YAML alias diagnostic paths: ' . implode(', ', array_column(array_slice($yamlDiagnostics, 4), 'path')) . "\n";
echo 'YAML custom tag provenance: ' . count($yamlTagProvenance) . "\n";
echo 'YAML custom tag provenance paths: ' . implode(', ', array_filter(array_column($yamlTagProvenance, 'path'))) . "\n";
echo 'Compact sequence item: ' . ($meta['compact-review-items'][0]['label'] ?? '') . ' / ' . ($meta['compact-review-items'][1]['source:key'] ?? '') . "\n";
echo 'Source review log: ' . str_replace("\n", ' | ', $meta['source-review-log'] ?? '') . "\n";
echo 'Source revision: ' . ($meta['source-revision'] ?? '') . "\n";
echo 'Typed review revision: ' . ($meta['typed-review']['typed-revision'] ?? '') . ' / confidence ' . ($meta['typed-review']['confidence'] ?? '') . "\n";
echo 'Tag directive review: ' . ($meta['tag-directive-review']['owner'] ?? '') . ' / priority ' . ($meta['tag-directive-review']['priority'] ?? '') . "\n";
echo 'Non-specific tag review: ' . ($meta['non-specific-review']['owner'] ?? '') . ' / ' . implode(', ', $meta['non-specific-review']['labels'] ?? []) . "\n";
echo 'Source captured at: ' . ($meta['source-captured-at'] ?? '') . "\n";
echo 'Review binary bytes: ' . ($meta['review-binary']['note-bytes'] ?? '') . ' / ' . ($meta['review-binary']['digest-bytes'] ?? '') . "\n";
echo 'Multiline flow labels: ' . implode(', ', $meta['multiline-flow-labels'] ?? []) . "\n";
echo 'Flow comment labels: ' . implode(', ', $meta['flow-comment-labels'] ?? []) . "\n";
echo 'Escaped source title: ' . ($meta['escaped-source-title'] ?? '') . "\n";
echo 'Multiline source title: ' . ($meta['multiline-source-title'] ?? '') . "\n";
echo 'Single quoted source note: ' . ($meta['single-quoted-source-note'] ?? '') . "\n";
echo 'Plain continuation note: ' . ($meta['plain-continuation-review']['note'] ?? '') . "\n";
echo 'Reference: ' . ($meta['references'][0]['id'] ?? '') . ' / ' . ($meta['references'][0]['title'] ?? '') . "\n\n";
echo $blocks . "\n";
echo 'Implicit opening title: ' . ($implicitOpeningMeta['title'] ?? '') . "\n";
echo 'Implicit opening review: ' . ($implicitOpeningMeta['review']['status'] ?? '') . ' / priority ' . ($implicitOpeningMeta['review']['priority'] ?? '') . "\n";
echo 'Implicit opening reference: ' . ($implicitOpeningMeta['references'][0]['id'] ?? '') . "\n";
echo $implicitOpeningBlocks . "\n";
