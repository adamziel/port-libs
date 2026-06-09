# Pandoc OPC XML Relationships Core Current Base

Session: `port-dev-pandoc-opc-relationships-20260609T013230Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T013230Z`
Accepted base: `800b696344a9bf658321def4bebfd04d22ba2df2`

## Behavior

Corrected the OPC XML signature relationship-transform selector spelling used
for relationship-type groups.

- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now treats
  `mdssi:RelationshipsGroupReference SourceType="..."` as the canonical
  selector for relationship groups.
- The prior singular `mdssi:RelationshipGroupReference` spelling is now
  classified as `unsupported-relationship-transform-child`, so it does not
  silently select relationships.
- The existing output key `selectorRelationshipGroupReferenceCount` remains
  unchanged for compatibility with current review packets.

Source truth: ECMA-376 Part 2 digital-signature markup names the element
`RelationshipsGroupReference`; the relationship transform filters by matching
relationship `@Id` against `SourceId` or relationship `@Type` against
`SourceType`. Microsoft Open Specifications also describes the same
SourceId/SourceType filtering rule for the relationship transform.

## Evidence

Focused check after implementation:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Passed with `1 test files, 2933 assertions, 0 failures`.

WordPress DOCX OPC example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Passed with `opc docx preflight self-test ok`.

Changed-file syntax checks:

`php -l lanes/pandoc/src/OpcRelationshipGraph.php`
`php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
`php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Passed with no syntax errors.

Status JSON and lane diff checks:

`jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
`git diff --check -- lanes/pandoc`

Passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice corrects one existing mapped OPC relationship-transform selector
case. It does not repeat content-type inventory grouping, Pack URI validation,
signature reference ContentType query preflight, canonicalization transform
ordering, relationship target preflight, closure traversal, or digital-signature
role/content-type checks.

Mapped denominator and `phpPass` are intentionally unchanged because this is a
source-truth correction to existing focused OPC coverage rather than a new PHP
PASS line.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`OpcRelationshipGraph`, `OpcRelationships`, `OpcContentTypes`, `ZipPackage`,
DOM/libxml NONET XML parsing, focused `OpenPackagingConventionsTest.php`
coverage, and the WordPress DOCX OPC preflight example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig
validators, external XML tools, online services, live provider tests, and
live-service provider tests were not run and remain out of scope.

## Follow-Up

Next OPC work should stay non-overlapping: signature object policy diagnostics,
digest/reporting preflight, content-type/relationship role cross-checks, or
DOCX relationship handoff.
