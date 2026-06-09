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
abstract: | # source abstract reviewer comment
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
typed-block-review:
  approved: !!bool >-
    true
  priority: !!int |-
    0x2A
  captured-at: !!timestamp >-
    2026-06-08 10:15:30Z
  withdrawn: !!null |-
    reviewer note is intentionally nulled
  invalid-priority: !!int >-
    not-a-number
typed-sequence-review:
  - !!int
    0x2A
  - !!bool >-
    true
  - !!timestamp
    2026-06-08 12:34:56Z
  - !!null |-
    reviewer note is intentionally nulled
  - !!int
    not-a-number
typed-mapping-child-review:
  source-revision: !!str
    007
  priority: !!int
    0x2A
  approved: !!bool
    true
  captured-at: !!timestamp
    2026-06-08 12:34:56Z
  withdrawn: !!null
    reviewer note is intentionally nulled
  invalid-priority: !!int
    not-a-number
source-captured-at: !!timestamp 2026-06-05 06:46:51Z
review-binary:
  note-bytes: !!binary "UmV2aWV3IG1ldGFkYXRh"
  digest-bytes: !!binary |
    U291cmNl
    IFBhY2tldA==
  invalid-bytes: !!binary "not base64!"
optional-deadline:
blank-note: # intentionally blank in source packet
explicit-empty: ""
flow-empty-review: {migration-ticket:, quoted-empty: ""}
typed-flow-review: {priority: !!int "4", elapsed: !!int 0:01:05, enabled: !!bool "false", ticket: !!str 009}
boolean-synonym-flow-review: {published: y, archived: n, enabled: ON, disabled: OFF, quoted: "off"}
schema-number-review:
  duration: 1:20:30
  fractional-duration: 1:20:30.5
  explicit-duration: !!int 1:20:30
  explicit-fractional-duration: !!float 1:20:30.5
schema-number-flow-review: {elapsed: 0:01:05, explicit-elapsed: !!int 0:01:05}
schema-integer-review:
  leading-zero-ticket: 052
  signed-leading-zero-ticket: -052
  modern-octal-ticket: 0o52
  hexadecimal-ticket: 0x2A
  binary-ticket: 0b101010
  explicit-binary-ticket: !!int 0b101010
schema-integer-flow-review: {leading-zero-ticket: 010, modern-octal-ticket: 0o10, binary-ticket: 0b1000, explicit-binary-ticket: !!int 0b1000}
tag-directive-review:
  owner: !wpd!reviewer Directive Desk
  ticket: !yaml!str 010
  priority: !yaml!int "10"
  labels: [!wpd!label directive, !wpd!label metadata]
flow-tag-directive-review: {? !wpd!key "source:key": !wpd!value directive metadata, owner: !wpd!reviewer Flow Directive Desk}
flow-implicit-tag-key-review: {!wpd!reviewer owner: Flow Implicit Desk, !wpd!key "source:key": implicit directive metadata}
flow-key-tag-review: {? !wpd!key "source:key": directive key metadata}
plain-tag-key-review:
  !wpd!key source:key: plain key metadata
  !wpd!key "source:label": Plain Key Metadata
plain-tag-key-items:
  - status: queued
    !wpd!key source:key: compact item metadata
tag-uri-suffix-review:
  owner: !wpd!source%2Fowner URI Suffix Desk
  source-uri: !wpd!source?kind=uri https://example.test/exports/packet#tag-uri-suffix
  fragment-owner: !wpd!source#fragment Fragment Desk
  scoped-owner: !wpd!source;kind=review&draft=false Scoped Desk
flow-tag-uri-suffix-review: {owner: !wpd!flow%2Fowner Flow URI Desk, ? !wpd!key%2Fsource "source:key": !wpd!value?kind=flow metadata value}
undefined-tag-handle-review: {owner: !missing!reviewer Missing Handle Desk, labels: [!missing!label undeclared, !missing!label review], flow-owner: !missing!reviewer Flow Missing Desk}
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
core-collection-review: !!map
  status: queued
  labels: !!seq
    - core
    - metadata
core-flow-collection-review: !!map {status: approved, labels: !!seq [flow, core]}
core-reference-list: !!seq
  - id: core-collection-ref
    metadata: !!map {source: core-tag, status: approved}
core-tagged-items: !!seq
  - !!map {kind: flow-map, labels: !!seq [tagged, item]}
  - !!seq
    - nested
    - item
review-label-set: !!set {front-matter, wordpress, wordpress, "source:key", "source:key"}
block-label-set: !!set
  ? migration
  ? "qa:review"
  ? migration
sequence-label-sets:
  - !!set {draft, published, draft}
  - !!set
    ? queued
    ? "needs:review"
    ? queued
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
invalid-ordered-review: !!omap
  - source-title: Original export
    owner: Import Desk
  - [bad, key]
  - status: queued
invalid-pairs-review: !!pairs [{owner: Import Desk, role: editor}, [bad, key], status]
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
invalid-merge-review:
  <<: [ignored, *merge_review_base, [not, a, map], {labels: [diagnostic]}]
  owner: Merge Audit Desk
invalid-direct-merge:
  <<: ignored
  owner: Direct Merge Desk
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
  # sequence explicit source key reviewer comment
  - ? [source, uri]
    : https://example.test/exports/packet#sequence-explicit-item
    status: queued
    labels:
      - migration
      - wordpress
  # sequence explicit owner key reviewer comment
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
source label: Migration review # writer scalar trailing source label
writer-hashtag-label: "#needs-review"
writer-hashtag-labels:
  - "#migration"
  - "#wp-import"
  - safe#fragment
writer-colon-label: ":needs-review"
writer-colon-labels:
  - ":migration"
  - ":wp-import"
  - safe:fragment
writer-sexagesimal-duration: "2:03"
writer-sexagesimal-labels:
  - "0:01"
  - "1:20:30.5"
  - safe:fragment
writer-special-float-status: ".inf"
writer-special-float-labels:
  - "-.inf"
  - "+.nan"
  - safe.inf
writer-timestamp-date: "2026-6-3"
writer-timestamp-captured-at: "2026-6-3T4:05:06Z"
writer-timestamp-labels:
  - "2026-6-4"
  - "2026-6-4T5:06:07+5"
  - release-2026-6-4
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
? !wpd!key "On"
: tagged quoted boolean-looking source field
? !wpd!key "0b101"
: tagged quoted binary-looking source field
ambiguous-field-review:
  true: nested reviewer boolean key stays visible
  15: nested reviewer numeric key stays visible
  status: queued
source-uri: /exports/packet#front-matter
escaped-source-title: "Escaped \u201cmetadata\u201d \U0001F4DD"
escaped-source-uri: "https:\/\/example.test\/exports\/packet\x23front-matter"
invalid-escaped-source-uri: "https://example.test/\zexports"
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
    # collect source packet sequence comment
    - Collect source
      metadata packet
    # approve source packet sequence comment
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
  ? "flow-document:15": quoted explicit flow key,
  ? !wpd!key "No": flow tagged quoted boolean-looking source field,
  ? !wpd!key "0b110": flow tagged quoted binary-looking source field
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
$yamlAliasProvenance = $document->attr('yamlMetadataAliasProvenance', []);
$yamlMergeProvenance = $document->attr('yamlMetadataMergeProvenance', []);
$yamlScalarProvenance = $document->attr('yamlMetadataScalarProvenance', []);
$yamlCollectionProvenance = $document->attr('yamlMetadataCollectionProvenance', []);
$yamlStreamProvenance = $document->attr('yamlMetadataStreamProvenance', []);
$yamlReviewSummary = $document->attr('yamlMetadataReviewSummary', []);
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
$invalidMergeDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-merge-value'
));
$streamOverrideDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'stream-field-overridden'
));
$flowNullKeyDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'flow-key-only-null'
));
$invalidOrderedPairDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-ordered-pair-member'
));
$undefinedTagHandleDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'undefined-tag-handle'
));
$undefinedTagHandleReviewDiagnostics = array_values(array_filter(
    $undefinedTagHandleDiagnostics,
    static fn (array $diagnostic): bool => str_starts_with($diagnostic['path'] ?? '', '/undefined-tag-handle-review')
));
$invalidDoubleQuotedEscapeDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-double-quoted-escape'
));
$invalidBinaryScalarDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-binary-scalar'
));
$duplicateSetDiagnostics = array_values(array_filter(
    $yamlDiagnostics,
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'duplicate-key'
        && (str_starts_with($diagnostic['path'] ?? '', '/review-label-set')
            || str_starts_with($diagnostic['path'] ?? '', '/block-label-set')
            || str_starts_with($diagnostic['path'] ?? '', '/sequence-label-sets/'))
));
$documentMarkerComments = array_values(array_filter(
    $yamlCommentProvenance,
    static fn (array $comment): bool => ($comment['context'] ?? '') === 'document-marker'
));
$blocks = (new WordPressBlockWriter())->write($document);
$abstractBlocks = $meta['abstractBlocks'] ?? [];
$abstractWordPress = $abstractBlocks === []
    ? ''
    : (new WordPressBlockWriter())->write(new AstNode('document', [], $abstractBlocks));
$metadataMarkdown = (new MarkdownWriter(['yamlMetadata' => true]))->write($document);
$metadataRoundTripDocument = (new MarkdownReader())->read($metadataMarkdown);
$metadataRoundTripMeta = $metadataRoundTripDocument->attr('meta', []);
$metadataRoundTripCollectionProvenance = $metadataRoundTripDocument->attr('yamlMetadataCollectionProvenance', []);
$metadataRoundTripScalarProvenance = $metadataRoundTripDocument->attr('yamlMetadataScalarProvenance', []);

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

