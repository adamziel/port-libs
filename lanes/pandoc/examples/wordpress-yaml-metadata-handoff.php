<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
The source export starts with a migration preface before metadata.

--- # source export front matter
%YAML 1.2
%TAG !wpd! tag:directive.example,2026:
%TAG !bad tag:invalid.example,2026: # malformed handle for reviewer diagnostics
%TAG !yaml! tag:yaml.org,2002:
--- # YAML document starts after directives
title: "Migration **Packet**" # source export title
author:
  - Data Liberation reviewer
  - "WordPress #import editor"
date: 2026-06-03
keywords: [migration, wordpress, metadata] # reviewer labels
abstract: |
  Source abstract keeps **review** emphasis and [source](https://example.test/exports/packet#abstract).

  - Preserve front matter
  - Keep `source:key` audit
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
  review-duration-seconds: !!int 1:20:30
  review-duration-fractional: !!float 1:20:30.5
  invalid-review-duration: !!int 1:60
  invalid-review-duration-fractional: !!float 1:60.5
  confidence: !!float "0.75"
  approved: !!bool "true"
  legacy-approved: yes
  legacy-blocked: NO
  legacy-enabled: On
  legacy-disabled: off
  explicit-legacy-enabled: !!bool y
  quoted-legacy-approved: "yes"
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
typed-flow-review: {priority: !!int "4", elapsed: !!int 0:01:05, enabled: !!bool "false", ticket: !!str 009}
boolean-synonym-flow-review: {published: y, archived: n, enabled: ON, disabled: OFF, quoted: "off"}
tag-directive-review:
  owner: !wpd!reviewer Directive Desk
  ticket: !yaml!str 010
  priority: !yaml!int "10"
  labels: [!wpd!label directive, !wpd!label metadata]
flow-tag-directive-review: {? !wpd!key "source:key": !wpd!value directive metadata, owner: !wpd!reviewer Flow Directive Desk}
flow-key-tag-review: {? !wpd!key "source:key": directive key metadata}
tag-uri-suffix-review:
  owner: !wpd!source%2Fowner URI Suffix Desk
  source-uri: !wpd!source?kind=uri https://example.test/exports/packet#tag-uri-suffix
  fragment-owner: !wpd!source#fragment Fragment Desk
  scoped-owner: !wpd!source;kind=review&draft=false Scoped Desk
flow-tag-uri-suffix-review: {owner: !wpd!flow%2Fowner Flow URI Desk, ? !wpd!key%2Fsource "source:key": !wpd!value?kind=flow metadata value}
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
source-anchor-defaults_: &source:review/defaults {status: queued, priority: 11, labels: [source, review]}
source-anchor-review:
  <<: *source:review/defaults
  owner: Anchor Desk
flow-source-anchor-review: {defaults: *source:review/defaults, status: approved}
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
marker-literal-review: |
  Keep source marker-looking lines:
  ...
  --- # not the closing fence
  Preserve reviewer text.
marker-folded-review: >-
  First reviewer line
  ...
  second reviewer line
marker-sequence-review:
  - |-
    Preserve item marker
    ---
    without ending metadata.
  - >-
    Preserve folded item
    ...
    without ending metadata.
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
merge-tag-review:
  !!merge <<: [*merge_review_override, *merge_review_base]
  priority: 9
merge-tag-flow-review: {!!merge <<: *merge_review_base, reviewer: Tagged Flow Desk}
merge-tag-explicit-review:
  ? !!merge <<
  : *merge_review_base
  status: explicit-tagged
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
? !wpd!key [tagged, source-uri]
: "https://example.test/exports/packet#tagged-explicit-key"
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
?
  ? nested
  : source-uri
: https://example.test/exports/packet#nested-explicit-key
nested-explicit-key-review:
  ?
    ? owner
    : desk
  : queued
  labels: !!set
    ?
      ? source
      : label
    ? status
nested-explicit-reference:
  id: nested-explicit-key-ref
  metadata:
    ?
      ? source
      : key
    : metadata value
flow-explicit-review: {? [source, uri]: https://example.test/exports/packet#flow-explicit-key, ? {owner: desk, ticket: 7}: queued, ? "source:key": "metadata: value"}
flow-explicit-reference:
  id: flow-explicit-key-ref
  metadata: {? [source, key]: metadata value, ? {type: review}: kept}
flow-explicit-null-review: {? source, ? [source, uri], ? {owner: desk, ticket: 7}, ? "source:key", status: approved}
flow-explicit-null-reference:
  id: flow-explicit-null-key-ref
  metadata: {? [source, key], ? {type: review}, state: kept}
flow-implicit-null-review: {source, [source, uri], {owner: desk, ticket: 7}, "source:key", status: approved}
flow-implicit-null-reference:
  id: flow-implicit-null-key-ref
  metadata: {[source, key], {type: review}, state: kept}
block-explicit-null-review:
  ? source
  ? "source:key"
  ? [source, uri]
  ? {owner: desk, ticket: 7}
  ? !wpd!key tagged-source
  status: approved
block-explicit-null-reference:
  id: block-explicit-null-key-ref
  metadata:
    ? [source, key]
    ? {type: review}
    state: kept
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
sequence-explicit-null-review-items:
  - ? source
  - ? [source, uri]
  - ? {owner: desk, ticket: 7}
  - ? !wpd!key tagged-source
    status: queued
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
  indented-note:
    Queue log
      source: wp-export.xml
      status: pending
    Ready.
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
    source outline:
      Reviewer outline
        - collect metadata
        - confirm blocks
      Done.
punctuation-anchor-references:
  - &source/ref-primary
    id: anchor-punctuation-ref
    title: Anchor punctuation source
    metadata: {owner: Anchor Desk, stage: collected}
  - <<: *source/ref-primary
    id: anchor-punctuation-copy
    metadata: {stage: copied}
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

default-clip-note: | # default clip keeps one final newline
  YAML parser clips this note.
default-folded-note: > # default folded clip keeps one final newline
  Fold reviewer note before
  WordPress handoff.

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
--- # source metadata block ends before body

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
$yamlDirectiveProvenance = $document->attr('yamlMetadataDirectiveProvenance', []);
$yamlCommentProvenance = $document->attr('yamlMetadataCommentProvenance', []);
$yamlAnchorProvenance = $document->attr('yamlMetadataAnchorProvenance', []);
$yamlScalarProvenance = $document->attr('yamlMetadataScalarProvenance', []);
$yamlCollectionProvenance = $document->attr('yamlMetadataCollectionProvenance', []);
$yamlStreamProvenance = $document->attr('yamlMetadataStreamProvenance', []);
$invalidTagDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-tag-directive'
));
$ambiguousYamlDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'ambiguous-field-name'
));
$aliasYamlDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['type'] ?? '') === 'yaml-alias'
));
$mergeShadowDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'merge-sequence-shadowed-key'
));
$blocks = (new WordPressBlockWriter())->write($document);
$abstractBlocks = $meta['abstractBlocks'] ?? [];
$abstractWordPress = $abstractBlocks === []
    ? ''
    : (new WordPressBlockWriter())->write(new AstNode('document', [], $abstractBlocks));
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

