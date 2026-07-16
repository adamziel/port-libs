# Pandoc Table Geometry Core Current Base - Source Header Target Geometry

Date: 2026-06-09 UTC
Base accepted HEAD: 82ece526c3b1abf329ce3c42e1c2113cbac669aa
Micro-slice: pandoc-table-geometry-core-current-base-20260609T030724Z

## Behavior

Resolved source `headers` references in `TableGeometry::headerAssociations()` now carry the target header cell's geometry instead of only the target key, section, row, column, scope, text, and columns. The reference record includes target colspan/rowspan, row-head-column count, source-cell/source-column, source row start/end/range/list, global row start/end/range/list, row role, and source rowspan-to-section-end flags when present.

This is for importer and WordPress review packets that need to audit explicit HTML/DOCX/ODF-style header references after source tables are normalized into Pandoc-like AST tables.

## Evidence

Baseline focused test before this patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
# 1 test files, 1764 assertions, 0 failures
```

Final focused checks:

```sh
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
# 1 test files, 1815 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
# table geometry handoff self-test ok
```

Dashboard-local delta recorded for integration:

- `phpPass`: 2204 -> 2205
- `benchmarkDenominator.mapped`: 2614 -> 2615
- `mappedTableGeometryCoreCases`: 9 -> 10
- `tableGeometryCoreAssertions`: 155 -> 206

## Non-Overlap

This does not repeat the recent invalid source-scope fallback, duplicate header id, rowgroup/colgroup header propagation, section-scoped rowspan, rowHeadColumns, flat-grid, cell presentation, or OPC/ODF/DOCX package handoffs. It only enriches the resolved target metadata already attached to explicit source `headers` references.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `TableGeometry` section grids, row matrices, source `htmlAttributes` parsing, focused `TableGeometryTest.php`, and the existing WordPress table geometry handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.
