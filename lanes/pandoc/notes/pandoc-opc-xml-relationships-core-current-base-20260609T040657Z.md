# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260609T040657Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T040657Z`
Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Implementation

This slice extends native OPC relationship preflight metadata for internal
targets:

- `OpcRelationshipGraph::preflightInternalTargetReferences()` now returns only
  internal relationship targets for a source part, optionally filtered by
  relationship type.
- Each row includes the normalized `targetPart`, `targetQuery`,
  `targetFragment`, `sameSourceReference`, content type, existence, validity,
  relationship-part-target flag, relationship type policy metadata, and issue
  list.
- Invalid internal targets keep their original target and diagnostics while
  leaving part/query/fragment metadata null, so importer review queues can
  distinguish unsafe traversal from valid same-source bookmarks or query
  references.
- The WordPress DOCX OPC preflight example now reports internal same-source
  references through this native API instead of deriving them ad hoc from the
  broader target preflight output.

## Evidence

- Baseline accepted OPC evidence:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3147 assertions, 0 failures`.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3196 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

The focused OPC test file gained 1 PHP PASS case and 49 focused assertions.
Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, focused OPC fixtures/tests, and WordPress DOCX OPC preflight
smoke. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap / Follow-Up

This does not repeat accepted OPC slices for content-type parsing, relationship
target integrity preflight, external-target policy, relationship transform
selectors, signature manifest/SignedInfo references, XMLDSig role
authorization, embedded package traversal, reachable closure traversal,
relationship-part load summaries, DrawingML relationship type inventory, or
WordprocessingML fixed support relationship singleton rules.

A follow-up should choose a separate OPC package-semantics gap such as
relationship transform digest validation metadata, source-closure import
policy, or higher-level DOCX reader surfacing of existing OPC diagnostics.
