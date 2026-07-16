# Pandoc Table Geometry Core Current Base - 2026-06-09

Micro-slice: `pandoc-table-geometry-core-current-base-20260609T032106Z`
Base accepted HEAD: `50a0721b38afd3fbb00d7da806b11da7b3e09bf4`

## Behavior

HTML table body row-header detection now treats leading `th` cells with
`colspan` as visual row-head columns. This keeps Pandoc-style
`rowHeadColumns` aligned with the rendered table geometry when an imported
HTML body row starts with a row header spanning more than one visual column.

The native reader now carries that visual row-head count through:

- `table_body` `rowHeadColumns` metadata.
- Table geometry row-group summaries and coverage records.
- WordPress table output preserving the source `<th colspan="...">`.

## Evidence

Red-first focused check before the source change:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: failed in `counts html row header colspans as visual row-head columns` with expected `2`, actual `1` for `rowHeadColumns`.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: `1 test files, 1009 assertions, 0 failures`

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
- Result: `1 test files, 1764 assertions, 0 failures`

- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
- Result: `table geometry handoff self-test ok`

The new focused regression adds one PASS case and 17 targeted assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
HTML reader, `TableGeometry` review packets, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, TeX/PDF engine, online
service, live provider test, or live-service provider test was run. The local
hydrated Pandoc upstream checkout is still absent, so full upstream runner
parity remains outside this isolated micro-slice.

## Non-Overlap

This does not repeat prior accepted table geometry work for rowspan section
scoping, colgroup metadata, declared column overflow diagnostics, body-local
head rows, caption/source attributes, table layout/border/background
metadata, or DOCX/OpenXML connector/group-shape handoff behavior.

Next useful table-geometry work should target a non-overlapping gap such as
row-head interactions across multiple `tbody` groups, inherited source header
scopes in complex HTML tables, or writer downgrade metadata for additional
target formats.
