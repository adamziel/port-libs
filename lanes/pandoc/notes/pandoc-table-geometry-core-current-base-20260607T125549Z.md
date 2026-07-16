# Pandoc Table Geometry Global Row Coordinates

Slice: `pandoc-table-geometry-core-current-base-20260607T125549Z`
Base: `33dd90b2d97147e4b87532dfc006637ee405391e`

## Source Truth

This slice stays within the existing native PHP table geometry support row. The upstream cache does not contain a hydrated Pandoc checkout for this worker, so source truth is the accepted lane manifest plus the existing table geometry fixtures/tests that already map Pandoc table section, row-span, row-group, and WordPress handoff behavior. No Pandoc, Cabal, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Behavior Added

`TableGeometry::reviewPacket()` now carries global row coordinates across table sections:

- `sections[*]` expose `globalRowStart`, `globalRowEnd`, and `rowRange`.
- Serialized section rows expose `globalRow`.
- Cell coverage records expose `globalRow`, `globalRowEnd`, `globalRowRange`, and `globalRows`.
- Covered slots anchored to rowspanned cells expose `anchorGlobalRow`, `anchorGlobalRowEnd`, `anchorGlobalRowRange`, and `anchorGlobalRows`.
- Section row summaries expose `globalRow`.
- Packet summaries expose `globalRowCount`, `globalRowRange`, and `maxGlobalRow`.

This lets WordPress/importer review packets reconcile head/body/foot rows, body-local head rows, and rowspans without recomputing section offsets from section-local row indexes.

## Verification

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1306 assertions, 0 failures
```

Red-first probe:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1306 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1322 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1663 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Added one mapped native table geometry support case.
- Added one PHP PASS case and 16 focused assertions in `TableGeometryTest.php`.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator from 1922 to 1923.
- Updated `mappedTableGeometryCoreCases` from 8 to 9.
- Updated lane status text to carry `phpPass` from 1499 to 1500.

## Dependency Closure

No new support component is needed. The patch reuses native `TableGeometry`, focused table geometry tests, and the existing WordPress table geometry handoff example.

## Non-Overlap

This does not repeat accepted table span layout, row-head columns, section-scoped rowspans, declared-column overflow, source coordinate shifts, RST grid-table requirements, table-foot writer diagnostics, block-cell content metadata, nested-table rollups, source-header abbreviation handling, duplicate source-header diagnostics, or row-header map handoff. The new behavior is limited to global row coordinate serialization across already-built table section geometry.

## Follow-Up

Next table geometry work should stay bounded to non-overlapping writer downgrade metadata, source row/column provenance needed by DOCX/ODT/HTML readers, or AST/WordPress table handoff diagnostics.
