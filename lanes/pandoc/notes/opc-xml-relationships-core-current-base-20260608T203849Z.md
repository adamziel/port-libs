# OPC XML Relationships Current-Base Slice 2026-06-08

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T203849Z`

Base accepted HEAD: `ae5f6fd385045c5bd4eaa3669e2cb41d0fecb36c`

## Behavior

- Added nested embedded-package relationship closure metadata to `OpcRelationshipGraph::preflightEmbeddedPackageGraphs()`.
- Expanded embedded OPC packages now carry `nestedRelationshipClosure` from `/` through the nested officeDocument relationship, including reachable sources, external stops, missing stops, unloaded target-source stops, and closure validity.
- Nested closure issues are reflected on the embedded package graph as `embedded-*` issues so DOCX import preflight can mark invalid embedded packages without reparsing the embedded ZIP bytes.
- Updated the WordPress DOCX OPC preflight example to expose compact `wordpressImport.nestedEmbeddedRelationshipClosures` review packets.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` notes existed before editing.
- Baseline `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`: `1 test files, 2535 assertions, 0 failures`.
- Final `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`: `1 test files, 2569 assertions, 0 failures`.
- Added one focused PHP PASS case and 34 focused assertions.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`: passed.
- Changed-file PHP lint passed.
- `git diff --check -- lanes/pandoc`: passed.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage` package bytes, existing OPC content-type and relationship loading, `preflightOfficeDocumentRoot()`, and `relationshipSourceClosureInventory()` traversal. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted OPC content-type inventory, Pack URI part-name validation, relationship Id validation, signature relationship-transform content-type query preflight, or existing embedded package graph expansion. It adds importer-visible relationship closure traversal for nested embedded packages.