$lateInvalidBlockScalarMarkdown = <<<'MARKDOWN'
---
title: Late invalid block scalar **Packet**
abstract: |
  First source line is indented.
Second source line is not indented relative to the block scalar.
---

# Late invalid scalar body
MARKDOWN;

$lateInvalidBlockScalarDocument = (new MarkdownReader())->read($lateInvalidBlockScalarMarkdown);
$lateInvalidBlockScalarBlocks = (new WordPressBlockWriter())->write($lateInvalidBlockScalarDocument);

$duplicateKeyMarkdown = <<<'MARKDOWN'
---
title: Duplicate key packet
review:
  status: queued
  status: approved
flow-review: {owner: Import Desk, owner: QA Desk}
...

# Duplicate key body
MARKDOWN;

$duplicateKeyDocument = (new MarkdownReader())->read($duplicateKeyMarkdown);
$duplicateKeyMeta = $duplicateKeyDocument->attr('meta', []);
$duplicateKeyDiagnostics = $duplicateKeyDocument->attr('yamlMetadataDiagnostics', []);
$duplicateKeyBlocks = (new WordPressBlockWriter())->write($duplicateKeyDocument);

$specialFloatMarkdown = <<<'MARKDOWN'
---
title: Special float packet
review:
  positive-infinity: !!float .Inf
  negative-infinity: !!float -.inf
  not-a-number: !!float .NaN
  invalid-special: !!float .infinite
flow-review: {ceiling: !!float +.INF, missing: !!float .nan}
...

# Special float body
MARKDOWN;

$specialFloatDocument = (new MarkdownReader())->read($specialFloatMarkdown);
$specialFloatMeta = $specialFloatDocument->attr('meta', []);

$plainNumericMarkdown = <<<'MARKDOWN'
---
title: Plain numeric packet
review:
  decimal: 1_024
  signed-decimal: -1_024
  hexadecimal: 0x2A
  negative-hexadecimal: -0x2a
  binary: 0b101010
  octal: 0o52
  legacy-octal: 052
  sexagesimal: 1:20:30
  sexagesimal-float: 1:20:30.5
  signed-sexagesimal-float: -0:00:02.25
  invalid-sexagesimal: 1:60
  invalid-sexagesimal-float: 1:60.5
  decimal-float: 1_024.5
  exponent: 1.2e2
  positive-infinity: .inf
  negative-infinity: -.INF
  not-a-number: .NaN
  quoted-decimal: "1_024"
flow-review: {priority: 0o52, bits: 0b101010, score: +.INF, quoted-hex: "0x2A"}
references:
  - id: plain-numeric-ref
    metadata: {duration: 2:03, duration-float: 2:03.5, ratio: .5, quoted-ratio: ".5"}
...

# Plain numeric body
MARKDOWN;

$plainNumericDocument = (new MarkdownReader())->read($plainNumericMarkdown);
$plainNumericMeta = $plainNumericDocument->attr('meta', []);

