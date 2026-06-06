# OPC Relationships Record-Shape Diagnostics

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T215629Z`
Base accepted HEAD: `f4e557172b3b27cae095ea7602a0976b77d2578b`

## Behavior

This slice tightens bounded OPC `.rels` parsing and package preflight for malformed relationship records:

- `OpcRelationships::fromXml()` now fails closed when a `Relationship` element is missing required `Id`, `Type`, or `Target` attributes.
- `OpcRelationshipGraph::preflightRelationshipPartsInPackage()` now adds specific diagnostics for missing `Id`, missing `Type`, missing `Target`, invalid relationship id, and duplicate relationship id while preserving the existing `malformed-relationship-xml` load reason.
- The WordPress DOCX OPC preflight example now exposes those relationship-record guards for review packets.

Source-truth boundary: OPC relationship parts require relationship records to carry `Id`, `Type`, and `Target`; this native PHP support-library slice ports the package preflight contract, not full Pandoc/DOCX conversion.

## Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1380 assertions, 0 failures
```

Red-first after adding the focused test before source implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1389 assertions, 1 failures
Failure: missing specific missing-relationship-id issue while only malformed-relationship-xml was reported.
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1427 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok
```

Focused delta: `+1` PHP PASS case and `+47` focused assertions over the accepted-base focused test count.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `OpcRelationships`, `OpcRelationshipGraph`, and the existing DOCX OPC preflight example. No Pandoc runner, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML parser, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is distinct from the accepted OPC content-type inventory, package-signature relationship transform content-type query, and Pack URI part-name validation slices. It only classifies malformed relationship record shape and id diagnostics before package graph loading.

## Follow-Up

Next OPC work should stay non-overlapping, such as bounded package-signature object/certificate metadata preflight or DOCX reader integration of existing relationship preflight results. Cryptographic XMLDSig validation remains out of scope for this slice.
