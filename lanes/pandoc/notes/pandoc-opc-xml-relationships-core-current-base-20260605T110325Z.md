# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T110325Z`
Base: `f6dbe30624ad0570d265873814a3f8256148d7bb`

## Behavior Added

- Added `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` as
  a bounded native PHP audit for package signature XML.
- The helper reads a signature package part, finds XML Signature
  `Reference` elements with the OPC Relationship Transform algorithm, extracts
  `RelationshipReference SourceId` and `RelationshipsGroupReference
  SourceType` selectors from the OPC digital-signature namespace, and reuses
  the existing relationship selector/materializer to emit selected
  relationship XML.
- The audit reports the referenced relationships part, source package part,
  selected relationship ids, selector validity, target validity, following
  canonicalization transform algorithm, duplicate relationship transforms for
  one relationships part, unsupported transform children, missing selectors,
  and non-relationships reference URIs.
- Updated the WordPress DOCX OPC preflight example so review packets expose the
  parsed package-signature relationship-transform audit without invoking
  XMLDSig validators, office tools, Pandoc, or zip/unzip.

## Source Truth

- ECMA-376/OPC relationship transform behavior filters relationships by
  `@SourceId` and `@SourceType` selectors and treats multiple relationship
  transforms for one relationships part as an error:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_RelationshipsGroupRe_topic_ID0E6UGK.html
- The relationships transform algorithm keeps relationships whose `Id` or
  `Type` matches those selector values and requires package-specific
  relationship XML handling:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_Relationships_topic_ID0EMCHK.html
- Microsoft OPC selector API docs map signature markup
  `RelationshipReference` to relationship id and
  `RelationshipGroupReference` / `RelationshipsGroupReference` to relationship
  type selectors:
  https://learn.microsoft.com/windows/win32/api/msopc/nn-msopc-iopcrelationshipselector

## Verification

- Baseline focused OPC test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 686 assertions, 0 failures`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 724 assertions, 0 failures`.
- WordPress OPC preflight smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP lint, JSON validation, and diff whitespace checks are recorded in the
  final handoff.

## Status Delta

- Focused OPC tests moved from 45 to 46 PASS cases.
- Focused OPC assertions moved from 686 to 724, adding 38 assertions.
- `lane-status.json` `phpPass` moved from 854 to 855.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks moved from 1,312 to 1,313 with a
  new `mappedOpcSignatureRelationshipTransformCases` bucket.

## Dependency Closure

No new support component is required. This reuses existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` support.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME
content-type validation, Pack URI override and relationship part normalization,
relationship XML namespace/shape parsing, XML NCName Id validation, target
integrity preflight, relationship-part source validation, external target
policy, package-part and package-consistency preflight, digital-signature
origin/signature preflight, embedded package/object preflight, relationship
Type URI diagnostics, root office-document preflight, markup-compatibility
extension policy, reachable relationship closure traversal, SourceId/SourceType
selector preflight, relationship transform materialization, relationship-part
load audits, and relationship-family inventory.

It does not implement XML Signature digest validation, C14N execution,
certificate-chain policy, encrypted package handling, nested embedded package
graph expansion, WordprocessingML body parsing, or full Pandoc/Haskell runner
parity.

## Follow-Up

Keep full XML Signature digest/canonicalization validation, signature reference
URI content-type filtering, certificate-chain policy, encrypted package policy,
nested embedded package graph expansion, and higher-level DOCX UI treatment of
signature diagnostics as separate bounded slices.