if (($argv[1] ?? '') === '--self-test') {
    if (($meta['review']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing later review override');
    }
    if (!in_array('1.2', array_column($yamlDirectiveProvenance, 'version'), true)) {
        throw new RuntimeException('YAML metadata self-test missing YAML directive version provenance');
    }
    $yamlTagDirectives = array_values(array_filter(
        $yamlDirectiveProvenance,
        static fn (array $directive): bool => ($directive['directive'] ?? '') === 'TAG'
    ));
    if (array_column($yamlTagDirectives, 'handle') !== ['!wpd!', '!yaml!']) {
        throw new RuntimeException('YAML metadata self-test missing TAG directive handles');
    }
    if (array_column($yamlTagDirectives, 'prefix') !== ['tag:directive.example,2026:', 'tag:yaml.org,2002:']) {
        throw new RuntimeException('YAML metadata self-test missing TAG directive prefixes');
    }
    if (array_column($yamlTagDirectives, 'sourceLine') !== ['5', '7']) {
        throw new RuntimeException('YAML metadata self-test missing TAG directive source lines');
    }
    if (count($invalidTagDiagnostics) !== 1) {
        throw new RuntimeException('YAML metadata self-test missing invalid TAG directive diagnostic');
    }
    if (($invalidTagDiagnostics[0]['source'] ?? '') !== '%TAG !bad tag:invalid.example,2026:') {
        throw new RuntimeException('YAML metadata self-test missing invalid TAG directive source');
    }
    if (($invalidTagDiagnostics[0]['expected'] ?? '') !== '%TAG <handle> <prefix>') {
        throw new RuntimeException('YAML metadata self-test missing invalid TAG directive expectation');
    }
    if (($meta['abstract'] ?? '') !== "Source abstract keeps **review** emphasis and [source](https://example.test/exports/packet#abstract).\n\n- Preserve front matter\n- Keep `source:key` audit\n") {
        throw new RuntimeException('YAML metadata self-test failed to preserve raw abstract metadata');
    }
    if (
        !isset($meta['abstractBlocks'][0], $meta['abstractBlocks'][1])
        || !$meta['abstractBlocks'][0] instanceof AstNode
        || !$meta['abstractBlocks'][1] instanceof AstNode
        || $meta['abstractBlocks'][0]->type !== 'paragraph'
        || $meta['abstractBlocks'][1]->type !== 'bullet_list'
    ) {
        throw new RuntimeException('YAML metadata self-test missing parsed abstract block metadata');
    }
    if (
        !str_contains($abstractWordPress, '<strong>review</strong>')
        || !str_contains($abstractWordPress, '<a href="https://example.test/exports/packet#abstract">source</a>')
        || !str_contains($abstractWordPress, '<code>source:key</code>')
    ) {
        throw new RuntimeException('YAML metadata self-test missing WordPress abstract block handoff');
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
    if (($meta['typed-review']['review-duration-seconds'] ?? null) !== 4830) {
        throw new RuntimeException('YAML metadata self-test missing explicit sexagesimal integer coercion');
    }
    if (($meta['typed-review']['review-duration-fractional'] ?? null) !== 4830.5) {
        throw new RuntimeException('YAML metadata self-test missing explicit sexagesimal float coercion');
    }
    if (($meta['typed-review']['invalid-review-duration'] ?? null) !== '1:60') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid sexagesimal source text');
    }
    if (($meta['typed-review']['invalid-review-duration-fractional'] ?? null) !== '1:60.5') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid sexagesimal float source text');
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
    if (($meta['typed-review']['legacy-approved'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing yes boolean synonym');
    }
    if (($meta['typed-review']['legacy-blocked'] ?? null) !== false) {
        throw new RuntimeException('YAML metadata self-test missing NO boolean synonym');
    }
    if (($meta['typed-review']['legacy-enabled'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing On boolean synonym');
    }
    if (($meta['typed-review']['legacy-disabled'] ?? null) !== false) {
        throw new RuntimeException('YAML metadata self-test missing off boolean synonym');
    }
    if (($meta['typed-review']['explicit-legacy-enabled'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit y boolean synonym');
    }
    if (($meta['typed-review']['quoted-legacy-approved'] ?? null) !== 'yes') {
        throw new RuntimeException('YAML metadata self-test failed to preserve quoted yes string');
    }
    if (($meta['boolean-synonym-flow-review']['published'] ?? null) !== true || ($meta['boolean-synonym-flow-review']['archived'] ?? null) !== false) {
        throw new RuntimeException('YAML metadata self-test missing flow y/n boolean synonyms');
    }
    if (($meta['boolean-synonym-flow-review']['enabled'] ?? null) !== true || ($meta['boolean-synonym-flow-review']['disabled'] ?? null) !== false) {
        throw new RuntimeException('YAML metadata self-test missing flow on/off boolean synonyms');
    }
    if (($meta['boolean-synonym-flow-review']['quoted'] ?? null) !== 'off') {
        throw new RuntimeException('YAML metadata self-test failed to preserve quoted off string');
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
    if (($meta['typed-flow-review']['elapsed'] ?? null) !== 65) {
        throw new RuntimeException('YAML metadata self-test missing flow sexagesimal integer coercion');
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
    if (($meta['flow-key-tag-review']['source:key'] ?? '') !== 'directive key metadata') {
        throw new RuntimeException('YAML metadata self-test missing flow custom-tagged explicit key metadata');
    }
    if (($meta['tag-uri-suffix-review']['owner'] ?? '') !== 'URI Suffix Desk') {
        throw new RuntimeException('YAML metadata self-test missing percent-escaped tag URI suffix owner metadata');
    }
    if (($meta['tag-uri-suffix-review']['source-uri'] ?? '') !== 'https://example.test/exports/packet#tag-uri-suffix') {
        throw new RuntimeException('YAML metadata self-test missing query tag URI suffix source URI');
    }
    if (($meta['tag-uri-suffix-review']['fragment-owner'] ?? '') !== 'Fragment Desk') {
        throw new RuntimeException('YAML metadata self-test missing fragment tag URI suffix metadata');
    }
    if (($meta['tag-uri-suffix-review']['scoped-owner'] ?? '') !== 'Scoped Desk') {
        throw new RuntimeException('YAML metadata self-test missing scoped tag URI suffix metadata');
    }
    if (($meta['flow-tag-uri-suffix-review']['owner'] ?? '') !== 'Flow URI Desk') {
        throw new RuntimeException('YAML metadata self-test missing flow tag URI suffix owner metadata');
    }
    if (($meta['flow-tag-uri-suffix-review']['source:key'] ?? '') !== 'metadata value') {
        throw new RuntimeException('YAML metadata self-test missing flow tag URI suffix explicit key metadata');
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
    foreach ([
        '!<tag:directive.example,2026:source%2Fowner>',
        '!<tag:directive.example,2026:source?kind=uri>',
        '!<tag:directive.example,2026:source#fragment>',
        '!<tag:directive.example,2026:source;kind=review&draft=false>',
        '!<tag:directive.example,2026:flow%2Fowner>',
        '!<tag:directive.example,2026:key%2Fsource>',
        '!<tag:directive.example,2026:value?kind=flow>',
    ] as $expectedUriTag) {
        if (!in_array($expectedUriTag, $yamlTags, true)) {
            throw new RuntimeException('YAML metadata self-test missing URI suffix tag provenance ' . $expectedUriTag);
        }
    }
    if (in_array('!!str', $yamlTags, true) || in_array('!!merge', $yamlTags, true) || in_array('!', $yamlTags, true)) {
        throw new RuntimeException('YAML metadata self-test confused core/non-specific tags with custom tag provenance');
    }
    $yamlTagPaths = array_column($yamlTagProvenance, 'path');
    foreach (['/review/owner', '/tag-directive-review/labels/0', '/flow-tag-directive-review/source:key', '/tag-uri-suffix-review/owner', '/tag-uri-suffix-review/source-uri', '/tag-uri-suffix-review/fragment-owner', '/tag-uri-suffix-review/scoped-owner', '/flow-tag-uri-suffix-review/owner', '/flow-tag-uri-suffix-review/source:key', '/block-explicit-null-review/tagged-source', '/verbatim-tag-review/source-uri'] as $expectedPath) {
        if (!in_array($expectedPath, $yamlTagPaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing custom tag provenance path ' . $expectedPath);
        }
    }
    $foundFlowKeyTagPath = false;
    $foundBlockKeyTagPath = false;
    foreach ($yamlTagProvenance as $entry) {
        if (($entry['tag'] ?? '') !== '!<tag:directive.example,2026:key>') {
            continue;
        }

        $foundFlowKeyTagPath = $foundFlowKeyTagPath || (($entry['path'] ?? '') === '/flow-key-tag-review/source:key');
        $foundBlockKeyTagPath = $foundBlockKeyTagPath || (($entry['path'] ?? '') === '/[tagged, source-uri]');
    }
    if (!$foundFlowKeyTagPath) {
        throw new RuntimeException('YAML metadata self-test missing flow explicit-key tag provenance path');
    }
    if (!$foundBlockKeyTagPath) {
        throw new RuntimeException('YAML metadata self-test missing block explicit-key tag provenance path');
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
    if (($meta['source-anchor-review']['status'] ?? '') !== 'queued' || ($meta['source-anchor-review']['priority'] ?? null) !== 11) {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor merge metadata');
    }
    if (($meta['source-anchor-review']['labels'] ?? []) !== ['source', 'review']) {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor label metadata');
    }
    if (($meta['source-anchor-review']['owner'] ?? '') !== 'Anchor Desk') {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor explicit owner');
    }
    if (($meta['flow-source-anchor-review']['defaults']['priority'] ?? null) !== 11) {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor flow alias metadata');
    }
    if (($meta['flow-source-anchor-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor flow status');
    }
    if (array_key_exists('__yamlMetadataAnchorProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked anchor provenance into plain metadata');
    }
    if (array_key_exists('__yamlMetadataStreamProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked stream provenance into plain metadata');
    }
    if (array_key_exists('__yamlMetadataScalarProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked scalar provenance into plain metadata');
    }
    if (array_key_exists('__yamlMetadataCollectionProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked collection provenance into plain metadata');
    }
    $yamlPlainScalarPaths = [];
    foreach ($yamlScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-plain-scalar') {
            $yamlPlainScalarPaths[] = ($entry['path'] ?? '') . "\0" . ($entry['contentLineCount'] ?? '');
        }
    }
    foreach ([
        '/plain-continuation-review/note' . "\0" . '2',
        '/plain-continuation-review/steps/0' . "\0" . '2',
        '/plain-continuation-reference/metadata/source note' . "\0" . '2',
        '/plain-continuation-reference/metadata/source outline' . "\0" . '4',
    ] as $expectedPlainScalarPath) {
        if (!in_array($expectedPlainScalarPath, $yamlPlainScalarPaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing plain scalar provenance ' . str_replace("\0", ' ', $expectedPlainScalarPath));
        }
    }
    $yamlTypedScalarProvenance = [];
    foreach ($yamlScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-typed-scalar') {
            $yamlTypedScalarProvenance[$entry['path'] ?? ''] = $entry;
        }
    }
    foreach ([
        '/typed-review/typed-revision' => ['number', 'int', '"007"'],
        '/typed-review/approved' => ['boolean', 'bool', '"true"'],
        '/typed-review/withdrawn' => ['null', 'null', '"not carried"'],
        '/source-captured-at' => ['timestamp', 'timestamp', '2026-06-05 06:46:51Z'],
        '/typed-flow-review/elapsed' => ['number', 'int', '0:01:05'],
        '/boolean-synonym-flow-review/published' => ['boolean', null, 'y'],
    ] as $expectedTypedPath => [$expectedType, $expectedTag, $expectedSource]) {
        $entry = $yamlTypedScalarProvenance[$expectedTypedPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing typed scalar provenance ' . $expectedTypedPath);
        }
        if (($entry['scalarType'] ?? '') !== $expectedType || ($entry['source'] ?? '') !== $expectedSource) {
            throw new RuntimeException('YAML metadata self-test has wrong typed scalar provenance ' . $expectedTypedPath);
        }
        if ($expectedTag !== null && ($entry['explicitTag'] ?? '') !== $expectedTag) {
            throw new RuntimeException('YAML metadata self-test missing typed scalar explicit tag ' . $expectedTypedPath);
        }
    }
    if (array_key_exists('/typed-review/quoted-legacy-approved', $yamlTypedScalarProvenance)) {
        throw new RuntimeException('YAML metadata self-test recorded quoted yes as a typed scalar');
    }
    $yamlQuotedScalarProvenance = [];
    foreach ($yamlScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-quoted-scalar') {
            $yamlQuotedScalarProvenance[$entry['path'] ?? ''] = $entry;
        }
    }
    foreach ([
        '/title' => ['double-quoted', '1'],
        '/author/1' => ['double-quoted', '1'],
        '/typed-review/typed-revision' => ['double-quoted', '1'],
        '/typed-review/approved' => ['double-quoted', '1'],
        '/typed-review/quoted-legacy-approved' => ['double-quoted', '1'],
        '/escaped-source-title' => ['double-quoted', '1'],
        '/escaped-source-uri' => ['double-quoted', '1'],
        '/multiline-source-title' => ['double-quoted', '2'],
        '/source-continuation-uri' => ['double-quoted', '2'],
        '/single-quoted-source-note' => ['single-quoted', '2'],
        '/single-quoted-labels/0' => ['single-quoted', '1'],
        '/multiline-flow-review/owners/1' => ['double-quoted', '1'],
        '/flow-quoted-review/note' => ['double-quoted', '2'],
        '/flow-quoted-review/owner' => ['single-quoted', '2'],
        '/flow-comment-review/note' => ['double-quoted', '1'],
        '/boolean-synonym-flow-review/quoted' => ['double-quoted', '1'],
    ] as $expectedQuotedPath => [$expectedStyle, $expectedLineCount]) {
        $entry = $yamlQuotedScalarProvenance[$expectedQuotedPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing quoted scalar provenance ' . $expectedQuotedPath);
        }
        if (($entry['style'] ?? '') !== $expectedStyle || ($entry['sourceLineCount'] ?? '') !== $expectedLineCount) {
            throw new RuntimeException('YAML metadata self-test has wrong quoted scalar provenance ' . $expectedQuotedPath);
        }
    }
    $yamlCollectionPairs = [];
    $yamlCollectionByPath = [];
    foreach ($yamlCollectionProvenance as $entry) {
        $yamlCollectionPairs[] = ($entry['path'] ?? '') . "\0" . ($entry['kind'] ?? '') . "\0" . ($entry['style'] ?? '') . "\0" . ($entry['memberCount'] ?? '');
        $yamlCollectionByPath[$entry['path'] ?? ''] = $entry;
    }
    foreach ([
        '/plain-continuation-review' . "\0" . 'mapping' . "\0" . 'block' . "\0" . '4',
        '/plain-continuation-review/steps' . "\0" . 'sequence' . "\0" . 'block' . "\0" . '2',
        '/sequence-explicit-review-items' . "\0" . 'sequence' . "\0" . 'block' . "\0" . '3',
        '/sequence-explicit-review-items/0' . "\0" . 'mapping' . "\0" . 'block' . "\0" . '3',
        '/references' . "\0" . 'sequence' . "\0" . 'block' . "\0" . '1',
        '/references/0' . "\0" . 'mapping' . "\0" . 'block' . "\0" . '4',
        '/flow-document-review' . "\0" . 'mapping' . "\0" . 'flow' . "\0" . '3',
        '/flow-document-review/labels' . "\0" . 'sequence' . "\0" . 'flow' . "\0" . '2',
    ] as $expectedCollectionPair) {
        if (!in_array($expectedCollectionPair, $yamlCollectionPairs, true)) {
            throw new RuntimeException('YAML metadata self-test missing collection provenance ' . str_replace("\0", ' ', $expectedCollectionPair));
        }
    }
    $foundPlainReviewCollectionRange = false;
    foreach ($yamlCollectionProvenance as $entry) {
        if (($entry['path'] ?? '') !== '/plain-continuation-review') {
            continue;
        }

        $foundPlainReviewCollectionRange = (($entry['contentStartLine'] ?? '') !== '')
            && (($entry['contentEndLine'] ?? '') !== '')
            && ((int) ($entry['contentEndLine'] ?? '0') > (int) ($entry['contentStartLine'] ?? '0'));
    }
    if (!$foundPlainReviewCollectionRange) {
        throw new RuntimeException('YAML metadata self-test missing block collection line range');
    }
    $multilineFlowReviewLine = (int) ($yamlCollectionByPath['/multiline-flow-review']['sourceLine'] ?? '0');
    $multilineFlowOwnersLine = (int) ($yamlCollectionByPath['/multiline-flow-review/owners']['sourceLine'] ?? '0');
    $multilineFlowOwnerQuoteLine = (int) ($yamlQuotedScalarProvenance['/multiline-flow-review/owners/1']['sourceLine'] ?? '0');
    if (
        $multilineFlowReviewLine <= 0
        || $multilineFlowOwnersLine <= $multilineFlowReviewLine
        || $multilineFlowOwnerQuoteLine <= $multilineFlowOwnersLine
    ) {
        throw new RuntimeException('YAML metadata self-test missing multiline flow member source-line provenance');
    }
    if (count($yamlStreamProvenance) !== 3) {
        throw new RuntimeException('YAML metadata self-test missing stream provenance records');
    }
    if (array_column($yamlStreamProvenance, 'source') !== ['explicit', 'explicit', 'explicit']) {
        throw new RuntimeException('YAML metadata self-test missing explicit stream source provenance');
    }
    if (!str_contains($yamlStreamProvenance[0]['fields'] ?? '', '"title"')) {
        throw new RuntimeException('YAML metadata self-test missing first stream title field provenance');
    }
    if (($yamlStreamProvenance[1]['fields'] ?? '') !== '["review","summary"]') {
        throw new RuntimeException('YAML metadata self-test missing second stream override field provenance');
    }
    if (!str_contains($yamlStreamProvenance[2]['fields'] ?? '', '"flow-document-review"')) {
        throw new RuntimeException('YAML metadata self-test missing flow stream field provenance');
    }
    $yamlAnchorPairs = [];
    foreach ($yamlAnchorProvenance as $entry) {
        $yamlAnchorPairs[] = ($entry['anchor'] ?? '') . "\0" . ($entry['path'] ?? '') . "\0" . ($entry['kind'] ?? '');
    }
    foreach ([
        "&review_defaults\0/reviewDefaults_\0mapping",
        "&review_labels\0/reviewDefaults_/labels\0sequence",
        "&source:review/defaults\0/source-anchor-defaults_\0mapping",
        "&source/ref-primary\0/punctuation-anchor-references/0\0mapping",
        "&alias_diag_self\0/alias-diagnostics/self\0scalar",
        "&source_reference\0/references/0\0mapping",
    ] as $expectedAnchorPair) {
        if (!in_array($expectedAnchorPair, $yamlAnchorPairs, true)) {
            throw new RuntimeException('YAML metadata self-test missing anchor provenance ' . str_replace("\0", ' ', $expectedAnchorPair));
        }
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
    if (($meta['marker-literal-review'] ?? '') !== "Keep source marker-looking lines:\n...\n--- # not the closing fence\nPreserve reviewer text.\n") {
        throw new RuntimeException('YAML metadata self-test ended metadata at an indented literal block marker');
    }
    if (($meta['marker-folded-review'] ?? '') !== 'First reviewer line ... second reviewer line') {
        throw new RuntimeException('YAML metadata self-test ended metadata at an indented folded block marker');
    }
    if (($meta['marker-sequence-review'][0] ?? '') !== "Preserve item marker\n---\nwithout ending metadata.") {
        throw new RuntimeException('YAML metadata self-test ended metadata at an indented sequence literal marker');
    }
    if (($meta['marker-sequence-review'][1] ?? '') !== 'Preserve folded item ... without ending metadata.') {
        throw new RuntimeException('YAML metadata self-test ended metadata at an indented sequence folded marker');
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
    $mergeShadowPaths = array_column($mergeShadowDiagnostics, 'path');
    foreach (['/merge-sequence-review/status', '/merge-sequence-review/labels', '/merge-sequence-audit/status', '/flow-merge-review/status'] as $expectedPath) {
        if (!in_array($expectedPath, $mergeShadowPaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing merge precedence diagnostic path ' . $expectedPath);
        }
    }
    if (($meta['merge-tag-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing explicit merge-tag block merge');
    }
    if (($meta['merge-tag-review']['priority'] ?? null) !== 9) {
        throw new RuntimeException('YAML metadata self-test missing explicit merge-tag override');
    }
    if (($meta['merge-tag-flow-review']['status'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing explicit merge-tag flow merge');
    }
    if (($meta['merge-tag-flow-review']['reviewer'] ?? '') !== 'Tagged Flow Desk') {
        throw new RuntimeException('YAML metadata self-test missing explicit merge-tag flow override');
    }
    if (($meta['merge-tag-explicit-review']['status'] ?? '') !== 'explicit-tagged') {
        throw new RuntimeException('YAML metadata self-test missing explicit-key merge-tag override');
    }
    if (($meta['merge-tag-explicit-review']['priority'] ?? null) !== 5) {
        throw new RuntimeException('YAML metadata self-test missing explicit-key merge-tag inherited priority');
    }
    if (array_key_exists('!!merge <<', $meta['merge-tag-review'] ?? []) || array_key_exists('!!merge <<', $meta['merge-tag-flow-review'] ?? [])) {
        throw new RuntimeException('YAML metadata self-test leaked raw explicit merge-tag key');
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
    if (($meta['[tagged, source-uri]'] ?? '') !== 'https://example.test/exports/packet#tagged-explicit-key') {
        throw new RuntimeException('YAML metadata self-test missing custom-tagged explicit sequence key metadata');
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
    if (($meta['{nested: source-uri}'] ?? '') !== 'https://example.test/exports/packet#nested-explicit-key') {
        throw new RuntimeException('YAML metadata self-test missing nested explicit mapping source URI key');
    }
    if (($meta['nested-explicit-key-review']['{owner: desk}'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing nested explicit mapping owner key');
    }
    if (!array_key_exists('{source: label}', $meta['nested-explicit-key-review']['labels'] ?? []) || $meta['nested-explicit-key-review']['labels']['{source: label}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested explicit mapping set key');
    }
    if (($meta['nested-explicit-reference']['metadata']['{source: key}'] ?? '') !== 'metadata value') {
        throw new RuntimeException('YAML metadata self-test missing nested explicit mapping reference metadata');
    }
    if (array_key_exists('{nested: null}', $meta) || array_key_exists('nested', $meta['nested-explicit-key-review']['labels'] ?? [])) {
        throw new RuntimeException('YAML metadata self-test leaked partial nested explicit mapping key');
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
    if (!array_key_exists('source', $meta['flow-implicit-null-review'] ?? []) || $meta['flow-implicit-null-review']['source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow implicit null scalar key metadata');
    }
    if (!array_key_exists('[source, uri]', $meta['flow-implicit-null-review'] ?? []) || $meta['flow-implicit-null-review']['[source, uri]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow implicit null sequence key metadata');
    }
    if (!array_key_exists('{owner: desk, ticket: 7}', $meta['flow-implicit-null-review'] ?? []) || $meta['flow-implicit-null-review']['{owner: desk, ticket: 7}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow implicit null map key metadata');
    }
    if (!array_key_exists('source:key', $meta['flow-implicit-null-review'] ?? []) || $meta['flow-implicit-null-review']['source:key'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing flow implicit null quoted key metadata');
    }
    if (($meta['flow-implicit-null-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing flow implicit null status metadata');
    }
    if (($meta['flow-implicit-null-reference']['metadata']['state'] ?? '') !== 'kept') {
        throw new RuntimeException('YAML metadata self-test missing nested flow implicit null reference state');
    }
    if (!array_key_exists('[source, key]', $meta['flow-implicit-null-reference']['metadata'] ?? []) || $meta['flow-implicit-null-reference']['metadata']['[source, key]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested flow implicit null sequence key metadata');
    }
    if (!array_key_exists('{type: review}', $meta['flow-implicit-null-reference']['metadata'] ?? []) || $meta['flow-implicit-null-reference']['metadata']['{type: review}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested flow implicit null map key metadata');
    }
    if (!array_key_exists('source', $meta['block-explicit-null-review'] ?? []) || $meta['block-explicit-null-review']['source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing block explicit null scalar key metadata');
    }
    if (!array_key_exists('[source, uri]', $meta['block-explicit-null-review'] ?? []) || $meta['block-explicit-null-review']['[source, uri]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing block explicit null sequence key metadata');
    }
    if (!array_key_exists('{owner: desk, ticket: 7}', $meta['block-explicit-null-review'] ?? []) || $meta['block-explicit-null-review']['{owner: desk, ticket: 7}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing block explicit null map key metadata');
    }
    if (!array_key_exists('source:key', $meta['block-explicit-null-review'] ?? []) || $meta['block-explicit-null-review']['source:key'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing block explicit null quoted key metadata');
    }
    if (!array_key_exists('tagged-source', $meta['block-explicit-null-review'] ?? []) || $meta['block-explicit-null-review']['tagged-source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing block explicit null tagged key metadata');
    }
    if (($meta['block-explicit-null-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing block explicit null status metadata');
    }
    if (($meta['block-explicit-null-reference']['metadata']['state'] ?? '') !== 'kept') {
        throw new RuntimeException('YAML metadata self-test missing nested block explicit null reference state');
    }
    if (!array_key_exists('[source, key]', $meta['block-explicit-null-reference']['metadata'] ?? []) || $meta['block-explicit-null-reference']['metadata']['[source, key]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested block explicit null sequence key metadata');
    }
    if (!array_key_exists('{type: review}', $meta['block-explicit-null-reference']['metadata'] ?? []) || $meta['block-explicit-null-reference']['metadata']['{type: review}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing nested block explicit null map key metadata');
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
    if (!array_key_exists('source', $meta['sequence-explicit-null-review-items'][0] ?? []) || $meta['sequence-explicit-null-review-items'][0]['source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit null scalar key metadata');
    }
    if (!array_key_exists('[source, uri]', $meta['sequence-explicit-null-review-items'][1] ?? []) || $meta['sequence-explicit-null-review-items'][1]['[source, uri]'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit null sequence key metadata');
    }
    if (!array_key_exists('{owner: desk, ticket: 7}', $meta['sequence-explicit-null-review-items'][2] ?? []) || $meta['sequence-explicit-null-review-items'][2]['{owner: desk, ticket: 7}'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit null map key metadata');
    }
    if (!array_key_exists('tagged-source', $meta['sequence-explicit-null-review-items'][3] ?? []) || $meta['sequence-explicit-null-review-items'][3]['tagged-source'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit null tagged key metadata');
    }
    if (($meta['sequence-explicit-null-review-items'][3]['status'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing sequence item explicit null child metadata');
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
    if (count($aliasYamlDiagnostics) !== 3) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostics');
    }
    if (array_column($ambiguousYamlDiagnostics, 'reason') !== ['ambiguous-field-name', 'ambiguous-field-name', 'ambiguous-field-name', 'ambiguous-field-name']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name diagnostics');
    }
    if (array_column($ambiguousYamlDiagnostics, 'field') !== ['yes', 'True', '15', '0x2A']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name provenance');
    }
    if (array_column($ambiguousYamlDiagnostics, 'interpretedAs') !== ['bool', 'bool', 'number', 'number']) {
        throw new RuntimeException('YAML metadata self-test missing ambiguous field-name type provenance');
    }
    if (array_column($aliasYamlDiagnostics, 'reason') !== ['self-reference', 'unresolved-alias', 'unresolved-alias']) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic reasons');
    }
    if (array_column($aliasYamlDiagnostics, 'path') !== ['/alias-diagnostics/self', '/alias-diagnostics/missing', '/flow-alias-diagnostics/owner']) {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic metadata paths');
    }
    if (($aliasYamlDiagnostics[0]['definedAnchor'] ?? '') !== 'alias_diag_self') {
        throw new RuntimeException('YAML metadata self-test missing alias diagnostic anchor provenance');
    }
    $yamlCommentPairs = [];
    foreach ($yamlCommentProvenance as $entry) {
        $yamlCommentPairs[] = ($entry['path'] ?? '') . "\0" . ($entry['comment'] ?? '');
    }
    foreach ([
        "/title\0source export title",
        "/keywords\0reviewer labels",
        "/flow-comment-labels\0source label",
        "/flow-comment-review\0reviewer queue state",
        "/flow-comment-review\0reviewer import tag",
        "/source-summary\0folded source note for reviewer queue",
        "/source-review-log\0folded reviewer log with preserved nested lines",
        "/summary\0later metadata block overrides the first review status",
    ] as $expectedCommentPair) {
        if (!in_array($expectedCommentPair, $yamlCommentPairs, true)) {
            throw new RuntimeException('YAML metadata self-test missing comment provenance ' . str_replace("\0", ' ', $expectedCommentPair));
        }
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
    if (($meta['default-clip-note'] ?? '') !== "YAML parser clips this note.\n") {
        throw new RuntimeException('YAML metadata self-test missing default literal clip newline');
    }
    if (($meta['default-folded-note'] ?? '') !== "Fold reviewer note before WordPress handoff.\n") {
        throw new RuntimeException('YAML metadata self-test missing default folded clip newline');
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
    if (($meta['plain-continuation-review']['indented-note'] ?? '') !== "Queue log\n  source: wp-export.xml\n  status: pending\nReady.") {
        throw new RuntimeException('YAML metadata self-test missing plain multiline more-indented folding');
    }
    if (($meta['plain-continuation-review']['steps'] ?? []) !== ['Collect source metadata packet', 'Approve WordPress import']) {
        throw new RuntimeException('YAML metadata self-test missing sequence-item plain multiline folding');
    }
    if (($meta['plain-continuation-reference']['metadata']['source note'] ?? '') !== 'Source reviewer plain scalar') {
        throw new RuntimeException('YAML metadata self-test missing nested reference plain multiline folding');
    }
    if (($meta['plain-continuation-reference']['metadata']['source outline'] ?? '') !== "Reviewer outline\n  - collect metadata\n  - confirm blocks\nDone.") {
        throw new RuntimeException('YAML metadata self-test missing nested reference plain multiline more-indented folding');
    }
    if (($meta['punctuation-anchor-references'][0]['id'] ?? '') !== 'anchor-punctuation-ref') {
        throw new RuntimeException('YAML metadata self-test missing punctuation anchor source reference');
    }
    if (($meta['punctuation-anchor-references'][1]['id'] ?? '') !== 'anchor-punctuation-copy') {
        throw new RuntimeException('YAML metadata self-test missing punctuation alias copied reference id');
    }
    if (($meta['punctuation-anchor-references'][1]['title'] ?? '') !== 'Anchor punctuation source') {
        throw new RuntimeException('YAML metadata self-test missing punctuation alias copied reference title');
    }
    if (($meta['punctuation-anchor-references'][1]['metadata']['stage'] ?? '') !== 'copied') {
        throw new RuntimeException('YAML metadata self-test missing punctuation alias copied reference override');
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
    if (!str_contains($metadataMarkdown, "abstract: |\n  Source abstract keeps **review** emphasis")) {
        throw new RuntimeException('YAML metadata self-test did not write multiline abstract as a YAML block scalar');
    }
    if (!str_contains($metadataMarkdown, "source-review-log: |-\n  Review steps:\n    - preserve front matter")) {
        throw new RuntimeException('YAML metadata self-test did not write multiline review log as a stripped YAML block scalar');
    }
    if (!str_contains($metadataMarkdown, "review-notes:\n  - |-\n    Preserve original front matter.")) {
        throw new RuntimeException('YAML metadata self-test did not write sequence multiline note as a YAML block scalar');
    }
    if (str_contains($metadataMarkdown, 'Source abstract keeps **review** emphasis\\n\\n') || str_contains($metadataMarkdown, 'Review steps:\\n')) {
        throw new RuntimeException('YAML metadata self-test leaked escaped newline metadata after writer block-scalar handoff');
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
    if ($lateInvalidBlockScalarDocument->attr('meta') !== null) {
        throw new RuntimeException('YAML metadata self-test accepted late under-indented block scalar metadata');
    }
    if (!str_contains($lateInvalidBlockScalarBlocks, 'First source line is indented.')) {
        throw new RuntimeException('YAML metadata self-test lost late invalid block scalar first source line');
    }
    if (!str_contains($lateInvalidBlockScalarBlocks, 'Second source line is not indented relative to the block scalar.</p>')) {
        throw new RuntimeException('YAML metadata self-test lost late invalid block scalar under-indented source line');
    }
    if (($duplicateKeyMeta['review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing duplicate key final review status');
    }
    if (($duplicateKeyMeta['flow-review']['owner'] ?? '') !== 'QA Desk') {
        throw new RuntimeException('YAML metadata self-test missing duplicate flow key final owner');
    }
    if (array_column($duplicateKeyDiagnostics, 'reason') !== ['duplicate-key', 'duplicate-key']) {
        throw new RuntimeException('YAML metadata self-test missing duplicate key diagnostics');
    }
    if (array_column($duplicateKeyDiagnostics, 'path') !== ['/review/status', '/flow-review/owner']) {
        throw new RuntimeException('YAML metadata self-test missing duplicate key diagnostic paths');
    }
    if (!str_contains($duplicateKeyBlocks, '<h1 id="duplicate-key-body">Duplicate key body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing duplicate key body heading');
    }
    if (!is_infinite($specialFloatMeta['review']['positive-infinity'] ?? null) || ($specialFloatMeta['review']['positive-infinity'] ?? 0.0) < 0) {
        throw new RuntimeException('YAML metadata self-test missing positive infinity float metadata');
    }
    if (!is_infinite($specialFloatMeta['review']['negative-infinity'] ?? null) || ($specialFloatMeta['review']['negative-infinity'] ?? 0.0) > 0) {
        throw new RuntimeException('YAML metadata self-test missing negative infinity float metadata');
    }
    if (!is_nan($specialFloatMeta['review']['not-a-number'] ?? null)) {
        throw new RuntimeException('YAML metadata self-test missing NaN float metadata');
    }
    if (($specialFloatMeta['review']['invalid-special'] ?? null) !== '.infinite') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid special float source text');
    }
    if (!is_infinite($specialFloatMeta['flow-review']['ceiling'] ?? null) || ($specialFloatMeta['flow-review']['ceiling'] ?? 0.0) < 0) {
        throw new RuntimeException('YAML metadata self-test missing flow positive infinity float metadata');
    }
    if (!is_nan($specialFloatMeta['flow-review']['missing'] ?? null)) {
        throw new RuntimeException('YAML metadata self-test missing flow NaN float metadata');
    }
    if (($plainNumericMeta['review']['decimal'] ?? null) !== 1024) {
        throw new RuntimeException('YAML metadata self-test missing plain decimal underscore numeric metadata');
    }
    if (($plainNumericMeta['review']['signed-decimal'] ?? null) !== -1024) {
        throw new RuntimeException('YAML metadata self-test missing plain signed decimal underscore metadata');
    }
    if (($plainNumericMeta['review']['hexadecimal'] ?? null) !== 42 || ($plainNumericMeta['review']['negative-hexadecimal'] ?? null) !== -42) {
        throw new RuntimeException('YAML metadata self-test missing plain hexadecimal numeric metadata');
    }
    if (($plainNumericMeta['review']['binary'] ?? null) !== 42 || ($plainNumericMeta['review']['octal'] ?? null) !== 42 || ($plainNumericMeta['review']['legacy-octal'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing plain binary/octal numeric metadata');
    }
    if (($plainNumericMeta['review']['sexagesimal'] ?? null) !== 4830 || ($plainNumericMeta['review']['invalid-sexagesimal'] ?? null) !== '1:60') {
        throw new RuntimeException('YAML metadata self-test missing plain sexagesimal numeric metadata');
    }
    if (
        ($plainNumericMeta['review']['sexagesimal-float'] ?? null) !== 4830.5
        || ($plainNumericMeta['review']['signed-sexagesimal-float'] ?? null) !== -2.25
        || ($plainNumericMeta['review']['invalid-sexagesimal-float'] ?? null) !== '1:60.5'
    ) {
        throw new RuntimeException('YAML metadata self-test missing plain sexagesimal float metadata');
    }
    if (($plainNumericMeta['review']['decimal-float'] ?? null) !== 1024.5 || ($plainNumericMeta['review']['exponent'] ?? null) !== 120.0) {
        throw new RuntimeException('YAML metadata self-test missing plain float numeric metadata');
    }
    if (!is_infinite($plainNumericMeta['review']['positive-infinity'] ?? null) || ($plainNumericMeta['review']['positive-infinity'] ?? 0.0) < 0) {
        throw new RuntimeException('YAML metadata self-test missing plain positive infinity metadata');
    }
    if (!is_infinite($plainNumericMeta['review']['negative-infinity'] ?? null) || ($plainNumericMeta['review']['negative-infinity'] ?? 0.0) > 0) {
        throw new RuntimeException('YAML metadata self-test missing plain negative infinity metadata');
    }
    if (!is_nan($plainNumericMeta['review']['not-a-number'] ?? null)) {
        throw new RuntimeException('YAML metadata self-test missing plain NaN metadata');
    }
    if (($plainNumericMeta['review']['quoted-decimal'] ?? null) !== '1_024' || ($plainNumericMeta['flow-review']['quoted-hex'] ?? null) !== '0x2A') {
        throw new RuntimeException('YAML metadata self-test failed to preserve quoted numeric-looking metadata');
    }
    if (($plainNumericMeta['flow-review']['priority'] ?? null) !== 42 || ($plainNumericMeta['flow-review']['bits'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing flow plain numeric metadata');
    }
    if (!is_infinite($plainNumericMeta['flow-review']['score'] ?? null) || ($plainNumericMeta['flow-review']['score'] ?? 0.0) < 0) {
        throw new RuntimeException('YAML metadata self-test missing flow plain infinity metadata');
    }
    if (
        ($plainNumericMeta['references'][0]['metadata']['duration'] ?? null) !== 123
        || ($plainNumericMeta['references'][0]['metadata']['duration-float'] ?? null) !== 123.5
        || ($plainNumericMeta['references'][0]['metadata']['ratio'] ?? null) !== 0.5
    ) {
        throw new RuntimeException('YAML metadata self-test missing nested plain numeric metadata');
    }
    if (($plainNumericMeta['references'][0]['metadata']['quoted-ratio'] ?? null) !== '.5') {
        throw new RuntimeException('YAML metadata self-test failed to preserve nested quoted numeric metadata');
    }

    echo "yaml metadata handoff self-test ok\n";
    return;
}

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Review labels: ' . implode(', ', $meta['review']['labels'] ?? []) . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo 'Abstract blocks: ' . implode(', ', array_map(static fn (AstNode $node): string => $node->type, $abstractBlocks)) . "\n";
echo $abstractWordPress . "\n\n";
echo 'Writer YAML round-trip review: ' . ($metadataRoundTripMeta['review']['status'] ?? '') . "\n";
echo 'Review optional deadline is null: ' . ((array_key_exists('optional-deadline', $meta) && $meta['optional-deadline'] === null) ? 'yes' : 'no') . "\n";
echo 'Merge sequence review: ' . ($meta['merge-sequence-review']['status'] ?? '') . ' / priority ' . ($meta['merge-sequence-review']['priority'] ?? '') . "\n";
echo 'Explicit key review: ' . ($meta['explicit-review']['status'] ?? '') . ' / ' . ($meta['explicit-review']['source:key'] ?? '') . "\n";
echo 'Sequence key review: ' . ($meta['sequence-key-review']['[owner, desk]'] ?? '') . ' / ' . ($meta['[sequence, source-uri]'] ?? '') . "\n";
echo 'Map key review: ' . ($meta['map-key-review']['{owner: desk, ticket: 7}'] ?? '') . ' / ' . ($meta['{source: uri, type: review}'] ?? '') . "\n";
echo 'Nested explicit key review: ' . ($meta['nested-explicit-key-review']['{owner: desk}'] ?? '') . ' / ' . ($meta['{nested: source-uri}'] ?? '') . "\n";
echo 'Flow explicit key review: ' . ($meta['flow-explicit-review']['[source, uri]'] ?? '') . ' / ' . ($meta['flow-explicit-review']['{owner: desk, ticket: 7}'] ?? '') . "\n";
echo 'Block explicit null key review: ' . ($meta['block-explicit-null-review']['status'] ?? '') . ' / '
    . (array_key_exists('[source, uri]', $meta['block-explicit-null-review'] ?? []) ? 'sequence-null' : 'missing')
    . "\n";
echo 'Sequence item explicit key: ' . ($meta['sequence-explicit-review-items'][0]['[source, uri]'] ?? '') . ' / ' . ($meta['sequence-explicit-review-items'][1]['{owner: desk, ticket: 7}'] ?? '') . "\n";
echo 'Sequence item explicit null key: '
    . (array_key_exists('[source, uri]', $meta['sequence-explicit-null-review-items'][1] ?? []) ? 'sequence-null' : 'missing')
    . ' / '
    . ($meta['sequence-explicit-null-review-items'][3]['status'] ?? '')
    . "\n";
echo 'Ordered review duplicate key: ' . ($meta['ordered-review']['steps'][0]['key'] ?? '') . ' => ' . ($meta['ordered-review']['steps'][0]['value'] ?? '') . ' / ' . ($meta['ordered-review']['steps'][1]['value'] ?? '') . "\n";
echo 'Plain key review: ' . ($meta['plain-key-review']['source owner'] ?? '') . ' / ' . ($meta['source label'] ?? '') . "\n";
echo 'Flow colon key review: ' . ($meta['flow-colon-key-review']['source:key'] ?? '') . ' / ' . ($meta['flow-colon-key-review']['dc:title'] ?? '') . "\n";
echo 'Flow document review: ' . ($meta['flow-document-review']['status'] ?? '') . ' / priority ' . ($meta['flow-document-review']['priority'] ?? '') . "\n";
echo 'Ambiguous field diagnostics: ' . implode(', ', array_column($ambiguousYamlDiagnostics, 'field')) . "\n";
echo 'Quoted ambiguous fields: ' . ($meta['no'] ?? '') . ' / ' . ($meta['Off'] ?? '') . ' / ' . ($meta['3.14'] ?? '') . ' / ' . ($meta['0o52'] ?? '') . "\n";
echo 'YAML diagnostics: ' . count($yamlDiagnostics) . "\n";
echo 'YAML invalid TAG directives: ' . count($invalidTagDiagnostics) . "\n";
echo 'YAML alias diagnostic paths: ' . implode(', ', array_column($aliasYamlDiagnostics, 'path')) . "\n";
echo 'YAML custom tag provenance: ' . count($yamlTagProvenance) . "\n";
echo 'YAML custom tag provenance paths: ' . implode(', ', array_filter(array_column($yamlTagProvenance, 'path'))) . "\n";
echo 'YAML comment provenance: ' . count($yamlCommentProvenance) . "\n";
echo 'YAML comment provenance paths: ' . implode(', ', array_filter(array_column($yamlCommentProvenance, 'path'))) . "\n";
echo 'YAML anchor provenance: ' . count($yamlAnchorProvenance) . "\n";
echo 'YAML anchor provenance paths: ' . implode(', ', array_filter(array_column($yamlAnchorProvenance, 'path'))) . "\n";
echo 'YAML collection provenance: ' . count($yamlCollectionProvenance) . "\n";
echo 'YAML collection provenance paths: ' . implode(', ', array_filter(array_column($yamlCollectionProvenance, 'path'))) . "\n";
echo 'YAML stream provenance: ' . count($yamlStreamProvenance) . "\n";
echo 'YAML stream provenance fields: ' . implode(' | ', array_column($yamlStreamProvenance, 'fields')) . "\n";
echo 'Compact sequence item: ' . ($meta['compact-review-items'][0]['label'] ?? '') . ' / ' . ($meta['compact-review-items'][1]['source:key'] ?? '') . "\n";
echo 'Source review log: ' . str_replace("\n", ' | ', $meta['source-review-log'] ?? '') . "\n";
echo 'Source revision: ' . ($meta['source-revision'] ?? '') . "\n";
echo 'Typed review revision: ' . ($meta['typed-review']['typed-revision'] ?? '') . ' / confidence ' . ($meta['typed-review']['confidence'] ?? '') . "\n";
echo 'Typed review duration seconds: ' . ($meta['typed-review']['review-duration-seconds'] ?? '') . ' / flow ' . ($meta['typed-flow-review']['elapsed'] ?? '') . "\n";
echo 'Boolean synonym review: '
    . (($meta['typed-review']['legacy-approved'] ?? null) === true ? 'yes=true' : 'yes=missing')
    . ' / '
    . (($meta['typed-review']['legacy-blocked'] ?? null) === false ? 'NO=false' : 'NO=missing')
    . ' / '
    . (($meta['boolean-synonym-flow-review']['enabled'] ?? null) === true ? 'ON=true' : 'ON=missing')
    . ' / '
    . (($meta['boolean-synonym-flow-review']['disabled'] ?? null) === false ? 'OFF=false' : 'OFF=missing')
    . "\n";
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
echo 'Duplicate key diagnostics: ' . implode(', ', array_column($duplicateKeyDiagnostics, 'path')) . "\n";
echo 'Duplicate key final review: ' . ($duplicateKeyMeta['review']['status'] ?? '') . ' / ' . ($duplicateKeyMeta['flow-review']['owner'] ?? '') . "\n";
echo $duplicateKeyBlocks . "\n";
echo 'Special float review: '
    . (is_infinite($specialFloatMeta['review']['positive-infinity'] ?? null) ? '+inf' : 'missing')
    . ' / '
    . (is_infinite($specialFloatMeta['review']['negative-infinity'] ?? null) ? '-inf' : 'missing')
    . ' / '
    . (is_nan($specialFloatMeta['review']['not-a-number'] ?? null) ? 'nan' : 'missing')
    . "\n";
echo 'Plain numeric review: '
    . ($plainNumericMeta['review']['decimal'] ?? '')
    . ' / hex '
    . ($plainNumericMeta['review']['hexadecimal'] ?? '')
    . ' / flow '
    . ($plainNumericMeta['flow-review']['priority'] ?? '')
    . "\n";