$invalidFlowCollectionMarkdown = <<<'MARKDOWN'
---
title: Invalid flow collection **Packet**
review: {
  status: queued,
  labels: [front-matter, wordpress]
owner: Import Desk
...

# Invalid flow collection body
MARKDOWN;

$invalidFlowCollectionDocument = (new MarkdownReader())->read($invalidFlowCollectionMarkdown);
$invalidFlowCollectionBlocks = (new WordPressBlockWriter())->write($invalidFlowCollectionDocument);

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

$lateDirectiveMarkdown = <<<'MARKDOWN'
---
%TAG !wp! tag:example.test,2026:
---
title: Directive boundary packet
review:
  owner: !wp!reviewer Import Desk
%TAG !wp! tag:late.example,2026:
late-review:
  owner: !wp!reviewer Late Desk
...

# Directive boundary body
MARKDOWN;

$lateDirectiveDocument = (new MarkdownReader())->read($lateDirectiveMarkdown);
$lateDirectiveMeta = $lateDirectiveDocument->attr('meta', []);
$lateDirectiveDiagnostics = $lateDirectiveDocument->attr('yamlMetadataDiagnostics', []);
$lateDirectiveDirectives = $lateDirectiveDocument->attr('yamlMetadataDirectiveProvenance', []);
$lateDirectiveTagProvenance = $lateDirectiveDocument->attr('yamlMetadataTagProvenance', []);
$lateDirectiveBlocks = (new WordPressBlockWriter())->write($lateDirectiveDocument);

$reservedDirectiveMarkdown = <<<'MARKDOWN'
---
%EXPORT WordPressReview 2026
---
title: Reserved directive packet
review: {status: queued, owner: Import Desk}
...

# Reserved directive body
MARKDOWN;

$reservedDirectiveDocument = (new MarkdownReader())->read($reservedDirectiveMarkdown);
$reservedDirectiveMeta = $reservedDirectiveDocument->attr('meta', []);
$reservedDirectiveDirectives = $reservedDirectiveDocument->attr('yamlMetadataDirectiveProvenance', []);
$reservedDirectiveBlocks = (new WordPressBlockWriter())->write($reservedDirectiveDocument);

$indentedBlockScalarMarkdown = <<<'MARKDOWN'
---
title: Indented block scalar packet
review:
  note:
    |-
      Preserve front matter
      Keep source audit
  summary:
    >-
      Import
      metadata
? |-
  source:key
: root metadata value
?
  >-
    source
    label
: folded key value
references:
  - id: block-key-ref
    metadata:
      ? |-
        source:key
      : metadata value
...

# Indented block scalar body
MARKDOWN;

$indentedBlockScalarDocument = (new MarkdownReader())->read($indentedBlockScalarMarkdown);
$indentedBlockScalarMeta = $indentedBlockScalarDocument->attr('meta', []);
$indentedBlockScalarProvenance = $indentedBlockScalarDocument->attr('yamlMetadataScalarProvenance', []);
$indentedBlockScalarBlocks = (new WordPressBlockWriter())->write($indentedBlockScalarDocument);

$invalidExplicitKeyBlockScalarMarkdown = <<<'MARKDOWN'
---
title: Invalid explicit key packet
? |-
bad:key
: root metadata value
review:
  ? >-
  owner
  : Import Desk
  status: queued
...

# Invalid explicit key body
MARKDOWN;

$invalidExplicitKeyBlockScalarDocument = (new MarkdownReader())->read($invalidExplicitKeyBlockScalarMarkdown);
$invalidExplicitKeyBlockScalarMeta = $invalidExplicitKeyBlockScalarDocument->attr('meta', []);
$invalidExplicitKeyBlockScalarDiagnostics = array_values(array_filter(
    $invalidExplicitKeyBlockScalarDocument->attr('yamlMetadataDiagnostics', []),
    static fn (array $diagnostic): bool => ($diagnostic['reason'] ?? '') === 'invalid-block-scalar-indentation'
));
$invalidExplicitKeyBlockScalarBlocks = (new WordPressBlockWriter())->write($invalidExplicitKeyBlockScalarDocument);

if (($argv[1] ?? '') === '--self-test') {
    if (($meta['review']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing later review override');
    }
    if (array_key_exists('__yamlMetadataReviewSummary', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked review summary into plain metadata');
    }
    if (($yamlReviewSummary['type'] ?? '') !== 'yaml-metadata-review') {
        throw new RuntimeException('YAML metadata self-test missing review summary type');
    }
    if (($yamlReviewSummary['reviewStatus'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing needs-review summary status');
    }
    if (($yamlReviewSummary['diagnosticCount'] ?? null) !== count($yamlDiagnostics)) {
        throw new RuntimeException('YAML metadata self-test review summary diagnostic count mismatch');
    }
    if (($yamlReviewSummary['streamCount'] ?? null) !== count($yamlStreamProvenance)) {
        throw new RuntimeException('YAML metadata self-test review summary stream count mismatch');
    }
    if (($yamlReviewSummary['tagCount'] ?? null) !== count($yamlTagProvenance)) {
        throw new RuntimeException('YAML metadata self-test review summary tag count mismatch');
    }
    if (($yamlReviewSummary['commentCount'] ?? null) !== count($yamlCommentProvenance)) {
        throw new RuntimeException('YAML metadata self-test review summary comment count mismatch');
    }
    if (($yamlReviewSummary['mergeCount'] ?? null) !== count($yamlMergeProvenance)) {
        throw new RuntimeException('YAML metadata self-test review summary merge count mismatch');
    }
    if (!in_array('review', $yamlReviewSummary['overriddenFields'] ?? [], true)) {
        throw new RuntimeException('YAML metadata self-test review summary missing overridden review field');
    }
    if (!in_array('stream-field-overridden', array_keys($yamlReviewSummary['diagnosticReasons'] ?? []), true)) {
        throw new RuntimeException('YAML metadata self-test review summary missing stream override reason');
    }
    if (!in_array('flow-document-review', $yamlReviewSummary['fields'] ?? [], true)) {
        throw new RuntimeException('YAML metadata self-test review summary missing final flow document field');
    }
    if (
        count($streamOverrideDiagnostics) !== 1
        || ($streamOverrideDiagnostics[0]['field'] ?? '') !== 'review'
        || ($streamOverrideDiagnostics[0]['path'] ?? '') !== '/review'
        || ($streamOverrideDiagnostics[0]['previousDocumentIndex'] ?? '') !== '1'
        || ($streamOverrideDiagnostics[0]['documentIndex'] ?? '') !== '2'
    ) {
        throw new RuntimeException('YAML metadata self-test missing stream override diagnostics');
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
    if (array_column($documentMarkerComments, 'comment') !== ['source export front matter', 'YAML document starts after directives', 'source metadata block ends before body']) {
        throw new RuntimeException('YAML metadata self-test missing document marker comments');
    }
    if (array_column($documentMarkerComments, 'markerRole') !== ['opening', 'document-start', 'closing']) {
        throw new RuntimeException('YAML metadata self-test missing document marker comment roles');
    }
    if (array_column($documentMarkerComments, 'marker') !== ['---', '---', '---']) {
        throw new RuntimeException('YAML metadata self-test missing document marker names');
    }
    if (array_column($documentMarkerComments, 'sourceLine') !== ['3', '8', '541']) {
        throw new RuntimeException('YAML metadata self-test missing document marker source lines');
    }
    if (($lateDirectiveMeta['review']['owner'] ?? '') !== 'Import Desk' || ($lateDirectiveMeta['late-review']['owner'] ?? '') !== 'Late Desk') {
        throw new RuntimeException('YAML metadata self-test missing late directive review metadata');
    }
    if (array_column($lateDirectiveDiagnostics, 'reason') !== ['directive-after-document-content']) {
        throw new RuntimeException('YAML metadata self-test missing late directive boundary diagnostic');
    }
    if (($lateDirectiveDiagnostics[0]['source'] ?? '') !== '%TAG !wp! tag:late.example,2026:') {
        throw new RuntimeException('YAML metadata self-test missing late directive source');
    }
    if (array_column($lateDirectiveDirectives, 'prefix') !== ['tag:example.test,2026:']) {
        throw new RuntimeException('YAML metadata self-test let a late directive rebind the tag prefix');
    }
    if (array_column($lateDirectiveTagProvenance, 'tag') !== ['!<tag:example.test,2026:reviewer>', '!<tag:example.test,2026:reviewer>']) {
        throw new RuntimeException('YAML metadata self-test did not preserve the preamble tag handle for late tagged values');
    }
    if (!str_contains($lateDirectiveBlocks, '<h1 id="directive-boundary-body">Directive boundary body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing late directive WordPress body handoff');
    }
    if (($reservedDirectiveMeta['title'] ?? '') !== 'Reserved directive packet') {
        throw new RuntimeException('YAML metadata self-test missing reserved directive metadata');
    }
    if (($reservedDirectiveMeta['review']['owner'] ?? '') !== 'Import Desk' || ($reservedDirectiveMeta['review']['status'] ?? '') !== 'queued') {
        throw new RuntimeException('YAML metadata self-test missing reserved directive review metadata');
    }
    if (array_column($reservedDirectiveDirectives, 'directive') !== ['EXPORT']) {
        throw new RuntimeException('YAML metadata self-test missing reserved directive provenance');
    }
    if (($reservedDirectiveDirectives[0]['reserved'] ?? '') !== 'true') {
        throw new RuntimeException('YAML metadata self-test missing reserved directive marker');
    }
    if (($reservedDirectiveDirectives[0]['parameters'] ?? '') !== 'WordPressReview 2026') {
        throw new RuntimeException('YAML metadata self-test missing reserved directive parameters');
    }
    if (!str_contains($reservedDirectiveBlocks, '<h1 id="reserved-directive-body">Reserved directive body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing reserved directive WordPress body handoff');
    }
    if (
        ($indentedBlockScalarMeta['review']['note'] ?? '') !== "Preserve front matter\nKeep source audit"
        || ($indentedBlockScalarMeta['review']['summary'] ?? '') !== 'Import metadata'
        || ($indentedBlockScalarMeta['source:key'] ?? '') !== 'root metadata value'
        || ($indentedBlockScalarMeta['source label'] ?? '') !== 'folded key value'
        || ($indentedBlockScalarMeta['references'][0]['metadata']['source:key'] ?? '') !== 'metadata value'
    ) {
        throw new RuntimeException('YAML metadata self-test missing indented block scalar metadata');
    }
    $indentedBlockScalarStyles = [];
    $indentedExplicitKeyStyles = [];
    foreach ($indentedBlockScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-block-scalar') {
            $indentedBlockScalarStyles[$entry['path'] ?? ''] = $entry['style'] ?? '';
        }
        if (($entry['type'] ?? '') === 'yaml-explicit-key-scalar') {
            $indentedExplicitKeyStyles[$entry['path'] ?? ''] = $entry['style'] ?? '';
        }
    }
    if (($indentedBlockScalarStyles['/review/note'] ?? '') !== 'literal' || ($indentedBlockScalarStyles['/review/summary'] ?? '') !== 'folded') {
        throw new RuntimeException('YAML metadata self-test missing indented block scalar provenance');
    }
    if (
        ($indentedExplicitKeyStyles['/source:key'] ?? '') !== 'literal'
        || ($indentedExplicitKeyStyles['/source label'] ?? '') !== 'folded'
        || ($indentedExplicitKeyStyles['/references/0/metadata/source:key'] ?? '') !== 'literal'
    ) {
        throw new RuntimeException('YAML metadata self-test missing block scalar explicit key provenance');
    }
    if (!str_contains($indentedBlockScalarBlocks, '<h1 id="indented-block-scalar-body">Indented block scalar body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing indented block scalar WordPress body handoff');
    }
    if (
        ($invalidExplicitKeyBlockScalarMeta['title'] ?? '') !== 'Invalid explicit key packet'
        || ($invalidExplicitKeyBlockScalarMeta['review']['status'] ?? '') !== 'queued'
        || array_key_exists('bad:key', $invalidExplicitKeyBlockScalarMeta)
        || array_key_exists('owner', $invalidExplicitKeyBlockScalarMeta['review'] ?? [])
    ) {
        throw new RuntimeException('YAML metadata self-test did not preserve valid siblings around invalid explicit-key block scalars');
    }
    if (
        count($invalidExplicitKeyBlockScalarDiagnostics) !== 2
        || array_column($invalidExplicitKeyBlockScalarDiagnostics, 'type') !== ['yaml-explicit-key', 'yaml-explicit-key']
        || array_column($invalidExplicitKeyBlockScalarDiagnostics, 'indicator') !== ['|', '>']
        || array_column($invalidExplicitKeyBlockScalarDiagnostics, 'contentLine') !== ['bad:key', 'owner']
        || array_column($invalidExplicitKeyBlockScalarDiagnostics, 'sourceLine') !== ['3', '7']
        || (($invalidExplicitKeyBlockScalarDiagnostics[1]['parentPath'] ?? '') !== '/review')
    ) {
        throw new RuntimeException('YAML metadata self-test missing invalid explicit-key block scalar diagnostics');
    }
    if (!str_contains($invalidExplicitKeyBlockScalarBlocks, '<h1 id="invalid-explicit-key-body">Invalid explicit key body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing invalid explicit-key WordPress body handoff');
    }
    if (
        ($meta['plain-tag-key-review']['source:key'] ?? '') !== 'plain key metadata'
        || ($meta['plain-tag-key-review']['source:label'] ?? '') !== 'Plain Key Metadata'
        || ($meta['plain-tag-key-items'][0]['source:key'] ?? '') !== 'compact item metadata'
    ) {
        throw new RuntimeException('YAML metadata self-test missing plain tagged key metadata');
    }
    $plainKeyTagPairs = array_map(
        static fn (array $entry): string => ($entry['tag'] ?? '') . "\0" . ($entry['path'] ?? ''),
        $yamlTagProvenance
    );
    foreach ([
        '!<tag:directive.example,2026:key>' . "\0" . '/plain-tag-key-review/source:key',
        '!<tag:directive.example,2026:key>' . "\0" . '/plain-tag-key-review/source:label',
        '!<tag:directive.example,2026:key>' . "\0" . '/plain-tag-key-items/0/source:key',
    ] as $expectedPlainKeyTagPair) {
        if (!in_array($expectedPlainKeyTagPair, $plainKeyTagPairs, true)) {
            throw new RuntimeException('YAML metadata self-test missing plain tagged key provenance');
        }
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
    if (($meta['typed-review']['legacy-approved'] ?? null) !== 'yes') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 yes as a string');
    }
    if (($meta['typed-review']['legacy-blocked'] ?? null) !== 'NO') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 NO as a string');
    }
    if (($meta['typed-review']['legacy-enabled'] ?? null) !== 'On') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 On as a string');
    }
    if (($meta['typed-review']['legacy-disabled'] ?? null) !== 'off') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 off as a string');
    }
    if (($meta['typed-review']['explicit-legacy-enabled'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit bool tag y coercion');
    }
    if (($meta['typed-review']['quoted-legacy-approved'] ?? null) !== 'yes') {
        throw new RuntimeException('YAML metadata self-test failed to preserve quoted yes string');
    }
    if (($meta['boolean-synonym-flow-review']['published'] ?? null) !== 'y' || ($meta['boolean-synonym-flow-review']['archived'] ?? null) !== 'n') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 flow y/n strings');
    }
    if (($meta['boolean-synonym-flow-review']['enabled'] ?? null) !== 'ON' || ($meta['boolean-synonym-flow-review']['disabled'] ?? null) !== 'OFF') {
        throw new RuntimeException('YAML metadata self-test did not preserve YAML 1.2 flow on/off strings');
    }
    if (($meta['boolean-synonym-flow-review']['quoted'] ?? null) !== 'off') {
        throw new RuntimeException('YAML metadata self-test failed to preserve quoted off string');
    }
    if (!array_key_exists('withdrawn', $meta['typed-review'] ?? []) || $meta['typed-review']['withdrawn'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit null tag coercion');
    }
    if (($meta['typed-block-review']['approved'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit block bool tag coercion');
    }
    if (($meta['typed-block-review']['priority'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit block integer tag coercion');
    }
    if (($meta['typed-block-review']['captured-at'] ?? '') !== '2026-06-08T10:15:30Z') {
        throw new RuntimeException('YAML metadata self-test missing explicit block timestamp tag normalization');
    }
    if (!array_key_exists('withdrawn', $meta['typed-block-review'] ?? []) || $meta['typed-block-review']['withdrawn'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit block null tag coercion');
    }
    if (($meta['typed-block-review']['invalid-priority'] ?? '') !== 'not-a-number') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid explicit block integer source text');
    }
    if (($meta['typed-sequence-review'][0] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit nested sequence integer tag coercion');
    }
    if (($meta['typed-sequence-review'][1] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit nested sequence bool tag coercion');
    }
    if (($meta['typed-sequence-review'][2] ?? '') !== '2026-06-08T12:34:56Z') {
        throw new RuntimeException('YAML metadata self-test missing explicit nested sequence timestamp tag normalization');
    }
    if (!array_key_exists(3, $meta['typed-sequence-review'] ?? []) || $meta['typed-sequence-review'][3] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit nested sequence null tag coercion');
    }
    if (($meta['typed-sequence-review'][4] ?? '') !== 'not-a-number') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid explicit nested sequence integer source text');
    }
    if (($meta['typed-mapping-child-review']['source-revision'] ?? '') !== '007') {
        throw new RuntimeException('YAML metadata self-test failed to preserve explicit mapping child string revision');
    }
    if (($meta['typed-mapping-child-review']['priority'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missing explicit mapping child integer tag coercion');
    }
    if (($meta['typed-mapping-child-review']['approved'] ?? null) !== true) {
        throw new RuntimeException('YAML metadata self-test missing explicit mapping child bool tag coercion');
    }
    if (($meta['typed-mapping-child-review']['captured-at'] ?? '') !== '2026-06-08T12:34:56Z') {
        throw new RuntimeException('YAML metadata self-test missing explicit mapping child timestamp tag normalization');
    }
    if (!array_key_exists('withdrawn', $meta['typed-mapping-child-review'] ?? []) || $meta['typed-mapping-child-review']['withdrawn'] !== null) {
        throw new RuntimeException('YAML metadata self-test missing explicit mapping child null tag coercion');
    }
    if (($meta['typed-mapping-child-review']['invalid-priority'] ?? '') !== 'not-a-number') {
        throw new RuntimeException('YAML metadata self-test did not preserve invalid explicit mapping child integer source text');
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
    if (($meta['review-binary']['invalid-bytes'] ?? '') !== 'not base64!') {
        throw new RuntimeException('YAML metadata self-test hid invalid binary metadata source');
    }
    if (
        count($invalidBinaryScalarDiagnostics) !== 1
        || ($invalidBinaryScalarDiagnostics[0]['path'] ?? '') !== '/review-binary/invalid-bytes'
        || ($invalidBinaryScalarDiagnostics[0]['source'] ?? '') !== 'not base64!'
        || ($invalidBinaryScalarDiagnostics[0]['expected'] ?? '') !== 'valid base64 for !!binary'
    ) {
        throw new RuntimeException('YAML metadata self-test missing invalid binary scalar diagnostics');
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
    if (
        ($meta['flow-implicit-tag-key-review']['owner'] ?? '') !== 'Flow Implicit Desk'
        || ($meta['flow-implicit-tag-key-review']['source:key'] ?? '') !== 'implicit directive metadata'
        || array_key_exists('!wpd!reviewer owner', $meta['flow-implicit-tag-key-review'] ?? [])
    ) {
        throw new RuntimeException('YAML metadata self-test missing flow implicit tagged key metadata');
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
    if (($meta['undefined-tag-handle-review']['owner'] ?? '') !== 'Missing Handle Desk') {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle metadata owner');
    }
    if (($meta['undefined-tag-handle-review']['labels'] ?? []) !== ['undeclared', 'review']) {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle metadata labels');
    }
    if (($meta['undefined-tag-handle-review']['flow-owner'] ?? '') !== 'Flow Missing Desk') {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle flow owner');
    }
    if (count($undefinedTagHandleReviewDiagnostics) !== 4) {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle diagnostics');
    }
    if (array_column($undefinedTagHandleReviewDiagnostics, 'handle') !== ['!missing!', '!missing!', '!missing!', '!missing!']) {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle names');
    }
    if (array_column($undefinedTagHandleReviewDiagnostics, 'suffix') !== ['reviewer', 'label', 'label', 'reviewer']) {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle suffixes');
    }
    if (array_column($undefinedTagHandleReviewDiagnostics, 'path') !== [
        '/undefined-tag-handle-review/owner',
        '/undefined-tag-handle-review/labels/0',
        '/undefined-tag-handle-review/labels/1',
        '/undefined-tag-handle-review/flow-owner',
    ]) {
        throw new RuntimeException('YAML metadata self-test missing undefined tag handle paths');
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
    foreach (['/review/owner', '/tag-directive-review/labels/0', '/flow-tag-directive-review/source:key', '/flow-implicit-tag-key-review/owner', '/flow-implicit-tag-key-review/source:key', '/On', '/0b101', '/No', '/0b110', '/tag-uri-suffix-review/owner', '/tag-uri-suffix-review/source-uri', '/tag-uri-suffix-review/fragment-owner', '/tag-uri-suffix-review/scoped-owner', '/flow-tag-uri-suffix-review/owner', '/flow-tag-uri-suffix-review/source:key', '/block-explicit-null-review/tagged-source', '/verbatim-tag-review/source-uri'] as $expectedPath) {
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
    if (array_key_exists('__yamlMetadataAliasProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked alias provenance into plain metadata');
    }
    if (array_key_exists('__yamlMetadataMergeProvenance', $meta)) {
        throw new RuntimeException('YAML metadata self-test leaked merge provenance into plain metadata');
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
        '/typed-block-review/approved' => ['boolean', 'bool', 'true'],
        '/typed-block-review/priority' => ['number', 'int', '0x2A'],
        '/typed-block-review/captured-at' => ['timestamp', 'timestamp', '2026-06-08 10:15:30Z'],
        '/typed-block-review/withdrawn' => ['null', 'null', 'reviewer note is intentionally nulled'],
        '/typed-sequence-review/0' => ['number', 'int', '0x2A'],
        '/typed-sequence-review/1' => ['boolean', 'bool', 'true'],
        '/typed-sequence-review/2' => ['timestamp', 'timestamp', '2026-06-08 12:34:56Z'],
        '/typed-sequence-review/3' => ['null', 'null', 'reviewer note is intentionally nulled'],
        '/source-captured-at' => ['timestamp', 'timestamp', '2026-06-05 06:46:51Z'],
        '/review-binary/note-bytes' => ['binary', 'binary', '"UmV2aWV3IG1ldGFkYXRh"'],
        '/review-binary/digest-bytes' => ['binary', 'binary', "U291cmNl\nIFBhY2tldA==\n"],
        '/typed-flow-review/elapsed' => ['number', 'int', '0:01:05'],
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
    foreach ([
        '/typed-review/legacy-approved',
        '/typed-review/legacy-blocked',
        '/typed-review/legacy-enabled',
        '/typed-review/legacy-disabled',
        '/boolean-synonym-flow-review/published',
        '/boolean-synonym-flow-review/archived',
        '/boolean-synonym-flow-review/enabled',
        '/boolean-synonym-flow-review/disabled',
    ] as $yaml12LegacyBoolPath) {
        if (array_key_exists($yaml12LegacyBoolPath, $yamlTypedScalarProvenance)) {
            throw new RuntimeException('YAML metadata self-test recorded YAML 1.2 legacy boolean word as a typed scalar');
        }
    }
    if (($meta['schema-number-review']['duration'] ?? '') !== '1:20:30') {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 implicit sexagesimal duration');
    }
    if (($meta['schema-number-review']['fractional-duration'] ?? '') !== '1:20:30.5') {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 implicit sexagesimal float duration');
    }
    if (($meta['schema-number-flow-review']['elapsed'] ?? '') !== '0:01:05') {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 implicit flow sexagesimal duration');
    }
    if (($meta['schema-number-review']['explicit-duration'] ?? null) !== 4830) {
        throw new RuntimeException('YAML metadata self-test missed explicit YAML 1.2 sexagesimal integer');
    }
    if (($meta['schema-number-review']['explicit-fractional-duration'] ?? null) !== 4830.5) {
        throw new RuntimeException('YAML metadata self-test missed explicit YAML 1.2 sexagesimal float');
    }
    if (($meta['schema-number-flow-review']['explicit-elapsed'] ?? null) !== 65) {
        throw new RuntimeException('YAML metadata self-test missed explicit YAML 1.2 flow sexagesimal integer');
    }
    foreach ([
        '/schema-number-review/duration',
        '/schema-number-review/fractional-duration',
        '/schema-number-flow-review/elapsed',
    ] as $yaml12LegacyNumberPath) {
        if (array_key_exists($yaml12LegacyNumberPath, $yamlTypedScalarProvenance)) {
            throw new RuntimeException('YAML metadata self-test recorded YAML 1.2 implicit sexagesimal scalar as typed ' . $yaml12LegacyNumberPath);
        }
    }
    foreach ([
        '/schema-number-review/explicit-duration' => ['number', 'int', '1:20:30'],
        '/schema-number-review/explicit-fractional-duration' => ['number', 'float', '1:20:30.5'],
        '/schema-number-flow-review/explicit-elapsed' => ['number', 'int', '0:01:05'],
    ] as $expectedPath => [$expectedType, $expectedTag, $expectedSource]) {
        $entry = $yamlTypedScalarProvenance[$expectedPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing explicit YAML 1.2 sexagesimal provenance ' . $expectedPath);
        }
        if (($entry['scalarType'] ?? '') !== $expectedType || ($entry['explicitTag'] ?? '') !== $expectedTag || ($entry['source'] ?? '') !== $expectedSource) {
            throw new RuntimeException('YAML metadata self-test has wrong explicit YAML 1.2 sexagesimal provenance ' . $expectedPath);
        }
    }
    if (($meta['schema-integer-review']['leading-zero-ticket'] ?? null) !== 52) {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 leading-zero decimal as legacy octal');
    }
    if (($meta['schema-integer-review']['signed-leading-zero-ticket'] ?? null) !== -52) {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 signed leading-zero decimal as legacy octal');
    }
    if (($meta['schema-integer-review']['modern-octal-ticket'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missed YAML 1.2 0o octal integer');
    }
    if (($meta['schema-integer-review']['hexadecimal-ticket'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missed YAML 1.2 hexadecimal integer');
    }
    if (($meta['schema-integer-review']['binary-ticket'] ?? '') !== '0b101010') {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 implicit binary integer');
    }
    if (($meta['schema-integer-review']['explicit-binary-ticket'] ?? null) !== 42) {
        throw new RuntimeException('YAML metadata self-test missed explicit YAML 1.2 binary integer');
    }
    if (($meta['schema-integer-flow-review']['leading-zero-ticket'] ?? null) !== 10) {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 flow leading-zero decimal as legacy octal');
    }
    if (($meta['schema-integer-flow-review']['modern-octal-ticket'] ?? null) !== 8) {
        throw new RuntimeException('YAML metadata self-test missed YAML 1.2 flow 0o octal integer');
    }
    if (($meta['schema-integer-flow-review']['binary-ticket'] ?? '') !== '0b1000') {
        throw new RuntimeException('YAML metadata self-test coerced YAML 1.2 flow implicit binary integer');
    }
    if (($meta['schema-integer-flow-review']['explicit-binary-ticket'] ?? null) !== 8) {
        throw new RuntimeException('YAML metadata self-test missed explicit YAML 1.2 flow binary integer');
    }
    foreach ([
        '/schema-integer-review/binary-ticket',
        '/schema-integer-flow-review/binary-ticket',
    ] as $yaml12ImplicitBinaryPath) {
        if (array_key_exists($yaml12ImplicitBinaryPath, $yamlTypedScalarProvenance)) {
            throw new RuntimeException('YAML metadata self-test recorded YAML 1.2 implicit binary scalar as typed ' . $yaml12ImplicitBinaryPath);
        }
    }
    foreach ([
        '/schema-integer-review/leading-zero-ticket' => ['number', null, '052'],
        '/schema-integer-review/signed-leading-zero-ticket' => ['number', null, '-052'],
        '/schema-integer-review/modern-octal-ticket' => ['number', null, '0o52'],
        '/schema-integer-review/hexadecimal-ticket' => ['number', null, '0x2A'],
        '/schema-integer-review/explicit-binary-ticket' => ['number', 'int', '0b101010'],
        '/schema-integer-flow-review/leading-zero-ticket' => ['number', null, '010'],
        '/schema-integer-flow-review/modern-octal-ticket' => ['number', null, '0o10'],
        '/schema-integer-flow-review/explicit-binary-ticket' => ['number', 'int', '0b1000'],
    ] as $expectedPath => [$expectedType, $expectedTag, $expectedSource]) {
        $entry = $yamlTypedScalarProvenance[$expectedPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing YAML 1.2 integer provenance ' . $expectedPath);
        }
        if (($entry['scalarType'] ?? '') !== $expectedType || ($entry['source'] ?? '') !== $expectedSource) {
            throw new RuntimeException('YAML metadata self-test has wrong YAML 1.2 integer provenance ' . $expectedPath);
        }
        if ($expectedTag !== null && ($entry['explicitTag'] ?? '') !== $expectedTag) {
            throw new RuntimeException('YAML metadata self-test missing YAML 1.2 integer explicit tag ' . $expectedPath);
        }
    }
    if (array_key_exists('/typed-block-review/invalid-priority', $yamlTypedScalarProvenance)) {
        throw new RuntimeException('YAML metadata self-test recorded invalid explicit block integer as a typed scalar');
    }
    if (array_key_exists('/typed-sequence-review/4', $yamlTypedScalarProvenance)) {
        throw new RuntimeException('YAML metadata self-test recorded invalid explicit nested sequence integer as a typed scalar');
    }
    if (array_key_exists('/review-binary/invalid-bytes', $yamlTypedScalarProvenance)) {
        throw new RuntimeException('YAML metadata self-test recorded invalid explicit binary scalar as typed');
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
        '/invalid-escaped-source-uri' => ['double-quoted', '1'],
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
    $yamlExplicitKeyScalarProvenance = [];
    foreach ($yamlScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-explicit-key-scalar') {
            $yamlExplicitKeyScalarProvenance[$entry['path'] ?? ''] = $entry;
        }
    }
    foreach ([
        '/explicit-review/source:key' => ['double-quoted', '"source:key"', 'block'],
        '/explicit:source-uri' => ['double-quoted', '"explicit:source-uri"', 'block'],
        '/flow-explicit-review/source:key' => ['double-quoted', '"source:key"', 'flow'],
        '/flow-explicit-null-review/source' => ['plain', 'source', 'flow-null'],
        '/flow-explicit-null-review/source:key' => ['double-quoted', '"source:key"', 'flow-null'],
    ] as $expectedExplicitKeyPath => [$expectedStyle, $expectedSource, $expectedSyntax]) {
        $entry = $yamlExplicitKeyScalarProvenance[$expectedExplicitKeyPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing explicit key scalar provenance ' . $expectedExplicitKeyPath);
        }
        if (($entry['style'] ?? '') !== $expectedStyle || ($entry['source'] ?? '') !== $expectedSource || ($entry['syntax'] ?? '') !== $expectedSyntax) {
            throw new RuntimeException('YAML metadata self-test has wrong explicit key scalar provenance ' . $expectedExplicitKeyPath);
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
    foreach ([
        '/review-label-set' => 'set',
        '/block-label-set' => 'set',
        '/sequence-label-sets/0' => 'set',
        '/sequence-label-sets/1' => 'set',
        '/review-order_' => 'omap',
        '/ordered-review/reviewer-pairs' => 'pairs',
        '/flow-ordered-review/steps' => 'omap',
        '/flow-ordered-review/reviewers' => 'pairs',
        '/core-collection-review' => 'map',
        '/core-collection-review/labels' => 'seq',
        '/core-flow-collection-review' => 'map',
        '/core-flow-collection-review/labels' => 'seq',
        '/core-reference-list' => 'seq',
        '/core-reference-list/0/metadata' => 'map',
        '/core-tagged-items' => 'seq',
        '/core-tagged-items/0' => 'map',
        '/core-tagged-items/0/labels' => 'seq',
        '/core-tagged-items/1' => 'seq',
    ] as $expectedPath => $expectedTag) {
        if (($yamlCollectionByPath[$expectedPath]['explicitTag'] ?? '') !== $expectedTag) {
            throw new RuntimeException('YAML metadata self-test missing explicit collection tag provenance ' . $expectedPath . ' ' . $expectedTag);
        }
    }
    $yamlExplicitCollectionKeyProvenance = [];
    foreach ($yamlCollectionProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-explicit-key-collection') {
            $yamlExplicitCollectionKeyProvenance[$entry['path'] ?? ''] = $entry;
        }
    }
    foreach ([
        '/[sequence, source-uri]' => ['sequence', 'flow', '2', 'block', '[sequence, source-uri]'],
        '/{source: uri, type: review}' => ['mapping', 'flow', '2', 'block', '{source: uri, type: review}'],
        '/{source: owner, desk: import}' => ['mapping', 'block', '2', 'block', "source: owner\ndesk: import"],
        '/sequence-key-review/[owner, desk]' => ['sequence', 'flow', '2', 'block', '[owner, desk]'],
        '/sequence-key-label-set/[qa, true]' => ['sequence', 'flow', '2', 'set', '[qa, true]'],
        '/map-key-review/{owner: desk, ticket: 7}' => ['mapping', 'flow', '2', 'block', '{owner: desk, ticket: 7}'],
        '/flow-explicit-review/[source, uri]' => ['sequence', 'flow', '2', 'flow', '[source, uri]'],
        '/flow-explicit-review/{owner: desk, ticket: 7}' => ['mapping', 'flow', '2', 'flow', '{owner: desk, ticket: 7}'],
        '/flow-explicit-null-review/[source, uri]' => ['sequence', 'flow', '2', 'flow-null', '[source, uri]'],
        '/flow-explicit-null-review/{owner: desk, ticket: 7}' => ['mapping', 'flow', '2', 'flow-null', '{owner: desk, ticket: 7}'],
        '/block-explicit-null-review/[source, uri]' => ['sequence', 'flow', '2', 'block-null', '[source, uri]'],
        '/block-explicit-null-review/{owner: desk, ticket: 7}' => ['mapping', 'flow', '2', 'block-null', '{owner: desk, ticket: 7}'],
    ] as $expectedCollectionKeyPath => [$expectedKind, $expectedStyle, $expectedCount, $expectedSyntax, $expectedSource]) {
        $entry = $yamlExplicitCollectionKeyProvenance[$expectedCollectionKeyPath] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing explicit collection key provenance ' . $expectedCollectionKeyPath);
        }
        if (
            ($entry['kind'] ?? '') !== $expectedKind
            || ($entry['style'] ?? '') !== $expectedStyle
            || ($entry['memberCount'] ?? '') !== $expectedCount
            || ($entry['syntax'] ?? '') !== $expectedSyntax
            || ($entry['source'] ?? '') !== $expectedSource
        ) {
            throw new RuntimeException('YAML metadata self-test has wrong explicit collection key provenance ' . $expectedCollectionKeyPath);
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
    $stepsCollection = $yamlCollectionByPath['/plain-continuation-review/steps'] ?? [];
    if (
        ($stepsCollection['memberStartLine'] ?? '') === ''
        || ($stepsCollection['memberEndLine'] ?? '') === ''
        || (int) ($stepsCollection['memberStartLine'] ?? '0') <= (int) ($stepsCollection['contentStartLine'] ?? '0')
        || (int) ($stepsCollection['memberEndLine'] ?? '0') !== (int) ($stepsCollection['contentEndLine'] ?? '0')
    ) {
        throw new RuntimeException('YAML metadata self-test missing block collection member source span');
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
    $yamlAliasPairs = [];
    foreach ($yamlAliasProvenance as $entry) {
        $yamlAliasPairs[] = ($entry['alias'] ?? '') . "\0" . ($entry['path'] ?? '') . "\0" . ($entry['valueKind'] ?? '') . "\0" . ($entry['resolved'] ?? '');
    }
    foreach ([
        "*review_defaults\0/review/<<\0mapping\0true",
        "*source:review/defaults\0/source-anchor-review/<<\0mapping\0true",
        "*source:review/defaults\0/flow-source-anchor-review/defaults\0mapping\0true",
        "*review_labels\0/aliases/labels\0sequence\0true",
        "*missing_alias\0/alias-diagnostics/missing\0scalar\0false",
        "*source/ref-primary\0/punctuation-anchor-references/1/<<\0mapping\0true",
    ] as $expectedAliasPair) {
        if (!in_array($expectedAliasPair, $yamlAliasPairs, true)) {
            throw new RuntimeException('YAML metadata self-test missing alias provenance ' . str_replace("\0", ' ', $expectedAliasPair));
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
    if (($meta['core-collection-review']['labels'] ?? []) !== ['core', 'metadata']) {
        throw new RuntimeException('YAML metadata self-test missing explicit core sequence tag metadata');
    }
    if (($meta['core-flow-collection-review']['status'] ?? '') !== 'approved') {
        throw new RuntimeException('YAML metadata self-test missing explicit core flow map tag metadata');
    }
    if (($meta['core-flow-collection-review']['labels'] ?? []) !== ['flow', 'core']) {
        throw new RuntimeException('YAML metadata self-test missing explicit core flow sequence tag metadata');
    }
    if (($meta['core-reference-list'][0]['metadata']['source'] ?? '') !== 'core-tag') {
        throw new RuntimeException('YAML metadata self-test missing explicit core nested map tag metadata');
    }
    if (($meta['core-tagged-items'][0]['labels'] ?? []) !== ['tagged', 'item']) {
        throw new RuntimeException('YAML metadata self-test missing explicit core sequence item map tag metadata');
    }
    if (($meta['core-tagged-items'][1] ?? []) !== ['nested', 'item']) {
        throw new RuntimeException('YAML metadata self-test missing explicit core sequence item sequence tag metadata');
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
    if (array_column($duplicateSetDiagnostics, 'path') !== [
        '/review-label-set/wordpress',
        '/review-label-set/source:key',
        '/block-label-set/migration',
        '/sequence-label-sets/0/draft',
        '/sequence-label-sets/1/queued',
    ]) {
        throw new RuntimeException('YAML metadata self-test missing duplicate set member diagnostic paths');
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
    if (($meta['invalid-ordered-review'][0]['key'] ?? '') !== 'source-title' || ($meta['invalid-ordered-review'][0]['value'] ?? '') !== 'Original export') {
        throw new RuntimeException('YAML metadata self-test missing invalid ordered-map first key');
    }
    if (($meta['invalid-ordered-review'][1]['key'] ?? '') !== 'owner' || ($meta['invalid-ordered-review'][1]['value'] ?? '') !== 'Import Desk') {
        throw new RuntimeException('YAML metadata self-test failed to preserve invalid ordered-map extra key');
    }
    if (
        ($meta['invalid-ordered-review'][2]['key'] ?? '') !== '[bad, key]'
        || !array_key_exists('value', $meta['invalid-ordered-review'][2] ?? [])
        || $meta['invalid-ordered-review'][2]['value'] !== null
    ) {
        throw new RuntimeException('YAML metadata self-test failed to preserve invalid ordered-map sequence member');
    }
    if (($meta['invalid-pairs-review'][0]['key'] ?? '') !== 'owner' || ($meta['invalid-pairs-review'][1]['key'] ?? '') !== 'role') {
        throw new RuntimeException('YAML metadata self-test failed to preserve invalid pairs multi-key member');
    }
    if (($meta['invalid-pairs-review'][2]['key'] ?? '') !== '[bad, key]' || ($meta['invalid-pairs-review'][3]['key'] ?? '') !== 'status') {
        throw new RuntimeException('YAML metadata self-test failed to preserve invalid pairs scalar members');
    }
    if (count($invalidOrderedPairDiagnostics) !== 5) {
        throw new RuntimeException('YAML metadata self-test missing invalid ordered-pair diagnostics');
    }
    $invalidOrderedPairDiagnosticsByPath = [];
    foreach ($invalidOrderedPairDiagnostics as $diagnostic) {
        $invalidOrderedPairDiagnosticsByPath[$diagnostic['path'] ?? ''] = $diagnostic;
    }
    foreach ([
        '/invalid-ordered-review/0' => ['omap', '0', 'mapping', '2'],
        '/invalid-ordered-review/1' => ['omap', '1', 'sequence', null],
        '/invalid-pairs-review/0' => ['pairs', '0', 'mapping', '2'],
        '/invalid-pairs-review/1' => ['pairs', '1', 'sequence', null],
        '/invalid-pairs-review/2' => ['pairs', '2', 'scalar', null],
    ] as $expectedPath => [$expectedTag, $expectedIndex, $expectedKind, $expectedMemberCount]) {
        $diagnostic = $invalidOrderedPairDiagnosticsByPath[$expectedPath] ?? null;
        if ($diagnostic === null) {
            throw new RuntimeException('YAML metadata self-test missing invalid ordered-pair diagnostic ' . $expectedPath);
        }
        if (($diagnostic['type'] ?? '') !== 'yaml-ordered-pair' || ($diagnostic['expected'] ?? '') !== 'single-pair mapping') {
            throw new RuntimeException('YAML metadata self-test has wrong invalid ordered-pair diagnostic type ' . $expectedPath);
        }
        if (($diagnostic['explicitTag'] ?? '') !== $expectedTag || ($diagnostic['pairIndex'] ?? '') !== $expectedIndex) {
            throw new RuntimeException('YAML metadata self-test has wrong invalid ordered-pair index ' . $expectedPath);
        }
        if (($diagnostic['valueKind'] ?? '') !== $expectedKind || (($diagnostic['memberCount'] ?? null) !== $expectedMemberCount)) {
            throw new RuntimeException('YAML metadata self-test has wrong invalid ordered-pair value kind ' . $expectedPath);
        }
        if (($diagnostic['sourceLine'] ?? '') === '') {
            throw new RuntimeException('YAML metadata self-test missing invalid ordered-pair source line ' . $expectedPath);
        }
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
    if (($meta['invalid-merge-review']['status'] ?? '') !== 'queued' || ($meta['invalid-merge-review']['owner'] ?? '') !== 'Merge Audit Desk') {
        throw new RuntimeException('YAML metadata self-test failed to keep valid maps from invalid merge sequence');
    }
    if (($meta['invalid-direct-merge']['owner'] ?? '') !== 'Direct Merge Desk' || array_key_exists('status', $meta['invalid-direct-merge'] ?? [])) {
        throw new RuntimeException('YAML metadata self-test failed to keep invalid direct merge diagnostic-only');
    }
    $mergeShadowPaths = array_column($mergeShadowDiagnostics, 'path');
    foreach (['/merge-sequence-review/status', '/merge-sequence-review/labels', '/merge-sequence-audit/status', '/flow-merge-review/status'] as $expectedPath) {
        if (!in_array($expectedPath, $mergeShadowPaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing merge precedence diagnostic path ' . $expectedPath);
        }
    }
    $invalidMergePaths = array_column($invalidMergeDiagnostics, 'path');
    foreach (['/invalid-merge-review/<<', '/invalid-direct-merge/<<'] as $expectedPath) {
        if (!in_array($expectedPath, $invalidMergePaths, true)) {
            throw new RuntimeException('YAML metadata self-test missing invalid merge diagnostic path ' . $expectedPath);
        }
    }
    if (array_column($invalidMergeDiagnostics, 'valueKind') !== ['scalar', 'sequence', 'scalar']) {
        throw new RuntimeException('YAML metadata self-test missing invalid merge value-kind metadata');
    }
    $yamlMergeProvenanceByPath = [];
    foreach ($yamlMergeProvenance as $entry) {
        $path = $entry['path'] ?? '';
        if ($path !== '') {
            $yamlMergeProvenanceByPath[$path] = $entry;
        }
    }
    $expectedMergeProvenance = [
        '/merge-sequence-review/<<' => ['sequence', '2', '0', 'applied'],
        '/invalid-merge-review/<<' => ['sequence', '2', '2', 'partial'],
        '/invalid-direct-merge/<<' => ['scalar', '0', '1', 'invalid'],
        '/merge-tag-flow-review/<<' => ['mapping', '1', '0', 'applied'],
        '/explicit-review/<<' => ['mapping', '1', '0', 'applied'],
    ];
    foreach ($expectedMergeProvenance as $path => [$valueKind, $mergeSourceCount, $invalidMergeSourceCount, $policy]) {
        $entry = $yamlMergeProvenanceByPath[$path] ?? null;
        if ($entry === null) {
            throw new RuntimeException('YAML metadata self-test missing merge provenance path ' . $path);
        }
        if (($entry['type'] ?? '') !== 'yaml-merge' || ($entry['reason'] ?? '') !== 'merge-source') {
            throw new RuntimeException('YAML metadata self-test missing merge provenance record type for ' . $path);
        }
        if (
            ($entry['valueKind'] ?? '') !== $valueKind
            || ($entry['mergeSourceCount'] ?? '') !== $mergeSourceCount
            || ($entry['invalidMergeSourceCount'] ?? '') !== $invalidMergeSourceCount
            || ($entry['policy'] ?? '') !== $policy
        ) {
            throw new RuntimeException('YAML metadata self-test mismatched merge provenance source counts for ' . $path);
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
    $flowNullKeyDiagnosticsByPath = [];
    foreach ($flowNullKeyDiagnostics as $diagnostic) {
        $flowNullKeyDiagnosticsByPath[$diagnostic['path'] ?? ''] = $diagnostic;
    }
    foreach ([
        '/flow-explicit-null-review/source' => 'explicit',
        '/flow-explicit-null-review/source:key' => 'explicit',
        '/flow-explicit-null-review/[source, uri]' => 'explicit',
        '/flow-explicit-null-review/{owner: desk, ticket: 7}' => 'explicit',
        '/flow-explicit-null-reference/metadata/[source, key]' => 'explicit',
        '/flow-explicit-null-reference/metadata/{type: review}' => 'explicit',
        '/flow-implicit-null-review/source' => 'implicit',
        '/flow-implicit-null-review/source:key' => 'implicit',
        '/flow-implicit-null-review/[source, uri]' => 'implicit',
        '/flow-implicit-null-review/{owner: desk, ticket: 7}' => 'implicit',
        '/flow-implicit-null-reference/metadata/[source, key]' => 'implicit',
        '/flow-implicit-null-reference/metadata/{type: review}' => 'implicit',
    ] as $expectedPath => $expectedSyntax) {
        $diagnostic = $flowNullKeyDiagnosticsByPath[$expectedPath] ?? null;
        if ($diagnostic === null || ($diagnostic['syntax'] ?? '') !== $expectedSyntax) {
            throw new RuntimeException('YAML metadata self-test missing flow null-key diagnostic ' . $expectedPath);
        }
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
    if (($meta['writer-hashtag-label'] ?? '') !== '#needs-review') {
        throw new RuntimeException('YAML metadata self-test missing writer hashtag label source metadata');
    }
    if (($meta['writer-hashtag-labels'] ?? []) !== ['#migration', '#wp-import', 'safe#fragment']) {
        throw new RuntimeException('YAML metadata self-test missing writer hashtag label list source metadata');
    }
    if (($meta['writer-colon-label'] ?? '') !== ':needs-review') {
        throw new RuntimeException('YAML metadata self-test missing writer colon label source metadata');
    }
    if (($meta['writer-colon-labels'] ?? []) !== [':migration', ':wp-import', 'safe:fragment']) {
        throw new RuntimeException('YAML metadata self-test missing writer colon label list source metadata');
    }
    if (($meta['writer-sexagesimal-duration'] ?? '') !== '2:03') {
        throw new RuntimeException('YAML metadata self-test missing writer sexagesimal-looking duration source metadata');
    }
    if (($meta['writer-sexagesimal-labels'] ?? []) !== ['0:01', '1:20:30.5', 'safe:fragment']) {
        throw new RuntimeException('YAML metadata self-test missing writer sexagesimal-looking label list source metadata');
    }
    if (($meta['writer-special-float-status'] ?? '') !== '.inf') {
        throw new RuntimeException('YAML metadata self-test missing writer special-float-looking status source metadata');
    }
    if (($meta['writer-special-float-labels'] ?? []) !== ['-.inf', '+.nan', 'safe.inf']) {
        throw new RuntimeException('YAML metadata self-test missing writer special-float-looking label list source metadata');
    }
    if (($meta['writer-timestamp-date'] ?? '') !== '2026-6-3') {
        throw new RuntimeException('YAML metadata self-test missing writer timestamp-looking date source metadata');
    }
    if (($meta['writer-timestamp-captured-at'] ?? '') !== '2026-6-3T4:05:06Z') {
        throw new RuntimeException('YAML metadata self-test missing writer timestamp-looking datetime source metadata');
    }
    if (($meta['writer-timestamp-labels'] ?? []) !== ['2026-6-4', '2026-6-4T5:06:07+5', 'release-2026-6-4']) {
        throw new RuntimeException('YAML metadata self-test missing writer timestamp-looking label list source metadata');
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
    if (($meta['On'] ?? '') !== 'tagged quoted boolean-looking source field') {
        throw new RuntimeException('YAML metadata self-test missing tagged quoted explicit top-level block key');
    }
    if (($meta['0b101'] ?? '') !== 'tagged quoted binary-looking source field') {
        throw new RuntimeException('YAML metadata self-test missing tagged quoted explicit top-level numeric block key');
    }
    if (($meta['No'] ?? '') !== 'flow tagged quoted boolean-looking source field') {
        throw new RuntimeException('YAML metadata self-test missing tagged quoted explicit top-level flow key');
    }
    if (($meta['0b110'] ?? '') !== 'flow tagged quoted binary-looking source field') {
        throw new RuntimeException('YAML metadata self-test missing tagged quoted explicit top-level numeric flow key');
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
    if (array_intersect(['no', 'Off', '3.14', '0o52', 'On', '0b101', 'No', '0b110'], array_column($yamlDiagnostics, 'field')) !== []) {
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
        "/abstract\0source abstract reviewer comment",
        "/flow-comment-labels\0source label",
        "/flow-comment-review\0reviewer queue state",
        "/flow-comment-review\0reviewer import tag",
        "/plain-continuation-review/steps\0collect source packet sequence comment",
        "/plain-continuation-review/steps\0approve source packet sequence comment",
        "/sequence-explicit-review-items/0/[source, uri]\0sequence explicit source key reviewer comment",
        "/sequence-explicit-review-items/1/{owner: desk, ticket: 7}\0sequence explicit owner key reviewer comment",
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
    if (($meta['invalid-escaped-source-uri'] ?? '') !== 'https://example.test/\zexports') {
        throw new RuntimeException('YAML metadata self-test hid invalid escaped source URI');
    }
    if (
        count($invalidDoubleQuotedEscapeDiagnostics) !== 1
        || ($invalidDoubleQuotedEscapeDiagnostics[0]['path'] ?? '') !== '/invalid-escaped-source-uri'
        || ($invalidDoubleQuotedEscapeDiagnostics[0]['escape'] ?? '') !== '\z'
        || ($invalidDoubleQuotedEscapeDiagnostics[0]['type'] ?? '') !== 'yaml-scalar'
    ) {
        throw new RuntimeException('YAML metadata self-test missing invalid double-quoted escape diagnostics');
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
    if (!str_contains($metadataMarkdown, "abstract: | # source abstract reviewer comment\n  Source abstract keeps **review** emphasis")) {
        throw new RuntimeException('YAML metadata self-test did not write multiline abstract as a YAML block scalar');
    }
    if (!str_contains($metadataMarkdown, "source-review-log: |- # folded reviewer log with preserved nested lines\n  Review steps:\n    - preserve front matter")) {
        throw new RuntimeException('YAML metadata self-test did not write multiline review log as a stripped YAML block scalar');
    }
    if (!str_contains($metadataMarkdown, "review-notes:\n  - |-\n    Preserve original front matter.")) {
        throw new RuntimeException('YAML metadata self-test did not write sequence multiline note as a YAML block scalar');
    }
    if (
        !str_contains($metadataMarkdown, 'note-bytes: !!binary "UmV2aWV3IG1ldGFkYXRh"')
        || !str_contains($metadataMarkdown, 'digest-bytes: !!binary "U291cmNlIFBhY2tldA=="')
    ) {
        throw new RuntimeException('YAML metadata self-test did not preserve valid binary scalar tags in writer metadata');
    }
    if (
        str_contains($metadataMarkdown, 'note-bytes: "Review metadata"')
        || str_contains($metadataMarkdown, 'digest-bytes: "Source Packet"')
        || !str_contains($metadataMarkdown, 'invalid-bytes: "not base64!"')
    ) {
        throw new RuntimeException('YAML metadata self-test confused valid and invalid binary scalar writer metadata');
    }
    if (!str_contains($metadataMarkdown, "  reviewer-pairs: !!pairs\n    - owner: \"Import Desk\"\n    - owner: \"QA Desk\"")) {
        throw new RuntimeException('YAML metadata self-test did not preserve ordered pair tag in nested writer metadata');
    }
    if (!str_contains($metadataMarkdown, "flow-ordered-review:\n  steps: !!omap\n    - stage: collected\n    - stage: normalized\n  reviewers: !!pairs\n    - owner: \"Import Desk\"")) {
        throw new RuntimeException('YAML metadata self-test did not preserve flow ordered collection tags in writer metadata');
    }
    if (
        !str_contains($metadataMarkdown, "review-label-set: !!set\n  ? front-matter\n  ? wordpress\n  ? \"source:key\"")
        || !str_contains($metadataMarkdown, "block-label-set: !!set\n  ? migration\n  ? \"qa:review\"")
        || !str_contains($metadataMarkdown, "sequence-label-sets:\n  - !!set\n    ? draft\n    ? published\n  - !!set\n    ? queued\n    ? \"needs:review\"")
    ) {
        throw new RuntimeException('YAML metadata self-test did not preserve explicit set tags in writer metadata');
    }
    if (
        !str_contains($metadataMarkdown, "  # sequence explicit source key reviewer comment\n  - \"[source, uri]\": https://example.test/exports/packet#sequence-explicit-item")
        || !str_contains($metadataMarkdown, "  # sequence explicit owner key reviewer comment\n  - \"{owner: desk, ticket: 7}\": approved")
    ) {
        throw new RuntimeException('YAML metadata self-test did not preserve standalone source comments in writer metadata');
    }
    if (!str_contains($metadataMarkdown, "\"source label\": \"Migration review\" # writer scalar trailing source label")) {
        throw new RuntimeException('YAML metadata self-test did not preserve trailing scalar source comments in writer metadata');
    }
    if (str_contains($metadataMarkdown, "reviewer-pairs:\n    - key: owner")) {
        throw new RuntimeException('YAML metadata self-test flattened ordered pair metadata into key/value records');
    }
    if (str_contains($metadataMarkdown, "review-label-set:\n  front-matter: null") || str_contains($metadataMarkdown, "block-label-set:\n  migration: null")) {
        throw new RuntimeException('YAML metadata self-test flattened set metadata into null-valued maps');
    }
    if (
        !str_contains($metadataMarkdown, "writer-hashtag-label: \"#needs-review\"")
        || !str_contains($metadataMarkdown, "  - \"#migration\"")
        || !str_contains($metadataMarkdown, "  - \"#wp-import\"")
    ) {
        throw new RuntimeException('YAML metadata self-test did not quote comment-looking writer scalars');
    }
    if (
        !str_contains($metadataMarkdown, "writer-colon-label: \":needs-review\"")
        || !str_contains($metadataMarkdown, "  - \":migration\"")
        || !str_contains($metadataMarkdown, "  - \":wp-import\"")
        || !str_contains($metadataMarkdown, "  - safe:fragment")
    ) {
        throw new RuntimeException('YAML metadata self-test did not quote colon-indicator writer scalars');
    }
    if (
        !str_contains($metadataMarkdown, "writer-sexagesimal-duration: \"2:03\"")
        || !str_contains($metadataMarkdown, "  - \"0:01\"")
        || !str_contains($metadataMarkdown, "  - \"1:20:30.5\"")
        || !str_contains($metadataMarkdown, "  - safe:fragment")
    ) {
        throw new RuntimeException('YAML metadata self-test did not quote sexagesimal-looking writer scalars');
    }
    if (
        !str_contains($metadataMarkdown, "writer-special-float-status: \".inf\"")
        || !str_contains($metadataMarkdown, "  - \"-.inf\"")
        || !str_contains($metadataMarkdown, "  - \"+.nan\"")
        || !str_contains($metadataMarkdown, "  - safe.inf")
    ) {
        throw new RuntimeException('YAML metadata self-test did not quote special-float-looking writer scalars');
    }
    if (
        !str_contains($metadataMarkdown, "writer-timestamp-date: \"2026-6-3\"")
        || !str_contains($metadataMarkdown, "writer-timestamp-captured-at: \"2026-6-3T4:05:06Z\"")
        || !str_contains($metadataMarkdown, "  - \"2026-6-4\"")
        || !str_contains($metadataMarkdown, "  - \"2026-6-4T5:06:07+5\"")
        || !str_contains($metadataMarkdown, "  - release-2026-6-4")
    ) {
        throw new RuntimeException('YAML metadata self-test did not quote timestamp-looking writer scalars');
    }
    if (str_contains($metadataMarkdown, 'writer-sexagesimal-duration: 2:03')) {
        throw new RuntimeException('YAML metadata self-test emitted ambiguous sexagesimal writer scalar');
    }
    if (str_contains($metadataMarkdown, 'writer-special-float-status: .inf')) {
        throw new RuntimeException('YAML metadata self-test emitted ambiguous special-float writer scalar');
    }
    if (str_contains($metadataMarkdown, 'writer-timestamp-date: 2026-6-3') || str_contains($metadataMarkdown, 'writer-timestamp-captured-at: 2026-6-3T4:05:06Z')) {
        throw new RuntimeException('YAML metadata self-test emitted ambiguous timestamp writer scalar');
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
    $metadataRoundTripCollectionTags = [];
    foreach ($metadataRoundTripCollectionProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-collection' && isset($entry['explicitTag'])) {
            $metadataRoundTripCollectionTags[$entry['path'] ?? ''] = $entry['explicitTag'];
        }
    }
    foreach ([
        '/review-label-set' => 'set',
        '/block-label-set' => 'set',
        '/sequence-label-sets/0' => 'set',
        '/sequence-label-sets/1' => 'set',
        '/ordered-review/reviewer-pairs' => 'pairs',
        '/flow-ordered-review/steps' => 'omap',
        '/flow-ordered-review/reviewers' => 'pairs',
    ] as $expectedPath => $expectedTag) {
        if (($metadataRoundTripCollectionTags[$expectedPath] ?? null) !== $expectedTag) {
            throw new RuntimeException('YAML metadata self-test lost writer explicit collection tag at ' . $expectedPath);
        }
    }
    $metadataRoundTripScalarTags = [];
    foreach ($metadataRoundTripScalarProvenance as $entry) {
        if (($entry['type'] ?? '') === 'yaml-typed-scalar' && isset($entry['explicitTag'])) {
            $metadataRoundTripScalarTags[$entry['path'] ?? ''] = $entry;
        }
    }
    foreach ([
        '/review-binary/note-bytes',
        '/review-binary/digest-bytes',
    ] as $expectedPath) {
        if (($metadataRoundTripScalarTags[$expectedPath]['explicitTag'] ?? null) !== 'binary') {
            throw new RuntimeException('YAML metadata self-test lost writer explicit binary scalar tag at ' . $expectedPath);
        }
        if (($metadataRoundTripScalarTags[$expectedPath]['scalarType'] ?? null) !== 'binary') {
            throw new RuntimeException('YAML metadata self-test lost writer binary scalar type at ' . $expectedPath);
        }
    }
    if (isset($metadataRoundTripScalarTags['/review-binary/invalid-bytes'])) {
        throw new RuntimeException('YAML metadata self-test promoted invalid binary scalar during writer round trip');
    }
    if (($metadataRoundTripMeta['source-uri'] ?? '') !== '/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test lost quoted writer source URI during round trip');
    }
    if (($metadataRoundTripMeta['writer-hashtag-label'] ?? '') !== '#needs-review') {
        throw new RuntimeException('YAML metadata self-test lost comment-looking writer scalar during round trip');
    }
    if (($metadataRoundTripMeta['writer-hashtag-labels'] ?? []) !== ['#migration', '#wp-import', 'safe#fragment']) {
        throw new RuntimeException('YAML metadata self-test lost comment-looking writer sequence scalars during round trip');
    }
    if (($metadataRoundTripMeta['writer-colon-label'] ?? '') !== ':needs-review') {
        throw new RuntimeException('YAML metadata self-test lost colon-indicator writer scalar during round trip');
    }
    if (($metadataRoundTripMeta['writer-colon-labels'] ?? []) !== [':migration', ':wp-import', 'safe:fragment']) {
        throw new RuntimeException('YAML metadata self-test lost colon-indicator writer sequence scalars during round trip');
    }
    if (($metadataRoundTripMeta['writer-sexagesimal-duration'] ?? '') !== '2:03') {
        throw new RuntimeException('YAML metadata self-test lost sexagesimal-looking writer scalar during round trip');
    }
    if (($metadataRoundTripMeta['writer-sexagesimal-labels'] ?? []) !== ['0:01', '1:20:30.5', 'safe:fragment']) {
        throw new RuntimeException('YAML metadata self-test lost sexagesimal-looking writer sequence scalars during round trip');
    }
    if (($metadataRoundTripMeta['writer-special-float-status'] ?? '') !== '.inf') {
        throw new RuntimeException('YAML metadata self-test lost special-float-looking writer scalar during round trip');
    }
    if (($metadataRoundTripMeta['writer-special-float-labels'] ?? []) !== ['-.inf', '+.nan', 'safe.inf']) {
        throw new RuntimeException('YAML metadata self-test lost special-float-looking writer sequence scalars during round trip');
    }
    if (($metadataRoundTripMeta['writer-timestamp-date'] ?? '') !== '2026-6-3') {
        throw new RuntimeException('YAML metadata self-test lost timestamp-looking writer date during round trip');
    }
    if (($metadataRoundTripMeta['writer-timestamp-captured-at'] ?? '') !== '2026-6-3T4:05:06Z') {
        throw new RuntimeException('YAML metadata self-test lost timestamp-looking writer datetime during round trip');
    }
    if (($metadataRoundTripMeta['writer-timestamp-labels'] ?? []) !== ['2026-6-4', '2026-6-4T5:06:07+5', 'release-2026-6-4']) {
        throw new RuntimeException('YAML metadata self-test lost timestamp-looking writer sequence scalars during round trip');
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
    if ($invalidFlowCollectionDocument->attr('meta') !== null) {
        throw new RuntimeException('YAML metadata self-test accepted an unterminated multiline flow collection');
    }
    if (!str_contains($invalidFlowCollectionBlocks, '<p>title: Invalid flow collection <strong>Packet</strong>')) {
        throw new RuntimeException('YAML metadata self-test failed to keep invalid flow title source visible');
    }
    if (!str_contains($invalidFlowCollectionBlocks, 'labels: [front-matter, wordpress]')) {
        throw new RuntimeException('YAML metadata self-test failed to keep invalid flow labels source visible');
    }
    if (!str_contains($invalidFlowCollectionBlocks, '<h1 id="invalid-flow-collection-body">Invalid flow collection body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing invalid flow fallback body heading');
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
echo 'Writer hashtag labels: ' . ($metadataRoundTripMeta['writer-hashtag-label'] ?? '') . ' / ' . implode(', ', $metadataRoundTripMeta['writer-hashtag-labels'] ?? []) . "\n";
echo 'Writer colon labels: ' . ($metadataRoundTripMeta['writer-colon-label'] ?? '') . ' / ' . implode(', ', $metadataRoundTripMeta['writer-colon-labels'] ?? []) . "\n";
echo 'Writer sexagesimal labels: ' . ($metadataRoundTripMeta['writer-sexagesimal-duration'] ?? '') . ' / ' . implode(', ', $metadataRoundTripMeta['writer-sexagesimal-labels'] ?? []) . "\n";
echo 'Writer special float labels: ' . ($metadataRoundTripMeta['writer-special-float-status'] ?? '') . ' / ' . implode(', ', $metadataRoundTripMeta['writer-special-float-labels'] ?? []) . "\n";
echo 'Writer timestamp labels: ' . ($metadataRoundTripMeta['writer-timestamp-date'] ?? '') . ' / ' . implode(', ', $metadataRoundTripMeta['writer-timestamp-labels'] ?? []) . "\n";
echo 'Flow colon key review: ' . ($meta['flow-colon-key-review']['source:key'] ?? '') . ' / ' . ($meta['flow-colon-key-review']['dc:title'] ?? '') . "\n";
echo 'Flow document review: ' . ($meta['flow-document-review']['status'] ?? '') . ' / priority ' . ($meta['flow-document-review']['priority'] ?? '') . "\n";
echo 'Ambiguous field diagnostics: ' . implode(', ', array_column($ambiguousYamlDiagnostics, 'field')) . "\n";
echo 'Quoted ambiguous fields: ' . ($meta['no'] ?? '') . ' / ' . ($meta['Off'] ?? '') . ' / ' . ($meta['3.14'] ?? '') . ' / ' . ($meta['0o52'] ?? '') . "\n";
echo 'YAML diagnostics: ' . count($yamlDiagnostics) . "\n";
echo 'YAML invalid TAG directives: ' . count($invalidTagDiagnostics) . "\n";
echo 'YAML reserved directive: ' . ($reservedDirectiveDirectives[0]['directive'] ?? '') . ' / ' . ($reservedDirectiveDirectives[0]['parameters'] ?? '') . "\n";
echo 'YAML invalid merge diagnostics: ' . count($invalidMergeDiagnostics) . "\n";
echo 'YAML invalid ordered pair diagnostics: ' . count($invalidOrderedPairDiagnostics) . "\n";
echo 'YAML undefined tag handle diagnostics: ' . implode(', ', array_column($undefinedTagHandleReviewDiagnostics, 'path')) . "\n";
echo 'YAML invalid binary diagnostics: ' . count($invalidBinaryScalarDiagnostics) . "\n";
echo 'YAML duplicate set diagnostics: ' . implode(', ', array_column($duplicateSetDiagnostics, 'path')) . "\n";
echo 'YAML alias diagnostic paths: ' . implode(', ', array_column($aliasYamlDiagnostics, 'path')) . "\n";
echo 'YAML custom tag provenance: ' . count($yamlTagProvenance) . "\n";
echo 'YAML custom tag provenance paths: ' . implode(', ', array_filter(array_column($yamlTagProvenance, 'path'))) . "\n";
echo 'YAML comment provenance: ' . count($yamlCommentProvenance) . "\n";
echo 'YAML comment provenance paths: ' . implode(', ', array_filter(array_column($yamlCommentProvenance, 'path'))) . "\n";
echo 'YAML anchor provenance: ' . count($yamlAnchorProvenance) . "\n";
echo 'YAML anchor provenance paths: ' . implode(', ', array_filter(array_column($yamlAnchorProvenance, 'path'))) . "\n";
echo 'YAML alias provenance: ' . count($yamlAliasProvenance) . "\n";
echo 'YAML alias provenance paths: ' . implode(', ', array_filter(array_column($yamlAliasProvenance, 'path'))) . "\n";
echo 'YAML collection provenance: ' . count($yamlCollectionProvenance) . "\n";
echo 'YAML collection provenance paths: ' . implode(', ', array_filter(array_column($yamlCollectionProvenance, 'path'))) . "\n";
echo 'YAML stream provenance: ' . count($yamlStreamProvenance) . "\n";
echo 'YAML stream provenance fields: ' . implode(' | ', array_column($yamlStreamProvenance, 'fields')) . "\n";
echo 'YAML review summary: ' . ($yamlReviewSummary['reviewStatus'] ?? '') . ' / diagnostics ' . ($yamlReviewSummary['diagnosticCount'] ?? '') . ' / streams ' . ($yamlReviewSummary['streamCount'] ?? '') . "\n";
echo 'Compact sequence item: ' . ($meta['compact-review-items'][0]['label'] ?? '') . ' / ' . ($meta['compact-review-items'][1]['source:key'] ?? '') . "\n";
echo 'Source review log: ' . str_replace("\n", ' | ', $meta['source-review-log'] ?? '') . "\n";
echo 'Source revision: ' . ($meta['source-revision'] ?? '') . "\n";
echo 'Typed review revision: ' . ($meta['typed-review']['typed-revision'] ?? '') . ' / confidence ' . ($meta['typed-review']['confidence'] ?? '') . "\n";
echo 'Typed review duration seconds: ' . ($meta['typed-review']['review-duration-seconds'] ?? '') . ' / flow ' . ($meta['typed-flow-review']['elapsed'] ?? '') . "\n";
echo 'Boolean synonym review: '
    . (($meta['typed-review']['legacy-approved'] ?? null) === 'yes' ? 'yes=string' : 'yes=missing')
    . ' / '
    . (($meta['typed-review']['legacy-blocked'] ?? null) === 'NO' ? 'NO=string' : 'NO=missing')
    . ' / '
    . (($meta['boolean-synonym-flow-review']['enabled'] ?? null) === 'ON' ? 'ON=string' : 'ON=missing')
    . ' / '
    . (($meta['boolean-synonym-flow-review']['disabled'] ?? null) === 'OFF' ? 'OFF=string' : 'OFF=missing')
    . "\n";
echo 'YAML 1.2 integer review: '
    . ($meta['schema-integer-review']['leading-zero-ticket'] ?? '')
    . ' / '
    . ($meta['schema-integer-review']['binary-ticket'] ?? '')
    . ' / flow '
    . ($meta['schema-integer-flow-review']['leading-zero-ticket'] ?? '')
    . "\n";
echo 'Tag directive review: ' . ($meta['tag-directive-review']['owner'] ?? '') . ' / priority ' . ($meta['tag-directive-review']['priority'] ?? '') . "\n";
echo 'Non-specific tag review: ' . ($meta['non-specific-review']['owner'] ?? '') . ' / ' . implode(', ', $meta['non-specific-review']['labels'] ?? []) . "\n";
echo 'Source captured at: ' . ($meta['source-captured-at'] ?? '') . "\n";
echo 'Review binary bytes: ' . ($meta['review-binary']['note-bytes'] ?? '') . ' / ' . ($meta['review-binary']['digest-bytes'] ?? '') . "\n";
echo 'Multiline flow labels: ' . implode(', ', $meta['multiline-flow-labels'] ?? []) . "\n";
echo 'Flow comment labels: ' . implode(', ', $meta['flow-comment-labels'] ?? []) . "\n";
echo 'Escaped source title: ' . ($meta['escaped-source-title'] ?? '') . "\n";
echo 'Invalid escaped source URI diagnostics: ' . implode(', ', array_column($invalidDoubleQuotedEscapeDiagnostics, 'path')) . "\n";
echo 'Multiline source title: ' . ($meta['multiline-source-title'] ?? '') . "\n";
echo 'Single quoted source note: ' . ($meta['single-quoted-source-note'] ?? '') . "\n";
echo 'Plain continuation note: ' . ($meta['plain-continuation-review']['note'] ?? '') . "\n";
echo 'Reference: ' . ($meta['references'][0]['id'] ?? '') . ' / ' . ($meta['references'][0]['title'] ?? '') . "\n\n";
echo $blocks . "\n";
echo 'Implicit opening title: ' . ($implicitOpeningMeta['title'] ?? '') . "\n";
echo 'Implicit opening review: ' . ($implicitOpeningMeta['review']['status'] ?? '') . ' / priority ' . ($implicitOpeningMeta['review']['priority'] ?? '') . "\n";
echo 'Implicit opening reference: ' . ($implicitOpeningMeta['references'][0]['id'] ?? '') . "\n";
echo $implicitOpeningBlocks . "\n";
echo 'Invalid flow collection metadata: ' . ($invalidFlowCollectionDocument->attr('meta') === null ? 'rejected' : 'accepted') . "\n";
echo $invalidFlowCollectionBlocks . "\n";
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
echo 'Indented block scalar review: '
    . str_replace("\n", ' | ', $indentedBlockScalarMeta['review']['note'] ?? '')
    . ' / key '
    . ($indentedBlockScalarMeta['source:key'] ?? '')
    . "\n";
