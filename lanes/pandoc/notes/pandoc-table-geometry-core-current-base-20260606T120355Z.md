# Pandoc Table Geometry Body-Local Head Row Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T120355Z`
Base accepted HEAD: `d47b3c76a0d3fdd485e8cd24ceaaf45bbcc209b6`

## Behavior

`TableGeometry::writerDowngradeDiagnostics()` now reports bounded non-HTML writer handoff diagnostics when a Pandoc `TableBody` carries body-local `headRows`.

The diagnostics identify that Markdown flattens those rows into its global pipe-table header/body shape, while AsciiDoc and LaTeX require reviewer attention for body-local header row semantics. Each diagnostic carries the caption, column count, table/body/head row counts, body sections, per-body head row counts, per-body row counts, and section summaries. Existing row-header column diagnostics remain first when a table has both `rowHeadColumns` and body head rows.

WordPress table output remains the preservation path: body-local head rows continue to render as `<th>` cells inside `<tbody>`.

## Source Truth

The accepted static Pandoc inventory already maps HTML-reader/native table fixtures with four `TableBody` head-row shapes from `test/html-reader.html` and `test/html-reader.native`. This slice ports the format contract into native PHP handoff diagnostics without attempting external writer execution.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1071 assertions, 0 failures`

Red-first:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1072 assertions, 1 failures`
  - missing `markdown-body-head-rows-flattened`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1105 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1446 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

## Status Delta

- `phpPass`: `1323 -> 1324`
- manifest mapped checks: `1737 -> 1738`
- `mappedTableGeometryCoreCases`: `8 -> 9`
- `tableGeometryCoreAssertions`: `143 -> 177`
- focused `TableGeometryTest.php`: `1071 -> 1105` assertions

## Dependency Closure

No new support component is needed. This slice reuses native PHP `AstNode`, `TableGeometry`, `MarkdownWriter`, `WordPressBlockWriter`, `MarkdownReader`, and the existing focused test harness.

No Pandoc, Cabal, Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted row-header column diagnostics, row-header WordPress rendering, row-header accessibility metadata, footer writer diagnostics, block-cell content diagnostics, RST grid-table requirement diagnostics, or multiple body-group writer diagnostics. The new behavior is limited to `TableBody` body-local `headRows` writer handoff metadata.

## Follow-Up

Keep actual Markdown/AsciiDoc/LaTeX body-local head-row rendering mitigation, richer writer syntax-policy selection, DOCX/ODF body-head-row provenance, and full upstream Pandoc runner parity as separate bounded slices.
