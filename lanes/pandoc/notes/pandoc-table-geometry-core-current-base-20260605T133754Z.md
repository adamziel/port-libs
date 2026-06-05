# Pandoc Table Geometry Core Current Base 2026-06-05

## Scope

Micro-slice: `pandoc-table-geometry-core-current-base-20260605T133754Z`.

Accepted base: `d93cb59e263d5bec6bba4ac974f8dbb66ee5ed6a`.

This is a bounded native PHP table-geometry handoff slice. No Pandoc binary,
Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`,
`unzip`, external writer, TeX/PDF engine, browser renderer, online sanitizer,
or online service was executed as progress.

## Source Truth

The slice stays within the accepted Pandoc table contract already mapped from
the static pinned inventory at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:

- `test/html-reader.html` and `test/html-reader.native` table-section cells with
  `colspan`, `rowspan`, omitted sections, row-head columns, and body-local
  header rows.
- `test/tables.native` and `test/tables.markdown` table AST and Markdown writer
  handoff shapes.
- `test/command/rst-writer-gridtable-if-rowspans.md` for writer-side span
  diagnostics without invoking external writers.

This patch owns only malformed source span normalization diagnostics for the
native PHP table layout/review-packet path.

## Patch

`TableGeometry::diagnostics()` now reports `cell-span-normalized` diagnostics
when a source cell carries an invalid or unsupported `colspan` or `rowspan`
attribute that the layout normalizes before WordPress/Markdown output.

Each diagnostic records the table section, visual row and column, source cell
coordinate, source attribute, raw type/value where scalar, normalized value,
minimum accepted value, and whether zero has row-group sentinel meaning.
`rowspan="0"` remains accepted as the HTML row-group sentinel and is not
reported as malformed.

`TableGeometry::reviewPacket()` summary now exposes:

- `hasNormalizedSpans`
- `normalizedSpanCount`

The WordPress table handoff example now includes a malformed-span source table
and verifies that malformed span attributes do not leak into rendered WordPress
table output while review packets keep the raw import evidence.

## Non-Overlap

This slice does not repeat visual span layout, colspec parsing, row-head column
output, body-local head-row output, section-scoped rowspan clamping, declared
column overflow diagnostics, occupied-slot coverage, accessibility header
attributes, reader-packet source attributes, nested-table rollups,
`rowspan="0"` sentinel handling, Markdown/RST span downgrade behavior,
colgroup/caption/width summaries, or any DOCX/ODT/EPUB/PDF/YAML/CSL/BibTeX/
ZIP/OPC/charset/math/syntax-highlighting/legacy-DOC behavior.

## Verification

- Baseline focused count before this patch:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 494 assertions, 0 failures`
- Red/intermediate run after adding the new expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 524 assertions, 1 failures`
  - Failure was the exact Markdown writer padding expectation for the new
    normalized table row.
- Focused table-geometry test after the fix:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 525 assertions, 0 failures`
- Focused table-geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 763 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- PHP syntax:
  `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/TableGeometry.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php`
- PHP syntax:
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- Diff whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.
- Root harness:
  not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case.
- Raised lane `phpPass` from `924` to `925`.
- Raised `TableGeometryTest.php` focused coverage from `494` to `525`
  assertions.
- Raised combined table-geometry family coverage to `763` assertions.
- Raised manifest mapped denominator from `1381` to `1382`.
- Added `mappedTableGeometryMalformedSpanNormalizationCases: 1`.
- Added `tableGeometryMalformedSpanNormalizationAssertions: 31`.

## Dependency Closure

No new native support component is needed. The slice reuses the existing
`AstNode`, `TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter`
bounded PHP support.

Full upstream Pandoc runner parity remains gated on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and a non-mutating Cabal
dependency plan for `test-pandoc` and `test-pandoc-lua-engine`. This slice did
not attempt that runner.

## Follow-Up

Keep broader table writer parity separate: caption/short-caption edge cases,
col-width formatting, target-specific table downgrades, and full upstream
Pandoc Haskell runner parity should remain their own bounded slices.
