# pandoc-table-geometry-core-current-base-20260608T094120Z

## Scope

Added bounded native HTML table column decimal-alignment provenance for legacy
`<colgroup>` / `<col>` sources that use `align="char"`, `char`, or `charoff`.
The handoff now exposes `columnDecimalAlignments` in `TableGeometry` review
packets, summarizes aligned columns/chars/offsets, reports Markdown/AsciiDoc/
LaTeX writer diagnostics, and preserves safe `align="char"`, `char`, and
`charoff` attributes in WordPress table `<colgroup>` / `<col>` output.

## Source Truth And Non-Overlap

Source truth was the lane-local HTML table reader and existing Pandoc static
manifest rows for structured HTML table fixtures with caption, colgroup widths,
table sections, row-header cells, and inline content. This slice is additive on
accepted base `bc200aef66601a21c11500cbacbc2cbed269780c` and did not run
Pandoc, Cabal, Haskell runners, external writers, browser renderers, online
services, live provider tests, or live-service provider tests.

Non-overlap: avoided the already-covered table geometry footer writer,
block-cell content, header abbreviation, row-group ranges, global row
coordinates, source-summary, rowgroup/colgroup header scopes, axis metadata,
body groups, section boundary rowspans, declared-column overflow, nested-table
rollups, and ordinary colgroup width/alignment handoffs. This slice only owns
HTML decimal character-alignment provenance and writer/WordPress handoff.

## Focused Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors detected.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors detected.
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: no syntax errors detected.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: `1 test files, 496 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: `2 test files, 2048 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`: `table geometry handoff self-test ok`.

Final JSON validation and whitespace checks are recorded in `lane-status.json`
for this slice.

## Status Delta

- `phpPass`: `1600 -> 1601`.
- `benchmarkDenominator.mapped`: `2019 -> 2020`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 191`.
- Focused assertion growth: `+36` in `TableGeometryReaderHandoffTest.php`.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses
`MarkdownReader` HTML table colgroup/col provenance, `TableGeometry` review
packet and writer-diagnostic plumbing, `WordPressBlockWriter` safe table
attribute rendering, focused table geometry tests, and the existing WordPress
table-geometry smoke example. Full upstream runner parity remains gated on a
hydrated pinned Pandoc checkout and a reviewed non-mutating Cabal plan.
