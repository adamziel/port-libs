# Pandoc Table Geometry Core Current Base - 2026-06-09

Micro-slice: `pandoc-table-geometry-core-current-base-20260609T040657Z`
Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Behavior

Table geometry review packets now expose row-head widths per HTML `tbody`
group. Imported tables with multiple body groups can carry different
`rowHeadColumns` values, and the packet summary now records:

- `rowHeadSections`
- `rowHeadColumnCounts`
- `rowHeadGroupRanges`
- `hasDifferingRowHeadColumns`

The multiple-body writer diagnostic now also reports:

- `bodySectionRowHeadColumns`
- `rowHeadBodySections`
- `rowHeadColumnCounts`
- `rowHeadSectionRanges`
- aggregate row-head group/count flags

WordPress output remains unchanged for the source table: `tbody` attributes,
`th`, and `colspan` are preserved.

## Evidence

Baseline focused check before the new assertion:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: `1 test files, 1009 assertions, 0 failures`

Red-first focused check after adding the new case but before the source
change:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: failed in `reports row-head columns per html tbody group in writer
  handoff` because expected `summary.rowHeadSections` was absent.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- Result: `1 test files, 1035 assertions, 0 failures`

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
- Result: `1 test files, 1862 assertions, 0 failures`

- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
- Result: `table geometry handoff self-test ok`

The focused regression adds one PASS case and 26 targeted assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
HTML reader path in `MarkdownReader`, `TableGeometry` row-group summaries,
writer downgrade diagnostics, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser
renderer, online service, live provider test, or live-service provider test
was run. The local hydrated Pandoc upstream checkout is still absent, so full
upstream runner parity remains outside this isolated micro-slice.

## Non-Overlap

This does not repeat prior accepted table geometry work for visual
`rowHeadColumns` colspan detection, rowspan section scoping, body-local head
rows, multiple-body boundary diagnostics, colgroup metadata, caption/source
attributes, layout/border/background metadata, or PDF engine handoff behavior.

Next useful table-geometry work should target a non-overlapping gap such as
inherited source header scopes in complex HTML tables, row-head/body-head
interactions with `tfoot`, or additional writer downgrade metadata for target
formats.
