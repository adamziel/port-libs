# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T113544Z`
Base accepted HEAD: `651615e05fea9d010bb9bbcaa297afe05c6cf991`

## Behavior Added

- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now
  accepts singular `RelationshipGroupReference` elements as `SourceType`
  relationship selectors, alongside the already supported plural
  `RelationshipsGroupReference` spelling.
- Added a focused package-signature fixture proving a singular
  `RelationshipGroupReference SourceType` selector selects only matching
  relationship types and materializes the expected relationship XML.
- Updated the WordPress DOCX OPC preflight example so its package-signature
  review packet uses the singular selector spelling and still reports the
  selected package relationship group without invoking XMLDSig validators.

## Source Truth

- OPC relationship transform behavior filters relationships by `SourceId` and
  `SourceType` selector values:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_RelationshipsGroupRe_topic_ID0E6UGK.html
- Microsoft OPC selector API docs map `RelationshipReference` to relationship
  id selection and `RelationshipGroupReference` / `RelationshipsGroupReference`
  to relationship type selection:
  https://learn.microsoft.com/windows/win32/api/msopc/nn-msopc-iopcrelationshipselector

## Evidence

- Baseline focused OPC check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 724 assertions, 0 failures`.
- Red-first singular selector check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 700 assertions, 1 failures`.
  - Failure: the singular selector fixture returned an invalid relationship
    transform row before the parser accepted `RelationshipGroupReference`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 740 assertions, 0 failures`.
- WordPress OPC preflight smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP lint, JSON validation, and diff whitespace checks are recorded in the
  final handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 46 to 47 PASS cases.
- Focused OPC assertions moved from 724 to 740, adding 16 assertions.
- Lane `phpPass` moved from `867` to `868`.
- Manifest mapped native checks moved from `1325` to `1326`.
- Added `mappedOpcSingularRelationshipGroupReferenceCases = 1` and
  `opcSingularRelationshipGroupReferenceAssertions = 16`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` primitives.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, Word, LibreOffice, `zip`, `unzip`, external office tools, XMLDSig
validators, external template engines, TeX/PDF engines, browser renderers,
online sanitizers, or online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME
content-type validation, Pack URI override and relationship part
normalization, relationship XML namespace/shape parsing, XML NCName Id
validation, target integrity preflight, relationship-part source validation,
external target policy, package-part and package-consistency preflight,
digital-signature origin/signature preflight, embedded package/object
preflight, relationship Type URI diagnostics, root office-document preflight,
markup-compatibility extension policy, reachable relationship closure
traversal, SourceId/SourceType selector preflight, relationship transform
materialization, relationship-part load audits, relationship-family inventory,
and package-signature relationship-transform auditing.

It does not implement XML Signature digest validation, C14N execution,
certificate-chain policy, encrypted package handling, nested embedded package
graph expansion, WordprocessingML body parsing, or full Pandoc/Haskell runner
parity.

## Follow-Up

Keep full XML Signature digest/canonicalization validation, signature
reference URI content-type filtering, certificate-chain policy, encrypted
package policy, nested embedded package graph expansion, and higher-level DOCX
UI treatment of signature diagnostics as separate bounded slices.
